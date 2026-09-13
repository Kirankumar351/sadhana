<?php

declare(strict_types=1);

namespace App\Filament\Resources\TestSeriesResource\Pages;

use App\Filament\Resources\TestSeriesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTestSeries extends EditRecord
{
    protected static string $resource = TestSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
