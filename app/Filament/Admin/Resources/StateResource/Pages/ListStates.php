<?php

namespace App\Filament\Admin\Resources\StateResource\Pages;

use App\Filament\Admin\Resources\StateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStates extends ListRecords
{
    protected static string $resource = StateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

