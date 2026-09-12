<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AnalyticsDailyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyticsDaily extends Model
{
    /** @use HasFactory<AnalyticsDailyFactory> */
    use HasFactory;

    protected $table = 'analytics_daily';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'value' => 'float',
        ];
    }
}
