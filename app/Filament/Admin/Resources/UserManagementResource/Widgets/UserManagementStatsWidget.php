<?php

namespace App\Filament\Admin\Resources\UserManagementResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class UserManagementStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        try {
            // Get user counts by gender
            $genderCounts = DB::table('Wo_Users')
                ->select('gender', DB::raw('COUNT(*) as count'))
                ->groupBy('gender')
                ->get()
                ->pluck('count', 'gender')
                ->toArray();

            // Get active/inactive counts
            $activeCount = DB::table('Wo_Users')->where('active', '1')->count();
            $inactiveCount = DB::table('Wo_Users')->where('active', '0')->count();

            $stats = [];
            
            // Add gender stats
            foreach ($genderCounts as $gender => $count) {
                $genderName = \App\Models\Gender::getGenderName($gender);
                $stats[] = Stat::make($genderName . ' Users', number_format($count))
                    ->description("Total {$genderName} users")
                    ->descriptionIcon('heroicon-m-users')
                    ->color(match($gender) {
                        'male' => 'info',
                        'female' => 'success',
                        '1802' => 'warning',
                        'mal****' => 'gray',
                        default => 'primary',
                    });
            }
            
            // Add active/inactive stats
            $stats[] = Stat::make('Active Users', number_format($activeCount))
                ->description('Currently active users')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success');

            $stats[] = Stat::make('Inactive Users', number_format($inactiveCount))
                ->description('Currently inactive users')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('warning');

            return $stats;
        } catch (\Exception $e) {
            return [
                Stat::make('Error', 'N/A')
                    ->description('Unable to load statistics')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),
            ];
        }
    }
}
