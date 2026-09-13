<?php

declare(strict_types=1);

namespace App\Filament\Resources\ModerationFlagResource\Pages;

use App\Filament\Resources\ModerationFlagResource;
use Filament\Resources\Pages\CreateRecord;

class CreateModerationFlag extends CreateRecord
{
    protected static string $resource = ModerationFlagResource::class;
}
