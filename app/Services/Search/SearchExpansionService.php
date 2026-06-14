<?php

namespace App\Services\Search;

use App\Support\CategoryCatalog;
use App\Support\GlobalBookMarketplaces;
use App\Support\KosovoMarketplaces;
use App\Support\LivePlatformRegistry;
use App\Support\UniversalMarketplaceBridge;

/**
 * Expands parsed AI attributes into broader search filters (nearby countries, similar colors, etc.).
 */
class SearchExpansionService
{
    /** @var array<string, array<string>> */
    private array $nearbyCountries = [
        'XK' => ['AL', 'MK', 'RS', 'ME', 'DE', 'IT'],
        'AL' => ['XK', 'MK', 'IT', 'GR', 'DE'],
        'DE' => ['AT', 'CH', 'FR', 'NL', 'PL', 'IT'],
        'CH' => ['DE', 'FR', 'IT', 'AT'],
        'NL' => ['DE', 'BE', 'FR', 'GB'],
        'US' => ['CA', 'MX'],
        'GB' => ['IE', 'FR', 'DE'],
        'IN' => ['PK', 'BD', 'NP', 'LK', 'AE'],
    ];

    /** @var array<string, array<string, string>> */
    private array $countryLabels = [
        'CH' => 'Switzerland',
        'XK' => 'Kosovo',
        'AL' => 'Albania',
        'DE' => 'Germany',
        'IT' => 'Italy',
        'FR' => 'France',
        'AT' => 'Austria',
        'NL' => 'Netherlands',
        'IN' => 'India',
        'US' => 'United States',
        'GB' => 'United Kingdom',
    ];

