<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentWebhook;
use App\Models\Plan;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\Contracts\PaymentGateway;
use App\Services\Billing\FeatureGate;
use App\Support\SeoBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Buying a plan.
 *
 * The pricing page is honest about the current posture: while gates are open, everything
 * paid is also free, and the page says so. Selling something the buyer would get anyway
 * without telling them is the kind of thing people find out about and do not forgive.
 */
class BillingController extends Controller
{
    public function plans(FeatureGate $gate): View
    {
        return view('billing.plans', [
            'seo' => SeoBuilder::forRoute(
                'billing.plans',
                __('Premium'),
                __('Ad-free, full test series and higher AI limits. The core of Sadhana stays free forever.'),
            ),
            'plans' => Plan::query()->where('is_active', true)->where('is_public', true)
                ->orderBy('sort_order')->orderBy('price_paise')->get(),
            'gatesOpen' => $gate->gatesOpen(),
            'currentPlan' => auth()->user()?->subscriptions()
                ->where('status', 'active')->latest()->first(),
        ]);
    }

    public function checkout(Request $request, CheckoutService $checkout): JsonResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', 'exists:plans,slug'],
            'coupon' => ['nullable', 'string', 'max:40'],
        ]);

        $plan = Plan::query()->where('slug', $validated['plan'])->firstOrFail();

        try {
            $result = $checkout->start($request->user(), $plan, $validated['coupon'] ?? null);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'order' => $result['order']->uuid,
            'checkout' => $result['checkout'],
            // A fully discounted order is already fulfilled; the client skips checkout.
            'complete' => $result['order']->status === 'paid',
        ]);
    }

    public function callback(Request $request, CheckoutService $checkout): RedirectResponse
    {
        $order = Order::query()
            ->where('uuid', $request->input('order'))
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $ok = $checkout->confirm($order, $request->all());

        return $ok
            ? redirect()->route('billing.plans')->with('status', __('Payment received. Your plan is active.'))
            : redirect()->route('billing.plans')->withErrors(['payment' => __('We could not confirm that payment. If money left your account, it will be returned within 5 working days — nothing is lost.')]);
    }

    /**
     * Gateway webhook.
     *
     * Stored BEFORE it is acted on and deduplicated by the gateway's own event id.
     * Gateways retry, duplicate and deliver out of order; processing a payment webhook
     * twice would grant two subscriptions for one payment.
     *
     * Not behind auth or CSRF — it is a server-to-server call, authenticated by signature.
     */
    public function webhook(Request $request, PaymentGateway $gateway, CheckoutService $checkout): JsonResponse
    {
        $raw = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature', '');

        if (! $gateway->verifyWebhook($raw, $signature)) {
            Log::warning('billing.webhook.bad_signature');

            // 200 so the gateway stops retrying something that will never verify, while
            // the log records that someone is posting to this endpoint.
            return response()->json(['ignored' => true]);
        }

        $payload = $request->json()->all();
        $eventId = (string) $request->header('X-Razorpay-Event-Id', md5($raw));

        $webhook = PaymentWebhook::firstOrCreate(
            ['gateway' => 'razorpay', 'event_id' => $eventId],
            [
                'event_type' => (string) data_get($payload, 'event', 'unknown'),
                'payload' => $payload,
                'signature_valid' => true,
                'status' => 'received',
            ],
        );

        if (! $webhook->wasRecentlyCreated) {
            return response()->json(['duplicate' => true]);
        }

        try {
            if (data_get($payload, 'event') === 'payment.captured') {
                $receipt = data_get($payload, 'payload.payment.entity.notes.order_uuid');

                $order = Order::query()->where('uuid', $receipt)->first();

                if ($order !== null) {
                    // Idempotent: the browser callback may already have fulfilled it.
                    $checkout->fulfil($order);
                }
            }

            $webhook->update(['status' => 'processed', 'processed_at' => now()]);
        } catch (Throwable $e) {
            report($e);
            $webhook->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }
}
