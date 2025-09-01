<?php

namespace App\Filament\Admin\Resources\GroupResource\Widgets;

use App\Models\Group;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class GroupsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Get groups by category
        $generalGroups = Group::where('category', 1)->count();
        $techGroups = Group::where('category', 2)->count();
        $businessGroups = Group::where('category', 3)->count();
        $entertainmentGroups = Group::where('category', 4)->count();
        
        // Get groups by privacy
        $privateGroups = Group::where('privacy', 'private')->count();
        $secretGroups = Group::where('privacy', 'secret')->count();
        
        // Get recent groups (last 30 days)
        $recentGroups = Group::where('time', '>=', now()->subDays(30)->timestamp)->count();
        
        // Get groups with most members (assuming group members table exists)
        try {
            $mostPopularGroup = DB::table('Wo_Group_Members')
                ->select('group_id', DB::raw('count(*) as member_count'))
                ->groupBy('group_id')
                ->orderBy('member_count', 'desc')
                ->first();
        } catch (\Exception $e) {
            $mostPopularGroup = null;
        }

        return [
            Stat::make('General Groups', $generalGroups)
                ->description('General category groups')
                ->descriptionIcon('heroicon-m-folder')
                ->color('gray')
                ->icon('heroicon-o-folder'),
            
            Stat::make('Tech Groups', $techGroups)
                ->description('Technology category groups')
                ->descriptionIcon('heroicon-m-computer-desktop')
                ->color('blue')
                ->icon('heroicon-o-computer-desktop'),
            
            Stat::make('Business Groups', $businessGroups)
                ->description('Business category groups')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('green')
                ->icon('heroicon-o-briefcase'),
            
            Stat::make('Entertainment Groups', $entertainmentGroups)
                ->description('Entertainment category groups')
                ->descriptionIcon('heroicon-m-film')
                ->color('purple')
                ->icon('heroicon-o-film'),
            
            Stat::make('Private Groups', $privateGroups)
                ->description('Private groups')
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color('orange')
                ->icon('heroicon-o-lock-closed'),
            
            Stat::make('Secret Groups', $secretGroups)
                ->description('Secret groups')
                ->descriptionIcon('heroicon-m-eye-slash')
                ->color('red')
                ->icon('heroicon-o-eye-slash'),
            
            Stat::make('Recent Groups', $recentGroups)
                ->description('Created in last 30 days')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('indigo')
                ->icon('heroicon-o-calendar'),
            
            Stat::make('Most Popular', $mostPopularGroup ? $mostPopularGroup->member_count : 0)
                ->description('Members in most popular group')
                ->descriptionIcon('heroicon-m-star')
                ->color('yellow')
                ->icon('heroicon-o-star'),
        ];
    }
}
