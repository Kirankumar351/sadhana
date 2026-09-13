<?php

declare(strict_types=1);

use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Push\PushBudget;

/**
 * The push budget.
 *
 * This is the code that protects users from us. Over-notification is the fastest way to
 * get permanently muted, and a muted user is effectively lost — they do not un-mute, they
 * just stop coming back. The cap is five a day and it is not a guideline.
 */
beforeEach(function (): void {
    $this->budget = new PushBudget;
    $this->user = User::factory()->create();
});

it('allows a message when nothing has been sent today', function (): void {
    expect($this->budget->allows($this->user, 'daily_quiz'))->toBeTrue();
});

it('enforces the five-a-day cap', function (): void {
    // Four different types, so no per-type limit interferes.
    foreach (['daily_quiz', 'streak', 'new_notification', 'new_notification', 'new_notification'] as $type) {
        $this->budget->record($this->user, $type);
    }

    expect($this->budget->totalSentToday($this->user))->toBe(5)
        ->and($this->budget->remaining($this->user))->toBe(0)
        ->and($this->budget->allows($this->user, 'streak'))->toBeFalse();
});

/**
 * THE ONE EXCEPTION, AND IT STAYS THE ONLY ONE.
 *
 * A deadline reminder goes through a full budget because the cost of silence is that
 * somebody misses a job application they had already decided to make. That is a different
 * order of harm from missing a quiz nudge.
 */
it('lets a deadline reminder through a full budget', function (): void {
    foreach (range(1, 6) as $i) {
        $this->budget->record($this->user, 'new_notification');
    }

    expect($this->budget->allows($this->user, 'deadline'))->toBeTrue()
        ->and($this->budget->allows($this->user, 'daily_quiz'))->toBeFalse()
        ->and($this->budget->allows($this->user, 'current_affairs'))->toBeFalse();
});

/**
 * A second "did you do the quiz" on the same day reads as nagging to someone who decided
 * not to, and as broken to someone who already has.
 */
it('sends the daily quiz at most once a day', function (): void {
    $this->budget->record($this->user, 'daily_quiz');

    expect($this->budget->allows($this->user, 'daily_quiz'))->toBeFalse()
        // Other types are unaffected — this is a per-type limit, not the overall cap.
        ->and($this->budget->allows($this->user, 'new_notification'))->toBeTrue();
});

it('caps new job alerts at three a day', function (): void {
    foreach (range(1, 3) as $i) {
        $this->budget->record($this->user, 'new_notification');
    }

    expect($this->budget->allows($this->user, 'new_notification'))->toBeFalse();
});

// ---------------------------------------------------------------- opt-in behaviour

/**
 * A user who has never opened settings still expects the daily quiz they signed up for,
 * so the absence of a preference row means yes for the core types.
 */
it('defaults core notification types to on', function (string $type): void {
    expect($this->budget->allows($this->user, $type))->toBeTrue();
})->with(['daily_quiz', 'new_notification', 'deadline', 'streak']);

/**
 * Integration Map gap 10: the current-affairs digest was designed after the five-a-day cap
 * was set. Defaulting it on would have quietly made the real ceiling six, so it is opt-in
 * and it competes inside the same budget as everything else.
 */
it('defaults the digest and community replies to off', function (string $type): void {
    expect($this->budget->allows($this->user, $type))->toBeFalse();
})->with(['current_affairs', 'community']);

it('respects an explicit opt-out', function (): void {
    NotificationPreference::create([
        'user_id' => $this->user->id,
        'type' => 'daily_quiz',
        'push' => false,
        'whatsapp' => false,
    ]);

    expect($this->budget->allows($this->user, 'daily_quiz'))->toBeFalse();
});

it('honours an opt-in for a type that is off by default', function (): void {
    NotificationPreference::create([
        'user_id' => $this->user->id,
        'type' => 'current_affairs',
        'push' => true,
    ]);

    expect($this->budget->allows($this->user, 'current_affairs'))->toBeTrue();
});

/**
 * WhatsApp-only counts as opted in. Someone who wants alerts on WhatsApp but not as
 * browser notifications has made a clear choice, and reading that as "no" would silence
 * the channel they actually prefer.
 */
it('treats a whatsapp-only opt-in as opted in', function (): void {
    NotificationPreference::create([
        'user_id' => $this->user->id,
        'type' => 'deadline',
        'push' => false,
        'whatsapp' => true,
    ]);

    expect($this->budget->allows($this->user, 'deadline'))->toBeTrue();
});
