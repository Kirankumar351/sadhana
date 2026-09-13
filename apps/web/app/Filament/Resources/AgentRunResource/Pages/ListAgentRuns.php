<?php

declare(strict_types=1);

namespace App\Filament\Resources\AgentRunResource\Pages;

use App\Filament\Resources\AgentRunResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAgentRuns extends ListRecords
{
    protected static string $resource = AgentRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
