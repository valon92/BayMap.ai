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
        'CH' => ['DE', 'FR', 'IT', 'AT'],
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

        $expanded = [
            'original' => $parsed,
            'search_country_code' => $countryCode,
            'nearby_countries' => $this->nearbyCountryLabels($countryCode),
            'marketplaces' => $this->marketplacesForCategory($parsed['category'] ?? 'marketplace', $countryCode),
            'smart_filters' => $this->buildSmartFilters($parsed, $locale),
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
    public function buildDynamicFilters(array $parsed, ?string $locale = 'en'): array
    {
        $filters = [];
        $category = $parsed['category'] ?? 'marketplace';
        $sq = $locale === 'sq';

        if ($category === 'car') {
            $currency = $parsed['currency'] ?? 'EUR';
            $priceMax = $parsed['max_price'] ?? null;
            $targetCountry = $parsed['search_country'] ?? $parsed['country'] ?? null;
            $countryOptions = array_values(array_unique(array_filter([
                $targetCountry,
                'Switzerland', 'Germany', 'Kosovo', 'Albania', 'Italy', 'Austria', 'France',
            ])));

            $filters[] = ['key' => 'year', 'type' => 'range', 'label' => $sq ? 'Viti' : 'Year', 'min' => 1995, 'max' => (int) date('Y'), 'value' => $parsed['year'] ?? null];
            $filters[] = ['key' => 'max_km', 'type' => 'range', 'label' => $sq ? 'Km max' : 'Max mileage', 'min' => 0, 'max' => 300000, 'value' => $parsed['max_km'] ?? null];
            $filters[] = ['key' => 'color', 'type' => 'select', 'label' => $sq ? 'Ngjyra' : 'Color', 'options' => ['white', 'black', 'silver', 'grey', 'red', 'blue'], 'value' => $parsed['color'] ?? null];
            $filters[] = ['key' => 'transmission', 'type' => 'select', 'label' => $sq ? 'Transmisioni' : 'Transmission', 'options' => ['automatic', 'manual'], 'value' => $parsed['transmission'] ?? null];
            $filters[] = ['key' => 'fuel', 'type' => 'select', 'label' => $sq ? 'Karburanti' : 'Fuel', 'options' => ['petrol', 'diesel', 'electric', 'hybrid'], 'value' => $parsed['fuel'] ?? null];
            $filters[] = [
                'key' => 'price',
                'type' => 'range',
                'label' => ($sq ? 'Çmimi max' : 'Max price').' ('.$currency.')',
                'min' => 1000,
                'max' => $currency === 'CHF' ? 80000 : 150000,
                'value' => $priceMax,
            ];
            $filters[] = [
                'key' => 'country',
                'type' => 'select',
                'label' => $sq ? 'Vendi' : 'Country',
                'options' => $countryOptions,
                'value' => $targetCountry,
            ];
            $filters[] = ['key' => 'condition', 'type' => 'select', 'label' => 'Condition', 'options' => ['new', 'used', 'certified'], 'value' => 'used'];
            $filters[] = ['key' => 'seller_type', 'type' => 'select', 'label' => 'Seller', 'options' => ['dealer', 'private'], 'value' => null];
        } elseif ($category === 'book') {
            $filters[] = ['key' => 'genre', 'type' => 'select', 'label' => 'Genre', 'options' => ['thriller', 'psychological', 'mystery', 'romance', 'sci-fi'], 'value' => $parsed['genre'] ?? null];
            $filters[] = ['key' => 'format', 'type' => 'select', 'label' => 'Format', 'options' => ['paperback', 'hardcover', 'ebook'], 'value' => null];
            $filters[] = ['key' => 'price', 'type' => 'range', 'label' => 'Price', 'min' => 5, 'max' => 80, 'value' => $parsed['max_price'] ?? null];
        } elseif ($category === 'painting') {
            $filters[] = ['key' => 'style', 'type' => 'select', 'label' => 'Style', 'options' => ['vintage', 'modern', 'abstract', 'impressionist', 'minimalist'], 'value' => $parsed['style'] ?? null];
            $filters[] = ['key' => 'room', 'type' => 'select', 'label' => 'Room', 'options' => ['living room', 'bedroom', 'office', 'kitchen'], 'value' => $parsed['room'] ?? null];
            $filters[] = ['key' => 'color', 'type' => 'select', 'label' => 'Color', 'options' => ['blue', 'red', 'green', 'neutral', 'multicolor'], 'value' => $parsed['color'] ?? null];
            $filters[] = ['key' => 'price', 'type' => 'range', 'label' => 'Price', 'min' => 20, 'max' => 5000, 'value' => $parsed['max_price'] ?? null];
        } elseif ($category === 'electronics') {
            $filters[] = ['key' => 'product_type', 'type' => 'select', 'label' => 'Type', 'options' => ['laptop', 'phone', 'tablet', 'monitor'], 'value' => $parsed['product_type'] ?? null];
            $filters[] = ['key' => 'price', 'type' => 'range', 'label' => 'Price', 'min' => 200, 'max' => 5000, 'value' => $parsed['max_price'] ?? null];
            $filters[] = ['key' => 'condition', 'type' => 'select', 'label' => 'Condition', 'options' => ['new', 'used', 'refurbished'], 'value' => $parsed['condition'] ?? null];
        } elseif ($category === 'real_estate') {
            $filters[] = ['key' => 'min_sqm', 'type' => 'range', 'label' => 'Min area (m²)', 'min' => 40, 'max' => 300, 'value' => $parsed['min_sqm'] ?? null];
            $filters[] = ['key' => 'listing_type', 'type' => 'select', 'label' => 'Listing', 'options' => ['rent', 'sale'], 'value' => $parsed['listing_type'] ?? null];
            $filters[] = ['key' => 'price', 'type' => 'range', 'label' => 'Price (€)', 'min' => 200, 'max' => 500000, 'value' => null];
        } elseif ($category === 'fashion' || $category === 'luxury') {
            $filters[] = [
                'key' => 'size',
                'type' => 'number',
                'label' => $sq ? 'Numri (EU)' : 'Size (EU)',
                'min' => 35,
                'max' => 48,
                'step' => 0.5,
                'value' => $parsed['size'] ?? null,
            ];
            $filters[] = [
                'key' => 'brand',
                'type' => 'select',
                'label' => $sq ? 'Marka' : 'Brand',
                'options' => ['adidas', 'nike', 'puma', 'reebok', 'new balance', 'jordan'],
                'value' => isset($parsed['brand']) ? mb_strtolower($parsed['brand']) : null,
            ];
            $filters[] = [
                'key' => 'color',
                'type' => 'select',
                'label' => $sq ? 'Ngjyra' : 'Color',
                'options' => ['black', 'white', 'red', 'blue', 'grey', 'green'],
                'value' => $parsed['color'] ?? null,
            ];
            $filters[] = [
                'key' => 'product_type',
                'type' => 'select',
                'label' => $sq ? 'Lloji' : 'Type',
                'options' => ['sneakers', 'shoes', 'boots', 'trainers'],
                'value' => $parsed['product_type'] ?? null,
            ];
            $filters[] = ['key' => 'price', 'type' => 'range', 'label' => $sq ? 'Çmimi max (€)' : 'Max price (€)', 'min' => 10, 'max' => 500, 'value' => $parsed['max_price'] ?? null];
            $filters[] = ['key' => 'condition', 'type' => 'select', 'label' => $sq ? 'Gjendja' : 'Condition', 'options' => ['new', 'used'], 'value' => $parsed['condition'] ?? null];
        } else {
            $filters[] = ['key' => 'price', 'type' => 'range', 'label' => 'Price', 'min' => 10, 'max' => 10000, 'value' => $parsed['max_price'] ?? null];
            $filters[] = ['key' => 'condition', 'type' => 'select', 'label' => 'Condition', 'options' => ['new', 'used', 'vintage'], 'value' => null];
        }

        return $filters;
    }

    /**
     * @return array<int, string>
     */
    private function marketplacesForCategory(string $category, string $countryCode = 'XK'): array
    {
        if ($countryCode === 'CH' && $category === 'car') {
            return ['autoscout24', 'tutti', 'ricardo', 'facebook_marketplace', 'mobile.de', 'ebay'];
        }

        if ($countryCode === 'XK' && in_array($category, ['fashion', 'luxury', 'marketplace'], true)) {
            return ['driloni', 'ebay', 'google_shopping', 'etsy', 'facebook_marketplace'];
        }

        return match ($category) {
            'car' => ['mobile.de', 'autoscout24', 'facebook_marketplace'],
            'book' => ['amazon', 'ebay', 'google_shopping'],
            'painting', 'collectibles', 'gift', 'fashion', 'luxury' => ['etsy', 'ebay', 'facebook_marketplace', 'google_shopping'],
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
}
