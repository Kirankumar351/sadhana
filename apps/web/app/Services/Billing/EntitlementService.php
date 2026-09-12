<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Entitlement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Grants, revokes and answers questions about entitlements.
 *
 * An entitlement is a key a user holds, with a source and an optional expiry. Five things
 * can grant one — a subscription, a one-off purchase, a coupon, a referral reward, or a
 * manual staff grant — and feature code needs to know none of that. It asks whether the
 * key is held; this class knows why.
 *
 * Reads are cached for five minutes and busted on every write. The cache matters because
 * `allows()` is called several times on most page renders, and the expiry window is short
 * because the cost of a stale grant is small and bounded while the cost of a slow feed on
 * notification day is not.
 */
final class EntitlementService
{
    private const CACHE_TTL_SECONDS = 300;

    public function has(User $user, string $key): bool
    {
        return in_array($key, $this->keysFor($user), true);
    }

    /**
     * @return list<string>
     */
    public function keysFor(User $user): array
    {
        /** @var list<string> */
        return Cache::remember(
            $this->cacheKey($user),
            self::CACHE_TTL_SECONDS,
            static fn (): array => Entitlement::query()
                ->where('user_id', $user->id)
                ->where('is_revoked', false)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->pluck('key')
                ->unique()
                ->values()
                ->all(),
        );
    }

    /**
     * @param  list<string>  $keys
     */
    public function grantMany(
        User $user,
        array $keys,
        string $sourceType,
        ?int $sourceId = null,
        ?CarbonInterface $expiresAt = null,
    ): void {
        DB::transaction(function () use ($user, $keys, $sourceType, $sourceId, $expiresAt): void {
            foreach ($keys as $key) {
                $this->grant($user, $key, $sourceType, $sourceId, $expiresAt);
            }
        });
    }

    public function grant(
        User $user,
        string $key,
        string $sourceType,
        ?int $sourceId = null,
        ?CarbonInterface $expiresAt = null,
    ): Entitlement {
        /**
         * Deliberately one row per grant rather than an upsert.
         *
         * A user can hold the same key from two sources at once — an annual subscription
         * and a campus coupon, say — and when the coupon lapses the subscription must still
         * cover them. Collapsing these into a single row loses that, and the user loses
         * access to something they paid for.
         */
        $entitlement = Entitlement::create([
            'user_id' => $user->id,
            'key' => $key,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'granted_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        $this->flush($user);

        return $entitlement;
    }

    /**
     * Revoke every grant from one source — a cancelled subscription or a reversed payment.
     * Grants from other sources are untouched, which is the whole point of keeping them
     * as separate rows.
     */
    public function revokeBySource(User $user, string $sourceType, ?int $sourceId, string $reason): int
    {
        $count = Entitlement::query()
            ->where('user_id', $user->id)
            ->where('source_type', $sourceType)
            ->when($sourceId !== null, fn ($q) => $q->where('source_id', $sourceId))
            ->where('is_revoked', false)
            ->update([
                'is_revoked' => true,
                'revoke_reason' => $reason,
                'updated_at' => now(),
            ]);

        $this->flush($user);

        return $count;
    }

    public function flush(User $user): void
    {
        Cache::forget($this->cacheKey($user));
    }

    private function cacheKey(User $user): string
    {
        return "entitlements:{$user->id}";
    }
}
