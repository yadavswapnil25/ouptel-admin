<?php

namespace App\Filament\Admin\Resources\NewsCategoryResource\Pages;

use App\Filament\Admin\Resources\NewsCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNewsCategories extends ListRecords
{
    protected static string $resource = NewsCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
