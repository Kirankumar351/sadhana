<?php

declare(strict_types=1);

namespace App\Livewire\Quiz;

use App\Jobs\BuildFlashcardsFromAttempt;
use App\Models\DailyQuiz as DailyQuizModel;
use App\Models\Question;
use App\Models\QuizAttempt;
use App\Services\StreakService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The daily quiz — one question at a time.
 *
 * One question per screen rather than a scrolling list, because the target device is a
 * small phone held one-handed and a list means the reader loses their place every time
 * they tap an option.
 *
 * Per-question language toggle: a Telugu-medium student often knows a technical term only
 * in English, and being able to flip one question without changing the whole interface is
 * the difference between answering and giving up.
 */
class DailyQuiz extends Component
{
    #[Locked]
    public int $quizId;

    public int $index = 0;

    /** @var array<int, int> question id => chosen option index */
    public array $answers = [];

    /** @var array<int, int> question id => seconds spent */
    public array $timings = [];

    /** Per-question language override, so it does not change the whole UI. */
    public ?string $questionLocale = null;

    public bool $submitting = false;

    private ?Collection $cachedQuestions = null;

    public function mount(DailyQuizModel $quiz): void
    {
        $this->quizId = $quiz->id;
    }

    /**
     * @return Collection<int, Question>
     */
    public function getQuestionsProperty(): Collection
    {
        return $this->cachedQuestions ??= DailyQuizModel::find($this->quizId)->questions();
    }

    public function getCurrentProperty(): ?Question
    {
        return $this->questions[$this->index] ?? null;
    }

    public function getLocaleProperty(): string
    {
        return $this->questionLocale ?? app()->getLocale();
    }

    public function toggleLanguage(): void
    {
        $this->questionLocale = $this->locale === 'te' ? 'en' : 'te';
    }

    public function choose(int $questionId, int $optionIndex): void
    {
        $this->answers[$questionId] = $optionIndex;
    }

    public function next(): void
    {
        if ($this->index < $this->questions->count() - 1) {
            $this->index++;
            $this->questionLocale = null;   // each question starts in the user's own language
        }
    }

    public function previous(): void
    {
        if ($this->index > 0) {
            $this->index--;
            $this->questionLocale = null;
        }
    }

    public function jumpTo(int $index): void
    {
        if ($index >= 0 && $index < $this->questions->count()) {
            $this->index = $index;
            $this->questionLocale = null;
        }
    }

    /**
     * Submit and score.
     *
     * Scoring happens SERVER-SIDE from the stored correct_index, never from anything the
     * client sends. The client is told which answer was right only after submission, so a
     * reader of the page source cannot see the key in advance.
     */
    public function submit(StreakService $streaks)
    {
        $this->submitting = true;

        $quiz = DailyQuizModel::findOrFail($this->quizId);
        $questions = $this->questions;
        $user = auth()->user();

        // Idempotent: a flaky mobile connection retrying the request must not create a
        // second attempt or bump the streak twice.
        $existing = $quiz->attemptFor($user);

        if ($existing !== null) {
            return $this->redirectRoute('quiz.result', ['attempt' => $existing->id], navigate: true);
        }

        $score = 0;
        $payload = [];
        $bySubject = [];

        foreach ($questions as $question) {
            $chosen = $this->answers[$question->id] ?? null;
            $correct = $chosen !== null && $question->isCorrect($chosen);

            if ($correct) {
                $score++;
            }

            $payload[] = [
                'q' => $question->id,
                'a' => $chosen,
                't' => $this->timings[$question->id] ?? null,
            ];

            $subject = $question->subject ?? 'general';
            $bySubject[$subject]['total'] = ($bySubject[$subject]['total'] ?? 0) + 1;
            $bySubject[$subject]['correct'] = ($bySubject[$subject]['correct'] ?? 0) + ($correct ? 1 : 0);
        }

        // Percentage per subject — this is what the "work on this" card and the study
        // planner read, so it is computed once here rather than re-derived later.
        $breakdown = [];
        foreach ($bySubject as $subject => $counts) {
            $breakdown[$subject] = (int) round($counts['correct'] / $counts['total'] * 100);
        }

        $attempt = DB::transaction(function () use ($quiz, $user, $payload, $score, $questions, $breakdown) {
            $attempt = QuizAttempt::create([
                'user_id' => $user->id,
                'daily_quiz_id' => $quiz->id,
                'answers' => $payload,
                'score' => $score,
                'total_marks' => $questions->count(),
                'time_taken_sec' => array_sum($this->timings) ?: null,
                'subject_breakdown' => $breakdown,
                'completed_at' => now(),
            ]);

            $quiz->increment('attempt_count');

            // Difficulty statistics, so the pool can be rebalanced over time.
            foreach ($payload as $row) {
                if ($row['a'] === null) {
                    continue;
                }

                $question = $questions->firstWhere('id', $row['q']);

                if ($question !== null && $question->isCorrect((int) $row['a'])) {
                    $question->incrementQuietly('times_correct');
                }
            }

            return $attempt;
        });

        $streaks->record($user);

        // Every question missed becomes a card due today. This is the loop that makes the
        // quiz worth doing daily: mistakes do not just get scored, they come back.
        BuildFlashcardsFromAttempt::dispatch($attempt->id);

        return $this->redirectRoute('quiz.result', ['attempt' => $attempt->id], navigate: true);
    }

    public function render()
    {
        return view('livewire.quiz.daily-quiz');
    }
}
