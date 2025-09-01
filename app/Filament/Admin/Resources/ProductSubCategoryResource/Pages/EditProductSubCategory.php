<?php

namespace App\Filament\Admin\Resources\ProductSubCategoryResource\Pages;

use App\Filament\Admin\Resources\ProductSubCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductSubCategory extends EditRecord
{
    protected static string $resource = ProductSubCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}


