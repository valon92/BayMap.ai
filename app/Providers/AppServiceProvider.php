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
        $this->app->singleton(\App\Services\Catalog\PlatformCatalogRepository::class);
        $this->app->singleton(\App\Services\Catalog\PlatformRoutingEngine::class);
        $this->app->singleton(\App\Services\Catalog\CatalogSyncService::class);
        $this->app->singleton(\App\Services\Catalog\PlatformCuratorService::class);
        $this->app->singleton(\App\Services\Catalog\CountryCatalogStatsService::class);
        $this->app->singleton(\App\Services\Catalog\PlatformCandidateAnalyzer::class);
        $this->app->singleton(\App\Services\Providers\ProviderIntelligenceService::class);
        $this->app->singleton(\App\Services\Providers\GlobalProviderRegistry::class);
        $this->app->singleton(\App\Services\Providers\ProviderExpansionEngine::class);
        $this->app->singleton(\App\Services\Orchestration\ProviderDiscoveryEngine::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
