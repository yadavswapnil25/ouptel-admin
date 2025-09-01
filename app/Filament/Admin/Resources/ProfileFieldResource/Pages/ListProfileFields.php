<?php

namespace App\Filament\Admin\Resources\ProfileFieldResource\Pages;

use App\Filament\Admin\Resources\ProfileFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProfileFields extends ListRecords
{
    protected static string $resource = ProfileFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}


