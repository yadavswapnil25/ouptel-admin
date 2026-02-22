<?php

namespace App\Filament\Admin\Resources\CommunityPreferenceResource\Pages;

use App\Filament\Admin\Resources\CommunityPreferenceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCommunityPreference extends EditRecord
{
    protected static string $resource = CommunityPreferenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
