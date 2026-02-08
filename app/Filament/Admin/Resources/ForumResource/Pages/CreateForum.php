<?php

namespace App\Filament\Admin\Resources\ForumResource\Pages;

use App\Filament\Admin\Resources\ForumResource;
use Filament\Resources\Pages\CreateRecord;

class CreateForum extends CreateRecord
{
    protected static string $resource = ForumResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set default values for statistics
        // sections can be null if no section is selected
        if (!isset($data['sections'])) {
            $data['sections'] = 0;
        }
        $data['posts'] = 0;
        $data['last_post'] = 0;
        
        return $data;
    }
}

