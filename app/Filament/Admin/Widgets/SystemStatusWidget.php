<?php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SystemStatusWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        $stats = [];

        // Database Connection Status
        try {
            DB::connection()->getPdo();
            $stats[] = Stat::make('Database', 'Connected')
                ->description('Database connection is active')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success');
        } catch (\Exception $e) {
            $stats[] = Stat::make('Database', 'Error')
                ->description('Database connection failed')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger');
        }

        // Storage Status
        try {
            $storageTest = Storage::disk('local')->put('test.txt', 'test');
            if ($storageTest) {
                Storage::disk('local')->delete('test.txt');
                $stats[] = Stat::make('Storage', 'Available')
                    ->description('File storage is working')
                    ->descriptionIcon('heroicon-m-check-circle')
                    ->color('success');
            } else {
                $stats[] = Stat::make('Storage', 'Error')
                    ->description('File storage is not working')
                    ->descriptionIcon('heroicon-m-x-circle')
                    ->color('danger');
            }
        } catch (\Exception $e) {
            $stats[] = Stat::make('Storage', 'Error')
                ->description('File storage error: ' . $e->getMessage())
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger');
        }

        // PHP Version
        $phpVersion = PHP_VERSION;
        $stats[] = Stat::make('PHP Version', $phpVersion)
            ->description('Current PHP version')
            ->descriptionIcon('heroicon-m-code-bracket')
            ->color('info');

        // Laravel Version
        $laravelVersion = app()->version();
        $stats[] = Stat::make('Laravel Version', $laravelVersion)
            ->description('Current Laravel version')
            ->descriptionIcon('heroicon-m-cog-6-tooth')
            ->color('primary');

        // Memory Usage
        $memoryUsage = $this->formatBytes(memory_get_usage(true));
        $memoryLimit = ini_get('memory_limit');
        $stats[] = Stat::make('Memory Usage', $memoryUsage)
            ->description("Limit: {$memoryLimit}")
            ->descriptionIcon('heroicon-m-cpu-chip')
            ->color('warning');

        // Disk Space
        $diskFree = $this->formatBytes(disk_free_space('.'));
        $diskTotal = $this->formatBytes(disk_total_space('.'));
        $diskUsed = $this->formatBytes(disk_total_space('.') - disk_free_space('.'));
        $stats[] = Stat::make('Disk Space', $diskUsed . ' / ' . $diskTotal)
            ->description("Free: {$diskFree}")
            ->descriptionIcon('heroicon-m-server')
            ->color('secondary');

        // Server Uptime
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $stats[] = Stat::make('Server Load', number_format($load[0], 2))
                ->description('1 minute average')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info');
        }

        return $stats;
    }

    private function formatBytes($size, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
}
