<?php

namespace App\Filament\Admin\Resources\ForumResource\Widgets;

use App\Models\Forum;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ForumStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalForums = Forum::count();
        
        // Get total forum posts (from Wo_Forum_Threads table)
        try {
            $totalPosts = DB::table('Wo_Forum_Threads')
                ->where('posted', '>', 0)
                ->count();
        } catch (\Exception $e) {
            $totalPosts = 0;
        }
        
        // Get total forum replies (from Wo_ForumThreadReplies table)
        try {
            $totalReplies = DB::table('Wo_ForumThreadReplies')
                ->where('posted_time', '>', 0)
                ->count();
        } catch (\Exception $e) {
            $totalReplies = 0;
        }
        
        // Get total forum topics/threads
        try {
            $totalTopics = DB::table('Wo_Forum_Threads')
                ->where('posted', '>', 0)
                ->count();
        } catch (\Exception $e) {
            $totalTopics = 0;
        }
        
        // Calculate total posts count from forums table
        $totalForumPosts = Forum::sum('posts');

        return [
            Stat::make('Total Forums', $totalForums)
                ->description('All forums in the system')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('primary')
                ->icon('heroicon-o-chat-bubble-left-right'),
            
            Stat::make('Total Topics', number_format($totalTopics))
                ->description('Discussion threads across all forums')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('success')
                ->icon('heroicon-o-document-text'),
            
            Stat::make('Total Replies', number_format($totalReplies))
                ->description('Replies to forum topics')
                ->descriptionIcon('heroicon-m-chat-bubble-left-ellipsis')
                ->color('warning')
                ->icon('heroicon-o-chat-bubble-left-ellipsis'),
            
            Stat::make('Total Posts', number_format($totalForumPosts))
                ->description('Total posts in all forums')
                ->descriptionIcon('heroicon-m-chat-bubble-bottom-center-text')
                ->color('info')
                ->icon('heroicon-o-chat-bubble-bottom-center-text'),
        ];
    }
}

