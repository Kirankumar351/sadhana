<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AnswerEvaluationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnswerEvaluation extends Model
{
    /** @use HasFactory<AnswerEvaluationFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rubric' => 'array',
            'points_hit' => 'array',
            'points_missed' => 'array',
            'band' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(MainsQuestion::class, 'mains_question_id');
    }
}
