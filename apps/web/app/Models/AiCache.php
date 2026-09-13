<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AiCacheFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiCache extends Model
{
    /** @use HasFactory<AiCacheFactory> */
    use HasFactory;

    /**
     * Singular by design: one cache, not many caches. Left to the convention this becomes
     * ai_caches, every lookup throws, and the gateway's catch-all turns that into a silent
     * "AI temporarily unavailable" across the entire product.
     */
    protected $table = 'ai_cache';

    protected $primaryKey = 'cache_key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
