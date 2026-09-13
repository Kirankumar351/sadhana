<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiDraftResource\Pages;

use App\Filament\Resources\AiDraftResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAiDraft extends EditRecord
{
    protected static string $resource = AiDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
