<?php

namespace App\Filament\Admin\Resources\ArticleResource\Pages;

use App\Filament\Admin\Resources\ArticleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Unset empty thumbnail value to prevent null constraint violation
        if (empty($data['thumbnail'])) {
            unset($data['thumbnail']);
        }

        // Handle tags array to string conversion
        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = implode(',', array_filter($data['tags']));
        }
        
        return $data;
    }
}



