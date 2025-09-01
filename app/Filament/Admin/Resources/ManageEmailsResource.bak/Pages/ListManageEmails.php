<?php

namespace App\Filament\Admin\Resources\ManageEmailsResource\Pages;

use App\Filament\Admin\Resources\ManageEmailsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListManageEmails extends ListRecords
{
    protected static string $resource = ManageEmailsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
