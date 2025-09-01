<?php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class DashboardStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Get current time for online users calculation
        $currentTime = time();
        $onlineThreshold = $currentTime - 300; // 5 minutes
        
        // Get today's timestamp
        $today = strtotime('today');
        $tomorrow = strtotime('tomorrow');
        
        // Get statistics
        $totalUsers = DB::table('Wo_Users')->count();
        $totalPosts = DB::table('Wo_Posts')->count();
        $totalPages = DB::table('Wo_Pages')->count();
        $totalGroups = DB::table('Wo_Groups')->count();
        $totalComments = DB::table('Wo_Comments')->count();
        $totalGames = DB::table('Wo_Games')->count();
        $totalMessages = DB::table('Wo_Messages')->count();
        $totalReports = DB::table('Wo_Reports')->count();
        
        // Online users (using lastseen column)
        $onlineUsers = DB::table('Wo_Users')
            ->where('lastseen', '>', $onlineThreshold)
            ->count();
            
        // Today's statistics
        $todayUsers = DB::table('Wo_Users')
            ->where('joined', '>=', $today)
            ->where('joined', '<', $tomorrow)
            ->count();
            
        $todayPosts = DB::table('Wo_Posts')
            ->where('time', '>=', $today)
            ->where('time', '<', $tomorrow)
            ->count();
            
        $todayPages = DB::table('Wo_Pages')
            ->where('time', '>=', $today)
            ->where('time', '<', $tomorrow)
            ->count();
            
        $todayGroups = DB::table('Wo_Groups')
            ->where('time', '>=', $today)
            ->where('time', '<', $tomorrow)
            ->count();

        return [
            Stat::make('Total Users', number_format($totalUsers))
                ->description('Registered users')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary')
                ->chart([7, 2, 10, 3, 15, 4, 17]),

            Stat::make('Total Posts', number_format($totalPosts))
                ->description('User posts')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info')
                ->chart([15, 4, 10, 2, 12, 4, 12]),

            Stat::make('Total Pages', number_format($totalPages))
                ->description('Business pages')
                ->descriptionIcon('heroicon-m-flag')
                ->color('warning')
                ->chart([2, 10, 3, 15, 4, 17, 7]),

            Stat::make('Total Groups', number_format($totalGroups))
                ->description('User groups')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('secondary')
                ->chart([10, 3, 15, 4, 17, 7, 2]),

            Stat::make('Online Users', number_format($onlineUsers))
                ->description('Currently online')
                ->descriptionIcon('heroicon-m-signal')
                ->color('success')
                ->chart([3, 15, 4, 17, 7, 2, 10]),

            Stat::make('Total Comments', number_format($totalComments))
                ->description('Post comments')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('primary')
                ->chart([4, 17, 7, 2, 10, 3, 15]),

            Stat::make('Total Games', number_format($totalGames))
                ->description('Available games')
                ->descriptionIcon('heroicon-m-play')
                ->color('info')
                ->chart([17, 7, 2, 10, 3, 15, 4]),

            Stat::make('Total Messages', number_format($totalMessages))
                ->description('Private messages')
                ->descriptionIcon('heroicon-m-envelope')
                ->color('warning')
                ->chart([7, 2, 10, 3, 15, 4, 17]),

            Stat::make('Total Reports', number_format($totalReports))
                ->description('User reports')
                ->descriptionIcon('heroicon-m-flag')
                ->color('danger')
                ->chart([2, 10, 3, 15, 4, 17, 7]),

            Stat::make("Today's New Users", number_format($todayUsers))
                ->description('Joined today')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('success')
                ->chart([10, 3, 15, 4, 17, 7, 2]),

            Stat::make("Today's New Posts", number_format($todayPosts))
                ->description('Posted today')
                ->descriptionIcon('heroicon-m-plus-circle')
                ->color('info')
                ->chart([3, 15, 4, 17, 7, 2, 10]),

            Stat::make("Today's New Pages", number_format($todayPages))
                ->description('Created today')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('warning')
                ->chart([15, 4, 17, 7, 2, 10, 3]),
        ];
    }
}

