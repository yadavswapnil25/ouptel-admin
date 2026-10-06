<?php

namespace App\Providers;

use App\Mail\Transport\BirdTransport;
use App\Providers\Filament\AdminPanelProvider;
use Illuminate\Support\Facades\Mail;
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
        Mail::extend('bird', fn () => new BirdTransport(
            apiKey: (string) config('services.bird.key'),
            baseUrl: config('services.bird.base_url') ?: null,
            fromAddress: config('services.bird.from_address') ?: null,
            fromName: config('services.bird.from_name') ?: null,
            timeout: (int) config('services.bird.timeout', 15),
        ));
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