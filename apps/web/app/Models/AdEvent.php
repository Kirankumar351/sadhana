<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AdEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdEvent extends Model
{
    /** @use HasFactory<AdEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
}
