<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\QuizAttempt;
use App\Services\Learning\CardsFromMistakes;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Feed the flashcard deck from a finished attempt.
 *
 * Queued because it happens on the path a student is watching: they tap submit and expect
 * their score, not a wait while cards are written. Nothing here is visible until they open
 * the flashcard screen, so a second of lag costs nothing.
 *
 * Failing here must never look like a failed quiz. The attempt is already committed; if
 * card building breaks, the score still stands and the worst outcome is a thinner deck.
 */
class BuildFlashcardsFromAttempt implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly int $attemptId)
    {
        $this->onQueue('low');
    }

    public function handle(CardsFromMistakes $cards): void
    {
        $attempt = QuizAttempt::query()->with(['user', 'test'])->find($this->attemptId);

        if ($attempt === null || $attempt->user_id === null) {
            return;
        }

        $cards->fromAttempt($attempt);
    }
}
