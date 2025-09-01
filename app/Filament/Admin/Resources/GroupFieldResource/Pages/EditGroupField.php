<?php

namespace App\Filament\Admin\Resources\GroupFieldResource\Pages;

use App\Filament\Admin\Resources\GroupFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGroupField extends EditRecord
{
    protected static string $resource = GroupFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}


