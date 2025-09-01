<?php

namespace App\Filament\Admin\Resources\PageSubCategoryResource\Pages;

use App\Filament\Admin\Resources\PageSubCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPageSubCategories extends ListRecords
{
    protected static string $resource = PageSubCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}


