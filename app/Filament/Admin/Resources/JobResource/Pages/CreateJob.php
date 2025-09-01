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
}



