<?php

namespace App\Filament\Admin\Resources\AccountDeletionRequestsResource\Pages;

use App\Filament\Admin\Resources\AccountDeletionRequestsResource;
use Filament\Resources\Pages\ListRecords;

class ListAccountDeletionRequests extends ListRecords
{
    protected static string $resource = AccountDeletionRequestsResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
