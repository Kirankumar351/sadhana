<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\User;
use InvalidArgumentException;

/**
 * The single place that decides whether a user may use a paid capability.
 *
 * THIS CLASS IS WHERE THE CENTRAL PRODUCT TENSION IS RESOLVED.
 *
 * Vol 1 Decision D2 says the core stays free forever and the business is ad-funded, because
 * traffic and habit are the moat and a paywall kills both. The brief for this repository
 * asks for real revenue from students. Both hold, because machinery and posture are
 * separated:
 *
 *   - the machinery is complete. Plans, subscriptions, entitlements, credits, coupons,
 *     invoices and refunds all work. Money can be taken today.
 *   - the posture is `commerce.gates_open = true`. Nothing is actually withheld.
 *
 * While gates are open, a purchase still creates a subscription and still grants
 * entitlements — the rows are all written. So the day a gate closes, the paying users
 * already hold what they bought and nothing needs backfilling.
 *
 * Closing a gate then becomes a product decision someone makes on a date, for a reason,
 * with a metric in mind. It is never an accident of how a feature was written.
 *
 * ALWAYS ASK FOR A KEY, NEVER FOR A PLAN. Code must call
 *     $gate->allows($user, 'test_series_full')
 * and never
 *     $user->subscription?->plan->slug === 'premium'
 * That indirection is what lets a plan change, a coupon, a campus deal, a manual grant and
 * a refund all flow through one code path.
 */
final class FeatureGate
{
    public function __construct(
        private readonly EntitlementService $entitlements,
    ) {}

    /**
     * @param  string  $key  an entitlement key from config('commerce.entitlements')
     */
    public function allows(?User $user, string $key): bool
    {
        $this->assertGatable($key);

        // Open posture: every capability is available to everyone, signed in or not —
        // except the keys that buy headroom rather than access. See `commerce.cost_capped`.
        if ($this->gatesOpen() && ! $this->protectsCost($key)) {
            return true;
        }

        if (! $user instanceof User) {
            return false;
        }

        return $this->entitlements->has($user, $key);
    }

    /**
     * Whether this capability WOULD be restricted if gates were closed.
     *
     * Used by the UI to show an honest "this is a premium feature, free while we are in
     * early access" label, rather than pretending a paid feature is permanently free and
     * then taking it away later. People forgive a price; they do not forgive a bait.
     */
    public function isPaidCapability(string $key): bool
    {
        $this->assertGatable($key);

        return true;
    }

    /**
     * Whether this key raises a cost limit rather than unlocking a feature.
     *
     * A revenue gate withholds something in order to sell it, and follows the open posture.
     * A cost cap withholds nothing — it protects unit economics — so it is checked against
     * the entitlement whether gates are open or closed.
     */
    public function protectsCost(string $key): bool
    {
        return in_array($key, (array) config('commerce.cost_capped', []), true);
    }

    public function gatesOpen(): bool
    {
        return (bool) config('commerce.gates_open', true);
    }

    /**
     * Guard against a gate being placed on something that must never be gated.
     *
     * The five capabilities in `commerce.never_gated` are the SEO engine and the habit
     * loop — the notification feed, the eligibility check, exam hub pages, the daily quiz
     * and official material. They are the moat. Gating any of them to raise ARPU would
     * trade a compounding asset for one quarter of revenue, which is the worst trade
     * available in this business. So it throws rather than returning false: this is a
     * programming error to be caught in CI, not a runtime condition to handle.
     *
     * @throws InvalidArgumentException
     */
    private function assertGatable(string $key): void
    {
        /** @var list<string> $neverGated */
        $neverGated = config('commerce.never_gated', []);

        if (in_array($key, $neverGated, true)) {
            throw new InvalidArgumentException(
                "'{$key}' is listed in commerce.never_gated and must stay free at any revenue "
                .'target. It is part of the SEO engine or the habit loop. If this genuinely '
                .'needs to change, it is a decision for the decision log, not a code change.'
            );
        }

        /** @var array<string, string> $known */
        $known = config('commerce.entitlements', []);

        if (! array_key_exists($key, $known)) {
            throw new InvalidArgumentException(
                "Unknown entitlement '{$key}'. Add it to config/commerce.php first, so the "
                .'catalogue stays the single list of what can be sold.'
            );
        }
    }
}
