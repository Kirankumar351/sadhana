<?php

declare(strict_types=1);

namespace App\Filament\Resources\ScrapeSourceResource\Pages;

use App\Filament\Resources\ScrapeSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListScrapeSources extends ListRecords
{
    protected static string $resource = ScrapeSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
