<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdCampaignResource\Pages;

use App\Filament\Resources\AdCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdCampaign extends EditRecord
{
    protected static string $resource = AdCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
