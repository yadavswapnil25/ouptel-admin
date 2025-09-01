<?php

namespace App\Filament\Admin\Resources\UsersInvitationResource\Pages;

use App\Filament\Admin\Resources\UsersInvitationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewUsersInvitation extends ViewRecord
{
    protected static string $resource = UsersInvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Invitation')
                ->modalDescription('Are you sure you want to delete this invitation? This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete it'),
        ];
    }
}


