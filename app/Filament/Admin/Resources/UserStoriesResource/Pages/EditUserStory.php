<?php

namespace App\Filament\Admin\Resources\UserStoriesResource\Pages;

use App\Filament\Admin\Resources\UserStoriesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserStory extends EditRecord
{
    protected static string $resource = UserStoriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}


