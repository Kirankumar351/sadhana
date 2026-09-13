<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * How many notifications a user may receive today, and of what kind.
 *
 * HARD CAP: FIVE PUSHES PER USER PER DAY. Over-notification is the fastest way to get
 * permanently muted, and once a user mutes us they are effectively lost — they do not
 * un-mute, they just stop coming back. The cap is not a guideline.
 *
 * Integration Map gap 10: the current-affairs digest was designed after this cap was set,
 * and adding it on top would have quietly made the real ceiling six. Every push type
 * competes INSIDE the five. When the budget is full, the lowest-priority type is the one
 * that does not get sent.
 *
 * Priority order is deliberate and reflects what a user loses by missing it:
 *   1. deadline    a job closes tomorrow. Miss this and they miss the application.
 *   2. new job     a notification they are eligible for. Time-sensitive.
 *   3. daily quiz  the habit. Valuable, but tomorrow exists.
 *   4. streak      a nudge. Nice to have.
 *   5. digest      current affairs. Entirely deferrable.
 *   6. community   a reply. Batched anyway.
 */
final class PushBudget
{
    public const DAILY_CAP = 5;

    /** Lower number wins when the budget is tight. */
    private const PRIORITY = [
        'deadline' => 1,
        'new_notification' => 2,
        'daily_quiz' => 3,
        'streak' => 4,
        'current_affairs' => 5,
        'community' => 6,
    ];

    /**
     * Per-type daily limits, applied before the overall cap.
     *
     * The daily quiz is once. A second "did you do the quiz" on the same day reads as
     * nagging to someone who has decided not to, and as broken to someone who already has.
     */
    private const PER_TYPE_DAILY = [
        'daily_quiz' => 1,
        'new_notification' => 3,
        'streak' => 1,
        'current_affairs' => 1,
        'deadline' => 2,
        'community' => 1,
    ];

    public function allows(User $user, string $type): bool
    {
        if (! $this->userWants($user, $type)) {
            return false;
        }

        if ($this->sentToday($user, $type) >= (self::PER_TYPE_DAILY[$type] ?? 1)) {
            return false;
        }

        $used = $this->totalSentToday($user);

        if ($used < self::DAILY_CAP) {
            return true;
        }

        /**
         * The budget is full. A deadline reminder may still go, because the cost of
         * silence there is that someone misses a job application entirely — which is a
         * different order of harm from missing a quiz nudge.
         *
         * Nothing else gets through. This is the only exception and it stays the only one.
         */
        return (self::PRIORITY[$type] ?? 99) === 1;
    }

    public function record(User $user, string $type): void
    {
        Cache::increment($this->key($user, $type));
        Cache::increment($this->totalKey($user));

        // Seed the TTL on first write. Cache::increment does not set one.
        Cache::put($this->key($user, $type), $this->sentToday($user, $type), now()->endOfDay());
        Cache::put($this->totalKey($user), $this->totalSentToday($user), now()->endOfDay());
    }

    public function sentToday(User $user, string $type): int
    {
        return (int) Cache::get($this->key($user, $type), 0);
    }

    public function totalSentToday(User $user): int
    {
        return (int) Cache::get($this->totalKey($user), 0);
    }

    public function remaining(User $user): int
    {
        return max(0, self::DAILY_CAP - $this->totalSentToday($user));
    }

    /**
     * Has the user opted in to this type, on any channel?
     *
     * Defaults to true when no preference row exists, because a user who has never opened
     * settings still expects the daily quiz they signed up for. Marketing-adjacent types
     * default off — nobody signs up expecting those.
     */
    private function userWants(User $user, string $type): bool
    {
        $preference = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->first();

        if ($preference === null) {
            return ! in_array($type, ['current_affairs', 'community'], true);
        }

        return $preference->push || $preference->whatsapp;
    }

    private function key(User $user, string $type): string
    {
        return "push:{$user->id}:{$type}:".today()->toDateString();
    }

    private function totalKey(User $user): string
    {
        return "push:{$user->id}:total:".today()->toDateString();
    }
}
