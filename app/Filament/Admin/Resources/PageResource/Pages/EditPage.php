<?php

namespace App\Filament\Admin\Resources\PageResource\Pages;

use App\Filament\Admin\Resources\PageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Debug: Log original form data
        Log::info('Original form data:', $data);
        
        // Always ensure verified field is included (it's a boolean toggle)
        if (!isset($data['verified'])) {
            $data['verified'] = false;
            Log::info('Verified field was missing, set to false');
        } else {
            Log::info('Verified field value:', ['verified' => $data['verified'], 'type' => gettype($data['verified'])]);
        }
        
        // If avatar is empty, preserve the existing value
        if (empty($data['avatar'])) {
            unset($data['avatar']);
        }
        
        // If cover is empty, preserve the existing value
        if (empty($data['cover'])) {
            unset($data['cover']);
        }
        
        // If phone is empty, preserve the existing value
        if (empty($data['phone'])) {
            unset($data['phone']);
        }
        
        // If address is empty, preserve the existing value
        if (empty($data['address'])) {
            unset($data['address']);
        }
        
        // If website is empty, preserve the existing value
        if (empty($data['website'])) {
            unset($data['website']);
        }
        
        // Debug: Log final data being saved
        Log::info('Final form data to be saved:', $data);
        
        return $data;
    }
}





