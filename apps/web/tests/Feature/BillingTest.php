<?php

declare(strict_types=1);

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\Contracts\PaymentGateway;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\FeatureGate;
use Illuminate\Support\Facades\DB;

/**
 * Money.
 *
 * 95% coverage target, level with the eligibility engine. The failure modes here are not
 * cosmetic: granting access nobody paid for, charging for something already bought, or
 * losing a payment that went through.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();

    $this->plan = Plan::create([
        'slug' => 'premium',
        'name' => ['en' => 'Premium'],
        'entitlements' => ['ads_free', 'test_series_full'],
        'price_paise' => 19_900,
        'period' => 'yearly',
        'ai_credits_per_period' => 500,
        'is_active' => true,
        'is_public' => true,
    ]);

    $this->gateway = Mockery::mock(PaymentGateway::class);
    $this->gateway->shouldReceive('name')->andReturn('razorpay');
    $this->app->instance(PaymentGateway::class, $this->gateway);
});

function checkout(): CheckoutService
{
    return app(CheckoutService::class);
}

// ================================================================ the open-gate posture

describe('feature gate', function (): void {
    /**
     * DECISION D12. While gates are open the machinery still records everything, so the
     * day a gate closes the paying users already hold what they bought and nothing needs
     * backfilling.
     */
    it('grants every capability to everyone while gates are open', function (): void {
        config(['commerce.gates_open' => true]);

        expect(app(FeatureGate::class)->allows(null, 'test_series_full'))->toBeTrue()
            ->and(app(FeatureGate::class)->allows($this->user, 'ai_answer_eval'))->toBeTrue();
    });

    it('requires the entitlement once gates are closed', function (): void {
        config(['commerce.gates_open' => false]);

        $gate = app(FeatureGate::class);

        expect($gate->allows($this->user, 'test_series_full'))->toBeFalse();

        app(EntitlementService::class)->grant($this->user, 'test_series_full', 'manual');

        expect($gate->allows($this->user->fresh(), 'test_series_full'))->toBeTrue();
    });

    /**
     * DECISION D13. These five are the SEO engine and the habit loop — the compounding
     * assets. Gating one to raise ARPU trades a multi-year moat for a quarter of revenue,
     * so it throws rather than returning false: a programming error caught in CI.
     */
    it('refuses to gate anything that must stay free', function (string $key): void {
        expect(fn () => app(FeatureGate::class)->allows($this->user, $key))
            ->toThrow(InvalidArgumentException::class);
    })->with(['notification_feed', 'eligibility_check', 'exam_hub_pages', 'daily_quiz', 'official_material']);

    it('refuses an entitlement key that is not in the catalogue', function (): void {
        expect(fn () => app(FeatureGate::class)->allows($this->user, 'invented_key'))
            ->toThrow(InvalidArgumentException::class);
    });
});

// ================================================================ entitlements

describe('entitlements', function (): void {
    /**
     * One row per grant, never an upsert. A user can hold the same key from a subscription
     * AND a campus coupon at once; when the coupon lapses the subscription must still
     * cover them. Collapsing them loses access the user paid for.
     */
    it('keeps grants from different sources separate', function (): void {
        $service = app(EntitlementService::class);

        $service->grant($this->user, 'ads_free', 'subscription', 1);
        $service->grant($this->user, 'ads_free', 'coupon', 2);

        $service->revokeBySource($this->user, 'coupon', 2, 'expired');

        expect($service->has($this->user, 'ads_free'))->toBeTrue();
    });

    it('ignores an expired grant', function (): void {
        $service = app(EntitlementService::class);

        $service->grant($this->user, 'ads_free', 'subscription', 1, now()->subDay());

        expect($service->has($this->user, 'ads_free'))->toBeFalse();
    });

    it('ignores a revoked grant', function (): void {
        $service = app(EntitlementService::class);

        $service->grant($this->user, 'ads_free', 'subscription', 1);
        $service->revokeBySource($this->user, 'subscription', 1, 'refunded');

        expect($service->has($this->user, 'ads_free'))->toBeFalse();
    });
});

// ================================================================ checkout

