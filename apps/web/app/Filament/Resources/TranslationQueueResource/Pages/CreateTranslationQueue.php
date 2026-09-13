<?php

declare(strict_types=1);

namespace App\Filament\Resources\TranslationQueueResource\Pages;

use App\Filament\Resources\TranslationQueueResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTranslationQueue extends CreateRecord
{
    protected static string $resource = TranslationQueueResource::class;
}
