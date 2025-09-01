<?php

namespace App\Filament\Admin\Resources\GroupResource\Widgets;

use App\Models\Group;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class GroupsStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalGroups = Group::count();
        
        // Get total group posts (assuming there's a posts table with group_id)
        try {
            $totalPosts = DB::table('Wo_Posts')
                ->where('group_id', '!=', 0)
                ->count();
        } catch (\Exception $e) {
            $totalPosts = 0;
        }
        
        // Get total group members (assuming there's a group members table)
        try {
            $totalMembers = DB::table('Wo_Group_Members')
                ->count();
        } catch (\Exception $e) {
            $totalMembers = 0;
        }
        
        // Get pending join requests (assuming there's a join requests table)
        try {
            $pendingRequests = DB::table('Wo_Group_Join_Requests')
                ->where('status', 'pending')
                ->count();
        } catch (\Exception $e) {
            $pendingRequests = 0;
        }

        return [
            Stat::make('Total Groups', $totalGroups)
                ->description('All groups in the system')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary')
                ->icon('heroicon-o-user-group'),
            
            Stat::make('Joined Groups', number_format($totalMembers))
                ->description('Total group memberships')
                ->descriptionIcon('heroicon-m-users')
                ->color('success')
                ->icon('heroicon-o-users'),
            
            Stat::make('Total Posts', number_format($totalPosts))
                ->description('Posts across all groups')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning')
                ->icon('heroicon-o-document-text'),
            
            Stat::make('Join Requests', $pendingRequests)
                ->description('Join requests awaiting approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color('danger')
                ->icon('heroicon-o-clock'),
        ];
    }
}
