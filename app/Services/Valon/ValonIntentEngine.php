<?php

namespace App\Services\Valon;

use App\Support\CategoryCatalog;

/**
 * Intent analysis for Valon AI orchestrator — structures query into execution-ready intent.
 */
class ValonIntentEngine
{
    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $expanded
     * @param  array<string, mixed>  $geo
     * @return array<string, mixed>
     */
    public function analyze(array $parsed, array $expanded, array $geo = []): array
    {
        $category = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');

        return [
            'orchestrator' => config('valon.orchestrator_name', 'Valon AI'),
            'category' => $category,
            'attributes' => $this->extractAttributes($parsed, $category),
            'price_range' => $this->extractPriceRange($parsed),
            'location_priority' => $this->extractLocationPriority($parsed, $expanded, $geo),
            'keywords' => $this->extractKeywords($parsed),
            'search_query' => $parsed['raw_query'] ?? $parsed['search_query'] ?? '',
            'parsed' => $parsed,
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function extractAttributes(array $parsed, string $category): array
    {
        $common = array_filter([
            'brand' => $parsed['brand'] ?? null,
            'model' => $parsed['model'] ?? null,
            'color' => $parsed['color'] ?? null,
            'size' => $parsed['size'] ?? null,
            'gender' => $parsed['gender'] ?? null,
            'product_type' => $parsed['product_type'] ?? null,
            'condition' => $parsed['condition'] ?? null,
            'fuel' => $parsed['fuel'] ?? null,
            'year_min' => $parsed['year_min'] ?? $parsed['year'] ?? null,
            'year_max' => $parsed['year_max'] ?? $parsed['year'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($category === 'real_estate') {
            $common = array_merge($common, array_filter([
                'city' => $parsed['city'] ?? null,
                'bedrooms' => $parsed['bedrooms'] ?? null,
                'min_sqm' => $parsed['min_sqm'] ?? null,
                'property_type' => $parsed['property_type'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''));
        }

        return $common;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function extractPriceRange(array $parsed): array
    {
        return array_filter([
            'max' => $parsed['max_price'] ?? null,
            'min' => $parsed['min_price'] ?? null,
            'currency' => $parsed['currency'] ?? 'EUR',
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $expanded
     * @param  array<string, mixed>  $geo
     * @return array<int, array<string, mixed>>
     */
    private function extractLocationPriority(array $parsed, array $expanded, array $geo): array
    {
        $tiers = $expanded['location_tiers'] ?? [];
        if ($tiers !== []) {
            return $tiers;
        }

        $city = $geo['city'] ?? null;
        $country = $parsed['search_country'] ?? $geo['country'] ?? 'Kosovo';

        $priority = [];
        if ($city) {
            $priority[] = ['level' => 'city', 'label' => $city, 'suffix' => $city];
        }
        $priority[] = ['level' => 'country', 'label' => $country, 'suffix' => $country];
        $priority[] = ['level' => 'international', 'label' => 'Global', 'suffix' => ''];

        return $priority;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    private function extractKeywords(array $parsed): array
    {
        $keywords = $parsed['keywords'] ?? [];
        if (! is_array($keywords)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($k) => mb_strtolower(trim((string) $k)),
            $keywords
        ), fn ($k) => strlen($k) > 2)));
    }
}
