<?php

namespace App\Filament\Admin\Resources\GendersResource\Pages;

use App\Filament\Admin\Resources\GendersResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGenders extends ListRecords
{
    protected static string $resource = GendersResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action since we're working with aggregated data
        ];
    }
}
