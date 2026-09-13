<?php

declare(strict_types=1);

namespace App\Filament\Resources\ScrapeSourceResource\Pages;

use App\Filament\Resources\ScrapeSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditScrapeSource extends EditRecord
{
    protected static string $resource = ScrapeSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
