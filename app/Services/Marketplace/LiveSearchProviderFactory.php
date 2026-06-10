<?php

namespace App\Services\Marketplace;

use App\Contracts\FederatedSearchProviderInterface;
use App\Services\Marketplace\Providers\LivePlatformSearchProvider;
use App\Support\LivePlatformRegistry;

class LiveSearchProviderFactory
{
    public function __construct(private LivePlatformScraperService $scraper) {}

    /**
     * @return array<int, FederatedSearchProviderInterface>
     */
    public function all(): array
    {
        $providers = [];

        foreach (LivePlatformRegistry::all() as $key => $meta) {
            $providers[] = new LivePlatformSearchProvider($key, $meta, $this->scraper);
        }

        return $providers;
    }

    public function forKey(string $key): ?FederatedSearchProviderInterface
    {
        $meta = LivePlatformRegistry::platform($key);

        return $meta !== null
            ? new LivePlatformSearchProvider($key, $meta, $this->scraper)
            : null;
    }
}
