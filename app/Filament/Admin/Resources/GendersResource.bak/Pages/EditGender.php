<?php

namespace App\Filament\Admin\Resources\GendersResource\Pages;

use App\Filament\Admin\Resources\GendersResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGender extends EditRecord
{
    protected static string $resource = GendersResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
