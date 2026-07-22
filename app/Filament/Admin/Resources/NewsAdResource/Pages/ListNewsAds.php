<?php

namespace App\Filament\Admin\Resources\NewsAdResource\Pages;

use App\Filament\Admin\Resources\NewsAdResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNewsAds extends ListRecords
{
    protected static string $resource = NewsAdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
