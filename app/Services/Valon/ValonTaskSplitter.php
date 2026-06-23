<?php

namespace App\Services\Valon;

use App\Contracts\FederatedSearchProviderInterface;
use App\Support\CategoryCatalog;
use App\Support\LivePlatformRegistry;
use App\Support\SwissFashionMarketplaces;
use App\Support\UniversalMarketplaceBridge;

/**
 * Splits Valon intent into stateless Valon Worker execution units.
 */
class ValonTaskSplitter
{
    /**
     * @param  array<string, mixed>  $intent
     * @param  array<string, mixed>  $activation
     * @param  array<string, mixed>  $expanded
     * @return array<int, array<string, mixed>>
     */
    public function split(array $intent, array $activation, array $expanded): array
    {
        $workers = [];
        $index = 1;
        $parsed = $intent['parsed'] ?? [];
        $countryCode = strtoupper((string) ($parsed['search_country_code'] ?? ''));
        $category = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');
        $liveFanOut = LivePlatformRegistry::shouldFanOut($parsed, $countryCode);
        $max = $liveFanOut
            ? $this->maxWorkersForFanOut($parsed, $activation)
            : (int) config('valon.max_workers', config('agent_pools.max_agents', 6));
        $max = min($max, (int) config('search.max_workers', 12));

        $multiCountryCount = (int) ($expanded['_multi_country_count'] ?? 0);
        if (($expanded['_multi_country_search'] ?? false) && $multiCountryCount > 1) {
            if (CategoryCatalog::isAutomotiveParts($category)) {
                $perCountryCap = max(5, (int) floor((int) config('search.max_workers', 12) / $multiCountryCount));
            } elseif (in_array($category, ['fashion', 'sports_outdoor'], true)) {
                $perCountryCap = max(6, (int) floor((int) config('search.max_workers', 12) / $multiCountryCount));
            } else {
                $perCountryCap = max(3, (int) floor((int) config('search.max_workers', 12) / $multiCountryCount));
            }
            $max = min($max, $perCountryCap);
        }
        $prefix = $this->workerPrefix($category);
        $providers = $activation['providers'] ?? [];
        $agents = $activation['agents'] ?? [];

        if (CategoryCatalog::isAutomotiveParts($category)) {
            [$agents, $providers] = $this->prioritizeLivePlatformsForParts($agents, $providers);
        }

        if (($expanded['_multi_country_search'] ?? false) && CategoryCatalog::isAutomotive($category)) {
            [$agents, $providers] = $this->prioritizeLocalAutomotiveWorkers($agents, $providers, $countryCode);
        }

        if ($liveFanOut
            && in_array($category, ['fashion', 'sports_outdoor'], true)
            && UniversalMarketplaceBridge::shouldAugmentLocalSearch()) {
            [$agents, $providers] = $this->prioritizeBridgeWorkers($agents, $providers);
        }

        $providerByKey = [];
        foreach ($providers as $provider) {
            $providerByKey[$provider->sourceKey()] = $provider;
            $providerByKey[$this->normalizePlatform($provider->sourceKey())] = $provider;
        }

        $usedPlatforms = [];

        foreach ($agents as $agent) {
            if ($index > $max) {
                break;
            }

            $agentId = (string) ($agent['id'] ?? 'FallbackAgent');
            $role = $this->roleLabel($agentId);
            $sources = $agent['sources'] ?? [];

            foreach ($sources as $sourceKey) {
                if ($index > $max) {
                    break 2;
                }

                $provider = $providerByKey[$sourceKey]
                    ?? $providerByKey[$this->normalizePlatform($sourceKey)]
                    ?? null;
                if (! $provider instanceof FederatedSearchProviderInterface) {
                    continue;
                }

                $platformKey = $this->normalizePlatform($provider->sourceKey());
                if (isset($usedPlatforms[$platformKey])) {
                    continue;
                }

                if ($this->shouldSkipAntiBotFashionWorker($parsed, $activation, $platformKey)) {
                    continue;
                }

                $usedPlatforms[$platformKey] = true;

                $workers[] = $this->buildWorkerSpec(
                    "{$prefix}-{$index}",
                    $role,
                    $agentId,
                    $provider,
                    $intent,
                    $expanded,
                );
                $index++;
            }
        }

        if ($liveFanOut && $providers !== []) {
            foreach ($providers as $provider) {
                if ($index > $max) {
                    break;
                }
                $platformKey = $this->normalizePlatform($provider->sourceKey());
                if (isset($usedPlatforms[$platformKey])) {
                    continue;
                }
                $usedPlatforms[$platformKey] = true;
                $workers[] = $this->buildWorkerSpec(
                    "{$prefix}-{$index}",
                    $this->roleLabel('LivePlatformAgent'),
                    'LivePlatformAgent',
                    $provider,
                    $intent,
                    $expanded,
                );
                $index++;
            }
        }

        if ($workers === [] && $providers !== []) {
            foreach (array_slice($providers, 0, $max) as $provider) {
                $key = $this->normalizePlatform($provider->sourceKey());
                if (isset($usedPlatforms[$key])) {
                    continue;
                }
                $usedPlatforms[$key] = true;
                $workers[] = $this->buildWorkerSpec(
                    "{$prefix}-{$index}",
                    $this->roleLabel('FallbackAgent'),
                    'FallbackAgent',
                    $provider,
                    $intent,
                    $expanded,
                );
                $index++;
            }
        }

        return $workers;
    }

