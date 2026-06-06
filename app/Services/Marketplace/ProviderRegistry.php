<?php

namespace App\Services\Marketplace;

use App\Contracts\FederatedSearchProviderInterface;
use App\Services\Marketplace\Providers\EbaySearchProvider;
use App\Services\Marketplace\Providers\MockSearchProvider;
use App\Services\Marketplace\Providers\SerpApiSearchProvider;
use App\Support\CategoryCatalog;
use App\Support\DutchCarMarketplaces;
use App\Support\GermanCarMarketplaces;
use App\Support\KosovoMarketplaces;
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
            $this->dutchAutomotiveProviders(),
            $this->germanAutomotiveProviders(),
            $this->kosovoMarketplaceProviders(),
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
        $dutchCar = $countryCode === 'NL' && CategoryCatalog::isAutomotive($category) && ! empty($parsedQuery['search_target']);
        $germanCar = $countryCode === 'DE' && CategoryCatalog::isAutomotive($category) && ! empty($parsedQuery['search_target']);
        $kosovoLocal = $countryCode === 'XK';

        return array_values(array_filter($this->all(), function (FederatedSearchProviderInterface $provider) use (
            $category,
            $countryCode,
            $targets,
            $swissCar,
            $dutchCar,
            $germanCar,
            $kosovoLocal,
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

            if ($dutchCar) {
                $dutchKeys = $targets ?: DutchCarMarketplaces::keys();
                if (! DutchCarMarketplaces::isTarget($provider->sourceKey(), $dutchKeys)
                    && ! in_array($provider->sourceKey(), $dutchKeys, true)) {
                    return false;
                }
                if (in_array($provider->sourceKey(), ['ebay', 'google_shopping', 'mobile.de', 'autoscout24', 'facebook_marketplace'], true)) {
                    return false;
                }
            }

            if ($germanCar) {
                $germanKeys = $targets ?: GermanCarMarketplaces::keys();
                if (! GermanCarMarketplaces::isTarget($provider->sourceKey(), $germanKeys)
                    && ! in_array($provider->sourceKey(), $germanKeys, true)) {
                    return false;
                }
                if (in_array($provider->sourceKey(), ['ebay', 'google_shopping', 'facebook_marketplace', 'amazon', 'etsy'], true)) {
                    return false;
                }
            }

            if ($kosovoLocal) {
                $kosovoKeys = $targets ?: KosovoMarketplaces::keysForCategory($category);
                $key = $provider->sourceKey();
                $isKosovo = KosovoMarketplaces::isTarget($key, $kosovoKeys) || in_array($key, $kosovoKeys, true);
                $isLiveGlobal = $provider->mode() === 'live';

                if (! $isKosovo && ! $isLiveGlobal) {
                    return false;
                }

                if (! $isLiveGlobal && in_array($key, ['amazon', 'etsy', 'facebook_marketplace', 'mobile.de', 'autoscout24'], true)) {
                    return false;
                }
            }

            if (! $provider->supportsCategory($category)) {
                return false;
            }

            if ($provider instanceof MockSearchProvider) {
                $effectiveCountry = $countryCode !== '' ? $countryCode : strtoupper((string) ($geo['country_code'] ?? ''));
                if (! $provider->supportsCountry($effectiveCountry)) {
                    return false;
                }
            }

            if ($targets !== [] && ! $this->matchesTarget($provider->sourceKey(), $targets, $swissCar, $dutchCar, $germanCar, $kosovoLocal)) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  array<int, string>  $targets
     */
    private function matchesTarget(string $sourceKey, array $targets, bool $swissCar = false, bool $dutchCar = false, bool $germanCar = false, bool $kosovoLocal = false): bool
    {
        if ($swissCar && SwissCarMarketplaces::isTarget($sourceKey, $targets)) {
            return true;
        }

        if ($dutchCar && DutchCarMarketplaces::isTarget($sourceKey, $targets)) {
            return true;
        }

        if ($germanCar && GermanCarMarketplaces::isTarget($sourceKey, $targets)) {
            return true;
        }

        if ($kosovoLocal && KosovoMarketplaces::isTarget($sourceKey, $targets)) {
            return true;
        }

        if (DutchCarMarketplaces::isTarget($sourceKey, $targets) && ! $dutchCar) {
            return false;
        }

        if (SwissCarMarketplaces::isTarget($sourceKey, $targets) && ! $swissCar) {
            return false;
        }

        if (KosovoMarketplaces::isTarget($sourceKey, $targets) && ! $kosovoLocal) {
            return false;
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

    /**
     * @return array<int, MockSearchProvider>
     */
    private function dutchAutomotiveProviders(): array
    {
        $providers = [];

        foreach (DutchCarMarketplaces::keys() as $key) {
            if (isset(config('marketplaces.providers', [])[$key])) {
                continue;
            }

            $providers[] = new MockSearchProvider(
                sourceKey: $key,
                sourceLabel: DutchCarMarketplaces::label($key),
                priority: 75,
                categories: ['automotive'],
                countries: ['NL'],
            );
        }

        return $providers;
    }

    /**
     * @return array<int, MockSearchProvider>
     */
    private function germanAutomotiveProviders(): array
    {
        $providers = [];

        foreach (GermanCarMarketplaces::keys() as $key) {
            if (isset(config('marketplaces.providers', [])[$key])) {
                continue;
            }

            $providers[] = new MockSearchProvider(
                sourceKey: $key,
                sourceLabel: GermanCarMarketplaces::label($key),
                priority: 72,
                categories: ['automotive'],
                countries: ['DE'],
            );
        }

        return $providers;
    }

    /**
     * @return array<int, MockSearchProvider>
     */
    private function kosovoMarketplaceProviders(): array
    {
        $providers = [];
        $registered = array_keys(config('marketplaces.providers', []));

        foreach (KosovoMarketplaces::keys() as $key) {
            if (in_array($key, $registered, true)) {
                continue;
            }

            $meta = KosovoMarketplaces::catalog()[$key];
            $providers[] = new MockSearchProvider(
                sourceKey: $key,
                sourceLabel: $meta['label'],
                priority: 70,
                categories: array_values(array_unique(array_merge(['marketplace'], $meta['categories']))),
                countries: ['XK'],
            );
        }

        return $providers;
    }
}
