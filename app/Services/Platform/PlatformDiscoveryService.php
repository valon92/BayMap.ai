<?php

namespace App\Services\Platform;

use App\Services\Catalog\PlatformRoutingEngine;
use App\Services\Providers\GlobalProviderRegistry;
use App\Services\Providers\ProviderExpansionEngine;

/**
 * Automatically discovers and classifies marketplaces for a parsed user intent.
 * Uses Global Provider Registry + Provider Expansion Engine (no circular calls).
 */
class PlatformDiscoveryService
{
    public function __construct(
        private PlatformRoutingEngine $router,
        private ProviderExpansionEngine $expansion,
        private GlobalProviderRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{
     *   scope: string,
     *   country_code: string,
     *   city: ?string,
     *   category: string,
     *   keys: array<int, string>,
     *   platforms: array<int, array<string, mixed>>,
     *   providers: array<int, array<string, mixed>>,
     *   routing_source?: string,
     *   discovery_engine?: string
     * }
     */
    public function discover(array $parsed): array
    {
        $geo = [
            'country_code' => $parsed['search_country_code'] ?? null,
            'city' => $parsed['search_city'] ?? null,
        ];

        $discovery = $this->expansion->discoverProviders($parsed, $geo, 'country');

        if ($discovery['keys'] === []) {
            $route = $this->router->route($parsed);

            return [
                'scope' => $route['scope'],
                'country_code' => $route['country_code'],
                'city' => $route['city'],
                'category' => $route['category'],
                'keys' => $route['keys'],
                'platforms' => $route['platforms'],
                'providers' => [],
                'routing_source' => $route['routing_source'],
                'discovery_engine' => 'platform_routing_fallback',
            ];
        }

        $providers = $discovery['providers'];
        if ($providers === []) {
            foreach ($discovery['keys'] as $key) {
                $record = $this->registry->provider($key);
                if ($record !== null) {
                    $providers[] = $record;
                }
            }
        }

        $platforms = array_map(fn (array $provider) => [
            'key' => $provider['key'],
            'label' => $provider['provider_name'],
            'country' => $provider['provider_country'],
            'source' => $discovery['routing_source'] === 'database' ? 'catalog_db' : 'live',
            'connector_type' => $provider['connector_type'],
            'provider_type' => $provider['provider_type'],
            'trust_score' => $provider['trust_score'],
            'priority_score' => $provider['priority_score'],
            'effective_priority' => $provider['effective_priority'],
            'search_capabilities' => $provider['search_capabilities'],
            'score' => 100 - (int) ($provider['effective_priority'] ?? 50),
        ], $providers);

        $scope = \App\Support\SearchScopeResolver::isUniversal($parsed) ? 'universal' : 'targeted';

        return [
            'scope' => $scope,
            'country_code' => $discovery['country_codes'][0] ?? ($parsed['search_country_code'] ?? ''),
            'city' => isset($parsed['search_city']) ? (string) $parsed['search_city'] : null,
            'category' => \App\Support\CategoryCatalog::normalize($parsed['category'] ?? 'marketplace'),
            'keys' => $discovery['keys'],
            'platforms' => $platforms,
            'providers' => $providers,
            'routing_source' => $discovery['routing_source'],
            'discovery_engine' => 'global_provider_registry_v1',
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    public function keys(array $parsed): array
    {
        return $this->discover($parsed)['keys'];
    }
}