    /**
     * @param  array<int, array<string, mixed>>  $agents
     * @param  array<int, FederatedSearchProviderInterface>  $providers
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, FederatedSearchProviderInterface>}
     */
    private function prioritizeLocalAutomotiveWorkers(array $agents, array $providers, string $countryCode): array
    {
        usort($agents, function (array $a, array $b) use ($countryCode): int {
            $aLocal = $this->isLocalAutomotiveWorker($a, $countryCode) ? 0 : 1;
            $bLocal = $this->isLocalAutomotiveWorker($b, $countryCode) ? 0 : 1;

            return $aLocal <=> $bLocal;
        });

        $local = [];
        $other = [];
        foreach ($providers as $provider) {
            if ($this->isLocalAutomotiveProvider($provider, $countryCode)) {
                $local[] = $provider;
            } else {
                $other[] = $provider;
            }
        }

        usort($local, fn (FederatedSearchProviderInterface $a, FederatedSearchProviderInterface $b) => $a->priority() <=> $b->priority());

        return [$agents, array_merge($local, $other)];
    }

    /**
     * Reserve worker slots for SerpAPI/eBay bridge before live HTML scrapers.
     *
     * @param  array<int, array<string, mixed>>  $agents
     * @param  array<int, FederatedSearchProviderInterface>  $providers
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, FederatedSearchProviderInterface>}
     */
    private function prioritizeBridgeWorkers(array $agents, array $providers): array
    {
        $bridgeAgents = [];
        $otherAgents = [];
        foreach ($agents as $agent) {
            $isBridge = false;
            foreach ((array) ($agent['sources'] ?? []) as $source) {
                if (UniversalMarketplaceBridge::isBridgeProvider((string) $source)) {
                    $isBridge = true;
                    break;
                }
            }
            if ($isBridge) {
                $bridgeAgents[] = $agent;
            } else {
                $otherAgents[] = $agent;
            }
        }

        $bridgeProviders = [];
        $otherProviders = [];
        foreach ($providers as $provider) {
            if (UniversalMarketplaceBridge::isBridgeProvider($provider->sourceKey())) {
                $bridgeProviders[] = $provider;
            } else {
                $otherProviders[] = $provider;
            }
        }

        return [array_merge($bridgeAgents, $otherAgents), array_merge($bridgeProviders, $otherProviders)];
    }

    /**
     * @param  array<string, mixed>  $agent
     */
    private function isLocalAutomotiveWorker(array $agent, string $countryCode): bool
    {
        $source = (string) (($agent['sources'] ?? [])[0] ?? '');

        return $source !== '' && $this->isLocalAutomotiveSource($source, $countryCode);
    }

    private function isLocalAutomotiveProvider(FederatedSearchProviderInterface $provider, string $countryCode): bool
    {
        return $this->isLocalAutomotiveSource($provider->sourceKey(), $countryCode);
    }

    private function isLocalAutomotiveSource(string $sourceKey, string $countryCode): bool
    {
        if (UniversalMarketplaceBridge::isBridgeProvider($sourceKey)) {
            return false;
        }

        if (str_contains(strtolower($sourceKey), '_ww') || str_contains(strtolower($sourceKey), 'worldwide')) {
            return false;
        }

        $meta = LivePlatformRegistry::platform($sourceKey);

        return $meta !== null
            && strtoupper((string) ($meta['country'] ?? '')) === strtoupper($countryCode);
    }

