<?php

namespace App\Filament\Admin\Resources\CommunityPreferenceResource\Pages;

use App\Filament\Admin\Resources\CommunityPreferenceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCommunityPreferences extends ListRecords
{
    protected static string $resource = CommunityPreferenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
