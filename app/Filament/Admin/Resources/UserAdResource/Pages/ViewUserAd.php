<?php

namespace App\Filament\Admin\Resources\UserAdResource\Pages;

use App\Filament\Admin\Resources\UserAdResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewUserAd extends ViewRecord
{
    protected static string $resource = UserAdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