describe('checkout', function (): void {
    it('creates a pending order at the plan price', function (): void {
        $this->gateway->shouldReceive('createOrder')->andReturn([
            'gateway_order_id' => 'order_test',
            'amount_paise' => 19_900,
            'currency' => 'INR',
            'checkout' => [],
        ]);

        $result = checkout()->start($this->user, $this->plan);

        expect($result['order']->total_paise)->toBe(19_900)
            ->and($result['order']->status)->toBe('pending');
    });

    /**
     * The price is frozen at purchase. A plan's price may change tomorrow; what this
     * person agreed to pay today must still be recoverable years later for a refund
     * dispute or a tax audit.
     */
    it('freezes the plan terms on the order', function (): void {
        $this->gateway->shouldReceive('createOrder')->andReturn([
            'gateway_order_id' => 'order_test', 'amount_paise' => 19_900, 'currency' => 'INR', 'checkout' => [],
        ]);

        $order = checkout()->start($this->user, $this->plan)['order'];

        $this->plan->update(['price_paise' => 49_900]);

        expect($order->refresh()->item_snapshot['price_paise'])->toBe(19_900);
    });

    it('applies a percentage coupon', function (): void {
        Coupon::create([
            'code' => 'HALF', 'type' => 'percent', 'percent_off' => 50, 'is_active' => true, 'per_user_limit' => 1,
        ]);

        $this->gateway->shouldReceive('createOrder')->andReturn([
            'gateway_order_id' => 'o', 'amount_paise' => 9_950, 'currency' => 'INR', 'checkout' => [],
        ]);

        $order = checkout()->start($this->user, $this->plan, 'HALF')['order'];

        expect($order->discount_paise)->toBe(9_950)->and($order->total_paise)->toBe(9_950);
    });

    /**
     * A fully discounted order needs no gateway round-trip. Sending someone to a payment
     * screen for a zero-rupee charge is a step that can only fail.
     */
    it('fulfils a fully discounted order without touching the gateway', function (): void {
        Coupon::create([
            'code' => 'FREE', 'type' => 'percent', 'percent_off' => 100, 'is_active' => true, 'per_user_limit' => 1,
        ]);

        $result = checkout()->start($this->user, $this->plan, 'FREE');

        expect($result['order']->status)->toBe('paid')
            ->and(app(EntitlementService::class)->has($this->user, 'ads_free'))->toBeTrue();
    });

    it('rejects a coupon the user has already used', function (): void {
        Coupon::create([
            'code' => 'ONCE', 'type' => 'percent', 'percent_off' => 10, 'is_active' => true, 'per_user_limit' => 1,
        ]);

        $this->gateway->shouldReceive('createOrder')->andReturn([
            'gateway_order_id' => 'o', 'amount_paise' => 17_910, 'currency' => 'INR', 'checkout' => [],
        ]);

        checkout()->start($this->user, $this->plan, 'ONCE');
        checkout()->fulfil(Order::query()->latest('id')->first());

        expect(fn () => checkout()->start($this->user, $this->plan, 'ONCE'))
            ->toThrow(RuntimeException::class);
    });

    it('rejects an expired coupon', function (): void {
        Coupon::create([
            'code' => 'OLD', 'type' => 'percent', 'percent_off' => 10,
            'is_active' => true, 'ends_at' => now()->subDay(), 'per_user_limit' => 1,
        ]);

        expect(fn () => checkout()->start($this->user, $this->plan, 'OLD'))
            ->toThrow(RuntimeException::class);
    });
});

// ================================================================ fulfilment

describe('fulfilment', function (): void {
    beforeEach(function (): void {
        $this->gateway->shouldReceive('createOrder')->andReturn([
            'gateway_order_id' => 'order_test', 'amount_paise' => 19_900, 'currency' => 'INR', 'checkout' => [],
        ]);

        $this->order = checkout()->start($this->user, $this->plan)['order'];
    });

    it('grants a subscription, entitlements and credits', function (): void {
        checkout()->fulfil($this->order);

        expect($this->order->refresh()->status)->toBe('paid')
            ->and($this->user->subscriptions()->where('status', 'active')->exists())->toBeTrue()
            ->and(app(EntitlementService::class)->has($this->user, 'test_series_full'))->toBeTrue()
            ->and((int) DB::table('credit_ledger')->where('user_id', $this->user->id)->sum('delta'))->toBe(500);
    });

    /**
     * IDEMPOTENCY IS THE WHOLE POINT.
     *
     * Gateways retry, duplicate and deliver out of order, and the browser callback and the
     * webhook routinely both arrive. Fulfilling twice would grant two subscriptions for one
     * payment — and the user would be right to demand a refund for one of them.
     */
    it('is idempotent when fulfilled twice', function (): void {
        checkout()->fulfil($this->order);
        checkout()->fulfil($this->order->refresh());

        expect($this->user->subscriptions()->count())->toBe(1)
            ->and((int) DB::table('credit_ledger')->where('user_id', $this->user->id)->sum('delta'))->toBe(500);
    });

    /**
     * UPI mandates are opt-in, never pre-ticked. A surprise renewal on a Rs 199 product
     * buys one refund request and loses one user permanently.
     */
    it('never enables auto-renew by default', function (): void {
        checkout()->fulfil($this->order);

        expect($this->user->subscriptions()->first()->auto_renew)->toBeFalse();
    });

    /**
     * A tampered callback claiming a Rs 1 payment for a Rs 199 plan must not grant it.
     */
    it('refuses to confirm when the paid amount does not match the order', function (): void {
        $this->gateway->shouldReceive('verifyCallback')->andReturn(true);
        $this->gateway->shouldReceive('fetchPayment')->andReturn([
            'status' => 'captured',
            'amount_paise' => 100,      // not 19,900
            'method' => 'upi',
            'raw' => [],
        ]);

        $confirmed = checkout()->confirm($this->order, [
            'razorpay_payment_id' => 'pay_x',
            'razorpay_order_id' => 'order_test',
            'razorpay_signature' => 'sig',
        ]);

        expect($confirmed)->toBeFalse()
            ->and($this->order->refresh()->status)->toBe('pending');
    });

    /**
     * An unverified callback is just an attacker claiming to have paid.
     */
    it('refuses to confirm on a bad signature', function (): void {
        $this->gateway->shouldReceive('verifyCallback')->andReturn(false);

        $confirmed = checkout()->confirm($this->order, ['razorpay_payment_id' => 'pay_x']);

        expect($confirmed)->toBeFalse()
            ->and($this->order->refresh()->status)->toBe('pending');
    });
});

// ================================================================ the public page

it('shows the pricing page to a guest', function (): void {
    $this->get('/te/premium')->assertSuccessful();
});

/**
 * While gates are open the page must say so. Selling something the buyer would get anyway
 * without telling them is the kind of thing people find out about and do not forgive.
 */
it('tells visitors the paid features are currently free', function (): void {
    config(['commerce.gates_open' => true]);

    $this->get('/en/premium')->assertSee('free right now', false);
});
