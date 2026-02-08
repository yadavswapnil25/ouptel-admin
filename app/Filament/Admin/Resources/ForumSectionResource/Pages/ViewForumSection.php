<?php

namespace App\Filament\Admin\Resources\ForumSectionResource\Pages;

use App\Filament\Admin\Resources\ForumSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewForumSection extends ViewRecord
{
    protected static string $resource = ForumSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}

