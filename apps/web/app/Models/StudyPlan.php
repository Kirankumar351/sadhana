<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StudyPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyPlan extends Model
{
    /** @use HasFactory<StudyPlanFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'plan' => 'array',
            'weak_subjects' => 'array',
            'target_date' => 'date',
            'generated_at' => 'datetime',
            'superseded_at' => 'datetime',
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
}
