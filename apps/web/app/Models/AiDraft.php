<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AiDraftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDraft extends Model
{
    /** @use HasFactory<AiDraftFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'source_refs' => 'array',
            'reviewed_at' => 'datetime',
            'model_confidence' => 'float',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
