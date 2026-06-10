<?php

namespace App\Services\Search;

use App\Services\Agents\AgentPoolRegistry;
use App\Support\CategoryCatalog;
use App\Support\CountryMatcher;
use App\Support\ShoeSize;

/**
 * Weighted ranking: 40% exact match, 25% price, 15% location, 10% availability, 10% trust.
 */
class WeightedRankingEngine
{
    public function __construct(
        private ExactMatchScoringService $exactMatch,
        private AgentPoolRegistry $agentPools,
    ) {}

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsed
     */
    public function score(array $product, array $parsed): int
    {
        $weights = $this->agentPools->rankingWeights();

        $exact = $this->exactMatchScore($product, $parsed);
        $price = $this->priceScore($product, $parsed);
        $location = $this->locationScore($product, $parsed);
        $availability = $this->availabilityScore($product);
        $trust = $this->trustScore($product);

        $weighted = (
            ($exact * ($weights['exact_match'] ?? 0.40)) +
            ($price * ($weights['price_relevance'] ?? 0.25)) +
            ($location * ($weights['location_proximity'] ?? 0.15)) +
            ($availability * ($weights['availability'] ?? 0.10)) +
            ($trust * ($weights['platform_trust'] ?? 0.10))
        );

        return min(99, max(35, (int) round($weighted)));
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsed
     */
    private function exactMatchScore(array $product, array $parsed): float
    {
        $base = 50.0;
        $bonus = (float) $this->exactMatch->exactMatchBonus($product, $parsed);
        $title = mb_strtolower($product['title'] ?? '');
        $tags = array_map('mb_strtolower', $product['tags'] ?? []);

        if (! empty($parsed['brand'])) {
            $brand = mb_strtolower((string) $parsed['brand']);
            if (str_contains($title, $brand) || in_array($brand, $tags, true)) {
                $base += 12;
            } elseif (CategoryCatalog::isAutomotive($parsed['category'] ?? '')) {
                $base -= 20;
            }
        }

        if (! empty($parsed['model'])) {
            $model = mb_strtolower(str_replace(' ', '', (string) $parsed['model']));
            if (str_contains(str_replace(' ', '', $title), $model) || in_array($model, $tags, true)) {
                $base += 18;
            }
        }

        if (! empty($parsed['size']) && ShoeSize::productHasSize($product, (string) $parsed['size'])) {
            $base += 15;
        }

        foreach ($parsed['keywords'] ?? [] as $keyword) {
            if (strlen($keyword) > 3 && (str_contains($title, $keyword) || in_array($keyword, $tags, true))) {
                $base += 2;
            }
        }

        return min(100.0, max(0.0, $base + $bonus));
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsed
     */
    private function priceScore(array $product, array $parsed): float
    {
        if (empty($parsed['max_price'])) {
            $price = (float) ($product['price_eur'] ?? $product['price'] ?? 0);

            return $price > 0 ? 65.0 : 50.0;
        }

        $limit = (float) $parsed['max_price'];
        $price = (float) ($product['price_eur'] ?? $product['price'] ?? 0);
        if ($price <= 0) {
            return 45.0;
        }

        if ($price <= $limit) {
            $ratio = $price / max(1.0, $limit);

            return 85.0 + (1.0 - $ratio) * 15.0;
        }

        if ($price <= $limit * 1.1) {
            return 55.0;
        }

        return 20.0;
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsed
     */
    private function locationScore(array $product, array $parsed): float
    {
        $location = mb_strtolower($product['location'] ?? '');
        $bonus = (float) $this->exactMatch->locationPriorityBonus($product, $parsed);

        $score = 50.0 + min(30.0, $bonus);

        if (! empty($parsed['search_country']) && CountryMatcher::locationMatchesFilter(
            (string) ($product['location'] ?? ''),
            (string) $parsed['search_country'],
            isset($product['country_code']) ? (string) $product['country_code'] : null,
        )) {
            $score = 95.0;
        } elseif (! empty($parsed['search_countries']) && is_array($parsed['search_countries'])) {
            foreach ($parsed['search_countries'] as $country) {
                if (CountryMatcher::locationMatchesFilter(
                    (string) ($product['location'] ?? ''),
                    (string) ($country['search_country'] ?? ''),
                    isset($product['country_code']) ? (string) $product['country_code'] : null,
                )) {
                    $score = 95.0;
                    break;
                }
            }
        }

        if (! empty($parsed['city']) && str_contains($location, mb_strtolower($parsed['city']))) {
            $score = min(100.0, $score + 10.0);
        }

        return min(100.0, max(0.0, $score));
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function availabilityScore(array $product): float
    {
        $score = 60.0;

        if (! empty($product['live'])) {
            $score += 25.0;
        }

        if (! empty($product['offer_count']) && (int) $product['offer_count'] > 1) {
            $score += min(15.0, (int) $product['offer_count'] * 2);
        }

        if (($product['condition'] ?? 'used') === 'new') {
            $score += 5.0;
        }

        if (! empty($product['url']) && $product['url'] !== '#') {
            $score += 5.0;
        }

        return min(100.0, $score);
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function trustScore(array $product): float
    {
        $key = (string) ($product['source_key'] ?? 'default');

        return (float) $this->agentPools->trustScore($key);
    }
}
