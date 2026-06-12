<?php

namespace App\Services\Orchestration;

use App\Data\SearchIntent;
use App\Support\LivePlatformRegistry;
use App\Support\LocalMarketplaceResolver;
use App\Support\SearchScopeResolver;

/**
 * Discovers external providers for country + category combinations.
 * BuyMap is a discovery layer — providers are third-party sellers/marketplaces.
 */
class ProviderDiscoveryEngine
{
    /**
     * @return array{
     *   scope: string,
     *   country_code: string,
     *   category: string,
     *   keys: array<int, string>,
     *   platforms: array<int, array{key: string, label: string, country: string, connector: string, priority: int}>
     * }
     */
    public function discover(SearchIntent $intent): array
    {
        $parsed = $intent->toParsedQuery();
        $discovery = LivePlatformRegistry::discover($parsed);

        $platforms = [];
        foreach ($discovery['keys'] as $key) {
            $meta = LivePlatformRegistry::platform($key) ?? [];
            $platforms[] = [
                'key' => $key,
                'label' => LivePlatformRegistry::label($key),
                'country' => (string) ($meta['country'] ?? $discovery['country_code']),
                'connector' => (string) ($meta['adapter'] ?? 'generic'),
                'priority' => (int) ($meta['priority'] ?? 50),
            ];
        }

        return [
            'scope' => $discovery['scope'],
            'country_code' => $discovery['country_code'],
            'category' => $discovery['category'],
            'keys' => $discovery['keys'],
            'platforms' => $platforms,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function providerKeys(SearchIntent $intent): array
    {
        return $this->discover($intent)['keys'];
    }

    public function shouldFanOut(SearchIntent $intent): bool
    {
        if (SearchScopeResolver::isUniversal($intent->parsed)) {
            return LivePlatformRegistry::keysFromParsed($intent->parsed) !== [];
        }

        return LocalMarketplaceResolver::isTargeted($intent->parsed)
            && LocalMarketplaceResolver::hasLocalPlatforms($intent->parsed);
    }

    public function maxWorkers(SearchIntent $intent): int
    {
        if ($this->shouldFanOut($intent)) {
            return LivePlatformRegistry::maxWorkersFor($intent->parsed);
        }

        return (int) config('orchestration.max_workers', config('valon.max_workers', 10));
    }
}
