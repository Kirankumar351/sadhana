<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FeatureFlagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    /** @use HasFactory<FeatureFlagFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'constraints' => 'array',
        ];
    }
}
