<?php

namespace App\Filament\Admin\Resources\JobCategoryResource\Pages;

use App\Filament\Admin\Resources\JobCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJobCategories extends ListRecords
{
    protected static string $resource = JobCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}


