<?php

namespace App\Filament\Admin\Resources\OnlineUsersResource\Pages;

use App\Filament\Admin\Resources\OnlineUsersResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOnlineUser extends EditRecord
{
    protected static string $resource = OnlineUsersResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}


