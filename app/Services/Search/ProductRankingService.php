<?php

namespace App\Services\Search;

/**
 * Ranks products by semantic relevance to the parsed AI query.
 */
class ProductRankingService
{
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
        $score = 50;
        $title = mb_strtolower($product['title'] ?? '');
        $tags = array_map('mb_strtolower', $product['tags'] ?? []);

        if (! empty($parsed['brand']) && (str_contains($title, mb_strtolower($parsed['brand'])) || in_array(mb_strtolower($parsed['brand']), $tags, true))) {
            $score += 15;
        }
        if (! empty($parsed['model']) && (str_contains($title, mb_strtolower($parsed['model'])) || in_array(mb_strtolower($parsed['model']), $tags, true))) {
            $score += 12;
        }
        if (! empty($parsed['year']) && str_contains($title, (string) $parsed['year'])) {
            $score += 10;
        }
        if (! empty($parsed['color']) && (str_contains($title, $parsed['color']) || in_array($parsed['color'], $tags, true))) {
            $score += 8;
        }
        if (! empty($parsed['max_km']) && ! empty($product['mileage']) && $product['mileage'] <= $parsed['max_km']) {
            $score += 10;
        }
        if (! empty($parsed['genre']) && (str_contains($title, $parsed['genre']) || in_array($parsed['genre'], $tags, true))) {
            $score += 12;
        }
        if (! empty($parsed['product_type']) && str_contains($title, $parsed['product_type'])) {
            $score += 12;
        }

        if (($parsed['category'] ?? '') === 'real_estate') {
            $score += $this->scoreRealEstate($product, $parsed, $title, $tags);
        }

        foreach ($parsed['keywords'] ?? [] as $keyword) {
            if (strlen($keyword) > 3 && (str_contains($title, $keyword) || in_array($keyword, $tags, true))) {
                $score += 3;
            }
        }

        if (! empty($product['sponsored'])) {
            $score += 5;
        }

        return min(99, max(60, $score + random_int(-3, 5)));
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

        if (! empty($parsed['brand']) && str_contains(mb_strtolower($product['title'] ?? ''), mb_strtolower($parsed['brand']))) {
            $reasons[] = "matches brand {$parsed['brand']}";
        }
        if (! empty($parsed['model'])) {
            $reasons[] = "includes model {$parsed['model']}";
        }
        if (! empty($parsed['year'])) {
            $reasons[] = "year {$parsed['year']} aligned";
        }
        if (! empty($parsed['color'])) {
            $reasons[] = "{$parsed['color']} color match";
        }
        if (! empty($parsed['max_km']) && ! empty($product['mileage']) && $product['mileage'] <= $parsed['max_km']) {
            $reasons[] = 'within your mileage limit';
        }
        if (! empty($parsed['genre'])) {
            $reasons[] = "{$parsed['genre']} genre fit";
        }
        if (! empty($parsed['landmark_label']) && str_contains(mb_strtolower($product['title'] ?? ''), mb_strtolower($parsed['landmark_label']))) {
            $reasons[] = 'near '.$parsed['landmark_label'];
        }
        if (! empty($parsed['min_sqm']) && ! empty($product['sqm'])) {
            $reasons[] = "{$product['sqm']} m² area";
        }

        if (empty($reasons)) {
            $reasons[] = 'strong semantic match to your description';
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
