<?php

namespace App\Filament\Admin\Resources\JobResource\Pages;

use App\Filament\Admin\Resources\JobResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJob extends CreateRecord
{
    protected static string $resource = JobResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['time'] = time();
        
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Unset empty image value to prevent null constraint violation
        if (empty($data['image'])) {
            unset($data['image']);
        }
        
        return $data;
    }
}



