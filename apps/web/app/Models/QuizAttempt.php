<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuizAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt at a daily quiz or a full test.
 *
 * `answers` holds [{"q":1,"a":2,"t":14}] where t is seconds on that question. Time per
 * question is what makes the post-test analytics useful — a wrong answer in 4 seconds is a
 * guess, the same wrong answer in 90 seconds is a misunderstanding, and they need
 * different advice.
 */
class QuizAttempt extends Model
{
    /** @use HasFactory<QuizAttemptFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'subject_breakdown' => 'array',
            'score' => 'float',
            'total_marks' => 'float',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dailyQuiz(): BelongsTo
    {
        return $this->belongsTo(DailyQuiz::class);
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function percentage(): float
    {
        if ($this->total_marks <= 0) {
            return 0.0;
        }

        return round($this->score / $this->total_marks * 100, 1);
    }

    public function correctCount(): int
    {
        return (int) $this->score;
    }

    /**
     * The weakest subject in this attempt, used for the "work on this" card.
     * Returns null when every subject scored equally — inventing a weakness out of noise
     * sends the user to study something they already know.
     */
    public function weakestSubject(): ?string
    {
        $breakdown = $this->subject_breakdown ?? [];

        if (count($breakdown) < 2) {
            return null;
        }

        asort($breakdown);
        $rates = array_values($breakdown);

        if ($rates[0] === end($rates)) {
            return null;
        }

        return (string) array_key_first($breakdown);
    }
}
