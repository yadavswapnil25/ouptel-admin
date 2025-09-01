<?php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DashboardChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Platform Statistics';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        // Get current year data
        $currentYear = date('Y');
        $yearStart = strtotime("1 January {$currentYear} 12:00am");
        $yearEnd = strtotime("31 December {$currentYear} 11:59pm");
        
        // Initialize monthly arrays
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $usersData = array_fill(0, 12, 0);
        $postsData = array_fill(0, 12, 0);
        $pagesData = array_fill(0, 12, 0);
        $groupsData = array_fill(0, 12, 0);
        
        // Get users data for each month
        for ($i = 1; $i <= 12; $i++) {
            $monthStart = strtotime("1 {$months[$i-1]} {$currentYear} 12:00am");
            $monthEnd = strtotime("31 {$months[$i-1]} {$currentYear} 11:59pm");
            
            // Adjust for months with fewer days
            if ($i == 2) { // February
                $monthEnd = strtotime("28 {$months[$i-1]} {$currentYear} 11:59pm");
                if (date('L', strtotime("{$currentYear}-01-01"))) { // Leap year
                    $monthEnd = strtotime("29 {$months[$i-1]} {$currentYear} 11:59pm");
                }
            } elseif (in_array($i, [4, 6, 9, 11])) { // April, June, September, November
                $monthEnd = strtotime("30 {$months[$i-1]} {$currentYear} 11:59pm");
            }
            
            $usersData[$i-1] = DB::table('Wo_Users')
                ->where('joined', '>=', $monthStart)
                ->where('joined', '<=', $monthEnd)
                ->count();
                
            $postsData[$i-1] = DB::table('Wo_Posts')
                ->where('time', '>=', $monthStart)
                ->where('time', '<=', $monthEnd)
                ->count();
                
            $pagesData[$i-1] = DB::table('Wo_Pages')
                ->where('time', '>=', $monthStart)
                ->where('time', '<=', $monthEnd)
                ->count();
                
            $groupsData[$i-1] = DB::table('Wo_Groups')
                ->where('time', '>=', $monthStart)
                ->where('time', '<=', $monthEnd)
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Users',
                    'data' => $usersData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 2,
                    'fill' => true,
                ],
                [
                    'label' => 'Posts',
                    'data' => $postsData,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'borderWidth' => 2,
                    'fill' => true,
                ],
                [
                    'label' => 'Pages',
                    'data' => $pagesData,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'borderColor' => 'rgb(245, 158, 11)',
                    'borderWidth' => 2,
                    'fill' => true,
                ],
                [
                    'label' => 'Groups',
                    'data' => $groupsData,
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                    'borderColor' => 'rgb(139, 92, 246)',
                    'borderWidth' => 2,
                    'fill' => true,
                ],
            ],
            'labels' => $months,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.1)',
                    ],
                ],
                'x' => [
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.1)',
                    ],
                ],
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
        ];
    }
}

