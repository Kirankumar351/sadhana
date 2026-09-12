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
