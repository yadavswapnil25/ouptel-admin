<?php

namespace App\Filament\Admin\Resources\ProductSubCategoryResource\Pages;

use App\Filament\Admin\Resources\ProductSubCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductSubCategory extends CreateRecord
{
    protected static string $resource = ProductSubCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'product';
        return $data;
    }
}
