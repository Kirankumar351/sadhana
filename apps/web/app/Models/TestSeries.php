<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TestSeriesFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class TestSeries extends Model
{
    /** @use HasFactory<TestSeriesFactory> */
    use HasFactory, HasTranslations, SoftDeletes;

    protected $table = 'test_series';

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['title', 'description'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function tests(): HasMany
    {
        return $this->hasMany(Test::class);
    }
}
