<?php

namespace App\Filament\Admin\Resources\UsersInvitationResource\Pages;

use App\Filament\Admin\Resources\UsersInvitationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsersInvitations extends ListRecords
{
    protected static string $resource = UsersInvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate')
                ->label('Generate New Code')
                ->action(function () {
                    \App\Models\AdminInvitation::createInvitation();
                    
                    \Filament\Notifications\Notification::make()
                        ->title('New invitation code generated successfully')
                        ->success()
                        ->send();
                })
                ->color('success')
                ->icon('heroicon-o-plus'),
        ];
    }
}


