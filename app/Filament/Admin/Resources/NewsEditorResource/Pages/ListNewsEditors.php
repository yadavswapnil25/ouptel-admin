<?php

namespace App\Filament\Admin\Resources\NewsEditorResource\Pages;

use App\Filament\Admin\Resources\NewsEditorResource;
use Filament\Resources\Pages\ListRecords;

class ListNewsEditors extends ListRecords
{
    protected static string $resource = NewsEditorResource::class;
}
