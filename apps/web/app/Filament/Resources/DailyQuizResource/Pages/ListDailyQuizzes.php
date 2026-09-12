<?php

namespace App\Filament\Resources\DailyQuizResource\Pages;

use App\Filament\Resources\DailyQuizResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDailyQuizzes extends ListRecords
{
    protected static string $resource = DailyQuizResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
