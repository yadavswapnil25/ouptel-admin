<?php

namespace App\Filament\Admin\Resources\PostReactionResource\Pages;

use App\Filament\Admin\Resources\PostReactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPostReactions extends ListRecords
{
    protected static string $resource = PostReactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

