<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StreakFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's streak. Primary key is user_id — one row per user, forever.
 *
 * The freeze exists because users have exams, travel and family emergencies. A streak that
 * punishes real life gets abandoned, and an abandoned streak almost never restarts.
 * One free miss a month costs nothing and saves a meaningful share of users.
 */
class Streak extends Model
{
    /** @use HasFactory<StreakFactory> */
    use HasFactory;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_active_date' => 'date',
            'freeze_reset_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActiveToday(): bool
    {
        return $this->last_active_date?->isToday() ?? false;
    }

    /**
     * At risk means: active yesterday, not yet today. Used for the 21:00 reminder.
     *
     * Deliberately not "anyone with a streak" — reminding someone who has already done
     * today's quiz is the fastest way to get the whole channel muted.
     */
    public function isAtRisk(): bool
    {
        return $this->current_streak > 0
            && ($this->last_active_date?->isYesterday() ?? false);
    }

    public function nextMilestone(): ?int
    {
        foreach ([3, 7, 30, 100, 365] as $milestone) {
            if ($this->current_streak < $milestone) {
                return $milestone;
            }
        }

        return null;
    }
}
