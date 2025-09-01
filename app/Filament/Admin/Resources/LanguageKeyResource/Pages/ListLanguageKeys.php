<?php

namespace App\Filament\Admin\Resources\LanguageKeyResource\Pages;

use App\Filament\Admin\Resources\LanguageKeyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLanguageKeys extends ListRecords
{
    protected static string $resource = LanguageKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}


