<?php

namespace App\Services\Search;

use App\Support\CategoryCatalog;
use App\Support\DutchCarMarketplaces;
use App\Support\GermanCarMarketplaces;
use App\Support\KosovoMarketplaces;
use App\Support\SwissCarMarketplaces;

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
        $marketplaces = $this->marketplacesForCategory($parsed['category'] ?? 'marketplace', $countryCode);

        $expanded = [
            'original' => $parsed,
            'search_country_code' => $countryCode,
            'nearby_countries' => $this->nearbyCountryLabels($countryCode),
            'marketplaces' => $marketplaces,
            'marketplace_labels' => $this->marketplaceLabels(
                $marketplaces,
                $countryCode,
                $parsed['category'] ?? '',
                ! empty($parsed['search_target']),
            ),
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

        if ($countryCode === 'CH' && CategoryCatalog::isAutomotive($category)) {
            return SwissCarMarketplaces::keys();
        }

        if ($countryCode === 'NL' && CategoryCatalog::isAutomotive($category)) {
            return DutchCarMarketplaces::keys();
        }

        if ($countryCode === 'DE' && CategoryCatalog::isAutomotive($category)) {
            return GermanCarMarketplaces::keys();
        }

        if ($countryCode === 'XK') {
            return KosovoMarketplaces::keysForCategory($category);
        }

        return match ($category) {
            'automotive' => ['ebay', 'mobile.de', 'autoscout24', 'facebook_marketplace'],
            'online_education' => ['amazon', 'ebay', 'google_shopping'],
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
        if ($searchTarget && $countryCode === 'CH' && CategoryCatalog::isAutomotive($category)) {
            return SwissCarMarketplaces::labels();
        }

        if ($searchTarget && $countryCode === 'NL' && CategoryCatalog::isAutomotive($category)) {
            return DutchCarMarketplaces::labels();
        }

        if ($searchTarget && $countryCode === 'DE' && CategoryCatalog::isAutomotive($category)) {
            return GermanCarMarketplaces::labels();
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
}
