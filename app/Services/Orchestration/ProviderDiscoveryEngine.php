<?php

namespace App\Services\Orchestration;

use App\Data\SearchIntent;
use App\Services\Providers\GlobalProviderRegistry;
use App\Services\Providers\ProviderExpansionEngine;
use App\Services\Providers\ProviderIntelligenceService;
use App\Support\CategoryCatalog;
use App\Support\SearchScopeResolver;

/**
 * Dynamic Provider Discovery Engine for BuyMap.ai.
 *
 * Step 1: AI parses intent (SearchIntent).
 * Step 2: Detect category, subcategory, location, search intent.
 * Step 3: Select relevant providers from Global Provider Registry.
 * Step 4: Plan dynamic Valon Workers (one worker per provider, parallel).
 */
class ProviderDiscoveryEngine
{
    public function __construct(
        private GlobalProviderRegistry $registry,
        private ProviderExpansionEngine $expansion,
        private ProviderIntelligenceService $intelligence,
    ) {}

    /**
     * @return array{
     *   scope: string,
     *   country_code: string,
     *   country_codes: array<int, string>,
     *   city: ?string,
     *   category: string,
     *   subcategory: ?string,
     *   keys: array<int, string>,
     *   providers: array<int, array<string, mixed>>,
     *   workers_planned: int,
     *   expansion_tier: string,
     *   routing_source: string,
     *   discovery_engine: string
     * }
     */
    public function discover(SearchIntent $intent): array
    {
        $parsed = $intent->toParsedQuery();
        $geo = [
            'country_code' => $intent->countryCode() ?? '',
            'city' => $intent->location['city'] ?? null,
        ];

        $scope = SearchScopeResolver::isUniversal($parsed) ? 'universal' : 'targeted';
        $expansionTier = $this->resolveExpansionTier($intent, $parsed);
        $discovery = $this->expansion->discoverProviders($parsed, $geo, $expansionTier);

        $keys = $discovery['keys'];
        $providers = $discovery['providers'];

        if ($providers === [] && $keys !== []) {
            foreach ($keys as $key) {
                $record = $this->registry->provider($key);
                if ($record !== null) {
                    $providers[] = $record;
                }
            }
        }

        $countryCodes = $discovery['country_codes'];
        $primaryCountry = $countryCodes[0] ?? ($intent->countryCode() ?? '');

        $cap = (int) config('live_platforms.max_workers_cap', 24);
        $workerCount = count($keys);

        return [
            'scope' => $scope,
            'country_code' => $primaryCountry,
            'country_codes' => $countryCodes,
            'city' => isset($parsed['search_city']) ? (string) $parsed['search_city'] : null,
            'category' => CategoryCatalog::normalize($intent->category),
            'subcategory' => $intent->subcategory,
            'keys' => $keys,
            'providers' => $providers,
            'workers_planned' => $workerCount > 0 ? min($workerCount, $cap) : 0,
            'expansion_tier' => $discovery['expansion_tier'],
            'routing_source' => $discovery['routing_source'],
            'discovery_engine' => 'global_provider_registry_v1',
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
        $parsed = $intent->toParsedQuery();
        $geo = [
            'country_code' => $intent->countryCode() ?? '',
            'city' => $intent->location['city'] ?? null,
        ];

        return $this->expansion->discoverProviders($parsed, $geo, 'country')['keys'] !== [];
    }

    public function maxWorkers(SearchIntent $intent): int
    {
        $parsed = $intent->toParsedQuery();
        $geo = [
            'country_code' => $intent->countryCode() ?? '',
            'city' => $intent->location['city'] ?? null,
        ];
        $count = count($this->expansion->discoverProviders($parsed, $geo, 'country')['keys']);
        $cap = (int) config('live_platforms.max_workers_cap', 24);

        if ($count > 0) {
            return min($count, $cap);
        }

        return (int) config('orchestration.max_workers', config('valon.max_workers', 10));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function providersForParsed(array $parsed, array $geo = []): array
    {
        $intent = SearchIntentFactory::fromParsed($parsed, [], $geo);

        return $this->discover($intent)['providers'];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function resolveExpansionTier(SearchIntent $intent, array $parsed): string
    {
        if ($intent->isMultiCountry() || ! empty($parsed['search_target'])) {
            return 'country';
        }

        if (SearchScopeResolver::isUniversal($parsed)) {
            return 'global';
        }

        return 'country';
    }
}
