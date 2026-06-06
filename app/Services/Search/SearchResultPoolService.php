<?php

namespace App\Services\Search;

use App\Support\CategoryCatalog;

/**
 * Caps ranked federated matches for pagination and estimates marketplace totals.
 */
class SearchResultPoolService
{

    /**
     * @param  array<int, array<string, mixed>>  $ranked
     * @param  array<string, mixed>  $parsed
     * @return array<int, array<string, mixed>>
     */
    public function expand(array $ranked, array $parsed): array
    {
        if ($ranked === []) {
            return [];
        }

        $target = $this->poolSize($parsed);

        // Only return real federated matches — never fabricate duplicate listings.
        return array_slice($ranked, 0, $target);
    }

    /**
     * Estimated total matches across marketplaces (for UI), always >= pool size.
     *
     * @param  array<string, mixed>  $parsed
     */
    public function estimateTotal(array $parsed, int $poolSize): int
    {
        if ($poolSize === 0) {
            return 0;
        }

        // With few real matches, show the actual pool size (no inflated marketplace estimate).
        if ($poolSize < 24) {
            return $poolSize;
        }

        $category = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');

        $base = match ($category) {
            'automotive' => 14_000,
            'fashion', 'sports_outdoor', 'luxury_collectibles' => 5_500,
            'real_estate' => 2_800,
            'electronics_tech', 'gaming_entertainment', 'home_appliances' => 6_200,
            default => 3_400,
        };

        if (! empty($parsed['search_country_code'])) {
            $base = (int) round($base * 1.35);
        }
        if (! empty($parsed['model'])) {
            $base = (int) round($base * 0.72);
        }
        if (! empty($parsed['year'])) {
            $base = (int) round($base * 0.85);
        }

        $jitter = crc32(json_encode([
            $parsed['raw_query'] ?? '',
            $parsed['brand'] ?? '',
            $parsed['model'] ?? '',
            $parsed['search_country_code'] ?? '',
        ])) % 4_500;

        return max($poolSize, $base + $jitter);
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function poolSize(array $parsed): int
    {
        return match (CategoryCatalog::normalize($parsed['category'] ?? 'marketplace')) {
            'automotive' => 120,
            'fashion', 'sports_outdoor', 'luxury_collectibles' => 72,
            default => 48,
        };
    }
}
