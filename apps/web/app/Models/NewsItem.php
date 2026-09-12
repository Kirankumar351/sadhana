<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NewsItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class NewsItem extends Model
{
    /** @use HasFactory<NewsItemFactory> */
    use HasFactory, HasTranslations;

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['title', 'summary', 'why_it_matters'];

    protected function casts(): array
    {
        return [
            'exam_tags' => 'array',
            'published_at' => 'datetime',
            'approved_at' => 'datetime',
            'digest_date' => 'date',
            'relevance_score' => 'float',
        ];
    }

    public function sources(): HasMany
    {
        return $this->hasMany(NewsItemSource::class);
    }
}
