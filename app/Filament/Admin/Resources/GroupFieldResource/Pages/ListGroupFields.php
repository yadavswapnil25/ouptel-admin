<?php

namespace App\Filament\Admin\Resources\GroupFieldResource\Pages;

use App\Filament\Admin\Resources\GroupFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGroupFields extends ListRecords
{
    protected static string $resource = GroupFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}


