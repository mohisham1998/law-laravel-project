<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\OpenRouter\OpenRouterService::class, fn () => \App\Services\OpenRouter\OpenRouterService::fromConfig());
        $this->app->singleton(\App\Services\Orchestration\PromptBuilder::class, fn () => \App\Services\Orchestration\PromptBuilder::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Disable Telescope in production for better performance
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }
}
