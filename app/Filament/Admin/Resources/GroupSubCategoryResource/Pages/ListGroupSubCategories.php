<?php

namespace App\Filament\Admin\Resources\GroupSubCategoryResource\Pages;

use App\Filament\Admin\Resources\GroupSubCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGroupSubCategories extends ListRecords
{
    protected static string $resource = GroupSubCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}


