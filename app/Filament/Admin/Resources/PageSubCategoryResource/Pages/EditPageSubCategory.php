<?php

namespace App\Filament\Admin\Resources\PageSubCategoryResource\Pages;

use App\Filament\Admin\Resources\PageSubCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPageSubCategory extends EditRecord
{
    protected static string $resource = PageSubCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}


