<?php

namespace App\Filament\Admin\Resources\ManageEmailsResource\Pages;

use App\Filament\Admin\Resources\ManageEmailsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditManageEmail extends EditRecord
{
    protected static string $resource = ManageEmailsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
