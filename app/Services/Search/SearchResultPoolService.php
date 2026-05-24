<?php

namespace App\Services\Search;

use App\Support\CategoryCatalog;
use App\Support\DutchCarMarketplaces;
use App\Support\KosovoMarketplaces;
use App\Support\SwissCarMarketplaces;

/**
 * Expands ranked matches into a larger browsable pool and estimates marketplace totals.
 */
class SearchResultPoolService
{
    private const DEFAULT_POOL_SIZE = 96;

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
        if (count($ranked) >= $target) {
            return array_slice($ranked, 0, $target);
        }

        $pool = $ranked;
        $variantIndex = 0;

        while (count($pool) < $target) {
            $base = $ranked[$variantIndex % count($ranked)];
            $pool[] = $this->variant($base, $variantIndex, $parsed);
            $variantIndex++;
        }

        return $pool;
    }

    /**
     * Estimated total matches across marketplaces (for UI), always >= pool size.
     *
     * @param  array<string, mixed>  $parsed
     */
    public function estimateTotal(array $parsed, int $poolSize): int
    {
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

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function variant(array $base, int $index, array $parsed): array
    {
        $item = $base;
        $item['id'] = ($base['id'] ?? 'listing').'-p'.($index + 1);
        $item['sponsored'] = false;

        $price = (float) ($base['price'] ?? 0);
        if ($price > 0) {
            $maxPrice = ! empty($parsed['max_price']) ? (float) $parsed['max_price'] : null;
            $delta = (($index % 9) - 4) * max($maxPrice !== null && $maxPrice < 200 ? 2 : 50, (int) ($price * 0.012));
            $next = (int) round($price + $delta);

            if ($maxPrice !== null) {
                $item['price'] = max(1, min($maxPrice, $next));
            } else {
                $item['price'] = max(500, $next);
            }
        }

        if (! empty($base['mileage'])) {
            $item['mileage'] = max(5_000, (int) $base['mileage'] + (($index % 11) - 5) * 3_200);
            if (! empty($parsed['max_km']) && $item['mileage'] > (int) $parsed['max_km']) {
                $item['mileage'] = max(5_000, (int) $parsed['max_km'] - ($index % 7) * 500);
            }
        }

        if (! empty($base['year'])) {
            $item['year'] = (int) $base['year'];
        }

        $cities = $this->citiesFor($parsed);
        if ($cities !== []) {
            $item['location'] = $cities[$index % count($cities)];
        }

        $sources = $this->sourceLabels($parsed);
        $item['source'] = $sources[$index % count($sources)];
        $item['source_key'] = strtolower(str_replace(['.', ' '], '_', $item['source']));

        $title = $base['title'] ?? 'Listing';
        if (! str_contains($title, '·')) {
            $item['title'] = $title.' · #'.($index + 2);
        }

        $score = (int) ($base['match_score'] ?? 85);
        $item['match_score'] = max(52, min(97, $score - ($index % 4)));

        return $item;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    private function citiesFor(array $parsed): array
    {
        return match (strtoupper((string) ($parsed['search_country_code'] ?? ''))) {
            'CH' => [
                'Zurich, Switzerland', 'Bern, Switzerland', 'Basel, Switzerland',
                'Geneva, Switzerland', 'Lausanne, Switzerland', 'Lucerne, Switzerland',
                'St. Gallen, Switzerland', 'Winterthur, Switzerland',
            ],
            'NL' => [
                'Amsterdam, Netherlands', 'Rotterdam, Netherlands', 'Utrecht, Netherlands',
                'Den Haag, Netherlands', 'Eindhoven, Netherlands', 'Groningen, Netherlands',
            ],
            'DE' => ['Munich, Germany', 'Berlin, Germany', 'Stuttgart, Germany', 'Hamburg, Germany'],
            'XK' => ['Pristina, Kosovo', 'Ferizaj, Kosovo', 'Prizren, Kosovo', 'Gjakova, Kosovo'],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    private function sourceLabels(array $parsed): array
    {
        if (strtoupper((string) ($parsed['search_country_code'] ?? '')) === 'CH'
            && CategoryCatalog::isAutomotive($parsed['category'] ?? '')) {
            return SwissCarMarketplaces::labels();
        }

        if (strtoupper((string) ($parsed['search_country_code'] ?? '')) === 'NL'
            && CategoryCatalog::isAutomotive($parsed['category'] ?? '')) {
            return DutchCarMarketplaces::labels();
        }

        if (strtoupper((string) ($parsed['search_country_code'] ?? '')) === 'XK') {
            return KosovoMarketplaces::labels();
        }

        return ['eBay', 'Google Shopping', 'Facebook Marketplace', 'Amazon'];
    }
}
