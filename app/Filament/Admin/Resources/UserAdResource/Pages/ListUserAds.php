<?php

namespace App\Filament\Admin\Resources\UserAdResource\Pages;

use App\Filament\Admin\Resources\UserAdResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserAds extends ListRecords
{
    protected static string $resource = UserAdResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
