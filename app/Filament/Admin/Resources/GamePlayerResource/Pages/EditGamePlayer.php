<?php

namespace App\Filament\Admin\Resources\GamePlayerResource\Pages;

use App\Filament\Admin\Resources\GamePlayerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGamePlayer extends EditRecord
{
    protected static string $resource = GamePlayerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}



