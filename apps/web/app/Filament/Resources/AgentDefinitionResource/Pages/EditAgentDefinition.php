<?php

declare(strict_types=1);

namespace App\Filament\Resources\AgentDefinitionResource\Pages;

use App\Filament\Resources\AgentDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAgentDefinition extends EditRecord
{
    protected static string $resource = AgentDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
