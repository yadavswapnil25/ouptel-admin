<?php

namespace App\Filament\Admin\Resources\ProfileInterestFieldResource\Pages;

use App\Filament\Admin\Resources\ProfileInterestFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditProfileInterestField extends EditRecord
{
    protected static string $resource = ProfileInterestFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['field_key'])) {
            $data['field_key'] = Str::snake($data['field_key']);
        }

        return $data;
    }
}
