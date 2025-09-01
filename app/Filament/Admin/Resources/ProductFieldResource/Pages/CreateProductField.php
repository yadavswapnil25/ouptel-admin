<?php

namespace App\Filament\Admin\Resources\ProductFieldResource\Pages;

use App\Filament\Admin\Resources\ProductFieldResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductField extends CreateRecord
{
    protected static string $resource = ProductFieldResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['placement'] = 'product';
        return $data;
    }
}


