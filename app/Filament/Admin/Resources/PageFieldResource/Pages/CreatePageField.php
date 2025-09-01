<?php

namespace App\Filament\Admin\Resources\PageFieldResource\Pages;

use App\Filament\Admin\Resources\PageFieldResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePageField extends CreateRecord
{
    protected static string $resource = PageFieldResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['placement'] = 'page';
        return $data;
    }
}


