<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Flashcard;
use App\Models\Question;
use App\Models\QuizAttempt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turning wrong answers into flashcards.
 *
 * THIS IS THE HALF OF SPACED REPETITION THAT ACTUALLY MATTERS. A scheduler with no deck is
 * an empty screen, and a deck built from syllabus topics is a textbook index. The cards
 * that move a score are the ones made from questions this particular student got wrong
 * last Tuesday — the quiz already records exactly that, and this is what turns the record
 * into practice.
 *
 * A REPEAT MISTAKE IS NOT A NEW CARD, IT IS A LAPSE. Getting the same question wrong again
 * three weeks later is the single strongest signal the interval was too long. Creating a
 * second card would split the history and hide that; instead the existing card is knocked
 * back to today and its ease drops, exactly as if it had been graded "forgot".
 */
final class CardsFromMistakes
{
    public function __construct(private readonly SpacedRepetition $scheduler) {}

    /**
     * Build (or reopen) cards for everything missed in one attempt.
     *
     * @return int how many cards were created or reopened
     */
    public function fromAttempt(QuizAttempt $attempt): int
    {
        $rows = $attempt->answers ?? [];

        if ($rows === []) {
            return 0;
        }

        // One query for the whole attempt. Checking each answer against a freshly fetched
        // question would be ten round trips on the path a student waits on after submit.
        $questions = Question::query()
            ->whereIn('id', array_filter(array_map(
                static fn (array $row): int => (int) ($row['q'] ?? 0),
                $rows,
            )))
            ->get()
            ->keyBy('id');

        $missed = $this->missedQuestions($rows, $questions, $this->skipsCount($attempt));

        if ($missed === []) {
            return 0;
        }

        $locale = $attempt->user?->preferred_locale ?: config('locales.default');
        $touched = 0;

        foreach ($missed as $question) {
            $touched += DB::transaction(
                fn (): int => $this->upsertCard($attempt->user_id, $question, $locale),
            );
        }

        return $touched;
    }

    /**
     * Whether leaving a question blank counts as having missed it.
     *
     * IN A DAILY QUIZ IT DOES. Ten questions, no clock, no penalty — a blank is not pacing,
     * it is a gap, and the topics a student quietly avoids are the ones they most need back.
     *
     * IN A MOCK TEST WITH NEGATIVE MARKING IT DOES NOT. The scorer treats a skip as a valid
     * and often correct strategy, because it is one in the hall. Making a card out of every
     * deliberate skip would bury the genuine mistakes under a hundred questions the student
     * was right to leave alone — and punish the exact instinct the mock is teaching.
     */
    private function skipsCount(QuizAttempt $attempt): bool
    {
        if ($attempt->test_id === null) {
            return true;
        }

        return (float) ($attempt->test?->negative_marking ?? 0) <= 0.0;
    }

    /**
     * The questions this attempt got wrong.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  Collection<int, Question>  $questions
     * @return list<Question>
     */
    private function missedQuestions(array $rows, $questions, bool $countSkipped): array
    {
        $missed = [];

        foreach ($rows as $row) {
            $question = $questions->get((int) ($row['q'] ?? 0));

            if ($question === null || isset($missed[$question->id])) {
                continue;
            }

            $chosen = $row['a'] ?? null;

            if ($chosen === null) {
                if ($countSkipped) {
                    $missed[$question->id] = $question;
                }

                continue;
            }

            if (! $question->isCorrect((int) $chosen)) {
                $missed[$question->id] = $question;
            }
        }

        return array_values($missed);
    }

    private function upsertCard(int $userId, Question $question, string $locale): int
    {
        $ref = 'question:'.$question->id;

        $existing = Flashcard::query()
            ->where('user_id', $userId)
            ->where('source_ref', $ref)
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            // Same question missed again: the interval was too long. Grade it as forgotten
            // rather than starting a parallel card with no history.
            $this->scheduler->review($existing, 'forgot');

            return 1;
        }

        Flashcard::create([
            'user_id' => $userId,
            'deck' => $question->subject ?: __('General'),
            'front' => $this->front($question),
            'back' => $this->back($question),
            'locale' => $locale,
            'source_type' => 'wrong_answer',
            'source_ref' => $ref,
            // Due immediately. The mistake is fresh and the correction is cheapest now.
            'due_at' => today()->toDateString(),
        ]);

        return 1;
    }

    /**
     * The card is built in every language the question exists in.
     *
     * A student who switches the interface to English mid-preparation should not find their
     * own deck has become unreadable.
     *
     * @return array<string, string>
     */
    private function front(Question $question): array
    {
        $front = [];

        foreach ($question->getTranslations('question') as $locale => $text) {
            if (filled($text)) {
                $front[$locale] = (string) $text;
            }
        }

        return $front !== [] ? $front : [config('locales.default') => (string) $question->question];
    }

    /**
     * The answer, plus the explanation when there is one.
     *
     * Without the explanation the card teaches the key and not the reason, and the student
     * memorises "option C" for a question that will be phrased differently in the hall.
     *
     * @return array<string, string>
     */
    private function back(Question $question): array
    {
        $back = [];
        $explanations = $question->getTranslations('explanation');

        foreach (array_keys($question->getTranslations('options')) as $locale) {
            $options = $question->optionsFor($locale);
            $answer = $options[$question->correct_index] ?? null;

            if ($answer === null) {
                continue;
            }

            $explanation = $explanations[$locale] ?? null;

            $back[$locale] = filled($explanation)
                ? $answer."\n\n".$explanation
                : $answer;
        }

        return $back !== [] ? $back : [config('locales.default') => ''];
    }
}
