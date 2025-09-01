<?php

namespace App\Filament\Admin\Resources\UserStoriesResource\Pages;

use App\Filament\Admin\Resources\UserStoriesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserStories extends ListRecords
{
    protected static string $resource = UserStoriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}


