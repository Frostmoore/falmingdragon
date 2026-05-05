<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Dashboard\AIEditorService;
use App\Services\Dashboard\EnvEditor;
use App\Services\Embeddings\EmbeddingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EmbeddingService::class, function () {
            return new EmbeddingService(
                apiKey: (string) env('OPENAI_API_KEY', ''),
            );
        });

        $this->app->singleton(EnvEditor::class, fn() => new EnvEditor());
        $this->app->singleton(AIEditorService::class, fn() => new AIEditorService());
    }

    public function boot(): void
    {
        // In debug/local mode disable SSL certificate verification.
        // NEVER do this in production — APP_DEBUG must be false on live servers.
        if (config('app.debug')) {
            Http::globalOptions(['verify' => false]);
        }
    }
}
