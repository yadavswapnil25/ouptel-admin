<?php

namespace App\Filament\Admin\Resources\FundingResource\Widgets;

use App\Models\Funding;
use App\Models\FundingRaise;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class FundingStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalFundings = Funding::count();
        
        // Get total amount raised across all fundings
        $totalRaised = FundingRaise::sum('amount') ?? 0;
        
        // Get total target amount across all fundings
        $totalTarget = Funding::sum('amount') ?? 0;
        
        // Get total donations count
        $totalDonations = FundingRaise::count();
        
        // Get average donation amount
        $averageDonation = $totalDonations > 0 ? $totalRaised / $totalDonations : 0;
        
        // Get completed fundings using a simpler approach
        $completedFundings = 0;
        try {
            $completedFundings = DB::table('Wo_Funding as f')
                ->join('Wo_Funding_Raise as fr', 'f.id', '=', 'fr.funding_id')
                ->select('f.id', DB::raw('SUM(fr.amount) as total_raised'), 'f.amount as target')
                ->groupBy('f.id', 'f.amount')
                ->havingRaw('total_raised >= target')
                ->count();
        } catch (\Exception $e) {
            $completedFundings = 0;
        }
        
        $activeFundings = $totalFundings - $completedFundings;
        
        // Get funding completion rate
        $completionRate = $totalFundings > 0 ? ($completedFundings / $totalFundings) * 100 : 0;
        
        // Get recent fundings (last 30 days)
        $recentFundings = Funding::where('time', '>=', now()->subDays(30)->timestamp)->count();
        
        // Get recent donations (last 30 days)
        $recentDonations = FundingRaise::where('time', '>=', now()->subDays(30)->timestamp)->count();

        return [
            Stat::make('Total Fundings', number_format($totalFundings))
                ->description('All funding campaigns')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('primary')
                ->icon('heroicon-o-currency-dollar'),
            
            Stat::make('Active Fundings', number_format($activeFundings))
                ->description('Ongoing campaigns')
                ->descriptionIcon('heroicon-m-play')
                ->color('success')
                ->icon('heroicon-o-play'),
            
            Stat::make('Completed Fundings', number_format($completedFundings))
                ->description('Successfully completed')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->icon('heroicon-o-check-circle'),
            
            Stat::make('Total Raised', '$' . number_format($totalRaised, 2))
                ->description('Amount raised across all campaigns')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning')
                ->icon('heroicon-o-banknotes'),
            
            Stat::make('Total Target', '$' . number_format($totalTarget, 2))
                ->description('Total target amount')
                ->descriptionIcon('heroicon-m-flag')
                ->color('info')
                ->icon('heroicon-o-flag'),
            
            Stat::make('Total Donations', number_format($totalDonations))
                ->description('All donations received')
                ->descriptionIcon('heroicon-m-heart')
                ->color('purple')
                ->icon('heroicon-o-heart'),
            
            Stat::make('Average Donation', '$' . number_format($averageDonation, 2))
                ->description('Average donation amount')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('gray')
                ->icon('heroicon-o-calculator'),
            
            Stat::make('Completion Rate', number_format($completionRate, 1) . '%')
                ->description('Success rate of campaigns')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('indigo')
                ->icon('heroicon-o-chart-bar'),
            
            Stat::make('Recent Fundings', number_format($recentFundings))
                ->description('Created in last 30 days')
                ->descriptionIcon('heroicon-m-clock')
                ->color('orange')
                ->icon('heroicon-o-clock'),
            
            Stat::make('Recent Donations', number_format($recentDonations))
                ->description('Donations in last 30 days')
                ->descriptionIcon('heroicon-m-gift')
                ->color('pink')
                ->icon('heroicon-o-gift'),
        ];
    }
}
