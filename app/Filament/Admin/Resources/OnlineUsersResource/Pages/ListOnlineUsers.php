<?php

namespace App\Filament\Admin\Resources\OnlineUsersResource\Pages;

use App\Filament\Admin\Resources\OnlineUsersResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOnlineUsers extends ListRecords
{
    protected static string $resource = OnlineUsersResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}


