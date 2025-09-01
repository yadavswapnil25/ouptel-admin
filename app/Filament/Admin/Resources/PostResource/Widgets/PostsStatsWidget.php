<?php

namespace App\Filament\Admin\Resources\PostResource\Widgets;

use App\Models\Post;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PostsStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Get total comments
        try {
            $totalComments = DB::table('Wo_Comments')->count();
        } catch (\Exception $e) {
            $totalComments = 0;
        }
        
        // Get total likes
        try {
            $totalLikes = DB::table('Wo_Likes')->count();
        } catch (\Exception $e) {
            $totalLikes = 0;
        }
        
        // Get total wonders/dislikes
        try {
            $totalWonders = DB::table('Wo_Wonders')->count();
        } catch (\Exception $e) {
            $totalWonders = 0;
        }
        
        // Get total replies
        try {
            $totalReplies = DB::table('Wo_Comment_Replies')->count();
        } catch (\Exception $e) {
            $totalReplies = 0;
        }

        return [
            Stat::make('Total Comments', number_format($totalComments))
                ->description('All comments on posts')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('primary')
                ->icon('heroicon-o-chat-bubble-left-right'),
            
            Stat::make('Total Likes', number_format($totalLikes))
                ->description('All likes on posts')
                ->descriptionIcon('heroicon-m-heart')
                ->color('success')
                ->icon('heroicon-o-heart'),
            
            Stat::make('Total Wonders', number_format($totalWonders))
                ->description('All wonders/dislikes on posts')
                ->descriptionIcon('heroicon-m-face-frown')
                ->color('warning')
                ->icon('heroicon-o-face-frown'),
            
            Stat::make('Total Replies', number_format($totalReplies))
                ->description('All replies to comments')
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color('info')
                ->icon('heroicon-o-arrow-uturn-left'),
        ];
    }
}
