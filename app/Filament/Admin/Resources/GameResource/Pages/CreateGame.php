<?php

namespace App\Filament\Admin\Resources\GameResource\Pages;

use App\Filament\Admin\Resources\GameResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGame extends CreateRecord
{
    protected static string $resource = GameResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['time'] = time();
        $data['last_play'] = 0;
        return $data;
    }
}



