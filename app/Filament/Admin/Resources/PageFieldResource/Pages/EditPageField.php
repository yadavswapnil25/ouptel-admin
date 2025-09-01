<?php

namespace App\Filament\Admin\Resources\PageFieldResource\Pages;

use App\Filament\Admin\Resources\PageFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPageField extends EditRecord
{
    protected static string $resource = PageFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}


