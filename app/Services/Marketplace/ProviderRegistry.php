<?php

namespace App\Services\Marketplace;

use App\Contracts\FederatedSearchProviderInterface;
use App\Services\Marketplace\Providers\EbaySearchProvider;
use App\Services\Marketplace\Providers\MockSearchProvider;
use App\Services\Marketplace\Providers\SerpApiSearchProvider;
use App\Support\CategoryCatalog;
use App\Support\SwissCarMarketplaces;

/**
 * Registry of all federated search connectors.
 * Scalable: register new providers in config/marketplaces.php + adapter class.
 */
class ProviderRegistry
{
    /** @var array<int, FederatedSearchProviderInterface>|null */
    private ?array $providers = null;

    public function __construct(
        private EbaySearchProvider $ebay,
        private SerpApiSearchProvider $serpApi,
    ) {}

    /**
     * @return array<int, FederatedSearchProviderInterface>
     */
    public function all(): array
    {
        if ($this->providers !== null) {
            return $this->providers;
        }

        $this->providers = array_merge(
            [$this->ebay, $this->serpApi],
            $this->mockProviders(),
            $this->swissAutomotiveProviders(),
        );

        usort($this->providers, fn ($a, $b) => $a->priority() <=> $b->priority());

        return $this->providers;
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @param  array<string, mixed>  $geo
     * @return array<int, FederatedSearchProviderInterface>
     */
    public function forSearch(array $parsedQuery, array $expandedFilters, array $geo = []): array
    {
        $category = CategoryCatalog::normalize($parsedQuery['category'] ?? 'marketplace');
        $countryCode = strtoupper((string) ($parsedQuery['search_country_code'] ?? $geo['country_code'] ?? ''));
        $targets = $expandedFilters['marketplaces'] ?? [];
        $swissCar = $countryCode === 'CH' && CategoryCatalog::isAutomotive($category);

        return array_values(array_filter($this->all(), function (FederatedSearchProviderInterface $provider) use (
            $category,
            $countryCode,
            $targets,
            $swissCar,
            $geo
        ) {
            if ($swissCar && ! SwissCarMarketplaces::isTarget($provider->sourceKey(), $targets ?: SwissCarMarketplaces::keys())) {
                if (! in_array($provider->sourceKey(), SwissCarMarketplaces::keys(), true)) {
                    return false;
                }
            }

            if ($swissCar && in_array($provider->sourceKey(), ['ebay', 'google_shopping'], true)) {
                return false;
            }

            if (! $provider->supportsCategory($category)) {
                return false;
            }

            if ($provider instanceof MockSearchProvider) {
                $geoCode = strtoupper((string) ($geo['country_code'] ?? ''));
                if (! $provider->supportsCountry($geoCode)) {
                    return false;
                }
            }

            if ($targets !== [] && ! $this->matchesTarget($provider->sourceKey(), $targets)) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  array<int, string>  $targets
     */
    private function matchesTarget(string $sourceKey, array $targets): bool
    {
        if (SwissCarMarketplaces::isTarget($sourceKey, $targets)) {
            return true;
        }

        $sourceNorm = strtolower(str_replace(['.', '_'], '', $sourceKey));

        foreach ($targets as $target) {
            $targetNorm = strtolower(str_replace(['.', '_', ' '], '', $target));
            if (str_contains($sourceNorm, $targetNorm) || str_contains($targetNorm, $sourceNorm)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, MockSearchProvider>
     */
    private function mockProviders(): array
    {
        $providers = [];

        foreach (config('marketplaces.providers', []) as $key => $meta) {
            if (($meta['adapter'] ?? '') !== 'mock') {
                continue;
            }

            $providers[] = new MockSearchProvider(
                sourceKey: $key,
                sourceLabel: (string) ($meta['label'] ?? ucfirst($key)),
                priority: (int) ($meta['priority'] ?? 100),
                categories: (array) ($meta['categories'] ?? ['marketplace']),
                countries: isset($meta['countries']) ? (array) $meta['countries'] : null,
            );
        }

        return $providers;
    }

    /**
     * @return array<int, MockSearchProvider>
     */
    private function swissAutomotiveProviders(): array
    {
        $providers = [];

        foreach (SwissCarMarketplaces::keys() as $key) {
            if (isset(config('marketplaces.providers', [])[$key])) {
                continue;
            }

            $providers[] = new MockSearchProvider(
                sourceKey: $key,
                sourceLabel: SwissCarMarketplaces::label($key),
                priority: 80,
                categories: ['automotive'],
                countries: ['CH'],
            );
        }

        return $providers;
    }
}
