<?php

namespace App\Services\Marketplace;

use App\Contracts\FederatedSearchProviderInterface;
use App\Services\Marketplace\Providers\EbaySearchProvider;
use App\Services\Marketplace\Providers\MockSearchProvider;
use App\Services\Marketplace\Providers\SerpApiSearchProvider;
use App\Support\LivePlatformRegistry;
use App\Support\CategoryCatalog;
use App\Support\DutchCarMarketplaces;
use App\Support\GermanCarMarketplaces;
use App\Support\GermanElectronicsMarketplaces;
use App\Support\GlobalBookMarketplaces;
use App\Support\KosovoCarMarketplaces;
use App\Support\KosovoMarketplaces;
use App\Support\LocalMarketplaceResolver;
use App\Support\SwissCarMarketplaces;
use App\Support\SwissRealEstateMarketplaces;
use App\Support\UKRealEstateMarketplaces;

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
        private LiveSearchProviderFactory $liveFactory,
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
            $this->liveFactory->all(),
            $this->mockProviders(),
            $this->swissAutomotiveProviders(),
            $this->swissRealEstateProviders(),
            $this->ukRealEstateProviders(),
            $this->dutchAutomotiveProviders(),
            $this->germanAutomotiveProviders(),
            $this->germanElectronicsProviders(),
            $this->globalBookProviders(),
            $this->kosovoMarketplaceProviders(),
        );

        $this->providers = $this->dedupeProviders($this->providers);
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
        $bookSearch = CategoryCatalog::isBookSearch($parsedQuery);
        $kosovoLocal = $countryCode === 'XK' && ! $bookSearch && empty($parsedQuery['search_target'])
            && ! CategoryCatalog::isAutomotive($category);
        $localTargeted = LocalMarketplaceResolver::isTargeted($parsedQuery)
            && LocalMarketplaceResolver::keys($countryCode, $category) !== [];
        $parsedForFanOut = $parsedQuery;
        if (empty($parsedForFanOut['search_country_code']) && ! empty($geo['country_code'])) {
            $parsedForFanOut['search_country_code'] = strtoupper((string) $geo['country_code']);
        }
        $liveFanOut = LivePlatformRegistry::shouldFanOut($parsedForFanOut, $countryCode);

        return array_values(array_filter($this->all(), function (FederatedSearchProviderInterface $provider) use (
            $parsedForFanOut,
            $parsedQuery,
            $category,
            $countryCode,
            $targets,
            $bookSearch,
            $kosovoLocal,
            $localTargeted,
            $liveFanOut,
            $geo
        ) {
            if ($liveFanOut) {
                $allowed = LivePlatformRegistry::keysFromParsed($parsedForFanOut);
                if (! in_array($provider->sourceKey(), $allowed, true)) {
                    return false;
                }
            }

            if ($localTargeted && ! LocalMarketplaceResolver::allowsProvider(
                $provider->sourceKey(),
                $provider,
                $parsedQuery,
                $targets,
                $countryCode,
                $category,
            )) {
                return false;
            }

            if ($bookSearch) {
                $bookKeys = $targets ?: GlobalBookMarketplaces::keysForCountry($countryCode);
                $key = $provider->sourceKey();
                $isBookTarget = GlobalBookMarketplaces::isTarget($key, $bookKeys)
                    || in_array($key, $bookKeys, true)
                    || in_array($key, ['ebay', 'google_shopping', 'amazon'], true);

                if (! $isBookTarget) {
                    return false;
                }

                if (in_array($key, ['mobile.de', 'autoscout24', 'mediamarkt', 'saturn', 'driloni', 'gjirafa50', 'facebook_marketplace', 'etsy'], true)) {
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

            if ($targets !== [] && ! LocalMarketplaceResolver::isTarget($provider->sourceKey(), $targets)) {
                return false;
            }

            return true;
        }));
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
            if (isset(config('marketplaces.providers', [])[$key]) || LivePlatformRegistry::isLivePlatform($key)) {
                continue;
            }

            if (! config('live_platforms.automotive_demo_fallback', false)) {
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
    private function swissRealEstateProviders(): array
    {
        $providers = [];

        foreach (SwissRealEstateMarketplaces::keys() as $key) {
            if (isset(config('marketplaces.providers', [])[$key]) || LivePlatformRegistry::isLivePlatform($key)) {
                continue;
            }

            $providers[] = new MockSearchProvider(
                sourceKey: $key,
                sourceLabel: SwissRealEstateMarketplaces::label($key),
                priority: 78,
                categories: ['real_estate'],
                countries: ['CH'],
            );
        }

        return $providers;
    }

    /**
     * @return array<int, MockSearchProvider>
     */
    private function ukRealEstateProviders(): array
    {
        $providers = [];

        foreach (UKRealEstateMarketplaces::keys() as $key) {
            if (isset(config('marketplaces.providers', [])[$key]) || LivePlatformRegistry::isLivePlatform($key)) {
                continue;
            }

            $providers[] = new MockSearchProvider(
                sourceKey: $key,
                sourceLabel: UKRealEstateMarketplaces::label($key),
                priority: 76,
                categories: ['real_estate'],
                countries: ['GB'],
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
            if (isset(config('marketplaces.providers', [])[$key]) || LivePlatformRegistry::isLivePlatform($key)) {
                continue;
            }

            if (! config('live_platforms.automotive_demo_fallback', false)) {
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
            if (isset(config('marketplaces.providers', [])[$key]) || LivePlatformRegistry::isLivePlatform($key)) {
                continue;
            }

            if (! config('live_platforms.automotive_demo_fallback', false)) {
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
    private function germanElectronicsProviders(): array
    {
        $providers = [];

        foreach (GermanElectronicsMarketplaces::keys() as $key) {
            if (isset(config('marketplaces.providers', [])[$key])) {
                continue;
            }

            $meta = GermanElectronicsMarketplaces::catalog()[$key];
            $providers[] = new MockSearchProvider(
                sourceKey: $key,
                sourceLabel: $meta['label'],
                priority: 68,
                categories: $meta['categories'],
                countries: ['DE'],
            );
        }

        return $providers;
    }

    /**
     * @return array<int, MockSearchProvider>
     */
    private function globalBookProviders(): array
    {
        $providers = [];
        $registered = array_keys(config('marketplaces.providers', []));

        foreach (GlobalBookMarketplaces::keys() as $key) {
            if (in_array($key, $registered, true)) {
                continue;
            }

            $meta = GlobalBookMarketplaces::catalog()[$key] ?? null;
            if ($meta === null) {
                continue;
            }

            $providers[] = new MockSearchProvider(
                sourceKey: $key,
                sourceLabel: $meta['label'],
                priority: 65,
                categories: $meta['categories'],
                countries: null,
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
            if (in_array($key, $registered, true) || LivePlatformRegistry::isLivePlatform($key)) {
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

    /**
     * @param  array<int, FederatedSearchProviderInterface>  $providers
     * @return array<int, FederatedSearchProviderInterface>
     */
    private function dedupeProviders(array $providers): array
    {
        $byKey = [];

        foreach ($providers as $provider) {
            $key = $provider->sourceKey();
            $existing = $byKey[$key] ?? null;

            if ($existing === null) {
                $byKey[$key] = $provider;

                continue;
            }

            $preferNew = ($provider->mode() === 'live' && $existing->mode() !== 'live')
                || ($provider->mode() === $existing->mode() && $provider->priority() < $existing->priority());

            if ($preferNew) {
                $byKey[$key] = $provider;
            }
        }

        return array_values($byKey);
    }
}
