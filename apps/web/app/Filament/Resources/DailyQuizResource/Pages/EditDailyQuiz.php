<?php

declare(strict_types=1);

namespace App\Filament\Resources\DailyQuizResource\Pages;

use App\Filament\Resources\DailyQuizResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDailyQuiz extends EditRecord
{
    protected static string $resource = DailyQuizResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
