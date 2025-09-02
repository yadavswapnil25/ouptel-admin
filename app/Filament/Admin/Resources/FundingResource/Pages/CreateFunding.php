<?php

namespace App\Filament\Admin\Resources\FundingResource\Pages;

use App\Filament\Admin\Resources\FundingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFunding extends CreateRecord
{
    protected static string $resource = FundingResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Unset empty image value to prevent null constraint violation
        if (empty($data['image'])) {
            unset($data['image']);
        }
        
        return $data;
    }
}



