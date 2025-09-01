<?php

namespace App\Filament\Admin\Resources\GroupSubCategoryResource\Pages;

use App\Filament\Admin\Resources\GroupSubCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGroupSubCategory extends CreateRecord
{
    protected static string $resource = GroupSubCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'group';
        return $data;
    }
}
