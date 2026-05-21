<?php

namespace App\Services\Search;

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
        'US' => ['CA', 'MX'],
        'GB' => ['IE', 'FR', 'DE'],
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
    public function expand(array $parsed, array $geo): array
    {
        $countryCode = $geo['country_code'] ?? 'XK';

        $expanded = [
            'original' => $parsed,
            'nearby_countries' => $this->nearbyCountries[$countryCode] ?? ['DE', 'IT', 'FR'],
            'marketplaces' => $this->marketplacesForCategory($parsed['category'] ?? 'marketplace'),
            'smart_filters' => $this->buildSmartFilters($parsed),
        ];

        if (! empty($parsed['color'])) {
            $expanded['color_variants'] = $this->colorVariants[$parsed['color']] ?? [$parsed['color']];
        }

        if (($parsed['category'] ?? '') === 'car') {
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
    public function buildDynamicFilters(array $parsed): array
    {
        $filters = [];
        $category = $parsed['category'] ?? 'marketplace';

        if ($category === 'car') {
            $filters[] = ['key' => 'year', 'type' => 'range', 'label' => 'Year', 'min' => 1995, 'max' => (int) date('Y'), 'value' => $parsed['year'] ?? null];
            $filters[] = ['key' => 'max_km', 'type' => 'range', 'label' => 'Max mileage', 'min' => 0, 'max' => 300000, 'value' => $parsed['max_km'] ?? null];
            $filters[] = ['key' => 'color', 'type' => 'select', 'label' => 'Color', 'options' => ['white', 'black', 'silver', 'grey', 'red', 'blue'], 'value' => $parsed['color'] ?? null];
            $filters[] = ['key' => 'transmission', 'type' => 'select', 'label' => 'Transmission', 'options' => ['automatic', 'manual'], 'value' => $parsed['transmission'] ?? null];
            $filters[] = ['key' => 'price', 'type' => 'range', 'label' => 'Price (€)', 'min' => 1000, 'max' => 150000, 'value' => null];
            $filters[] = ['key' => 'country', 'type' => 'select', 'label' => 'Country', 'options' => ['Kosovo', 'Albania', 'Germany', 'Italy', 'Austria'], 'value' => $parsed['country'] ?? null];
            $filters[] = ['key' => 'condition', 'type' => 'select', 'label' => 'Condition', 'options' => ['new', 'used', 'certified'], 'value' => 'used'];
            $filters[] = ['key' => 'seller_type', 'type' => 'select', 'label' => 'Seller', 'options' => ['dealer', 'private'], 'value' => null];
        } elseif ($category === 'book') {
            $filters[] = ['key' => 'genre', 'type' => 'select', 'label' => 'Genre', 'options' => ['thriller', 'psychological', 'mystery', 'romance', 'sci-fi'], 'value' => $parsed['genre'] ?? null];
            $filters[] = ['key' => 'price', 'type' => 'range', 'label' => 'Price', 'min' => 5, 'max' => 80, 'value' => null];
        } elseif ($category === 'electronics') {
            $filters[] = ['key' => 'product_type', 'type' => 'select', 'label' => 'Type', 'options' => ['laptop', 'phone', 'tablet', 'monitor'], 'value' => $parsed['product_type'] ?? null];
            $filters[] = ['key' => 'price', 'type' => 'range', 'label' => 'Price', 'min' => 200, 'max' => 5000, 'value' => $parsed['max_price'] ?? null];
        } elseif ($category === 'real_estate') {
            $filters[] = ['key' => 'min_sqm', 'type' => 'range', 'label' => 'Min area (m²)', 'min' => 40, 'max' => 300, 'value' => $parsed['min_sqm'] ?? null];
            $filters[] = ['key' => 'listing_type', 'type' => 'select', 'label' => 'Listing', 'options' => ['rent', 'sale'], 'value' => $parsed['listing_type'] ?? null];
            $filters[] = ['key' => 'price', 'type' => 'range', 'label' => 'Price (€)', 'min' => 200, 'max' => 500000, 'value' => null];
        } else {
            $filters[] = ['key' => 'price', 'type' => 'range', 'label' => 'Price', 'min' => 10, 'max' => 10000, 'value' => $parsed['max_price'] ?? null];
            $filters[] = ['key' => 'condition', 'type' => 'select', 'label' => 'Condition', 'options' => ['new', 'used', 'vintage'], 'value' => null];
        }

        return $filters;
    }

    /**
     * @return array<int, string>
     */
    private function marketplacesForCategory(string $category): array
    {
        return match ($category) {
            'car' => ['mobile.de', 'autoscout24', 'facebook_marketplace'],
            'book' => ['amazon', 'ebay', 'google_shopping'],
            'painting', 'collectibles', 'gift', 'fashion' => ['etsy', 'ebay', 'facebook_marketplace'],
            'electronics', 'furniture' => ['amazon', 'ebay', 'google_shopping'],
            'real_estate' => ['facebook_marketplace', 'google_shopping'],
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
            'A6' => ['A7', 'A5', 'A4'],
            'A4' => ['A3', 'A5', 'A6'],
            '3 SERIES' => ['5 Series', '4 Series'],
        ];

        return $map[strtoupper($model)] ?? [];
    }
}
