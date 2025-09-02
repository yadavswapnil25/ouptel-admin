<?php

namespace App\Filament\Admin\Resources\JobResource\Pages;

use App\Filament\Admin\Resources\JobResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJob extends EditRecord
{
    protected static string $resource = JobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
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



