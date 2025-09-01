<?php

namespace App\Filament\Admin\Resources\UserManagementResource\Pages;

use App\Filament\Admin\Resources\UserManagementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserManagement extends EditRecord
{
    protected static string $resource = UserManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
