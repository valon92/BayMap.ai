<?php

namespace App\Services\Search;

use App\Support\ShoeSize;
use App\Services\Ai\AiRequestParserService;
use App\Services\Ai\ProductVisionService;
use App\Services\Geo\GeoLocationService;
use App\Services\Geo\LocalLandmarkResolverService;
use App\Services\Marketplace\EbayOAuthService;
use App\Services\Marketplace\MarketplaceAggregator;
use App\Services\Marketplace\SerpApiShoppingService;

/**
 * Orchestrates: Vision/Text AI → local→regional→global internet search → rank.
 */
class SearchOrchestratorService
{
    public function __construct(
        private AiRequestParserService $parser,
        private ProductVisionService $vision,
        private GeoLocationService $geo,
        private LocalLandmarkResolverService $landmarks,
        private SearchExpansionService $expansion,
        private LocalSearchTierService $localTiers,
        private MarketplaceAggregator $aggregator,
        private ProductRankingService $ranking,
        private EbayOAuthService $ebayOAuth,
        private SerpApiShoppingService $serpApi,
        private QueryIntentEnricher $intentEnricher,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function search(
        string $query,
        array $filters = [],
        ?string $locale = null,
        ?string $imageBase64 = null,
        ?string $locationScope = 'auto',
    ): array {
        $started = microtime(true);
        $geo = $this->geo->resolve();
        $locale = $locale ?? $geo['locale'] ?? 'en';
        $locationScope = $this->normalizeLocationScope($locationScope);
        $visionAnalysis = null;
        $pipeline = [];

        // Step 1a: Vision AI (photo / upload)
        if ($imageBase64) {
            $visionAnalysis = $this->vision->analyze($imageBase64, $query ?: null, $geo, $locale);
            $pipeline[] = [
                'step' => 'vision_analyze',
                'status' => 'completed',
                'label' => 'AI analyzed your product photo',
            ];
            $query = trim($query.' '.$visionAnalysis['search_query'].' '.$visionAnalysis['description']);
        }

        // Step 1b: Text AI parser
        $parsed = $this->parser->parse(
            trim($query) ?: ($visionAnalysis['search_query'] ?? 'product'),
            $geo['country'] ?? null,
            $locale
        );
        if ($visionAnalysis) {
            $visionSize = ShoeSize::normalize($visionAnalysis['size'] ?? null)
                ?? ShoeSize::normalize($visionAnalysis['shoe_size'] ?? null)
                ?? ShoeSize::extractFromText($visionAnalysis['description'] ?? '');

            $parsed = array_merge($parsed, array_filter([
                'vision' => true,
                'description' => $visionAnalysis['description'] ?? null,
                'search_query' => $visionAnalysis['search_query'] ?? null,
                'brand' => $parsed['brand'] ?? $visionAnalysis['brand'] ?? null,
                'color' => $parsed['color'] ?? $visionAnalysis['color'] ?? null,
                'style' => $parsed['style'] ?? $visionAnalysis['style'] ?? null,
                'size' => $parsed['size'] ?? $visionSize,
                'product_type' => $parsed['product_type'] ?? $visionAnalysis['product_type'] ?? null,
                'category' => $visionAnalysis['category'] ?? $parsed['category'],
            ]));
            $parsed['raw_query'] = $visionAnalysis['search_query'] ?? $parsed['raw_query'];
        }
        $parsed = $this->intentEnricher->enrich($parsed, $query);
        $searchGeo = $this->intentEnricher->searchGeo($geo, $parsed);
        $parsed['country'] = $parsed['search_country'] ?? $parsed['country'] ?? $geo['country'];
        $parsed = $this->landmarks->enrich($parsed, $parsed['raw_query'] ?? $query, $searchGeo, $locale);
        $locationContext = $this->landmarks->locationContext($parsed);

        $pipeline[] = [
            'step' => 'ai_analyze',
            'status' => 'completed',
            'label' => 'AI understood product attributes',
        ];

        // Step 2: Location tiers — buyer's target country overrides visitor IP when specified
        $locationTiers = $this->localTiers->tiersForSearch($searchGeo, $parsed, $locationScope);
        $expanded = $this->expansion->expand($parsed, $searchGeo, $locale);
        $expanded['location_tiers'] = $locationTiers;
        $expanded['location_scope'] = $locationScope;
        $dynamicFilters = $this->expansion->buildDynamicFilters($parsed, $locale);

        // Step 3: Internet search (target country first, then broader)
        $search = $this->aggregator->searchAll($parsed, $expanded, $searchGeo);
        $products = $search['results'];
        $sourceReport = $search['report'];

        $pipeline[] = [
            'step' => 'internet_search',
            'status' => 'completed',
            'label' => 'Searched web: '.($parsed['search_country'] ?? $searchGeo['country'] ?? 'local').' → regional',
        ];

        $products = $this->applyClientFilters($products, $filters);
        $products = $this->ranking->rank($products, $parsed);

        $pipeline[] = [
            'step' => 'rank_results',
            'status' => 'completed',
            'label' => 'Ranked best matches',
        ];

        $processingMs = (int) round((microtime(true) - $started) * 1000);

        return [
            'query' => trim($query),
            'parsed' => $parsed,
            'location_context' => $locationContext,
            'vision' => $visionAnalysis,
            'expanded' => $expanded,
            'geo' => $geo,
            'locale' => in_array($locale, ['sq', 'en'], true) ? $locale : ($geo['locale'] === 'sq' ? 'sq' : 'en'),
            'filters' => $dynamicFilters,
            'results' => array_slice($products, 0, 24),
            'meta' => [
                'total' => count($products),
                'sources_queried' => $expanded['marketplaces'] ?? [],
                'source_report' => $sourceReport,
                'location_tiers' => $locationTiers,
                'location_scope' => $locationScope,
                'processing_ms' => $processingMs,
                'parser' => $parsed['parser'] ?? 'rules',
                'has_image' => (bool) $imageBase64,
                'internet_search' => [
                    'ebay_live' => $this->ebayOAuth->isConfigured(),
                    'google_shopping_live' => $this->serpApi->isConfigured(),
                    'live_sources' => count(array_filter($sourceReport, fn ($r) => ($r['mode'] ?? '') === 'live')),
                ],
            ],
            'pipeline' => $pipeline,
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
            if (isset($filters['country']) && $filters['country'] !== '') {
                $needle = mb_strtolower((string) $filters['country']);
                $loc = mb_strtolower($product['location'] ?? '');
                $matches = str_contains($loc, $needle);
                if (str_contains($needle, 'switzerland') || $needle === 'ch') {
                    $matches = $matches || (bool) preg_match('/switzerland|schweiz|zürich|zurich|bern|geneva|basel|lausanne/', $loc);
                }
                if (! $matches) {
                    return false;
                }
            }
            if (isset($filters['source']) && ($product['source_key'] ?? '') !== $filters['source']) {
                return false;
            }
            if (isset($filters['min_sqm']) && ($product['sqm'] ?? 0) < (int) $filters['min_sqm']) {
                return false;
            }
            if (isset($filters['size']) && $filters['size'] !== '' && ! ShoeSize::productHasSize($product, (string) $filters['size'])) {
                // Keep Kosovo local stores visible — buyer verifies sizes on the shop page
                if (($product['store'] ?? '') !== 'driloni') {
                    return false;
                }
            }
            if (isset($filters['brand']) && $filters['brand'] !== '') {
                $brand = mb_strtolower((string) $filters['brand']);
                $title = mb_strtolower($product['title'] ?? '');
                $tags = array_map('mb_strtolower', $product['tags'] ?? []);
                if (! str_contains($title, $brand) && ! in_array($brand, $tags, true)) {
                    return false;
                }
            }
            if (isset($filters['product_type']) && $filters['product_type'] !== '') {
                $type = mb_strtolower((string) $filters['product_type']);
                $title = mb_strtolower($product['title'] ?? '');
                if (! str_contains($title, $type)) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function normalizeLocationScope(?string $scope): string
    {
        $scope = strtolower((string) $scope);
        $allowed = ['auto', 'city', 'local', 'country', 'region', 'world', 'universal', 'global'];

        return in_array($scope, $allowed, true) ? $scope : 'auto';
    }
}
