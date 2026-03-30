<?php

namespace App\Providers;

use App\Models\CaseDocument;
use App\Services\LLM\LLMServiceInterface;
use App\Services\OpenRouter\OpenRouterService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OpenRouterService::class, fn () => OpenRouterService::fromConfig());
        // Default LLMServiceInterface binding — overridden per-job when provider = puter
        $this->app->bind(LLMServiceInterface::class, fn () => OpenRouterService::fromConfig());
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

        // Share storage stats with the app layout
        View::composer('layouts.app', function ($view) {
            $totalBytes = CaseDocument::sum('file_size');
            $totalGB = $totalBytes > 0 ? round($totalBytes / (1024 * 1024 * 1024), 1) : 0;
            $storageCapacityGB = 10;
            $storageUsedPercent = $storageCapacityGB > 0 ? min(100, round(($totalGB / $storageCapacityGB) * 100)) : 0;
            $view->with([
                'sidebarStorageGB' => $totalGB,
                'sidebarStorageCapacityGB' => $storageCapacityGB,
                'sidebarStoragePercent' => $storageUsedPercent,
            ]);
        });
    }
}
