<?php

declare(strict_types=1);

namespace App\Filament\Resources\DataRequestResource\Pages;

use App\Filament\Resources\DataRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDataRequest extends CreateRecord
{
    protected static string $resource = DataRequestResource::class;
}
