<?php

declare(strict_types=1);

use App\Models\Streak;
use App\Models\User;
use App\Services\StreakService;
use Illuminate\Support\Facades\Redis;

/**
 * Streaks — the retention mechanic.
 *
 * Target coverage is 90%. Every edge case here is about a boundary in time: midnight, a
 * month rollover, or the one-day gap the freeze is meant to absorb. Those are exactly the
 * cases nobody hits in manual testing and every user hits eventually.
 *
 * Redis is faked because the leaderboard is a side effect, not the behaviour under test,
 * and requiring a Redis server to run the suite would mean the suite stops being run.
 */
beforeEach(function (): void {
    Redis::spy();
    $this->service = app(StreakService::class);
    $this->user = User::factory()->create();
});

function streakFor(User $user, array $attributes): Streak
{
    return Streak::create(array_merge(['user_id' => $user->id], $attributes));
}

it('starts a streak at one on the first ever activity', function (): void {
    $result = $this->service->record($this->user);

    expect($result['streak'])->toBe(1)
        ->and($result['changed'])->toBeTrue()
        ->and($result['used_freeze'])->toBeFalse();
});

it('increments when the user was active yesterday', function (): void {
    streakFor($this->user, [
        'current_streak' => 10,
        'longest_streak' => 10,
        'last_active_date' => today()->subDay(),
    ]);

    expect($this->service->record($this->user)['streak'])->toBe(11);
});

/**
 * Idempotent within a day.
 *
 * Not a nicety: quiz submission is invoked from a phone on a patchy connection and the
 * request is retried. A second call must not award a second day.
 */
it('does nothing when the user was already active today', function (): void {
    streakFor($this->user, [
        'current_streak' => 5,
        'last_active_date' => today(),
    ]);

    $result = $this->service->record($this->user);

    expect($result['streak'])->toBe(5)
        ->and($result['changed'])->toBeFalse();
});

/**
 * The freeze. Users have exams, travel and family emergencies, and a streak that punishes
 * real life gets abandoned — permanently, because an abandoned streak almost never
 * restarts. One free miss a month costs nothing and saves a meaningful share of users.
 */
it('absorbs a single missed day using a freeze', function (): void {
    streakFor($this->user, [
        'current_streak' => 10,
        'longest_streak' => 10,
        'last_active_date' => today()->subDays(2),
        'freezes_left' => 1,
        'freeze_reset_at' => today()->startOfMonth(),
    ]);

    $result = $this->service->record($this->user);

    expect($result['streak'])->toBe(11)
        ->and($result['used_freeze'])->toBeTrue()
        ->and($this->user->streak()->first()->freezes_left)->toBe(0);
});

it('resets when a day is missed and no freeze is left', function (): void {
    streakFor($this->user, [
        'current_streak' => 10,
        'longest_streak' => 10,
        'last_active_date' => today()->subDays(2),
        'freezes_left' => 0,
        'freeze_reset_at' => today()->startOfMonth(),
    ]);

    expect($this->service->record($this->user)['streak'])->toBe(1);
});

/**
 * The freeze covers ONE missed day, not an absence. Two missed days is someone who stopped
 * using the product, and pretending otherwise would make the streak meaningless.
 */
it('resets after two missed days even with a freeze available', function (): void {
    streakFor($this->user, [
        'current_streak' => 10,
        'last_active_date' => today()->subDays(3),
        'freezes_left' => 1,
        'freeze_reset_at' => today()->startOfMonth(),
    ]);

    $result = $this->service->record($this->user);

    expect($result['streak'])->toBe(1)
        ->and($result['used_freeze'])->toBeFalse();
});

it('replenishes the freeze at the start of a new month', function (): void {
    streakFor($this->user, [
        'current_streak' => 10,
        'last_active_date' => today()->subDay(),
        'freezes_left' => 0,
        'freeze_reset_at' => today()->startOfMonth()->subMonth(),
    ]);

    $this->service->record($this->user);

    expect($this->user->streak()->first()->freezes_left)->toBe(1);
});

it('keeps the longest streak when the current one resets', function (): void {
    streakFor($this->user, [
        'current_streak' => 40,
        'longest_streak' => 40,
        'last_active_date' => today()->subDays(5),
        'freezes_left' => 0,
    ]);

    $result = $this->service->record($this->user);

    expect($result['streak'])->toBe(1)
        ->and($result['longest'])->toBe(40);
});

it('reports a milestone only on the exact day it is reached', function (): void {
    streakFor($this->user, [
        'current_streak' => 6,
        'last_active_date' => today()->subDay(),
        'freezes_left' => 1,
        'freeze_reset_at' => today()->startOfMonth(),
    ]);

    expect($this->service->record($this->user)['milestone'])->toBe(7);
});

it('reports no milestone on an ordinary day', function (): void {
    streakFor($this->user, [
        'current_streak' => 11,
        'last_active_date' => today()->subDay(),
        'freezes_left' => 1,
        'freeze_reset_at' => today()->startOfMonth(),
    ]);

    expect($this->service->record($this->user)['milestone'])->toBeNull();
});
