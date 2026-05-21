<?php

namespace App\Services\Marketplace;

/**
 * Aggregates results from multiple mock (or future real) marketplace providers.
 */
class MarketplaceAggregator
{
    /** @var array<int, string> */
    private array $sources = [
        'mobile.de',
        'autoscout24',
        'ebay',
        'etsy',
        'amazon',
        'google_shopping',
        'facebook_marketplace',
    ];

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @return array<int, array<string, mixed>>
     */
    public function searchAll(array $parsedQuery, array $expandedFilters): array
    {
        $results = [];
        $targetMarketplaces = $expandedFilters['marketplaces'] ?? $this->sources;

        foreach ($this->sources as $source) {
            if (! $this->shouldQuerySource($source, $targetMarketplaces, $parsedQuery['category'] ?? '')) {
                continue;
            }

            $provider = new MockMarketplaceService($source);
            $items = $provider->search($parsedQuery, $expandedFilters);
            $results = array_merge($results, $items);
        }

        // Deduplicate by id when multiple sources return same dataset
        $seen = [];
        $results = array_values(array_filter($results, function ($item) use (&$seen) {
            $key = ($item['id'] ?? '') . '-' . ($item['source_key'] ?? '');
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        }));

        return $results;
    }

    /**
     * @param  array<int, string>  $targetMarketplaces
     */
    private function shouldQuerySource(string $source, array $targetMarketplaces, string $category): bool
    {
        if (empty($targetMarketplaces)) {
            return true;
        }

        $sourceNorm = strtolower(str_replace(['.', '_'], '', $source));

        foreach ($targetMarketplaces as $target) {
            $targetNorm = strtolower(str_replace(['.', '_', ' '], '', $target));
            if (str_contains($sourceNorm, $targetNorm) || str_contains($targetNorm, $sourceNorm)) {
                return true;
            }
        }

        // Always include at least primary sources per category for demo richness
        $fallback = match ($category) {
            'car' => in_array($source, ['mobile.de', 'autoscout24', 'ebay'], true),
            'book', 'electronics' => in_array($source, ['amazon', 'ebay', 'google_shopping'], true),
            default => in_array($source, ['ebay', 'etsy', 'amazon'], true),
        };

        return $fallback;
    }
}
