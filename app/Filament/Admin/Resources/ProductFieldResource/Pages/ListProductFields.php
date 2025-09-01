<?php

namespace App\Filament\Admin\Resources\ProductFieldResource\Pages;

use App\Filament\Admin\Resources\ProductFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductFields extends ListRecords
{
    protected static string $resource = ProductFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}


