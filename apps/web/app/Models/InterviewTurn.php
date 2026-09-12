<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InterviewTurnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewTurn extends Model
{
    /** @use HasFactory<InterviewTurnFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'feedback' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InterviewSession::class, 'interview_session_id');
    }
}
