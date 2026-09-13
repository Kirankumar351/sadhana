<?php

declare(strict_types=1);

namespace App\Filament\Resources\TranslationQueueResource\Pages;

use App\Filament\Resources\TranslationQueueResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTranslationQueues extends ListRecords
{
    protected static string $resource = TranslationQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
