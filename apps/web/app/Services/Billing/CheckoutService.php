<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\Contracts\PaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Taking money, and turning it into access.
 *
 * MONEY IS INTEGER PAISE THROUGHOUT. Rs 199.00 is 19900. Never a float — floating-point
 * money bugs are found by accountants, months later, in aggregate, and by then every
 * affected invoice has already been sent.
 *
 * The whole flow is idempotent because payment gateways retry, duplicate, and deliver out
 * of order. Processing one payment twice would grant two subscriptions for one payment,
 * and the user would be right to expect a refund for one of them.
 */
final class CheckoutService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly EntitlementService $entitlements,
    ) {}

    /**
     * Create an order and the gateway-side order it maps to.
     *
     * @return array{order: Order, checkout: array<string, mixed>}
     */
    public function start(User $user, Plan $plan, ?string $couponCode = null): array
    {
        if (! $plan->is_active) {
            throw new RuntimeException('That plan is not available.');
        }

        $coupon = $couponCode !== null ? $this->resolveCoupon($couponCode, $plan, $user) : null;
        $discount = $coupon !== null ? $this->discountFor($coupon, $plan->price_paise) : 0;

        $subtotal = $plan->price_paise;
        $total = max(0, $subtotal - $discount);

        $order = DB::transaction(function () use ($user, $plan, $coupon, $subtotal, $discount, $total): Order {
            return Order::create([
                'user_id' => $user->id,
                'number' => $this->nextOrderNumber(),
                'item_type' => 'plan',
                'item_id' => $plan->id,
                /**
                 * The price and terms are FROZEN here.
                 *
                 * A plan's price may change tomorrow; what this person agreed to pay today
                 * must still be recoverable from the order years later, for a refund
                 * dispute or a tax audit.
                 */
                'item_snapshot' => [
                    'slug' => $plan->slug,
                    'name' => (string) $plan->name,
                    'price_paise' => $plan->price_paise,
                    'period' => $plan->period,
                    'entitlements' => $plan->entitlements,
                    'ai_credits' => $plan->ai_credits_per_period,
                ],
                'subtotal_paise' => $subtotal,
                'discount_paise' => $discount,
                'total_paise' => $total,
                'coupon_id' => $coupon?->id,
                'status' => 'pending',
            ]);
        });

        // A 100% coupon needs no gateway round-trip. Sending someone to a payment screen
        // for a zero-rupee charge is a step that can only fail.
        if ($total === 0) {
            $this->fulfil($order);

            return ['order' => $order->refresh(), 'checkout' => []];
        }

        $gatewayOrder = $this->gateway->createOrder($order);

        Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => $this->gateway->name(),
            'gateway_order_id' => $gatewayOrder['gateway_order_id'],
            'amount_paise' => $total,
            'status' => 'created',
        ]);

        return ['order' => $order, 'checkout' => $gatewayOrder['checkout']];
    }

    /**
     * Handle the browser callback after checkout.
     *
     * @param  array<string, mixed>  $payload
     */
    public function confirm(Order $order, array $payload): bool
    {
        if (! $this->gateway->verifyCallback($payload)) {
            Log::warning('checkout.invalid_signature', ['order' => $order->number]);

            return false;
        }

        $paymentId = (string) $payload['razorpay_payment_id'];

        // Ask the gateway what happened rather than believing the browser.
        $actual = $this->gateway->fetchPayment($paymentId);

        if (! in_array($actual['status'], ['captured', 'authorized'], true)) {
            return false;
        }

        // The amount must match. A tampered callback claiming a Rs 1 payment for a Rs 999
        // plan would otherwise grant the plan.
        if ($actual['amount_paise'] !== $order->total_paise) {
            Log::error('checkout.amount_mismatch', [
                'order' => $order->number,
                'expected' => $order->total_paise,
                'actual' => $actual['amount_paise'],
            ]);

            return false;
        }

        DB::transaction(function () use ($order, $paymentId, $actual, $payload): void {
            Payment::query()
                ->where('order_id', $order->id)
                ->update([
                    'gateway_payment_id' => $paymentId,
                    'gateway_signature' => $payload['razorpay_signature'] ?? null,
                    'method' => $actual['method'],
                    'status' => 'captured',
                    'gateway_payload' => $actual['raw'],
                    'captured_at' => now(),
                ]);

            $this->fulfil($order);
        });

        return true;
    }

    /**
     * Grant what was bought.
     *
     * IDEMPOTENT. Called from both the browser callback and the webhook, which routinely
     * both arrive — and sometimes the webhook arrives first. Whichever gets there second
     * must do nothing.
     */
    public function fulfil(Order $order): void
    {
        if ($order->status === 'paid') {
            return;
        }

        DB::transaction(function () use ($order): void {
            $snapshot = $order->item_snapshot;
            $user = $order->user;

            $period = $this->periodEnd((string) ($snapshot['period'] ?? 'yearly'));

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $order->item_id,
                'status' => 'active',
                'started_at' => now(),
                'current_period_start' => now(),
                'current_period_end' => $period,
                // UPI mandates are opt-in, never pre-ticked. A surprise renewal on a
                // Rs 199 product buys one refund request and loses one user permanently.
                'auto_renew' => false,
                'gateway' => $this->gateway->name(),
            ]);

            $this->entitlements->grantMany(
                user: $user,
                keys: (array) ($snapshot['entitlements'] ?? []),
                sourceType: 'subscription',
                sourceId: $subscription->id,
                expiresAt: $period,
            );

            if ($credits = (int) ($snapshot['ai_credits'] ?? 0)) {
                $this->grantCredits($user, $credits, $order->id);
            }

            $order->update(['status' => 'paid', 'paid_at' => now()]);

            if ($order->coupon_id !== null) {
                Coupon::query()->where('id', $order->coupon_id)->increment('redemption_count');

                DB::table('coupon_redemptions')->insert([
                    'coupon_id' => $order->coupon_id,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'discount_paise' => $order->discount_paise,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->issueInvoice($order);
        });
    }

    private function grantCredits(User $user, int $credits, int $orderId): void
    {
        $balance = (int) DB::table('credit_ledger')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->value('balance_after');

        DB::table('credit_ledger')->insert([
            'user_id' => $user->id,
            'delta' => $credits,
            'reason' => 'plan_grant',
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'balance_after' => $balance + $credits,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Invoice numbers are sequential per financial year and GAPLESS.
     *
     * India's financial year runs April to March, and a gap in the sequence is the first
     * thing an auditor asks about. Generated inside the fulfilment transaction so a
     * rollback cannot consume a number.
     */
    private function issueInvoice(Order $order): void
    {
        if (! config('commerce.gst.enabled')) {
            return;
        }

        $fy = $this->financialYear();
        $prefix = config('commerce.gst.invoice_prefix', 'SDH');

        $sequence = Invoice::query()->where('number', 'like', "{$prefix}/{$fy}/%")->lockForUpdate()->count() + 1;

        $rate = (float) config('commerce.gst.default_rate', 18.0);
        $taxable = (int) round($order->total_paise / (1 + $rate / 100));
        $tax = $order->total_paise - $taxable;

        Invoice::create([
            'number' => sprintf('%s/%s/%05d', $prefix, $fy, $sequence),
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'billing_name' => $order->user->name,
            'billing_phone' => $order->user->phone,
            'place_of_supply' => $order->user->profile?->state ?? config('commerce.gst.home_state'),
            'taxable_paise' => $taxable,
            'gst_rate' => $rate,
            // Intra-state is CGST + SGST split evenly; inter-state is IGST. Getting this
            // wrong is a filing problem, not a display one.
            'cgst_paise' => $this->isIntraState($order) ? (int) floor($tax / 2) : 0,
            'sgst_paise' => $this->isIntraState($order) ? (int) ceil($tax / 2) : 0,
            'igst_paise' => $this->isIntraState($order) ? 0 : $tax,
            'total_paise' => $order->total_paise,
            'issued_at' => now(),
        ]);
    }

    private function isIntraState(Order $order): bool
    {
        return ($order->user->profile?->state ?? config('commerce.gst.home_state'))
            === config('commerce.gst.home_state');
    }

    private function financialYear(): string
    {
        $now = CarbonImmutable::now();
        $start = $now->month >= 4 ? $now->year : $now->year - 1;

        return $start.'-'.substr((string) ($start + 1), 2);
    }

    private function periodEnd(string $period): ?CarbonImmutable
    {
        return match ($period) {
            'monthly' => CarbonImmutable::now()->addMonth(),
            'quarterly' => CarbonImmutable::now()->addMonths(3),
            'yearly' => CarbonImmutable::now()->addYear(),
            default => null,
        };
    }

    private function resolveCoupon(string $code, Plan $plan, User $user): ?Coupon
    {
        $coupon = Coupon::query()
            ->where('code', strtoupper(trim($code)))
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->first();

        if ($coupon === null) {
            throw new RuntimeException('That coupon code is not valid.');
        }

        if ($coupon->max_redemptions !== null && $coupon->redemption_count >= $coupon->max_redemptions) {
            throw new RuntimeException('That coupon has been fully used.');
        }

        $usedByUser = DB::table('coupon_redemptions')
            ->where('coupon_id', $coupon->id)
            ->where('user_id', $user->id)
            ->count();

        if ($usedByUser >= $coupon->per_user_limit) {
            throw new RuntimeException('You have already used that coupon.');
        }

        $applicable = $coupon->applicable_plans;

        if (! empty($applicable) && ! in_array($plan->slug, $applicable, true)) {
            throw new RuntimeException('That coupon does not apply to this plan.');
        }

        return $coupon;
    }

    private function discountFor(Coupon $coupon, int $priceP): int
    {
        return match ($coupon->type) {
            'percent' => (int) floor($priceP * ($coupon->percent_off ?? 0) / 100),
            'fixed' => min($priceP, (int) $coupon->amount_off_paise),
            default => 0,
        };
    }

    private function nextOrderNumber(): string
    {
        return sprintf('SDH-%s-%06d', date('Y'), Order::query()->count() + 1);
    }
}
