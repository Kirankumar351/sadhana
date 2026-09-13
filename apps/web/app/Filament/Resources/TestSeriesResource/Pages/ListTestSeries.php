<?php

declare(strict_types=1);

namespace App\Filament\Resources\TestSeriesResource\Pages;

use App\Filament\Resources\TestSeriesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTestSeries extends ListRecords
{
    protected static string $resource = TestSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
