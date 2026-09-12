<?php

namespace App\Filament\Resources\GlossaryResource\Pages;

use App\Filament\Resources\GlossaryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGlossaries extends ListRecords
{
    protected static string $resource = GlossaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
