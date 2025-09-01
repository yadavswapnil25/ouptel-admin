<?php

namespace App\Filament\Admin\Resources\JobCategoryResource\Pages;

use App\Filament\Admin\Resources\JobCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJobCategory extends EditRecord
{
    protected static string $resource = JobCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}


