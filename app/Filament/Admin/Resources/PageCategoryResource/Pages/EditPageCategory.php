<?php

namespace App\Filament\Admin\Resources\PageCategoryResource\Pages;

use App\Filament\Admin\Resources\PageCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPageCategory extends EditRecord
{
    protected static string $resource = PageCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}


