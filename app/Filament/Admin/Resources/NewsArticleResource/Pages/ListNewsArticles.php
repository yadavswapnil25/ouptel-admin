<?php

namespace App\Filament\Admin\Resources\NewsArticleResource\Pages;

use App\Filament\Admin\Resources\NewsArticleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNewsArticles extends ListRecords
{
    protected static string $resource = NewsArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
