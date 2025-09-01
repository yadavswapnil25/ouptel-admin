<?php

namespace App\Filament\Admin\Resources\PageSubCategoryResource\Pages;

use App\Filament\Admin\Resources\PageSubCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePageSubCategory extends CreateRecord
{
    protected static string $resource = PageSubCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'page';
        return $data;
    }
}
