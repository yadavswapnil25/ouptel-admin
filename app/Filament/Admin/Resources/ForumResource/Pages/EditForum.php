<?php

namespace App\Filament\Admin\Resources\ForumResource\Pages;

use App\Filament\Admin\Resources\ForumResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditForum extends EditRecord
{
    protected static string $resource = ForumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // sections can be null if no section is selected, otherwise use the selected section ID
        if (!isset($data['sections']) || $data['sections'] === null) {
            $data['sections'] = 0;
        }
        
        return $data;
    }
}

