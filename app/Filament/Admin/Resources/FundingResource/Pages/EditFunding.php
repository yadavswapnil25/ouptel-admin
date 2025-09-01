<?php

namespace App\Filament\Admin\Resources\FundingResource\Pages;

use App\Filament\Admin\Resources\FundingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFunding extends EditRecord
{
    protected static string $resource = FundingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}



