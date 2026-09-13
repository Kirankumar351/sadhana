<?php

declare(strict_types=1);

namespace App\Livewire\Ai;

use App\Models\AnswerEvaluation;
use App\Models\MainsQuestion;
use App\Services\AI\AiStructured;
use App\Services\AI\Features\AnswerEvaluator;
use App\Services\Billing\FeatureGate;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Answer evaluation — AI Layer feature 07.
 *
 * Pick a mains question, write the answer, get it read against the published rubric.
 *
 * THE RUBRIC IS VISIBLE BEFORE THEY WRITE, not revealed with the result. A student who can
 * see that a fifth of the marks ride on answering the directive word will answer it; one who
 * learns that afterwards has only learned it about an answer they can no longer change.
 */
class EvaluateAnswer extends Component
{
    use WithFileUploads;

    public ?int $questionId = null;

    public string $answer = '';

    public $photo = null;

    public ?AiStructured $result = null;

    public function getQuestionProperty(): ?MainsQuestion
    {
        return $this->questionId === null ? null : MainsQuestion::find($this->questionId);
    }

    /**
     * @return Collection<int, MainsQuestion>
     */
    public function getQuestionsProperty(): Collection
    {
        return MainsQuestion::query()
            ->when(
                auth()->user()?->primaryExam(),
                fn ($query, $exam) => $query->where('exam_id', $exam->id),
            )
            ->latest('source_year')
            ->limit(30)
            ->get();
    }

    public function getWordsProperty(): int
    {
        return app(AnswerEvaluator::class)->wordCount($this->answer);
    }

    /**
     * @return Collection<int, AnswerEvaluation>
     */
    public function getHistoryProperty(): Collection
    {
        return AnswerEvaluation::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->limit(30)
            ->get();
    }

    /**
     * @return array{gap: string, count: int, total: int}|null
     */
    public function getRecurringGapProperty(): ?array
    {
        return app(AnswerEvaluator::class)->recurringGap(auth()->user());
    }

    public function getAverageBandProperty(): ?float
    {
        $bands = $this->history->pluck('band')->filter();

        return $bands->isEmpty() ? null : round($bands->avg(), 1);
    }

    /**
     * Whether this is a paid capability at all.
     *
     * Used to show an honest "premium, free while we are in early access" label rather than
     * presenting a paid feature as permanently free and taking it away later. People forgive
     * a price; they do not forgive a bait.
     */
    public function getIsPremiumProperty(): bool
    {
        return app(FeatureGate::class)->isPaidCapability('ai_answer_eval');
    }

    public function evaluate(AnswerEvaluator $evaluator): void
    {
        $this->validate([
            'questionId' => ['required', 'exists:mains_questions,id'],
            // Short enough to be a fragment rather than an answer: evaluating it wastes one
            // of a small monthly allowance and teaches nothing.
            'answer' => ['required', 'string', 'min:200', 'max:20000'],
        ]);

        $this->result = $evaluator->evaluate(auth()->user(), $this->question, $this->answer);
    }

    public function render()
    {
        return view('livewire.ai.evaluate-answer');
    }
}
