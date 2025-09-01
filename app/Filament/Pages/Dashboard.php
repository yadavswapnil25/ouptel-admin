<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Admin\Widgets\DashboardStatsWidget;
use App\Filament\Admin\Widgets\DashboardChartWidget;
use App\Filament\Admin\Widgets\RecentActivityWidget;
use App\Filament\Admin\Widgets\SystemStatusWidget;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament.pages.dashboard';

    public function getWidgets(): array
    {
        return [
            DashboardStatsWidget::class,
            DashboardChartWidget::class,
            RecentActivityWidget::class,
            SystemStatusWidget::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return [
            'sm' => 1,
            'md' => 2,
            'lg' => 3,
            'xl' => 4,
        ];
    }
}

