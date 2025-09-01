<?php

namespace App\Filament\Admin\Resources\UserManagementResource\Pages;

use App\Filament\Admin\Resources\UserManagementResource;
use App\Filament\Admin\Resources\UserManagementResource\Widgets\UserManagementStatsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserManagements extends ListRecords
{
    protected static string $resource = UserManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UserManagementStatsWidget::class,
        ];
    }
}
