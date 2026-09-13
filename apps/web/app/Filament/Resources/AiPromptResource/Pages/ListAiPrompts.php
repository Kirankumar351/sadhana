<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiPromptResource\Pages;

use App\Filament\Resources\AiPromptResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAiPrompts extends ListRecords
{
    protected static string $resource = AiPromptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
