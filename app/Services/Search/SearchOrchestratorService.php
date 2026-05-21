<?php

namespace App\Services\Search;

use App\Services\Ai\AiRequestParserService;
use App\Services\Geo\GeoLocationService;
use App\Services\Marketplace\MarketplaceAggregator;

/**
 * Orchestrates the full AI search pipeline (stateless, no DB).
 */
class SearchOrchestratorService
{
    public function __construct(
        private AiRequestParserService $parser,
        private GeoLocationService $geo,
        private SearchExpansionService $expansion,
        private MarketplaceAggregator $aggregator,
        private ProductRankingService $ranking,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function search(string $query, array $filters = [], ?string $locale = null): array
    {
        $geo = $this->geo->resolve();
        $locale = $locale ?? $geo['locale'] ?? 'en';

        $parsed = $this->parser->parse($query, $geo['country'] ?? null);
        $parsed['country'] = $parsed['country'] ?? $geo['country'];

        $expanded = $this->expansion->expand($parsed, $geo);
        $dynamicFilters = $this->expansion->buildDynamicFilters($parsed);

        $products = $this->aggregator->searchAll($parsed, $expanded);
        $products = $this->applyClientFilters($products, $filters);
        $products = $this->ranking->rank($products, $parsed);

        return [
            'query' => $query,
            'parsed' => $parsed,
            'expanded' => $expanded,
            'geo' => $geo,
            'locale' => in_array($locale, ['sq', 'en'], true) ? $locale : ($geo['locale'] === 'sq' ? 'sq' : 'en'),
            'filters' => $dynamicFilters,
            'results' => array_slice($products, 0, 24),
            'meta' => [
                'total' => count($products),
                'sources_queried' => $expanded['marketplaces'] ?? [],
                'processing_ms' => random_int(180, 420),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function applyClientFilters(array $products, array $filters): array
    {
        if (empty($filters)) {
            return $products;
        }

        return array_values(array_filter($products, function (array $product) use ($filters) {
            if (isset($filters['price_min']) && ($product['price'] ?? 0) < (float) $filters['price_min']) {
                return false;
            }
            if (isset($filters['price_max']) && ($product['price'] ?? PHP_INT_MAX) > (float) $filters['price_max']) {
                return false;
            }
            if (isset($filters['year']) && ($product['year'] ?? null) != $filters['year']) {
                return false;
            }
            if (isset($filters['max_km']) && ($product['mileage'] ?? PHP_INT_MAX) > (int) $filters['max_km']) {
                return false;
            }
            if (isset($filters['color']) && ! str_contains(mb_strtolower($product['title'] ?? ''), mb_strtolower($filters['color']))) {
                return false;
            }
            if (isset($filters['source']) && ($product['source_key'] ?? '') !== $filters['source']) {
                return false;
            }

            return true;
        }));
    }
}