    /**
     * @param  array<string, mixed>  $intent
     * @param  array<string, mixed>  $expanded
     * @return array<string, mixed>
     */
    private function buildWorkerSpec(
        string $workerId,
        string $role,
        string $agentId,
        FederatedSearchProviderInterface $provider,
        array $intent,
        array $expanded,
    ): array {
        $parsed = $intent['parsed'] ?? [];

        return [
            'worker_id' => $workerId,
            'role' => $role,
            'agent_id' => $agentId,
            'platform' => $provider->sourceKey(),
            'platform_label' => $provider->label(),
            'provider' => $provider,
            'task' => [
                'category' => $intent['category'] ?? 'marketplace',
                'attributes' => $intent['attributes'] ?? [],
                'price_range' => $intent['price_range'] ?? [],
                'keywords' => $intent['keywords'] ?? [],
                'location_priority' => $intent['location_priority'] ?? [],
                'parsed_query' => $parsed,
            ],
            'expanded_filters' => $expanded,
        ];
    }

    private function roleLabel(string $agentId): string
    {
        $labels = config('valon.role_labels', []);

        return $labels[$agentId] ?? str_replace('Agent', ' search', $agentId);
    }

    private function normalizePlatform(string $sourceKey): string
    {
        return strtolower(str_replace(['.', ' ', '-'], '_', trim($sourceKey)));
    }

    private function workerPrefix(string $category): string
    {
        $map = (array) config('providers.worker_prefix_by_category', []);
        $normalized = CategoryCatalog::normalize($category);

        return (string) ($map[$normalized] ?? config('valon.worker_prefix', 'ValonWorker'));
    }

    /**
     * @param  array<string, mixed>  $activation
     */
    private function maxWorkersForFanOut(array $parsed, array $activation): int
    {
        $localMax = LivePlatformRegistry::maxWorkersFor($parsed);
        $bridgeReserve = 0;

        if (UniversalMarketplaceBridge::shouldAugmentLocalSearch()) {
            foreach ($activation['providers'] ?? [] as $provider) {
                if ($provider instanceof FederatedSearchProviderInterface
                    && UniversalMarketplaceBridge::isBridgeProvider($provider->sourceKey())
                    && $provider->isAvailable()) {
                    $bridgeReserve++;
                }
            }
        }

        $cap = (int) config('live_platforms.max_workers_cap', 24);

        return min($localMax + $bridgeReserve, $cap, (int) config('search.max_workers', 12));
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $activation
     */
    private function shouldSkipAntiBotFashionWorker(array $parsed, array $activation, string $platformKey): bool
    {
        $countryCode = strtoupper((string) ($parsed['search_country_code'] ?? ''));
        $category = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');

        if ($countryCode !== 'CH' || ! in_array($category, ['fashion', 'sports_outdoor'], true)) {
            return false;
        }

        if (! SwissFashionMarketplaces::isPlatform($platformKey)) {
            return false;
        }

        if (! UniversalMarketplaceBridge::allowsBridge('google_shopping', $countryCode, $category)) {
            return false;
        }

        if (! in_array('google_shopping', UniversalMarketplaceBridge::providerKeys(), true)) {
            return false;
        }

        foreach ($activation['providers'] ?? [] as $provider) {
            if ($provider instanceof FederatedSearchProviderInterface
                && $provider->sourceKey() === 'google_shopping'
                && $provider->isAvailable()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Auto parts: scrape registered stores first (Autodoc, Pro4matic, …), bridge last.
     *
     * @param  array<int, array<string, mixed>>  $agents
     * @param  array<int, FederatedSearchProviderInterface>  $providers
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, FederatedSearchProviderInterface>}
     */
    private function prioritizeLivePlatformsForParts(array $agents, array $providers): array
    {
        usort($agents, function (array $a, array $b): int {
            $rank = fn (array $agent): int => (($agent['id'] ?? '') === 'UniversalBridgeAgent') ? 99 : 0;

            return $rank($a) <=> $rank($b);
        });

        usort($providers, function (FederatedSearchProviderInterface $a, FederatedSearchProviderInterface $b): int {
            $rank = fn (FederatedSearchProviderInterface $provider): int => match ($provider->sourceKey()) {
                'google_shopping' => 99,
                'ebay', 'ebay_motors_ww' => 50,
                default => 0,
            };

            return $rank($a) <=> $rank($b);
        });

        return [$agents, $providers];
    }
}
