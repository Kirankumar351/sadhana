<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\StreakMilestoneReached;
use App\Models\Streak;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\LazyCollection;

/**
 * Streaks — the retention mechanic.
 *
 * Vol 1 ch.13.2 names D7 retention above 20% as the highest-risk assumption in the whole
 * business plan. This class is the mechanism that has to deliver it.
 *
 * WHY THE FREEZE EXISTS. Users have exams, travel and family emergencies. A streak that
 * punishes real life gets abandoned, and once a streak is abandoned it almost never
 * restarts — the loss is permanent, not temporary. One free miss a month costs nothing and
 * saves a meaningful share of users.
 *
 * TIMEZONE. Every date here is Asia/Kolkata. A user finishing the quiz at 11:50 PM IST and
 * again at 12:10 AM IST has acted on two different days, and a server running UTC would
 * silently merge them and break the streak. All comparisons go through `today()`, which is
 * bound to the app timezone.
 *
 * Target test coverage is 90% — the edge cases are all around midnight, timezones and
 * the freeze boundary.
 */
final class StreakService
{
    /** Milestones worth celebrating. Kept small: everything cannot be a milestone. */
    private const MILESTONES = [3, 7, 30, 100, 365];

    /**
     * Record activity for today and return the resulting streak.
     *
     * Idempotent within a day: calling it twice for the same user on the same date is a
     * no-op, which matters because it is invoked from quiz submission and that can be
     * retried by a flaky mobile connection.
     *
     * @return array{streak: int, longest: int, changed: bool, used_freeze: bool, milestone: int|null}
     */
    public function record(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            /** @var Streak $streak */
            $streak = Streak::query()
                ->lockForUpdate()
                ->firstOrCreate(['user_id' => $user->id]);

            $today = CarbonImmutable::parse(today());
            $last = $streak->last_active_date !== null
                ? CarbonImmutable::parse($streak->last_active_date)
                : null;

            // Monthly freeze allowance is replenished before it is spent, so a user whose
            // gap straddles a month boundary gets the new month's freeze.
            $this->replenishFreeze($streak, $today);

            if ($last !== null && $last->isSameDay($today)) {
                return [
                    'streak' => $streak->current_streak,
                    'longest' => $streak->longest_streak,
                    'changed' => false,
                    'used_freeze' => false,
                    'milestone' => null,
                ];
            }

            $usedFreeze = false;

            if ($last === null) {
                $streak->current_streak = 1;
            } elseif ($last->isSameDay($today->subDay())) {
                $streak->current_streak++;
            } elseif ($this->wholeDaysBetween($last, $today) === 2 && $streak->freezes_left > 0) {
                // Exactly one day missed, and a freeze is available.
                $streak->freezes_left--;
                $streak->current_streak++;
                $usedFreeze = true;
            } else {
                $streak->current_streak = 1;
            }

            $streak->longest_streak = max($streak->longest_streak, $streak->current_streak);
            $streak->last_active_date = $today->toDateString();
            $streak->save();

            $this->pushToLeaderboard($user, $streak->current_streak, $today);

            $milestone = in_array($streak->current_streak, self::MILESTONES, true)
                ? $streak->current_streak
                : null;

            if ($milestone !== null) {
                event(new StreakMilestoneReached($user, $milestone));
            }

            return [
                'streak' => $streak->current_streak,
                'longest' => $streak->longest_streak,
                'changed' => true,
                'used_freeze' => $usedFreeze,
                'milestone' => $milestone,
            ];
        });
    }

    /**
     * Whole calendar days between two dates, unsigned.
     *
     * Carbon 3 returns a signed float from diffInDays, which is why this is wrapped rather
     * than called inline — an unnoticed -1.0 here silently resets every streak.
     */
    private function wholeDaysBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) abs($from->startOfDay()->diffInDays($to->startOfDay()));
    }

    /**
     * One freeze per calendar month.
     */
    private function replenishFreeze(Streak $streak, CarbonImmutable $today): void
    {
        $monthStart = $today->startOfMonth();

        $needsReset = $streak->freeze_reset_at === null
            || CarbonImmutable::parse($streak->freeze_reset_at)->lt($monthStart);

        if ($needsReset) {
            $streak->freezes_left = 1;
            $streak->freeze_reset_at = $monthStart->toDateString();
        }
    }

    /**
     * Redis sorted set per ISO week. Leaderboard reads become O(log n) instead of a
     * full table scan, which matters on notification days when everyone is on the site
     * at once.
     *
     * ISO year-week ("o-W") is used deliberately over "Y-W": in the last days of December
     * the calendar year and the ISO week-year differ, and "Y-W" would split one week
     * across two leaderboards.
     */
    private function pushToLeaderboard(User $user, int $streak, CarbonImmutable $today): void
    {
        $week = $today->format('o-W');

        Redis::zadd("leaderboard:streak:{$week}", $streak, (string) $user->id);

        if ($user->profile?->district !== null) {
            $district = strtolower(str_replace(' ', '-', $user->profile->district));
            Redis::zadd("leaderboard:streak:{$week}:district:{$district}", $streak, (string) $user->id);
        }

        // Expire after five weeks so old leaderboards do not accumulate in memory forever.
        Redis::expire("leaderboard:streak:{$week}", 60 * 60 * 24 * 35);
    }

    /**
     * Users who were active yesterday but not yet today, for the 21:00 reminder.
     * Deliberately not "everyone with a streak": reminding someone who has already done
     * today's quiz is the fastest way to get the whole channel muted.
     *
     * @return LazyCollection<int, Streak>
     */
    public function atRiskToday(): LazyCollection
    {
        return Streak::query()
            ->where('current_streak', '>', 0)
            ->whereDate('last_active_date', today()->subDay())
            ->with('user')
            ->cursor();
    }
}
