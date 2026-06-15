<?php

namespace App\Services\Search;

use App\Services\Agents\AgentPoolRegistry;
use App\Support\CategoryCatalog;
use App\Support\CountryMatcher;
use App\Support\ShoeSize;
use App\Support\WebServicesIntentParser;

/**
 * Weighted ranking: 40% specification, 25% semantic, 15% location, 10% price, 10% trust.
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
        if (CategoryCatalog::normalize($parsed['category'] ?? '') === 'travel') {
            return min(99, max(40, (int) round($this->travelScore($product, $parsed))));
        }

        if (WebServicesIntentParser::isActive($parsed) || isset($product['provider_rank'])) {
            return min(99, max(40, (int) round($this->webServiceScore($product, $parsed))));
        }

        $weights = $this->agentPools->rankingWeights();

        $specification = $this->exactMatchScore($product, $parsed);
        $semantic = $this->semanticSimilarityScore($product, $parsed);
        $location = $this->locationScore($product, $parsed);
        $price = $this->priceScore($product, $parsed);
        $trust = $this->trustScore($product);

        $weighted = (
            ($specification * ($weights['specification_match'] ?? $weights['exact_match'] ?? 0.40)) +
            ($semantic * ($weights['semantic_similarity'] ?? 0.25)) +
            ($location * ($weights['location_relevance'] ?? $weights['location_proximity'] ?? 0.15)) +
            ($price * ($weights['price_relevance'] ?? 0.10)) +
            ($trust * ($weights['provider_trust'] ?? $weights['platform_trust'] ?? 0.10))
        );

        return min(99, max(35, (int) round($weighted)));
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsed
     */
    private function semanticSimilarityScore(array $product, array $parsed): float
    {
        $parts = array_filter([
            $parsed['brand'] ?? null,
            $parsed['model'] ?? null,
            $parsed['product_type'] ?? null,
            $parsed['color'] ?? null,
            ...($parsed['keywords'] ?? []),
        ], fn ($v) => is_string($v) && trim($v) !== '');

        if ($parts === []) {
            return 55.0;
        }

        $title = mb_strtolower((string) ($product['title'] ?? ''));
        $tags = mb_strtolower(implode(' ', array_map('strval', $product['tags'] ?? [])));
        $haystack = trim($title.' '.$tags);

        $tokens = array_values(array_unique(array_filter(
            preg_split('/\s+/u', mb_strtolower(implode(' ', $parts))) ?: [],
            fn ($t) => mb_strlen($t) > 2,
        )));

        if ($tokens === []) {
            return 55.0;
        }

        $hits = 0;
        foreach ($tokens as $token) {
            if (str_contains($haystack, $token)) {
                $hits++;
            }
        }

        return min(100.0, 35.0 + ($hits / count($tokens)) * 65.0);
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
     * @param  array<string, mixed>  $parsed
     */
    private function travelScore(array $product, array $parsed): float
    {
        $score = ! empty($product['price_on_request']) ? 52.0 : 78.0;

        if (($product['travel_mode'] ?? '') === 'flight' && (float) ($product['price'] ?? 0) > 0) {
            $score = 82.0;
            $dep = (string) ($product['departure_time'] ?? '');
            $from = (string) ($parsed['departure_time_from'] ?? '');
            $to = (string) ($parsed['departure_time_to'] ?? '');
            if ($dep !== '' && $from !== '' && $dep >= $from && ($to === '' || $dep <= $to)) {
                $score += 12.0;
            }
            if ((int) ($product['stops'] ?? 99) === 0) {
                $score += 6.0;
            }
        }

        if (($product['travel_mode'] ?? '') === 'train') {
            $score += 4.0;
        }

        if (! empty($product['live'])) {
            $score += 5.0;
        }

        return min(98.0, $score);
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsed
     */
    private function webServiceScore(array $product, array $parsed): float
    {
        $rank = (int) ($product['provider_rank'] ?? 50);
        $score = 100.0 - min(48.0, max(0, $rank - 1));

        if ((float) ($product['price'] ?? 0) > 0) {
            $score += 3.0;
        }

        if (! empty($product['live'])) {
            $score += 2.0;
        }

        $wantedType = (string) ($parsed['web_service_type'] ?? '');
        $productType = (string) ($product['web_service_type'] ?? $product['product_type'] ?? '');
        if ($wantedType !== '' && $wantedType !== 'combo' && $productType === $wantedType) {
            $score += 4.0;
        }

        return min(99.0, $score);
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
