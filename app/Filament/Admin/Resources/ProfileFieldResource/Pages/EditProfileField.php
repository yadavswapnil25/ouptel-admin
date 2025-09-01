<?php

namespace App\Filament\Admin\Resources\ProfileFieldResource\Pages;

use App\Filament\Admin\Resources\ProfileFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProfileField extends EditRecord
{
    protected static string $resource = ProfileFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}


