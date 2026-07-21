<?php

namespace App\Filament\Admin\Resources\BoostCampaignResource\Pages;

use App\Filament\Admin\Resources\BoostCampaignResource;
use Filament\Resources\Pages\ListRecords;

class ListBoostCampaigns extends ListRecords
{
    protected static string $resource = BoostCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
