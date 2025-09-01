<?php

namespace App\Filament\Admin\Resources\FundingResource\Pages;

use App\Filament\Admin\Resources\FundingResource;
use App\Filament\Admin\Pages\FundingSettings;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFundings extends ListRecords
{
    protected static string $resource = FundingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('funding_settings')
                ->label('Funding Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn (): string => FundingSettings::getUrl())
                ->openUrlInNewTab(false),
            Actions\CreateAction::make(),
        ];
    }


}
