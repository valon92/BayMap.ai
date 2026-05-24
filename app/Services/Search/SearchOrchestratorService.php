<?php

namespace App\Services\Search;

use App\Support\CategoryCatalog;
use App\Support\ShoeSize;
use App\Services\Ai\AiRequestParserService;
use App\Services\Ai\ProductVisionService;
use App\Services\Geo\GeoLocationService;
use App\Services\Geo\LocalLandmarkResolverService;
use App\Services\Marketplace\EbayOAuthService;
use App\Services\Marketplace\MarketplaceAggregator;
use App\Services\Marketplace\SerpApiShoppingService;

/**
 * Unified search pipeline: AI intent → federated multi-source search → aggregation → meta compare → exact rank.
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
        private ProductAggregationService $aggregation,
        private MetaSearchEngine $metaSearch,
        private ProductRankingService $ranking,
        private EbayOAuthService $ebayOAuth,
        private SerpApiShoppingService $serpApi,
        private QueryIntentEnricher $intentEnricher,
        private SearchResultPoolService $resultPool,
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
        int $page = 1,
        int $perPage = 12,
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
        $parsed['category'] = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');
        $searchGeo = $this->intentEnricher->searchGeo($geo, $parsed);
        if (empty($parsed['search_target'])) {
            $parsed['country'] = $geo['country'] ?? $parsed['country'] ?? null;
        } else {
            $parsed['country'] = $parsed['search_country'] ?? $parsed['country'] ?? $geo['country'];
        }
        $filters = $this->intentEnricher->mergeDefaultFilters($parsed, $filters);
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

        // Step 3: Federated real-time search (multi-source, no local DB)
        $search = $this->aggregator->searchAll($parsed, $expanded, $searchGeo);
        $products = $search['results'];
        $sourceReport = $search['report'];

        $pipeline[] = [
            'step' => 'federated_search',
            'status' => 'completed',
            'label' => 'Queried '.count($sourceReport).' marketplace connectors in real time',
        ];

        // Step 4: Product aggregation — normalize, unify attributes, dedupe
        $products = $this->aggregation->aggregate($products);

        $pipeline[] = [
            'step' => 'aggregate',
            'status' => 'completed',
            'label' => 'Standardized and deduplicated listings from all sources',
        ];

        // Step 5: Meta search — compare identical products across platforms
        $products = $this->metaSearch->enrich($products);

        $pipeline[] = [
            'step' => 'meta_compare',
            'status' => 'completed',
            'label' => 'Compared prices and sellers across marketplaces',
        ];

        $swissCarSearch = strtoupper((string) ($parsed['search_country_code'] ?? '')) === 'CH'
            && CategoryCatalog::isAutomotive($parsed['category'] ?? '');

        $pipeline[] = [
            'step' => 'internet_search',
            'status' => 'completed',
            'label' => $swissCarSearch
                ? 'Searched '.count($expanded['marketplaces'] ?? []).' Swiss car marketplaces'
                : 'Searched web: '.($parsed['search_country'] ?? $searchGeo['country'] ?? 'local').' → regional',
        ];

        $products = $this->applyClientFilters($products, $filters);
        $products = $this->ranking->rank($products, $this->intentEnricher->rankingContext($parsed, $geo));
        $products = $this->dedupeListings($products);
        $pool = $this->resultPool->expand($products, $parsed);
        $pool = $this->applyClientFilters($pool, $filters);
        $estimatedTotal = $this->resultPool->estimateTotal($parsed, count($pool));

        $page = max(1, $page);
        $perPage = max(6, min(36, $perPage));
        $offset = ($page - 1) * $perPage;
        $pageResults = array_slice($pool, $offset, $perPage);
        $returnedSoFar = min($offset + count($pageResults), count($pool));

        $pipeline[] = [
            'step' => 'rank_results',
            'status' => 'completed',
            'label' => 'Ranked by exact intent match and AI relevance',
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
            'results' => $pageResults,
            'meta' => [
                'total' => $estimatedTotal,
                'pool_size' => count($pool),
                'page' => $page,
                'per_page' => $perPage,
                'has_more' => $returnedSoFar < count($pool),
                'returned' => count($pageResults),
                'sources_queried' => $expanded['marketplaces'] ?? [],
                'marketplace_labels' => $expanded['marketplace_labels'] ?? [],
                'source_report' => $sourceReport,
                'location_tiers' => $locationTiers,
                'location_scope' => $locationScope,
                'location' => $this->intentEnricher->locationMeta($parsed, $geo, $searchGeo),
                'processing_ms' => $processingMs,
                'parser' => $parsed['parser'] ?? 'rules',
                'has_image' => (bool) $imageBase64,
                'internet_search' => [
                    'ebay_live' => $this->ebayOAuth->isConfigured(),
                    'google_shopping_live' => $this->serpApi->isConfigured(),
                    'live_sources' => count(array_filter($sourceReport, fn ($r) => ($r['mode'] ?? '') === 'live')),
                    'federated' => true,
                    'connectors_queried' => count($sourceReport),
                ],
            ],
            'platform' => [
                'type' => 'federated_meta_search',
                'positioning' => [
                    'ai_semantic_search',
                    'federated_product_discovery',
                    'intelligent_marketplace_aggregator',
                    'exact_product_discovery',
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
                if (! $this->productMatchesBrand($product, (string) $filters['brand'])) {
                    return false;
                }
            }
            if (isset($filters['product_type']) && $filters['product_type'] !== '') {
                if (! $this->productMatchesType($product, (string) $filters['product_type'])) {
                    return false;
                }
            }
            if (isset($filters['storage']) && $filters['storage'] !== '') {
                $storage = strtoupper((string) $filters['storage']);
                $title = strtoupper($product['title'] ?? '');
                $tags = array_map('strtoupper', $product['tags'] ?? []);
                if (! str_contains($title, $storage) && ! in_array($storage, $tags, true)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function productMatchesBrand(array $product, string $brand): bool
    {
        $brand = mb_strtolower($brand);
        $needles = match ($brand) {
            'apple' => ['apple', 'iphone', 'ipad', 'macbook', 'airpods'],
            'samsung' => ['samsung', 'galaxy'],
            default => [$brand],
        };
        $title = mb_strtolower($product['title'] ?? '');
        $tags = array_map('mb_strtolower', $product['tags'] ?? []);

        foreach ($needles as $needle) {
            if (str_contains($title, $needle) || in_array($needle, $tags, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function productMatchesType(array $product, string $type): bool
    {
        $type = mb_strtolower($type);
        $needles = match ($type) {
            'phone' => ['phone', 'iphone', 'smartphone', 'galaxy'],
            'laptop' => ['laptop', 'macbook', 'notebook', 'rog', 'legion'],
            'tablet' => ['tablet', 'ipad'],
            'headphones' => ['headphones', 'airpods', 'earbuds'],
            default => [$type],
        };
        $title = mb_strtolower($product['title'] ?? '');
        $tags = array_map('mb_strtolower', $product['tags'] ?? []);

        foreach ($needles as $needle) {
            if (str_contains($title, $needle) || in_array($needle, $tags, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    private function dedupeListings(array $products): array
    {
        $seen = [];
        $unique = [];

        foreach ($products as $product) {
            $key = ($product['id'] ?? '').'|'.($product['source_key'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $product;
        }

        return $unique;
    }

    private function normalizeLocationScope(?string $scope): string
    {
        $scope = strtolower((string) $scope);
        $allowed = ['auto', 'city', 'local', 'country', 'region', 'world', 'universal', 'global'];

        return in_array($scope, $allowed, true) ? $scope : 'auto';
    }
}
