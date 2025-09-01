<?php

namespace App\Filament\Admin\Resources\GamePlayerResource\Pages;

use App\Filament\Admin\Resources\GamePlayerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGamePlayer extends CreateRecord
{
    protected static string $resource = GamePlayerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['last_play'] = time();
        return $data;
    }
}



