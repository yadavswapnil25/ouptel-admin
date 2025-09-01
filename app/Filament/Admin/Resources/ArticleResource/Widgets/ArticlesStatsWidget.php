<?php

namespace App\Filament\Admin\Resources\ArticleResource\Widgets;

use App\Models\Article;
use App\Models\BlogComment;
use App\Models\BlogCommentReply;
use App\Models\BlogReaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ArticlesStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        try {
            // Use database aggregation for better performance with large datasets
            $stats = DB::table('Wo_Blog')
                ->selectRaw('
                    COUNT(*) as total_articles,
                    SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as published_articles,
                    SUM(view) as total_views
                ')
                ->first();

            $totalArticles = $stats->total_articles ?? 0;
            $publishedArticles = $stats->published_articles ?? 0;
            $totalViews = $stats->total_views ?? 0;

            // Get comment and reaction counts efficiently
            $totalComments = DB::table('Wo_BlogComments')->count();
            $totalReplies = DB::table('Wo_BlogCommentReplies')->count();
            $totalReactions = DB::table('Wo_Blog_Reaction')->count();

            return [
                Stat::make('Total Articles', $totalArticles)
                    ->description('All blog articles')
                    ->descriptionIcon('heroicon-m-document-text')
                    ->color('primary'),

                Stat::make('Published Articles', $publishedArticles)
                    ->description('Currently published articles')
                    ->descriptionIcon('heroicon-m-check-circle')
                    ->color('success'),

                Stat::make('Total Comments', $totalComments)
                    ->description('All article comments')
                    ->descriptionIcon('heroicon-m-chat-bubble-left')
                    ->color('info'),

                Stat::make('Total Replies', $totalReplies)
                    ->description('All comment replies')
                    ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                    ->color('warning'),

                Stat::make('Total Reactions', $totalReactions)
                    ->description('All article reactions')
                    ->descriptionIcon('heroicon-m-heart')
                    ->color('danger'),

                Stat::make('Total Views', number_format($totalViews))
                    ->description('All article views')
                    ->descriptionIcon('heroicon-m-eye')
                    ->color('gray'),
            ];
        } catch (\Exception $e) {
            return [
                Stat::make('Total Articles', '0')
                    ->description('Error loading data')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),

                Stat::make('Published Articles', '0')
                    ->description('Error loading data')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),

                Stat::make('Total Comments', '0')
                    ->description('Error loading data')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),

                Stat::make('Total Replies', '0')
                    ->description('Error loading data')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),

                Stat::make('Total Reactions', '0')
                    ->description('Error loading data')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),

                Stat::make('Total Views', '0')
                    ->description('Error loading data')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),
            ];
        }
    }
}
