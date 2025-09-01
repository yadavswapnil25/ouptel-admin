<?php

namespace App\Filament\Admin\Resources\ProductFieldResource\Pages;

use App\Filament\Admin\Resources\ProductFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductField extends EditRecord
{
    protected static string $resource = ProductFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}


