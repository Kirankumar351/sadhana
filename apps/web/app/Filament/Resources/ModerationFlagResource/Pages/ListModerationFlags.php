<?php

declare(strict_types=1);

namespace App\Filament\Resources\ModerationFlagResource\Pages;

use App\Filament\Resources\ModerationFlagResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListModerationFlags extends ListRecords
{
    protected static string $resource = ModerationFlagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
