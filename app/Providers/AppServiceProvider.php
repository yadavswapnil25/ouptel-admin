<?php

namespace App\Providers;

use App\Providers\Filament\AdminPanelProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->shouldRegisterFilament()) {
            $this->app->register(AdminPanelProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Filament panel discovery is expensive. Skip it on public API requests so
     * signup/login unique-checks aren't paying for admin boot on every call.
     */
    private function shouldRegisterFilament(): bool
    {
        $path = explode('?', (string) ($_SERVER['REQUEST_URI'] ?? ''))[0];
        $path = ltrim($path, '/');

        return $path === '' || !str_starts_with($path, 'api/');
    }
}