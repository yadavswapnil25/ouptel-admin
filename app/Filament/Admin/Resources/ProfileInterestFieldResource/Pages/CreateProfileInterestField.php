<?php

namespace App\Filament\Admin\Resources\ProfileInterestFieldResource\Pages;

use App\Filament\Admin\Resources\ProfileInterestFieldResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateProfileInterestField extends CreateRecord
{
    protected static string $resource = ProfileInterestFieldResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!empty($data['label']) && empty($data['field_key'])) {
            $data['field_key'] = Str::snake($data['label']);
        }

        if (!empty($data['field_key'])) {
            $data['field_key'] = Str::snake($data['field_key']);
        }

        return $data;
    }
}
