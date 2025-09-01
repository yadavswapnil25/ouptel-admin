<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OuptelStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', User::count())
                ->description('All registered users')
                ->descriptionIcon('heroicon-m-users')
                ->color('success'),
            
            Stat::make('Admin Users', User::where('email', 'like', '%@admin%')
                ->orWhere('email', 'admin@ouptel.com')
                ->count())
                ->description('Administrator accounts')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('warning'),
            
            Stat::make('New Users Today', User::whereDate('created_at', today())->count())
                ->description('Registered today')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('primary'),
        ];
    }
}
