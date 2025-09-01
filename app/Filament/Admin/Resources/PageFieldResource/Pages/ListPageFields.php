<?php

namespace App\Filament\Admin\Resources\PageFieldResource\Pages;

use App\Filament\Admin\Resources\PageFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPageFields extends ListRecords
{
    protected static string $resource = PageFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}


