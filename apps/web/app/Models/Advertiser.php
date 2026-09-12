<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AdvertiserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Advertiser extends Model
{
    /** @use HasFactory<AdvertiserFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owned_by');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class);
    }
}
