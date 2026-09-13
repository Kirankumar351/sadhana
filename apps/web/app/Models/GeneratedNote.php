<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A note a student generated and kept.
 *
 * The body is a snapshot, not a pointer. See the migration for why.
 */
class GeneratedNote extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sources' => 'array',
            'confidence' => 'float',
            'is_saved' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * How many notes this user has generated in the current calendar month.
     *
     * The cap itself is enforced in CostMeter against ai_requests — this is only what the
     * screen shows, so a student can see where they stand before they spend one.
     */
    public static function usedThisMonth(int $userId): int
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }
}
