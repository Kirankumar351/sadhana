<?php

declare(strict_types=1);

use App\Models\Flashcard;
use App\Models\User;
use App\Services\Learning\SpacedRepetition;
use Carbon\CarbonImmutable;

/**
 * The spaced repetition scheduler.
 *
 * Two of the rules here are deliberate departures from textbook SM-2 and both exist for the
 * same reason: our users have an exam date. A card scheduled after it may as well be
 * deleted, and a lapse knocked back to the floor wastes reviews they cannot spare.
 */
beforeEach(function (): void {
    $this->scheduler = app(SpacedRepetition::class);
    $this->user = User::factory()->create();
});

function card(array $attributes = []): Flashcard
{
    return Flashcard::factory()->create(array_merge(['user_id' => test()->user->id], $attributes));
}

it('follows the 1-6-multiply progression on clean recall', function (): void {
    $c = card(['interval_days' => 0, 'ease' => 2.5]);

    $this->scheduler->review($c, 'good');
    expect($c->interval_days)->toBe(1);

    $this->scheduler->review($c, 'good');
    expect($c->interval_days)->toBe(6);

    $this->scheduler->review($c, 'good');
    expect($c->interval_days)->toBe(15);      // 6 * 2.5
});

it('does not reset ease to the floor when a card is forgotten', function (): void {
    // The whole point of the departure: a forgotten card loses a little ease, not all of it.
    $c = card(['interval_days' => 20, 'ease' => 2.5]);

    $this->scheduler->review($c, 'forgot');

    expect($c->ease)->toBe(2.30)
        ->and($c->interval_days)->toBe(0)
        ->and($c->lapse_count)->toBe(1)
        ->and($c->due_at->isToday())->toBeTrue();
});

it('never lets ease fall below the floor no matter how many lapses', function (): void {
    $c = card(['ease' => 1.4]);

    foreach (range(1, 10) as $ignored) {
        $this->scheduler->review($c, 'forgot');
    }

    expect($c->ease)->toBe(1.30)
        ->and($c->lapse_count)->toBe(10);
});

it('caps ease at the ceiling on easy grades', function (): void {
    $c = card(['ease' => 2.75, 'interval_days' => 2]);

    $this->scheduler->review($c, 'easy');

    expect($c->ease)->toBe(2.80);
});

it('pulls an interval back inside the exam window', function (): void {
    // 200-day interval, exam in 30 days. Scheduling past the paper is the same as deleting.
    $exam = CarbonImmutable::parse(today())->addDays(30);
    $c = card(['interval_days' => 200, 'ease' => 2.5]);

    $this->scheduler->review($c, 'good', $exam);

    expect($c->due_at->lessThan($exam))->toBeTrue()
        // Landed four days clear of the paper: the last days are for full mocks and sleep.
        ->and($c->due_at->toDateString())->toBe($exam->subDays(4)->toDateString());
});

it('still schedules tomorrow when the exam is days away', function (): void {
    // Exam in two days: subtracting four would land in the past.
    $exam = CarbonImmutable::parse(today())->addDays(2);
    $c = card(['interval_days' => 30, 'ease' => 2.5]);

    $this->scheduler->review($c, 'good', $exam);

    expect($c->due_at->toDateString())
        ->toBe(CarbonImmutable::parse(today())->addDay()->toDateString());
});

it('orders lapsed cards first so the hard ones are not buried', function (): void {
    $easy = card(['deck' => 'Polity', 'lapse_count' => 0, 'due_at' => today()->subDay()]);
    $hard = card(['deck' => 'Polity', 'lapse_count' => 4, 'due_at' => today()]);

    $due = $this->scheduler->dueFor($this->user->id);

    expect($due->first()->id)->toBe($hard->id)
        ->and($due->last()->id)->toBe($easy->id);
});

it('leaves cards scheduled for the future alone', function (): void {
    card(['due_at' => today()->addDays(5)]);
    $dueToday = card(['due_at' => today()]);

    $due = $this->scheduler->dueFor($this->user->id);

    expect($due)->toHaveCount(1)
        ->and($due->first()->id)->toBe($dueToday->id);
});

it('never returns another users cards', function (): void {
    $other = User::factory()->create();
    Flashcard::factory()->create(['user_id' => $other->id, 'due_at' => today()]);
    card(['due_at' => today()]);

    expect($this->scheduler->dueFor($this->user->id))->toHaveCount(1);
});
