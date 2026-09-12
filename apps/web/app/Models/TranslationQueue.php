<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TranslationQueueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranslationQueue extends Model
{
    /** @use HasFactory<TranslationQueueFactory> */
    use HasFactory;

    protected $table = 'translation_queue';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_critical' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
