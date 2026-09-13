<?php

declare(strict_types=1);

namespace App\Filament\Resources\TestSeriesResource\Pages;

use App\Filament\Resources\TestSeriesResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTestSeries extends CreateRecord
{
    protected static string $resource = TestSeriesResource::class;
}
