<?php

declare(strict_types=1);

use App\Livewire\Ai\EvaluateAnswer as Screen;
use App\Models\AnswerEvaluation;
use App\Models\Exam;
use App\Models\MainsQuestion;
use App\Models\User;
use App\Services\AI\Features\AnswerEvaluator;
use Livewire\Livewire;
use Tests\Support\FakeAi;

/**
 * Descriptive answer evaluation.
 *
 * The rules that make it trustworthy: it gives feedback rather than a mark, the rubric is
 * published and frozen onto each evaluation, and the word count — the one objectively
 * checkable number — is counted in code rather than asked for.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create(['preferred_locale' => 'te']);
    $this->exam = Exam::factory()->withMains()->create();

    $this->question = MainsQuestion::create([
        'exam_id' => $this->exam->id,
        'paper' => 'Paper III',
        'subject' => 'Telangana Movement',
        'question' => ['en' => "Examine the role of the Gentlemen's Agreement of 1956 in shaping the Telangana movement."],
        'marks' => 15,
        'word_limit' => 250,
        'directive' => 'examine',
        'rubric' => [
            'Required content points' => 60,
            'Directive word answered' => 20,
            'Structure: intro, body, conclusion' => 10,
            'Word count discipline' => 10,
        ],
        'model_answer' => ['en' => 'A model answer.'],
    ]);
});

function evaluation(array $data = []): array
{
    return array_merge([
        'band' => 7,
        'points_hit' => ['Named the agreement and its 1956 context'],
        'points_missed' => [
            ['point' => 'The exact date — 20 February 1956', 'why' => 'Examiners reward specific dates'],
            ['point' => 'No conclusion at all', 'why' => 'A 15-mark answer without one loses marks structurally'],
        ],
        'directive_answered' => false,
        'has_conclusion' => false,
        'single_biggest_gain' => 'Add the date and a three-line conclusion — roughly 90 more words.',
    ], $data);
}

function longAnswer(): string
{
    return str_repeat('The Gentlemen agreement gave three assurances to Telangana leaders. ', 10);
}

it('counts words in code rather than asking the model for them', function (): void {
    // The one number here that is objectively checkable, so asking for it would invite an
    // error into the only part of the feedback that cannot be wrong.
    $evaluator = app(AnswerEvaluator::class);

    expect($evaluator->wordCount('one two three'))->toBe(3)
        ->and($evaluator->wordCount("  spaced \n out   words "))->toBe(3)
        ->and($evaluator->wordCount(''))->toBe(0)
        ->and($evaluator->wordCount('తెలంగాణ ఉద్యమం 1969'))->toBe(3);
});

it('stores the feedback against the question', function (): void {
    FakeAi::install('unused', [], evaluation());

    Livewire::actingAs($this->user)
        ->test(Screen::class)
        ->set('questionId', $this->question->id)
        ->set('answer', longAnswer())
        ->call('evaluate')
        ->assertSee(__('Points you missed'))
        ->assertSee('The exact date');

    $stored = AnswerEvaluation::where('user_id', $this->user->id)->first();

    expect($stored)->not->toBeNull()
        ->and($stored->band)->toBe(7.0)
        ->and($stored->word_count)->toBe(app(AnswerEvaluator::class)->wordCount(longAnswer()));
});

it('freezes the rubric onto the evaluation', function (): void {
    // A later rubric revision must never silently rewrite the history a student measures
    // their progress against.
    FakeAi::install('unused', [], evaluation());

    app(AnswerEvaluator::class)->evaluate($this->user, $this->question, longAnswer());

    $this->question->update(['rubric' => ['Everything' => 100]]);

    $stored = AnswerEvaluation::where('user_id', $this->user->id)->first();

    expect($stored->rubric)->toHaveKey('Required content points')
        ->and($stored->rubric)->not->toHaveKey('Everything');
});

it('never reports a band above the marks available', function (): void {
    FakeAi::install('unused', [], evaluation(['band' => 40]));

    app(AnswerEvaluator::class)->evaluate($this->user, $this->question, longAnswer());

    expect(AnswerEvaluation::first()->band)->toBe(15.0);
});

it('tells the model to judge against the rubric and never to predict a mark', function (): void {
    $client = FakeAi::install('unused', [], evaluation());

    app(AnswerEvaluator::class)->evaluate($this->user, $this->question, longAnswer());

    expect($client->instructions[0])->toContain('Required content points')
        ->and($client->instructions[0])->toContain('never a predicted mark')
        ->and($client->instructions[0])->toContain('Directive word: examine')
        // The two structural checks a student loses marks on without ever being told.
        ->and($client->instructions[0])->toContain('directive word was answered')
        ->and($client->instructions[0])->toContain('whether there is a conclusion');
});

it('shows the rubric before the answer is written, not after', function (): void {
    // A student who can see that a fifth of the marks ride on the directive word will answer
    // it. One who learns that afterwards has learned it about an answer they cannot change.
    Livewire::actingAs($this->user)
        ->test(Screen::class)
        ->set('questionId', $this->question->id)
        ->assertSee(__('The rubric'))
        ->assertSee('Directive word answered')
        ->assertSee(__('Published, not hidden. You can see exactly what it is scoring.'));
});

it('says plainly that this is feedback and not a mark', function (): void {
    Livewire::actingAs($this->user)
        ->test(Screen::class)
        ->assertSee(__('This is feedback, not a mark.'));
});

it('refuses a fragment rather than spending a monthly evaluation on it', function (): void {
    FakeAi::install('unused', [], evaluation());

    Livewire::actingAs($this->user)
        ->test(Screen::class)
        ->set('questionId', $this->question->id)
        ->set('answer', 'Too short.')
        ->call('evaluate')
        ->assertHasErrors('answer');

    expect(AnswerEvaluation::count())->toBe(0);
});

it('reports the cap honestly and keeps past evaluations available', function (): void {
    config(['ai.caps.free.answer_evaluations_per_month' => 1]);
    FakeAi::install('unused', [], evaluation());

    app(AnswerEvaluator::class)->evaluate($this->user, $this->question, longAnswer());

    Livewire::actingAs($this->user)
        ->test(Screen::class)
        ->set('questionId', $this->question->id)
        ->set('answer', longAnswer())
        ->call('evaluate')
        ->assertSee(__('Reading a mains answer properly is the most expensive thing we do, which is why the allowance is small. Your previous evaluations stay available.'));

    // The cap stopped a second one being written, and the first survives.
    expect(AnswerEvaluation::count())->toBe(1);
});

it('writes nothing when the provider fails', function (): void {
    FakeAi::install('unused', [], []);

    $result = app(AnswerEvaluator::class)->evaluate($this->user, $this->question, longAnswer());

    expect($result->succeeded())->toBeFalse()
        ->and(AnswerEvaluation::count())->toBe(0);
});

it('finds the habit repeating across many answers', function (): void {
    // One evaluation cannot show this, and it is the most useful thing the feature knows.
    foreach (range(1, 8) as $i) {
        AnswerEvaluation::create([
            'user_id' => $this->user->id,
            'mains_question_id' => $this->question->id,
            'answer_text' => 'x',
            'rubric' => $this->question->rubric,
            'band' => 7,
            'word_count' => 148,
            'points_hit' => [],
            'points_missed' => $i <= 6
                ? [['point' => 'No conclusion at all', 'why' => 'Structural']]
                : [['point' => 'The signatories', 'why' => 'Depth']],
        ]);
    }

    $gap = app(AnswerEvaluator::class)->recurringGap($this->user);

    expect($gap['gap'])->toBe('No conclusion at all')
        ->and($gap['count'])->toBe(6)
        ->and($gap['total'])->toBe(8);
});

it('does not call the last thing that happened a pattern', function (): void {
    AnswerEvaluation::create([
        'user_id' => $this->user->id,
        'mains_question_id' => $this->question->id,
        'answer_text' => 'x',
        'rubric' => $this->question->rubric,
        'points_missed' => [['point' => 'No conclusion at all']],
    ]);

    expect(app(AnswerEvaluator::class)->recurringGap($this->user))->toBeNull();
});

it('requires a login', function (): void {
    $this->get(route('answer-evaluation', ['locale' => 'te']))->assertRedirect();
});
