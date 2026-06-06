<?php

namespace App\Services\Search;

use App\Support\CategoryCatalog;
use App\Support\CountryMatcher;
use App\Support\ShoeSize;

/**
 * Ranks products by exact intent match and semantic relevance (AI meta search).
 */
class ProductRankingService
{
    public function __construct(
        private ExactMatchScoringService $exactMatch,
        private WeightedRankingEngine $weightedRanking,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<string, mixed>  $parsed
     * @return array<int, array<string, mixed>>
     */
    public function rank(array $products, array $parsed): array
    {
        foreach ($products as &$product) {
            $product['match_score'] = $this->calculateScore($product, $parsed);
            $product['ai_explanation'] = $this->buildExplanation($product, $parsed, $product['match_score']);
        }
        unset($product);

        usort($products, fn ($a, $b) => ($b['match_score'] ?? 0) <=> ($a['match_score'] ?? 0));

        return $products;
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsed
     */
    private function calculateScore(array $product, array $parsed): int
    {
        return $this->weightedRanking->score($product, $parsed);
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsed
     * @param  array<int, string>  $tags
     */
    private function scoreBrand(array $product, array $parsed, string $title, array $tags): int
    {
        if (empty($parsed['brand'])) {
            return 0;
        }

        $brand = mb_strtolower((string) $parsed['brand']);
        $needles = match (true) {
            str_contains($brand, 'mercedes') => ['mercedes', 'mercedes-benz', 'benz'],
            str_contains($brand, 'volkswagen') => ['volkswagen', 'vw'],
            default => [mb_strtolower((string) $parsed['brand'])],
        };

        foreach ($needles as $needle) {
            if (str_contains($title, $needle) || in_array($needle, $tags, true)) {
                return 14;
            }
        }

        return CategoryCatalog::isAutomotive($parsed['category'] ?? '') ? -35 : -12;
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsed
     * @param  array<int, string>  $tags
     */
    private function scoreModel(array $product, array $parsed, string $title, array $tags): int
    {
        if (empty($parsed['model']) || ! CategoryCatalog::isAutomotive($parsed['category'] ?? '')) {
            if (! empty($parsed['model']) && (str_contains($title, mb_strtolower($parsed['model'])) || in_array(mb_strtolower($parsed['model']), $tags, true))) {
                return 12;
            }

            return 0;
        }

        $wanted = mb_strtolower(str_replace(' ', '', $parsed['model']));
        $normalizedTitle = str_replace(' ', '', $title);

        if (str_contains($normalizedTitle, $wanted) || in_array($wanted, $tags, true)) {
            return 28;
        }

        if (preg_match('/\b(gle|glc|gla|gls|glb|q\d|x\d|a\d)\b/i', $title, $found)) {
            $foundModel = mb_strtolower($found[1]);
            if ($foundModel !== $wanted && ! str_contains($wanted, $foundModel)) {
                return -30;
            }
        }

        return -8;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function scoreYear(array $parsed, array $product, string $title): int
    {
        $minYear = ! empty($parsed['year_min']) ? (int) $parsed['year_min'] : null;
        $maxYear = ! empty($parsed['year_max']) ? (int) $parsed['year_max'] : null;

        if ($minYear === null && $maxYear === null && empty($parsed['year'])) {
            return 0;
        }

        if ($minYear === null && $maxYear === null) {
            $minYear = (int) $parsed['year'];
            $maxYear = $minYear;
        } elseif ($minYear === null) {
            $minYear = $maxYear;
        } elseif ($maxYear === null) {
            $maxYear = $minYear;
        }

        if (! empty($product['year'])) {
            $productYear = (int) $product['year'];
            if ($productYear >= $minYear && $productYear <= $maxYear) {
                return 14;
            }
            $diff = min(abs($productYear - $minYear), abs($productYear - $maxYear));

            return $diff <= 1 ? 4 : -12;
        }

        foreach (range($minYear, $maxYear) as $year) {
            if (str_contains($title, (string) $year)) {
                return 10;
            }
        }

        return -5;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function scoreTargetCountry(array $parsed, string $location): int
    {
        if (empty($parsed['search_country'])) {
            return 0;
        }

        $target = mb_strtolower($parsed['search_country']);
        if (str_contains($location, $target)) {
            return 22;
        }

        if (! empty($parsed['search_country_code']) && strtoupper($parsed['search_country_code']) === 'CH') {
            if (str_contains($location, 'switzerland') || str_contains($location, 'schweiz') || str_contains($location, 'zürich') || str_contains($location, 'zurich') || str_contains($location, 'bern') || str_contains($location, 'geneva')) {
                return 22;
            }
        }

        if (! empty($parsed['search_target']) && ($parsed['location_source'] ?? '') === 'query') {
            return -24;
        }

        return -10;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $product
     */
    private function scorePrice(array $parsed, array $product): int
    {
        if (empty($parsed['max_price'])) {
            return 0;
        }

        $limit = (float) $parsed['max_price'];
        $price = (float) ($product['price'] ?? 0);
        if ($price <= 0) {
            return 0;
        }

        if ($price <= $limit) {
            return 12;
        }

        if ($price <= $limit * 1.08) {
            return 4;
        }

        return -25;
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsed
     * @param  array<int, string>  $tags
     */
    private function scoreRealEstate(array $product, array $parsed, string $title, array $tags): int
    {
        $bonus = 0;

        if (! empty($parsed['city']) && (str_contains($title, mb_strtolower($parsed['city'])) || in_array(mb_strtolower($parsed['city']), $tags, true))) {
            $bonus += 12;
        }

        if (! empty($parsed['landmark_label']) && str_contains($title, mb_strtolower($parsed['landmark_label']))) {
            $bonus += 15;
        }
        if (! empty($parsed['landmark']) && (str_contains($title, $parsed['landmark']) || str_contains($title, 'gjykat'))) {
            $bonus += 10;
        }

        foreach ($parsed['nearby_streets'] ?? [] as $street) {
            $streetLower = mb_strtolower($street);
            if (str_contains($title, $streetLower) || in_array($streetLower, $tags, true)) {
                $bonus += 8;
                break;
            }
        }

        if (! empty($parsed['min_sqm'])) {
            $target = (int) $parsed['min_sqm'];
            $productSqm = (int) ($product['sqm'] ?? 0);
            if ($productSqm > 0 && abs($productSqm - $target) <= 15) {
                $bonus += 14;
            } elseif ($productSqm > 0 && abs($productSqm - $target) <= 30) {
                $bonus += 8;
            }
            if (str_contains($title, (string) $target)) {
                $bonus += 6;
            }
        }

        if (! empty($parsed['property_type']) && (str_contains($title, 'banes') || str_contains($title, 'apartament') || str_contains($title, 'apartment'))) {
            $bonus += 6;
        }

        return $bonus;
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsed
     */
    private function buildExplanation(array $product, array $parsed, int $score): string
    {
        $reasons = [];
        $title = mb_strtolower($product['title'] ?? '');
        $tags = array_map('mb_strtolower', $product['tags'] ?? []);

        if (! empty($parsed['brand']) && str_contains($title, mb_strtolower($parsed['brand']))) {
            $reasons[] = "matches brand {$parsed['brand']}";
        }

        if (! empty($parsed['model'])) {
            $wanted = mb_strtolower($parsed['model']);
            if (str_contains(str_replace(' ', '', $title), str_replace(' ', '', $wanted)) || in_array($wanted, $tags, true)) {
                $reasons[] = "matches model {$parsed['model']}";
            } else {
                $reasons[] = "similar {$parsed['brand']} listing (check model)";
            }
        }

        if (! empty($parsed['year'])) {
            if (! empty($product['year']) && (int) $product['year'] === (int) $parsed['year']) {
                $reasons[] = "year {$parsed['year']}";
            } elseif (str_contains($title, (string) $parsed['year'])) {
                $reasons[] = "year {$parsed['year']} in listing";
            }
        }

        if (! empty($parsed['search_country']) && CountryMatcher::locationMatchesFilter(
            (string) ($product['location'] ?? ''),
            (string) $parsed['search_country'],
            isset($product['country_code']) ? (string) $product['country_code'] : null,
        )) {
            $reasons[] = 'in '.$parsed['search_country'];
        }

        if (! empty($parsed['max_price']) && ! empty($product['price']) && (float) $product['price'] <= (float) $parsed['max_price']) {
            $cur = $product['currency'] ?? $parsed['currency'] ?? 'EUR';
            $reasons[] = 'within budget ('.number_format((float) $product['price'], 0).' '.$cur.')';
        }

        if (! empty($product['offer_count']) && (int) $product['offer_count'] > 1) {
            $reasons[] = 'compared across '.(int) $product['offer_count'].' marketplaces';
            if (! empty($product['price_spread_eur']) && (float) $product['price_spread_eur'] > 0) {
                $reasons[] = 'save up to €'.number_format((float) $product['price_spread_eur'], 0).' vs other sellers';
            }
        }

        if (! empty($parsed['color'])) {
            $reasons[] = "{$parsed['color']} color match";
        }

        if (empty($reasons)) {
            $reasons[] = 'semantic match to your search';
        }

        $source = $product['source'] ?? 'marketplace';

        return sprintf(
            '%d%% match — %s. Listed on %s.',
            $score,
            implode(', ', $reasons),
            $source
        );
    }
}
