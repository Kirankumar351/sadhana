<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AiChunkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiChunk extends Model
{
    /** @use HasFactory<AiChunkFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_stale' => 'boolean',
            'embedded_at' => 'datetime',
        ];
    }
}
