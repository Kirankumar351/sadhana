<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AdCampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class AdCampaign extends Model
{
    /** @use HasFactory<AdCampaignFactory> */
    use HasFactory, HasTranslations;

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['headline', 'body'];

    protected function casts(): array
    {
        return [
            'target_districts' => 'array',
            'target_exams' => 'array',
            'target_locales' => 'array',
            'target_qualifications' => 'array',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(Advertiser::class);
    }
}
