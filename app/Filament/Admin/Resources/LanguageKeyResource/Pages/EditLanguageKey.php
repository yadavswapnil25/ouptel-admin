<?php

namespace App\Filament\Admin\Resources\LanguageKeyResource\Pages;

use App\Filament\Admin\Resources\LanguageKeyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLanguageKey extends EditRecord
{
    protected static string $resource = LanguageKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}


