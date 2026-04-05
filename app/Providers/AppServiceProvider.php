<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // In debug/local mode disable SSL certificate verification.
        // NEVER do this in production — APP_DEBUG must be false on live servers.
        if (config('app.debug')) {
            Http::globalOptions(['verify' => false]);
        }
    }
}
