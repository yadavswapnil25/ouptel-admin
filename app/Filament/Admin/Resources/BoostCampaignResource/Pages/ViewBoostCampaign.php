<?php

namespace App\Filament\Admin\Resources\BoostCampaignResource\Pages;

use App\Filament\Admin\Resources\BoostCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBoostCampaign extends ViewRecord
{
    protected static string $resource = BoostCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
