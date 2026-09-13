<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Flashcard;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * SM-2 spaced repetition, adapted.
 *
 * Spaced repetition without a scheduler is just a list, and a list is what every other
 * Telugu exam app already offers. The whole value is that the algorithm decides what to
 * show today so the student does not have to.
 *
 * TWO DELIBERATE DEPARTURES FROM TEXTBOOK SM-2:
 *
 *   1. A LAPSE DOES NOT RESET EASE TO THE FLOOR. Classic SM-2 punishes a forgotten card
 *      hard, which is right for a decade-long language habit and wrong here: our users
 *      have an exam in 28 days and a card knocked back to the minimum interval wastes
 *      reviews they cannot spare. Ease drops, but gently.
 *
 *   2. INTERVALS ARE CAPPED BY THE EXAM DATE. Scheduling a card for 90 days out when the
 *      exam is in 30 is the same as deleting it. Anything that would land after the exam
 *      is pulled back inside the window.
 */
final class SpacedRepetition
{
    /** Below this the algorithm stops distinguishing difficulty and just repeats. */
    private const MIN_EASE = 1.3;

    private const MAX_EASE = 2.8;

    /**
     * Grade a review.
     *
     * @param  'forgot'|'hard'|'good'|'easy'  $grade
     */
    public function review(Flashcard $card, string $grade, ?CarbonImmutable $examDate = null): Flashcard
    {
        $ease = (float) $card->ease;
        $interval = (int) $card->interval_days;

        [$ease, $interval, $lapsed] = match ($grade) {
            // Forgotten. Show it again today, and lower ease a little — not to the floor.
            'forgot' => [max(self::MIN_EASE, $ease - 0.20), 0, true],

            // Recalled, but with effort. Shorter interval, slightly lower ease.
            'hard' => [max(self::MIN_EASE, $ease - 0.15), max(1, (int) round($interval * 1.2)), false],

            // Recalled cleanly. The standard SM-2 progression.
            'good' => [$ease, $this->nextInterval($interval, $ease), false],

            // Instant. Push it further out and reward the ease.
            'easy' => [min(self::MAX_EASE, $ease + 0.15), max(4, (int) round($this->nextInterval($interval, $ease) * 1.3)), false],

            default => [$ease, $this->nextInterval($interval, $ease), false],
        };

        $due = CarbonImmutable::parse(today())->addDays($interval);

        /**
         * Never schedule past the exam.
         *
         * A card due after the paper is a card that will never be seen again, which is
         * indistinguishable from deleting it — except the student thinks it is covered.
         */
        if ($examDate !== null && $due->greaterThan($examDate)) {
            $due = $this->beforeExam($examDate);
            $interval = (int) CarbonImmutable::parse(today())->diffInDays($due);
        }

        $card->forceFill([
            'ease' => round($ease, 2),
            'interval_days' => $interval,
            'due_at' => $due->toDateString(),
            'review_count' => $card->review_count + 1,
            'lapse_count' => $card->lapse_count + ($lapsed ? 1 : 0),
        ])->save();

        return $card;
    }

    /**
     * Standard SM-2 progression: 1 day, then 6, then multiply by ease.
     */
    private function nextInterval(int $current, float $ease): int
    {
        return match (true) {
            $current === 0 => 1,
            $current === 1 => 6,
            default => max(1, (int) round($current * $ease)),
        };
    }

    /**
     * Land the card a few days before the exam rather than on it.
     *
     * The final days are for full-length practice and sleep. Starting something new in the
     * last week costs more in confidence than it gains in marks.
     */
    private function beforeExam(CarbonImmutable $examDate): CarbonImmutable
    {
        $target = $examDate->subDays(4);
        $tomorrow = CarbonImmutable::parse(today())->addDay();

        return $target->lessThan($tomorrow) ? $tomorrow : $target;
    }

    /**
     * Cards due today, weakest first.
     *
     * Lapsed cards lead because they are the ones actually costing marks. A deck ordered by
     * due date alone buries the difficult cards behind easy ones the student already knows,
     * and the session ends before they are reached.
     *
     * @return Collection<int, Flashcard>
     */
    public function dueFor(int $userId, ?string $deck = null, int $limit = 40)
    {
        return Flashcard::query()
            ->where('user_id', $userId)
            ->when($deck !== null, fn ($q) => $q->where('deck', $deck))
            ->where(fn ($q) => $q->whereNull('due_at')->orWhereDate('due_at', '<=', today()))
            ->orderByDesc('lapse_count')
            ->orderBy('due_at')
            ->limit($limit)
            ->get();
    }
}
