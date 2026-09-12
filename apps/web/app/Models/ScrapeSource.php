<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ScrapeSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrapeSource extends Model
{
    /** @use HasFactory<ScrapeSourceFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(ExamNotification::class, 'source_id');
    }
}
