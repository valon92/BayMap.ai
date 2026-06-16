<?php

namespace App\Services\Providers;

use App\Services\Catalog\PlatformRoutingEngine;
use App\Support\CategoryCatalog;
use App\Support\SearchScopeResolver;

/**
 * Geographic provider expansion for BuyMap.ai discovery.
 *
 * Sequence when no explicit location: city → country → neighbors → region → global.
 * Respects explicit multi-country queries (e.g. Germany + Switzerland).
 */
class ProviderExpansionEngine
{
    public function __construct(
        private PlatformRoutingEngine $router,
        private GlobalProviderRegistry $registry,
    ) {}

    /**
     * Resolve which country codes to search based on intent + visitor geo.
     *
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $geo
     * @return array<int, string>
     */
    public function resolveTargetCountries(array $parsed, array $geo = [], string $expansionTier = 'country'): array
    {
        if (! empty($parsed['search_countries']) && is_array($parsed['search_countries'])) {
            $codes = [];
            foreach ($parsed['search_countries'] as $entry) {
                $code = strtoupper((string) ($entry['search_country_code'] ?? ''));
                if ($code !== '') {
                    $codes[] = $code;
                }
            }
            if ($codes !== []) {
                return array_values(array_unique($codes));
            }
        }

        if (SearchScopeResolver::isUniversal($parsed)) {
            return ['WW'];
        }

        $primary = strtoupper((string) ($parsed['search_country_code'] ?? $geo['country_code'] ?? ''));
        if ($primary === '') {
            return [];
        }

        if (! empty($parsed['search_target']) || $expansionTier === 'country') {
            return [$primary];
        }

        return $this->expansionSequence($primary, $expansionTier);
    }

    /**
     * Build country expansion sequence from a primary ISO2 code.
     *
     * @return array<int, string>
     */
    public function expansionSequence(string $primaryCode, string $maxTier = 'global'): array
    {
        $primaryCode = strtoupper($primaryCode);
        $tiers = (array) config('providers.expansion.tiers', ['city', 'country', 'neighbors', 'region', 'global']);
        $maxIndex = array_search($maxTier, $tiers, true);
        if ($maxIndex === false) {
            $maxIndex = count($tiers) - 1;
        }

        $sequence = [$primaryCode];
        $activeTiers = array_slice($tiers, 0, $maxIndex + 1);

        if (in_array('neighbors', $activeTiers, true)) {
            $sequence = array_merge($sequence, $this->neighborCountries($primaryCode));
        }

        if (in_array('region', $activeTiers, true)) {
            $sequence = array_merge($sequence, $this->regionCountries($primaryCode));
        }

        if (in_array('global', $activeTiers, true)) {
            $sequence[] = 'WW';
        }

        return array_values(array_unique(array_filter($sequence, fn (string $c) => $c !== '')));
    }

    /**
     * Discover providers across countries for a parsed intent.
     *
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $geo
     * @return array{
     *   country_codes: array<int, string>,
     *   keys: array<int, string>,
     *   providers: array<int, array<string, mixed>>,
     *   expansion_tier: string,
     *   routing_source: string
     * }
     */
    public function discoverProviders(array $parsed, array $geo = [], string $expansionTier = 'country'): array
    {
        $category = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');
        $countryCodes = $this->resolveTargetCountries($parsed, $geo, $expansionTier);

        if ($countryCodes === []) {
            return [
                'country_codes' => [],
                'keys' => [],
                'providers' => [],
                'expansion_tier' => $expansionTier,
                'routing_source' => 'none',
            ];
        }

        $allKeys = [];
        $allProviders = [];
        $routingSource = 'config';

        foreach ($countryCodes as $code) {
            if ($code === 'WW') {
                $perParsed = array_merge($parsed, ['search_scope' => 'universal', 'search_country_code' => 'WW']);
            } else {
                $perParsed = array_merge($parsed, ['search_country_code' => $code]);
            }

            $route = $this->router->route($perParsed);
            $routingSource = (string) ($route['routing_source'] ?? $routingSource);

            foreach ($route['keys'] as $key) {
                if (isset($allKeys[$key])) {
                    continue;
                }
                $record = $this->registry->provider($key);
                if ($record === null) {
                    continue;
                }
                $allKeys[$key] = true;
                $allProviders[] = $record;
            }
        }

        usort($allProviders, fn (array $a, array $b) => ($a['effective_priority'] ?? 50) <=> ($b['effective_priority'] ?? 50));

        $cap = (int) config('live_platforms.max_workers_cap', 24);
        if (count($allProviders) > $cap) {
            $allProviders = array_slice($allProviders, 0, $cap);
        }

        return [
            'country_codes' => $countryCodes,
            'keys' => array_map(fn (array $p) => $p['key'], $allProviders),
            'providers' => $allProviders,
            'expansion_tier' => $expansionTier,
            'routing_source' => $routingSource,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function neighborCountries(string $countryCode): array
    {
        $map = (array) config('providers.expansion.neighbor_map', []);

        return (array) ($map[strtoupper($countryCode)] ?? []);
    }

    /**
     * @return array<int, string>
     */
    private function regionCountries(string $countryCode): array
    {
        $countryCode = strtoupper($countryCode);
        $regionMap = (array) config('providers.expansion.region_map', []);
        $countries = [];

        foreach ($regionMap as $regionCode => $members) {
            if (in_array($countryCode, $members, true)) {
                $countries = array_merge($countries, $members);
            }
        }

        return array_values(array_unique($countries));
    }
}
