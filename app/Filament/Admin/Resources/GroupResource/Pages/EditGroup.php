<?php

namespace App\Filament\Admin\Resources\GroupResource\Pages;

use App\Filament\Admin\Resources\GroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGroup extends EditRecord
{
    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Unset empty values for optional fields to prevent null constraint violations
        if (empty($data['avatar'])) { unset($data['avatar']); }
        if (empty($data['cover'])) { unset($data['cover']); }
        
        return $data;
    }
}
