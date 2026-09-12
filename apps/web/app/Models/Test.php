<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Test extends Model
{
    /** @use HasFactory<TestFactory> */
    use HasFactory, HasTranslations;

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['title'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'is_free_sample' => 'boolean',
            'available_from' => 'datetime',
        ];
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(TestSeries::class, 'test_series_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(TestSection::class);
    }
}
