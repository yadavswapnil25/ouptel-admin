<?php

namespace App\Filament\Admin\Resources\ForumSectionResource\Pages;

use App\Filament\Admin\Resources\ForumSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditForumSection extends EditRecord
{
    protected static string $resource = ForumSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}

