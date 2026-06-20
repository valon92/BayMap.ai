<?php

namespace App\Services\Agents;

use App\Contracts\FederatedSearchProviderInterface;
use App\Services\Marketplace\ProviderRegistry;
use App\Support\CategoryCatalog;
use App\Support\KosovoFashionIntent;
use App\Support\LivePlatformRegistry;
use App\Support\LocalMarketplaceResolver;
use App\Support\UniversalMarketplaceBridge;

/**
 * Activates Valon Workers per query.
 * When country + category has registered platforms → one worker per store (universal fan-out).
 * Otherwise scores and picks top agents from the pool.
 */
class AgentActivationService
{
    public function __construct(
        private AgentPoolRegistry $pools,
        private ProviderRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $expanded
     * @param  array<string, mixed>  $geo
     * @return array{
     *   agents: array<int, array<string, mixed>>,
     *   source_keys: array<int, string>,
     *   providers: array<int, FederatedSearchProviderInterface>
     * }
     */
    public function activate(array $parsed, array $expanded, array $geo = []): array
    {
        $countryCode = strtoupper((string) ($parsed['search_country_code'] ?? $geo['country_code'] ?? 'XK'));
        $category = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');
        $parsedForFanOut = $parsed;
        if (empty($parsedForFanOut['search_country_code']) && ! empty($geo['country_code'])) {
            $parsedForFanOut['search_country_code'] = strtoupper((string) $geo['country_code']);
        }

        if (LocalMarketplaceResolver::isTargeted($parsed)) {
            $keys = LivePlatformRegistry::keysFromParsed($parsedForFanOut);
            $live = $keys !== []
                ? $this->activateLivePlatforms($parsedForFanOut, $expanded, $geo, $countryCode, $category)
                : ['agents' => [], 'source_keys' => [], 'providers' => []];

            if (UniversalMarketplaceBridge::enabled()
                && ($keys === [] ? UniversalMarketplaceBridge::useWhenNoLocalPlatforms() : UniversalMarketplaceBridge::shouldAugmentLocalSearch())
                && ! $this->shouldSkipBridgeForMultiCountry($expanded, $category)) {
                $bridge = $this->activateUniversalBridge($parsedForFanOut, $expanded, $geo, $countryCode, $category);

                return $this->mergeActivation($live, $bridge);
            }

            return $keys !== [] ? $live : [
                'agents' => [],
                'source_keys' => [],
                'providers' => [],
            ];
        }

        if (LivePlatformRegistry::shouldFanOut($parsedForFanOut, $countryCode)) {
            $live = $this->activateLivePlatforms($parsedForFanOut, $expanded, $geo, $countryCode, $category);
            if (UniversalMarketplaceBridge::shouldAugmentLocalSearch()
                && ! $this->shouldSkipBridgeForMultiCountry($expanded, $category)) {
                $bridge = $this->activateUniversalBridge($parsedForFanOut, $expanded, $geo, $countryCode, $category);

                return $this->mergeActivation($live, $bridge);
            }

            return $live;
        }
        $poolAgents = $this->pools->poolForCategory($category);
        $allProviders = $this->registry->forSearch($parsed, $expanded, $geo);
        $providerMap = [];
        foreach ($allProviders as $provider) {
            $providerMap[$provider->sourceKey()] = $provider;
        }

        $scored = [];

        foreach ($poolAgents as $agent) {
            $sources = $agent['sources'] ?? [];
            $matchedProviders = [];
            foreach ($sources as $source) {
                foreach ($allProviders as $provider) {
                    if ($this->pools->matchesSourceKey($provider->sourceKey(), [$source])) {
                        $matchedProviders[$provider->sourceKey()] = $provider;
                    }
                }
            }

            if ($matchedProviders === []) {
                continue;
            }

            $score = $this->scoreAgent($agent, $parsed, $geo, $countryCode, $matchedProviders);
            $scored[] = [
                'id' => $agent['id'],
                'sources' => array_keys($matchedProviders),
                'score' => $score,
                'trust' => (int) ($agent['trust'] ?? 70),
                'speed' => (int) ($agent['speed'] ?? 75),
                'providers' => array_values($matchedProviders),
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $kosovoBrandedFashion = strtoupper($countryCode) === 'XK' && KosovoFashionIntent::isBrandedCatalogSearch($parsed);
        $min = $kosovoBrandedFashion
            ? 1
            : (int) config('agent_pools.min_agents', 3);
        $max = (int) config('agent_pools.max_agents', 6);
        $selected = array_slice($scored, 0, $max);

        if (count($selected) < $min && $allProviders !== [] && ! $kosovoBrandedFashion) {
            $selected = $this->fillFromRegistry($scored, $allProviders, $min, $max, $parsed, $geo, $countryCode);
        }

        $providers = [];
        $sourceKeys = [];
        $agents = [];

        foreach ($selected as $entry) {
            $agents[] = [
                'id' => $entry['id'],
                'score' => round($entry['score'], 2),
                'sources' => $entry['sources'],
                'trust' => $entry['trust'],
            ];
            foreach ($entry['providers'] as $provider) {
                $key = $provider->sourceKey();
                if (! isset($providers[$key])) {
                    $providers[$key] = $provider;
                    $sourceKeys[] = $key;
                }
            }
        }

        return [
            'agents' => $agents,
            'source_keys' => array_values(array_unique($sourceKeys)),
            'providers' => array_values($providers),
        ];
    }

    /**
     * One Valon Worker per live platform in country+category (parallel scraping).
     *
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $expanded
     * @param  array<string, mixed>  $geo
     * @return array{
     *   agents: array<int, array<string, mixed>>,
     *   source_keys: array<int, string>,
     *   providers: array<int, FederatedSearchProviderInterface>
     * }
     */
    private function activateLivePlatforms(array $parsed, array $expanded, array $geo, string $countryCode, string $category): array
    {
        $allProviders = $this->registry->forSearch($parsed, $expanded, $geo);
        $wanted = LivePlatformRegistry::keysFromParsed($parsed);
        $byKey = [];

        foreach ($allProviders as $provider) {
            $key = $provider->sourceKey();
            if (in_array($key, $wanted, true)) {
                $byKey[$key] = $provider;
            }
        }

        $ordered = [];
        foreach ($wanted as $key) {
            if (isset($byKey[$key])) {
                $ordered[] = $byKey[$key];
            }
        }

        $agents = [];
        $sourceKeys = [];

        foreach ($ordered as $provider) {
            $key = $provider->sourceKey();
            $sourceKeys[] = $key;
            $agentId = $countryCode === 'CH' && in_array($category, ['fashion', 'sports_outdoor'], true)
                ? 'SwissFashionPlatformAgent'
                : 'LivePlatformAgent';
            $agents[] = [
                'id' => $agentId,
                'score' => 100.0,
                'sources' => [$key],
                'trust' => (int) ($this->pools->trustScore($key) ?: 85),
            ];
        }

        return [
            'agents' => $agents,
            'source_keys' => $sourceKeys,
            'providers' => $ordered,
        ];
    }

    /**
     * SerpAPI + eBay bridge workers for any country (automatic marketplace discovery).
     *
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $expanded
     * @param  array<string, mixed>  $geo
     * @return array{
     *   agents: array<int, array<string, mixed>>,
     *   source_keys: array<int, string>,
     *   providers: array<int, FederatedSearchProviderInterface>
     * }
     */
    private function activateUniversalBridge(array $parsed, array $expanded, array $geo, string $countryCode, string $category): array
    {
        $allProviders = $this->registry->all();
        $wanted = UniversalMarketplaceBridge::providerKeys();
        $ordered = [];
        $agents = [];
        $sourceKeys = [];

        foreach ($allProviders as $provider) {
            $key = $provider->sourceKey();
            if (! in_array($key, $wanted, true)) {
                continue;
            }
            if (! UniversalMarketplaceBridge::allowsBridge($key, $countryCode, $category)) {
                continue;
            }
            if (in_array($key, LocalMarketplaceResolver::excludedGlobalProviders($countryCode, $category), true)) {
                continue;
            }
            if (! $provider->isAvailable()) {
                continue;
            }

            $ordered[] = $provider;
            $sourceKeys[] = $key;
            $agents[] = [
                'id' => 'UniversalBridgeAgent',
                'score' => 88.0,
                'sources' => [$key],
                'trust' => (int) ($this->pools->trustScore($key) ?: 88),
            ];
        }

        return [
            'agents' => $agents,
            'source_keys' => $sourceKeys,
            'providers' => $ordered,
        ];
    }

    /**
     * @param  array{
     *   agents: array<int, array<string, mixed>>,
     *   source_keys: array<int, string>,
     *   providers: array<int, FederatedSearchProviderInterface>
     * }  $primary
     * @param  array{
     *   agents: array<int, array<string, mixed>>,
     *   source_keys: array<int, string>,
     *   providers: array<int, FederatedSearchProviderInterface>
     * }  $secondary
     * @return array{
     *   agents: array<int, array<string, mixed>>,
     *   source_keys: array<int, string>,
     *   providers: array<int, FederatedSearchProviderInterface>
     * }
     */
    private function mergeActivation(array $primary, array $secondary): array
    {
        $providers = [];
        $sourceKeys = [];
        $agents = $primary['agents'];
        $seenSources = [];
        foreach ($primary['agents'] as $agent) {
            foreach ((array) ($agent['sources'] ?? []) as $source) {
                $seenSources[(string) $source] = true;
            }
        }

        foreach ([$primary, $secondary] as $batch) {
            foreach ($batch['providers'] as $provider) {
                $key = $provider->sourceKey();
                if (isset($providers[$key])) {
                    continue;
                }
                $providers[$key] = $provider;
                $sourceKeys[] = $key;
            }
        }

        foreach ($secondary['agents'] as $agent) {
            $sources = array_values(array_filter(
                (array) ($agent['sources'] ?? []),
                fn (string $source) => ! isset($seenSources[$source]),
            ));
            if ($sources === []) {
                continue;
            }
            foreach ($sources as $source) {
                $seenSources[$source] = true;
            }
            $agent['sources'] = $sources;
            $agents[] = $agent;
        }

        return [
            'agents' => $agents,
            'source_keys' => array_values(array_unique(array_merge($primary['source_keys'], $sourceKeys))),
            'providers' => array_values($providers),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $scored
     * @param  array<int, FederatedSearchProviderInterface>  $allProviders
     * @return array<int, array<string, mixed>>
     */
    private function fillFromRegistry(
        array $scored,
        array $allProviders,
        int $min,
        int $max,
        array $parsed,
        array $geo,
        string $countryCode,
    ): array {
        $usedKeys = [];
        foreach ($scored as $entry) {
            foreach ($entry['sources'] as $source) {
                $usedKeys[$source] = true;
            }
        }

        foreach ($allProviders as $provider) {
            if (count($scored) >= $max) {
                break;
            }
            $key = $provider->sourceKey();
            if (isset($usedKeys[$key])) {
                continue;
            }

            $scored[] = [
                'id' => 'FallbackAgent',
                'sources' => [$key],
                'score' => $this->scoreProvider($provider, $parsed, $geo, $countryCode),
                'trust' => $this->pools->trustScore($key),
                'speed' => max(50, 100 - $provider->priority()),
                'providers' => [$provider],
            ];
            $usedKeys[$key] = true;
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, max($min, min($max, count($scored))));
    }

    /**
     * @param  array<string, mixed>  $agent
     * @param  array<string, FederatedSearchProviderInterface>  $providers
     */
    private function scoreAgent(array $agent, array $parsed, array $geo, string $countryCode, array $providers): float
    {
        if (LivePlatformRegistry::shouldFanOut($parsed, $countryCode)
            && in_array((string) ($agent['id'] ?? ''), ['BalkanScraperAgent', 'EUAggregatorAgent', 'AboutYouAgent', 'ASOSAgent', 'ZalandoAgent', 'LocalMarketplaceAgent'], true)) {
            return 0.0;
        }

        $location = $this->locationScore($agent['countries'] ?? ['*'], $countryCode, $parsed);
        $availability = $this->availabilityScore($providers);
        $speed = ((float) ($agent['speed'] ?? 75)) / 100;
        $trust = ((float) ($agent['trust'] ?? 70)) / 100;
        $budget = $this->budgetAffinity($parsed, $providers);

        return ($location * 0.35) + ($availability * 0.30) + ($speed * 0.15) + ($trust * 0.10) + ($budget * 0.10);
    }

    private function scoreProvider(
        FederatedSearchProviderInterface $provider,
        array $parsed,
        array $geo,
        string $countryCode,
    ): float {
        $availability = $provider->mode() === 'live' && $provider->isAvailable() ? 1.0 : ($provider->mode() === 'live' ? 0.2 : 0.75);
        $speed = max(0.5, 1 - ($provider->priority() / 120));
        $trust = $this->pools->trustScore($provider->sourceKey()) / 100;

        return ($availability * 0.45) + ($speed * 0.30) + ($trust * 0.25);
    }

    /**
     * @param  array<int, string>  $agentCountries
     */
    private function locationScore(array $agentCountries, string $countryCode, array $parsed): float
    {
        if (in_array('*', $agentCountries, true)) {
            return 0.65;
        }

        if (in_array($countryCode, $agentCountries, true)) {
            return 1.0;
        }

        $regional = [
            'XK' => ['AL', 'MK', 'RS', 'ME'],
            'AL' => ['XK', 'MK', 'IT', 'GR'],
            'DE' => ['AT', 'CH', 'NL', 'FR'],
            'CH' => ['DE', 'AT', 'FR', 'IT'],
            'NL' => ['DE', 'BE', 'FR'],
        ];

        foreach ($regional[$countryCode] ?? [] as $neighbor) {
            if (in_array($neighbor, $agentCountries, true)) {
                return 0.75;
            }
        }

        if (! empty($parsed['search_target'])) {
            return 0.25;
        }

        return 0.45;
    }

    /**
     * @param  array<string, FederatedSearchProviderInterface>  $providers
     */
    private function availabilityScore(array $providers): float
    {
        $scores = [];
        foreach ($providers as $provider) {
            if ($provider->mode() === 'live') {
                $scores[] = $provider->isAvailable() ? 1.0 : 0.15;
            } else {
                $scores[] = 0.8;
            }
        }

        return $scores === [] ? 0.5 : max($scores);
    }

    /**
     * @param  array<string, FederatedSearchProviderInterface>  $providers
     */
    private function budgetAffinity(array $parsed, array $providers): float
    {
        if (empty($parsed['max_price'])) {
            return 0.6;
        }

        $hasLive = false;
        foreach ($providers as $provider) {
            if ($provider->mode() === 'live' && $provider->isAvailable()) {
                $hasLive = true;
                break;
            }
        }

        return $hasLive ? 0.85 : 0.55;
    }

    /**
     * @param  array<string, mixed>  $expanded
     */
    private function shouldSkipBridgeForMultiCountry(array $expanded, string $category): bool
    {
        if (! ($expanded['_multi_country_search'] ?? false)) {
            return false;
        }

        return CategoryCatalog::isAutomotive($category);
    }
}
