<?php

namespace App\Filament\Admin\Resources\GamePlayerResource\Pages;

use App\Filament\Admin\Resources\GamePlayerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGamePlayers extends ListRecords
{
    protected static string $resource = GamePlayerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}



