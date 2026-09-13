<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiPromptResource\Pages;

use App\Filament\Resources\AiPromptResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAiPrompt extends CreateRecord
{
    protected static string $resource = AiPromptResource::class;
}