    /** @var array<string, array<string>> */
    private array $colorVariants = [
        'white' => ['pearl white', 'ivory', 'off-white', 'silver'],
        'black' => ['jet black', 'matte black', 'graphite'],
        'silver' => ['grey', 'gray', 'metallic'],
    ];

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $geo
     * @return array<string, mixed>
     */
    public function expand(array $parsed, array $geo, ?string $locale = 'en'): array
    {
        $countryCode = strtoupper((string) ($parsed['search_country_code'] ?? $geo['country_code'] ?? 'XK'));
        if (CategoryCatalog::isBookSearch($parsed)) {
            $parsed['category'] = 'online_education';
        }
        $parsedForDiscovery = $parsed;
        if (empty($parsedForDiscovery['search_country_code']) && ! empty($geo['country_code'])) {
            $parsedForDiscovery['search_country_code'] = strtoupper((string) $geo['country_code']);
        }
        $discovery = LivePlatformRegistry::discover($parsedForDiscovery);
        $marketplaces = $discovery['keys'] !== []
            ? $discovery['keys']
            : $this->marketplacesForCategory($parsed['category'] ?? 'marketplace', $countryCode);

        $marketplaceLabels = $this->marketplaceLabels(
            $marketplaces,
            $discovery['country_code'] ?: $countryCode,
            $parsed['category'] ?? '',
            ! empty($parsed['search_target']),
        );

        if (! empty($parsed['search_countries']) && is_array($parsed['search_countries']) && count($parsed['search_countries']) > 1) {
            $allKeys = $marketplaces;
            $allLabels = $marketplaceLabels;

            foreach ($parsed['search_countries'] as $country) {
                $code = strtoupper((string) ($country['search_country_code'] ?? ''));
                if ($code === '') {
                    continue;
                }

                $perParsed = array_merge($parsed, $country);
                unset($perParsed['search_countries']);
                $keys = LivePlatformRegistry::keysFromParsed($perParsed);
                $allKeys = array_merge($allKeys, $keys);
                $allLabels = array_merge($allLabels, $this->marketplaceLabels(
                    $keys,
                    $code,
                    $parsed['category'] ?? '',
                    ! empty($parsed['search_target']),
                ));
            }

            $marketplaces = array_values(array_unique($allKeys));
            $marketplaceLabels = array_values(array_unique(array_filter($allLabels)));
        }

        $marketplaceLabelsByCountry = $this->marketplaceLabelsByCountry($parsed);

        $expanded = [
            'original' => $parsed,
            'search_country_code' => $discovery['country_code'] ?: $countryCode,
            'search_scope' => $discovery['scope'],
            'discovered_platforms' => $discovery['platforms'],
            'nearby_countries' => $this->nearbyCountryLabels($countryCode),
            'marketplaces' => $marketplaces,
            'marketplace_labels' => $marketplaceLabels,
            'marketplace_labels_by_country' => $marketplaceLabelsByCountry,
            'smart_filters' => $this->buildSmartFilters($parsed),
        ];

        if (! empty($parsed['color'])) {
            $expanded['color_variants'] = $this->colorVariants[$parsed['color']] ?? [$parsed['color']];
        }

        if (CategoryCatalog::isAutomotive($parsed['category'] ?? '')) {
            $expanded['similar_trims'] = $this->similarTrims($parsed['model'] ?? null);
            $expanded['engine_hints'] = ['2.0 TDI', '2.0 TFSI', '3.0 TDI'];
            if (empty($parsed['transmission'])) {
                $expanded['default_transmission'] = 'automatic';
            }
        }

        return $expanded;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, array<string, mixed>>
     */
    public function buildDynamicFilters(array $parsed, ?string $locale = 'en'): array
    {
        return CategoryCatalog::buildFilters($parsed, $locale);
    }

    /**
     * @return array<int, string>
     */
    private function marketplacesForCategory(string $category, string $countryCode = 'XK'): array
    {
        $category = CategoryCatalog::normalize($category);

        if (CategoryCatalog::isBooks($category)) {
            return GlobalBookMarketplaces::keysForCountry($countryCode);
        }

        $registryKeys = LivePlatformRegistry::keysFor($countryCode, $category);
        if ($registryKeys !== []) {
            return $registryKeys;
        }

        if (UniversalMarketplaceBridge::enabled()) {
            return UniversalMarketplaceBridge::providerKeys();
        }

        if ($countryCode === 'XK') {
            return KosovoMarketplaces::keysForCategory($category);
        }

        return match ($category) {
            'automotive' => ['ebay', 'mobile.de', 'autoscout24', 'facebook_marketplace'],
            'online_education' => GlobalBookMarketplaces::keys(),
            'luxury_collectibles' => ['etsy', 'ebay', 'facebook_marketplace', 'google_shopping'],
            'fashion', 'sports_outdoor' => ['driloni', 'ebay', 'etsy', 'facebook_marketplace', 'google_shopping'],
            'electronics_tech', 'gaming_entertainment', 'home_appliances', 'home_furniture' => ['amazon', 'ebay', 'google_shopping'],
            'real_estate' => ['facebook_marketplace', 'google_shopping'],
            'travel' => ['google_shopping', 'facebook_marketplace'],
            default => ['ebay', 'amazon', 'google_shopping', 'etsy'],
        };
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildSmartFilters(array $parsed): array
    {
        return array_filter([
            'brand' => $parsed['brand'] ?? null,
            'model' => $parsed['model'] ?? null,
            'genre' => $parsed['genre'] ?? null,
            'style' => $parsed['style'] ?? null,
            'size' => $parsed['size'] ?? null,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function similarTrims(?string $model): array
    {
        if (! $model) {
            return [];
        }

        $map = [
            'Q5' => ['Q3', 'Q7'],
            'A6' => ['A7', 'A5', 'A4'],
            'A4' => ['A3', 'A5', 'A6'],
            '3 SERIES' => ['5 Series', '4 Series'],
        ];

        return $map[strtoupper($model)] ?? [];
    }

    /**
     * @return array<int, string>
     */
    private function nearbyCountryLabels(string $countryCode): array
    {
        $code = strtoupper($countryCode);
        $labels = [];
        foreach ($this->nearbyCountries[$code] ?? ['DE', 'IT', 'FR'] as $nearCode) {
            $labels[] = $this->countryLabels[$nearCode] ?? $nearCode;
        }

        return $labels;
    }

    /**
     * @param  array<int, string>  $marketplaces
     * @return array<int, string>
     */
    private function marketplaceLabels(array $marketplaces, string $countryCode, string $category, bool $searchTarget): array
    {
        if (CategoryCatalog::isBooks($category)) {
            $labels = [];
            foreach ($marketplaces as $key) {
                $label = GlobalBookMarketplaces::label($key);
                if ($label !== '') {
                    $labels[] = $label;
                }
            }

            return $labels;
        }

        if (LivePlatformRegistry::keysFor($countryCode, $category) !== []) {
            $labels = [];
            foreach ($marketplaces as $key) {
                $label = LivePlatformRegistry::label($key);
                if ($label !== '') {
                    $labels[] = $label;
                }
            }

            return $labels;
        }

        if ($countryCode === 'XK') {
            $labels = [];
            foreach ($marketplaces as $key) {
                $label = KosovoMarketplaces::label($key);
                if ($label !== '') {
                    $labels[] = $label;
                }
            }

            return $labels;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, array<int, string>>
     */
    private function marketplaceLabelsByCountry(array $parsed): array
    {
        $countries = $parsed['search_countries'] ?? [];
        if (! is_array($countries) || count($countries) <= 1) {
            return [];
        }

        $byCountry = [];
        $category = $parsed['category'] ?? '';

        foreach ($countries as $country) {
            $code = strtoupper((string) ($country['search_country_code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $perParsed = array_merge($parsed, $country);
            unset($perParsed['search_countries']);
            $keys = LivePlatformRegistry::keysFromParsed($perParsed);
            $byCountry[$code] = $this->marketplaceLabels(
                $keys,
                $code,
                $category,
                ! empty($parsed['search_target']),
            );
        }

        return $byCountry;
    }
}
