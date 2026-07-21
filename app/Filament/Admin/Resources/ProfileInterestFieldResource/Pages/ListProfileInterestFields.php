<?php

namespace App\Filament\Admin\Resources\ProfileInterestFieldResource\Pages;

use App\Filament\Admin\Resources\ProfileInterestFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProfileInterestFields extends ListRecords
{
    protected static string $resource = ProfileInterestFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
