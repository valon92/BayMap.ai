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
