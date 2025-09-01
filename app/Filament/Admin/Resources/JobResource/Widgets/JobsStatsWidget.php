<?php

namespace App\Filament\Admin\Resources\JobResource\Widgets;

use App\Models\Job;
use App\Models\JobApplication;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class JobsStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        try {
            // Total Jobs
            $totalJobs = Job::count();

            // Active Jobs
            $activeJobs = Job::where('status', 1)->count();

            // Total Applications
            $totalApplications = JobApplication::count();

            // Jobs with Applications
            $jobsWithApplications = Job::whereHas('applications')->count();

            return [
                Stat::make('Total Jobs', $totalJobs)
                    ->description('All job postings')
                    ->descriptionIcon('heroicon-m-briefcase')
                    ->color('primary'),

                Stat::make('Active Jobs', $activeJobs)
                    ->description('Currently active job postings')
                    ->descriptionIcon('heroicon-m-check-circle')
                    ->color('success'),

                Stat::make('Total Applications', $totalApplications)
                    ->description('All job applications received')
                    ->descriptionIcon('heroicon-m-users')
                    ->color('info'),

                Stat::make('Jobs with Applications', $jobsWithApplications)
                    ->description('Jobs that have received applications')
                    ->descriptionIcon('heroicon-m-clipboard-document-list')
                    ->color('warning'),
            ];
        } catch (\Exception $e) {
            return [
                Stat::make('Total Jobs', '0')
                    ->description('Error loading data')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),

                Stat::make('Active Jobs', '0')
                    ->description('Error loading data')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),

                Stat::make('Total Applications', '0')
                    ->description('Error loading data')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),

                Stat::make('Jobs with Applications', '0')
                    ->description('Error loading data')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),
            ];
        }
    }
}



