<?php

namespace App\Filament\Admin\Resources\GroupFieldResource\Pages;

use App\Filament\Admin\Resources\GroupFieldResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGroupField extends CreateRecord
{
    protected static string $resource = GroupFieldResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['placement'] = 'group';
        return $data;
    }
}


