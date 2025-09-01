<?php

namespace App\Filament\Admin\Resources\GroupSubCategoryResource\Pages;

use App\Filament\Admin\Resources\GroupSubCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGroupSubCategory extends EditRecord
{
    protected static string $resource = GroupSubCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}


