<?php

namespace App\Filament\Admin\Resources\GendersResource\Pages;

use App\Filament\Admin\Resources\GendersResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateGender extends CreateRecord
{
    protected static string $resource = GendersResource::class;
}
