<?php

declare(strict_types=1);

use App\Models\DailyQuiz;
use App\Models\Question;
use App\Notifications\QuizPoolLow;
use Illuminate\Support\Facades\Notification;

/**
 * Publishing the daily quiz.
 *
 * This command carries the retention engine. A morning with no quiz breaks the 7 AM habit
 * for every user at once, and a broken streak rarely restarts — so the rules about what it
 * will and will not publish matter more than the assembly logic.
 */
function approvedQuestion(array $attributes = []): Question
{
    static $n = 0;
    $n++;

    return Question::create(array_merge([
        'subject' => 'Indian Polity',
        'question' => ['en' => "Question {$n}?", 'te' => "ప్రశ్న {$n}?"],
        'options' => ['en' => ['A', 'B', 'C', 'D'], 'te' => ['ఎ', 'బి', 'సి', 'డి']],
        'correct_index' => 0,
        'approved_at' => now(),
    ], $attributes));
}

beforeEach(function (): void {
    Notification::fake();
});

it('publishes ten questions when the pool is healthy', function (): void {
    for ($i = 0; $i < 15; $i++) {
        approvedQuestion(['is_current_affairs' => $i < 8]);
    }

    $this->artisan('quiz:publish')->assertSuccessful();

    $quiz = DailyQuiz::today();

    expect($quiz)->not->toBeNull()
        ->and($quiz->question_ids)->toHaveCount(10)
        ->and($quiz->published_at)->not->toBeNull();
});

/**
 * NEVER PUBLISH A SHORT QUIZ.
 *
 * A user who opens "today's 10 questions" and finds six learns the product is unreliable,
 * and that impression is much harder to undo than a single missed day.
 */
it('refuses to publish a short quiz and alerts instead', function (): void {
    for ($i = 0; $i < 4; $i++) {
        approvedQuestion();
    }

    $this->artisan('quiz:publish')->assertFailed();

    expect(DailyQuiz::today())->toBeNull();

    Notification::assertSentOnDemand(QuizPoolLow::class);
});

/**
 * An unapproved answer key has not been checked by a person. A wrong key teaches thousands
 * of people something false and they carry it into the exam hall.
 */
it('will not serve questions whose answer key is unapproved', function (): void {
    for ($i = 0; $i < 15; $i++) {
        approvedQuestion(['approved_at' => null]);
    }

    $this->artisan('quiz:publish')->assertFailed();
});

/**
 * A question with no Telugu is invisible to most of our users. Serving it inside a Telugu
 * quiz is the silent failure that loses people: they do not report it, they just stop
 * doing the quiz.
 */
it('will not serve questions that have no Telugu', function (): void {
    for ($i = 0; $i < 15; $i++) {
        approvedQuestion(['question' => ['en' => "English only {$i}?"]]);
    }

    $this->artisan('quiz:publish')->assertFailed();
});

it('will not serve a disputed question', function (): void {
    for ($i = 0; $i < 15; $i++) {
        approvedQuestion(['is_disputed' => true]);
    }

    $this->artisan('quiz:publish')->assertFailed();
});

/**
 * Without a cooldown the rotation serves the same easy questions repeatedly, the accuracy
 * statistics stop meaning anything, and regular users notice immediately.
 */
it('does not repeat a question used within the cooldown window', function (): void {
    $questions = collect(range(1, 12))->map(fn () => approvedQuestion());

    $used = $questions->take(10)->pluck('id')->all();

    DailyQuiz::create([
        'quiz_date' => today()->subDays(5),
        'question_ids' => $used,
        'published_at' => now(),
    ]);

    // Only 2 unused remain, so a full quiz cannot be built without repeating.
    $this->artisan('quiz:publish')->assertFailed();
});

it('is idempotent for a day that is already published', function (): void {
    for ($i = 0; $i < 15; $i++) {
        approvedQuestion();
    }

    $this->artisan('quiz:publish')->assertSuccessful();
    $first = DailyQuiz::today()->question_ids;

    $this->artisan('quiz:publish')->assertSuccessful();

    expect(DailyQuiz::query()->whereDate('quiz_date', today())->count())->toBe(1)
        ->and(DailyQuiz::today()->question_ids)->toBe($first);
});

it('counts each served question so the pool rotates evenly', function (): void {
    for ($i = 0; $i < 12; $i++) {
        approvedQuestion();
    }

    $this->artisan('quiz:publish')->assertSuccessful();

    $ids = DailyQuiz::today()->question_ids;

    $counts = Question::query()->whereIn('id', $ids)->pluck('times_served')->all();

    expect($counts)->toHaveCount(10)
        ->and(array_unique($counts))->toBe([1]);

    // The two questions left out this time must still be at zero, so they sort first
    // tomorrow. That is what keeps the rotation even rather than serving favourites.
    $unused = Question::query()->whereNotIn('id', $ids)->pluck('times_served')->all();

    expect(array_sum($unused))->toBe(0);
});
