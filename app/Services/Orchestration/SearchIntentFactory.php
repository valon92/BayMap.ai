<?php

namespace App\Services\Orchestration;

use App\Data\SearchIntent;
use App\Support\CategoryCatalog;

/**
 * Builds a structured Search Intent Object from AI-parsed query attributes.
 */
class SearchIntentFactory
{
    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $expanded
     * @param  array<string, mixed>  $geo
     */
    public static function fromParsed(array $parsed, array $expanded = [], array $geo = []): SearchIntent
    {
        $category = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');
        $rawQuery = trim((string) ($parsed['raw_query'] ?? $parsed['search_query'] ?? ''));

        $specifications = array_filter([
            'fuel' => $parsed['fuel'] ?? null,
            'transmission' => $parsed['transmission'] ?? null,
            'year' => $parsed['year'] ?? null,
            'year_min' => $parsed['year_min'] ?? null,
            'year_max' => $parsed['year_max'] ?? null,
            'mileage_max' => $parsed['mileage_max'] ?? $parsed['max_km'] ?? null,
            'engine_liters' => $parsed['engine_liters'] ?? null,
            'ram' => $parsed['ram'] ?? null,
            'storage' => $parsed['storage'] ?? null,
            'bedrooms' => $parsed['bedrooms'] ?? null,
            'min_sqm' => $parsed['min_sqm'] ?? null,
            'property_type' => $parsed['property_type'] ?? null,
            'features' => $parsed['features'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $location = [
            'country_code' => $parsed['search_country_code'] ?? $geo['country_code'] ?? null,
            'country' => $parsed['search_country'] ?? $parsed['country'] ?? $geo['country'] ?? null,
            'city' => $parsed['search_city'] ?? $parsed['city'] ?? $geo['city'] ?? null,
            'scope' => $parsed['search_scope'] ?? 'targeted',
            'source' => $parsed['location_source'] ?? null,
        ];

        $optional = array_filter([
            'gender' => $parsed['gender'] ?? null,
            'style' => $parsed['style'] ?? null,
            'vision' => $parsed['vision'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        return new SearchIntent(
            rawQuery: $rawQuery,
            category: $category,
            subcategory: self::subcategory($parsed, $category),
            productType: isset($parsed['product_type']) ? (string) $parsed['product_type'] : null,
            brand: isset($parsed['brand']) ? (string) $parsed['brand'] : null,
            model: isset($parsed['model']) ? (string) $parsed['model'] : null,
            specifications: $specifications,
            color: isset($parsed['color']) ? (string) $parsed['color'] : null,
            size: isset($parsed['size']) ? (string) $parsed['size'] : null,
            maxPrice: isset($parsed['max_price']) ? (float) $parsed['max_price'] : null,
            minPrice: isset($parsed['min_price']) ? (float) $parsed['min_price'] : null,
            currency: isset($parsed['currency']) ? (string) $parsed['currency'] : null,
            condition: isset($parsed['condition']) ? (string) $parsed['condition'] : null,
            location: $location,
            keywords: self::keywords($parsed),
            optionalRequirements: $optional,
            searchScope: (string) ($parsed['search_scope'] ?? 'targeted'),
            searchTarget: ! empty($parsed['search_target']),
            parsed: $parsed,
        );
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private static function subcategory(array $parsed, string $category): ?string
    {
        if (CategoryCatalog::isAutomotive($category)) {
            return 'vehicle';
        }

        if (CategoryCatalog::isElectronics($category)) {
            return isset($parsed['product_type']) ? (string) $parsed['product_type'] : 'electronics';
        }

        if ($category === 'real_estate') {
            return isset($parsed['property_type']) ? (string) $parsed['property_type'] : 'property';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    private static function keywords(array $parsed): array
    {
        $keywords = $parsed['keywords'] ?? [];
        if (! is_array($keywords)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($k) => mb_strtolower(trim((string) $k)),
            $keywords,
        ), fn ($k) => strlen($k) > 2)));
    }
}
