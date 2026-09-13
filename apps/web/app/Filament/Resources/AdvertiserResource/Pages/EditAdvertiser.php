<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdvertiserResource\Pages;

use App\Filament\Resources\AdvertiserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdvertiser extends EditRecord
{
    protected static string $resource = AdvertiserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
