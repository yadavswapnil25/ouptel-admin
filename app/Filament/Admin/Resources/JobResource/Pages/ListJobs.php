<?php

namespace App\Filament\Admin\Resources\JobResource\Pages;

use App\Filament\Admin\Resources\JobResource;
use App\Filament\Admin\Resources\JobResource\Widgets;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJobs extends ListRecords
{
    protected static string $resource = JobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('reset_filters')
                ->label('Reset Filters')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    session()->forget('tableFilters');
                    return redirect()->to(request()->url());
                })
                ->visible(fn () => request()->has('tableFilters') || session()->has('tableFilters')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            Widgets\JobsStatsWidget::class,
        ];
    }
}



