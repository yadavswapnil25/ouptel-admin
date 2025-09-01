<?php

namespace App\Filament\Admin\Resources\ManageEmailsResource\Pages;

use App\Filament\Admin\Resources\ManageEmailsResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewManageEmail extends ViewRecord
{
    protected static string $resource = ManageEmailsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
