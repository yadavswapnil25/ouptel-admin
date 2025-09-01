<?php

namespace App\Filament\Admin\Resources\VerificationRequestsResource\Pages;

use App\Filament\Admin\Resources\VerificationRequestsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVerificationRequests extends ListRecords
{
    protected static string $resource = VerificationRequestsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}


