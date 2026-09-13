<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdCampaignResource\Pages;

use App\Filament\Resources\AdCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdCampaign extends CreateRecord
{
    protected static string $resource = AdCampaignResource::class;
}
