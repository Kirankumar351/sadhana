<?php

declare(strict_types=1);

namespace App\Filament\Resources\AgentRunResource\Pages;

use App\Filament\Resources\AgentRunResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAgentRun extends EditRecord
{
    protected static string $resource = AgentRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
