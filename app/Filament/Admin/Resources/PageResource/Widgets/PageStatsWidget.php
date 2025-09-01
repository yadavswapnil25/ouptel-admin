<?php

namespace App\Filament\Admin\Resources\PageResource\Widgets;

use App\Models\Page;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PageStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalPages = Page::count();
        $verifiedPages = Page::where('verified', true)->count();
        $activePages = Page::where('active', true)->count();
        
        // Get total likes (assuming there's a likes table)
        $totalLikes = DB::table('Wo_Pages_Likes')->count() ?? 0;
        
        // Get total posts (assuming there's a posts table)
        $totalPosts = DB::table('Wo_Posts')
            ->where('page_id', '!=', 0)
            ->count() ?? 0;

        return [
            Stat::make('Total Pages', $totalPages)
                ->description('All pages in the system')
                ->descriptionIcon('heroicon-m-document-duplicate')
                ->color('primary')
                ->icon('heroicon-o-flag'),
            
            Stat::make('Total Likes', number_format($totalLikes))
                ->description('Likes across all pages')
                ->descriptionIcon('heroicon-m-heart')
                ->color('info')
                ->icon('heroicon-o-heart'),
            
            Stat::make('Total Posts', number_format($totalPosts))
                ->description('Posts by pages')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning')
                ->icon('heroicon-o-document-text'),
            
            Stat::make('Verified Pages', $verifiedPages)
                ->description('Verified pages')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->icon('heroicon-o-check-circle'),
        ];
    }
}


