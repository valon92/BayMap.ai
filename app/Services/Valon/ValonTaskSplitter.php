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
        $prefix = $this->workerPrefix($category);
        $providers = $activation['providers'] ?? [];
        $agents = $activation['agents'] ?? [];

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

        return min($localMax + $bridgeReserve, $cap);
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
}
