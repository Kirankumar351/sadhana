<?php

declare(strict_types=1);

use App\Models\Flashcard;
use App\Models\Question;
use App\Models\QuizAttempt;
use App\Models\Test;
use App\Models\User;
use App\Services\Learning\CardsFromMistakes;

/**
 * The loop that makes the quiz worth doing daily.
 *
 * A scheduler with no deck is an empty screen. These tests cover the half that fills it:
 * every question missed becomes a card due today, and missing the same question again is
 * recorded as a lapse on the card that already exists rather than as a second card.
 */
function quizQuestion(array $attributes = []): Question
{
    static $n = 0;
    $n++;

    return Question::create(array_merge([
        'subject' => 'Indian Polity',
        'question' => ['en' => "Question {$n}?", 'te' => "ప్రశ్న {$n}?"],
        'options' => ['en' => ['Right', 'Wrong', 'C', 'D'], 'te' => ['సరైనది', 'తప్పు', 'సి', 'డి']],
        'explanation' => ['en' => "Because {$n}.", 'te' => "ఎందుకంటే {$n}."],
        'correct_index' => 0,
        'approved_at' => now(),
    ], $attributes));
}

beforeEach(function (): void {
    $this->cards = app(CardsFromMistakes::class);
    $this->user = User::factory()->create(['preferred_locale' => 'te']);
});

function attemptWith(array $rows, array $attributes = []): QuizAttempt
{
    return QuizAttempt::create(array_merge([
        'user_id' => test()->user->id,
        'answers' => $rows,
        'score' => 0,
        'total_marks' => count($rows),
        'completed_at' => now(),
    ], $attributes));
}

it('makes a card from every wrong answer and none from the right ones', function (): void {
    $wrong = quizQuestion();
    $right = quizQuestion();

    $created = $this->cards->fromAttempt(attemptWith([
        ['q' => $wrong->id, 'a' => 1, 't' => 20],
        ['q' => $right->id, 'a' => 0, 't' => 12],
    ]));

    expect($created)->toBe(1);

    $cards = Flashcard::where('user_id', $this->user->id)->get();

    expect($cards)->toHaveCount(1)
        ->and($cards->first()->source_ref)->toBe('question:'.$wrong->id)
        ->and($cards->first()->source_type)->toBe('wrong_answer')
        // Due immediately: the mistake is fresh and the correction is cheapest now.
        ->and($cards->first()->due_at->isToday())->toBeTrue();
});

it('carries the answer and the explanation in every language the question has', function (): void {
    $q = quizQuestion();

    $this->cards->fromAttempt(attemptWith([['q' => $q->id, 'a' => 2]]));

    $card = Flashcard::where('user_id', $this->user->id)->first();

    expect($card->front)->toHaveKeys(['en', 'te'])
        ->and($card->back['en'])->toContain('Right')
        // The explanation travels with the answer, or the card teaches "option A" for a
        // question that will be worded differently in the hall.
        ->and($card->back['en'])->toContain('Because')
        ->and($card->back['te'])->toContain('సరైనది');
});

it('records a repeat mistake as a lapse instead of a duplicate card', function (): void {
    $q = quizQuestion();

    $this->cards->fromAttempt(attemptWith([['q' => $q->id, 'a' => 1]]));

    $card = Flashcard::where('user_id', $this->user->id)->first();
    $card->update(['interval_days' => 21, 'ease' => 2.5, 'due_at' => today()->addDays(21)]);

    // Three weeks later, same question, wrong again.
    $this->cards->fromAttempt(attemptWith([['q' => $q->id, 'a' => 3]]));

    expect(Flashcard::where('user_id', $this->user->id)->count())->toBe(1);

    $card->refresh();

    expect($card->lapse_count)->toBe(1)
        ->and($card->ease)->toBe(2.30)
        ->and($card->due_at->isToday())->toBeTrue();
});

it('treats a blank in the daily quiz as a gap', function (): void {
    // No clock, no penalty — a blank here is something they did not know.
    $q = quizQuestion();

    $created = $this->cards->fromAttempt(attemptWith([['q' => $q->id, 'a' => null]]));

    expect($created)->toBe(1);
});

it('does not punish a strategic skip in a negatively marked mock', function (): void {
    $test = Test::factory()->create(['negative_marking' => 0.33]);
    $skipped = quizQuestion();
    $wrong = quizQuestion();

    $created = $this->cards->fromAttempt(attemptWith(
        [
            ['q' => $skipped->id, 'a' => null],
            ['q' => $wrong->id, 'a' => 2],
        ],
        ['test_id' => $test->id],
    ));

    // Only the genuine mistake. Skipping under negative marking is the instinct the mock
    // is meant to teach, and carding it would bury the real errors.
    expect($created)->toBe(1);

    $card = Flashcard::where('user_id', $this->user->id)->first();
    expect($card->source_ref)->toBe('question:'.$wrong->id);
});

it('counts a blank in a mock with no negative marking', function (): void {
    $test = Test::factory()->create(['negative_marking' => 0]);
    $q = quizQuestion();

    $created = $this->cards->fromAttempt(attemptWith(
        [['q' => $q->id, 'a' => null]],
        ['test_id' => $test->id],
    ));

    expect($created)->toBe(1);
});

it('files the card under the subject so decks build themselves', function (): void {
    $q = quizQuestion(['subject' => 'Modern History']);

    $this->cards->fromAttempt(attemptWith([['q' => $q->id, 'a' => 1]]));

    expect(Flashcard::where('user_id', $this->user->id)->first()->deck)->toBe('Modern History');
});

it('ignores answers referring to questions that no longer exist', function (): void {
    // A disputed question pulled from the pool must not blow up card building.
    $created = $this->cards->fromAttempt(attemptWith([['q' => 999999, 'a' => 1]]));

    expect($created)->toBe(0);
});
