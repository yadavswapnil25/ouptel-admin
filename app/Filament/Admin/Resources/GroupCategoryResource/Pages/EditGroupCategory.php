<?php

namespace App\Filament\Admin\Resources\GroupCategoryResource\Pages;

use App\Filament\Admin\Resources\GroupCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGroupCategory extends EditRecord
{
    protected static string $resource = GroupCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}


