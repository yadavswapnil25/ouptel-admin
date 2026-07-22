<?php

namespace App\Filament\Admin\Resources\NewsArticleResource\Pages;

use App\Filament\Admin\Resources\NewsArticleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsArticle extends CreateRecord
{
    protected static string $resource = NewsArticleResource::class;
}
