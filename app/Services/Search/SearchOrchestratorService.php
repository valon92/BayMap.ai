<?php

namespace App\Services\Search;

use App\Services\Ai\AiRequestParserService;
use App\Services\Ai\ProductVisionService;
use App\Services\Geo\GeoLocationService;
use App\Services\Geo\LocalLandmarkResolverService;
use App\Services\Market\MarketIntentService;
use App\Services\Marketplace\EbayOAuthService;
use App\Services\Marketplace\MarketplaceAggregator;
use App\Services\Marketplace\SerpApiShoppingService;
use App\Services\Orchestration\SearchIntentFactory;
use App\Support\AutomotiveColorResolver;
use App\Support\AutomotiveEngineResolver;
use App\Support\AutomotiveIntentParser;
use App\Support\AutomotiveModelResolver;
use App\Support\AutomotivePartsIntentParser;
use App\Support\BookIntentParser;
use App\Support\CategoryCatalog;
use App\Support\CountryMatcher;
use App\Support\ElectronicsIntentParser;
use App\Support\FashionFilterCatalog;
use App\Support\FashionIntentParser;
use App\Support\HomeFurnitureIntentParser;
use App\Support\IndustrialB2BIntentParser;
use App\Support\KosovoMarketplaces;
use App\Support\LivePlatformRegistry;
use App\Support\LocalMarketplaceResolver;
use App\Support\SearchCountryResolver;
use App\Support\ShoeSize;
use App\Support\SwissFashionMarketplaces;
use App\Support\WebServicesIntentParser;

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
        private MarketIntentService $marketIntent,
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
        ?string $marketMode = null,
        ?string $marketCode = null,
        bool $refineFilters = false,
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
        $parsed = $this->marketIntent->apply($parsed, $marketMode, $marketCode, $locale);
        $parsed = HomeFurnitureIntentParser::merge($parsed, $query);
        $parsed = $this->resolveSearchMarket($parsed, $marketMode, $marketCode, $locale);
        $parsed = IndustrialB2BIntentParser::merge($parsed, $query);
        if (CategoryCatalog::isAutomotiveParts($parsed['category'] ?? '')) {
            $parsed = AutomotivePartsIntentParser::merge($parsed, $query);
        }
        $parsed['category'] = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');
        $searchGeo = $this->intentEnricher->searchGeo($geo, $parsed);
        if (empty($parsed['search_target'])) {
            $parsed['country'] = $geo['country'] ?? $parsed['country'] ?? null;
        } else {
            $parsed['country'] = $parsed['search_country'] ?? $parsed['country'] ?? $geo['country'];
        }
        $aiParsed = $parsed;
        $filters = $this->intentEnricher->mergeDefaultFilters($parsed, $filters);
        $parsed = $this->intentEnricher->applyFilterOverridesToParsed($parsed, $filters, $aiParsed, $locale, $refineFilters);
        $parsed = $this->landmarks->enrich($parsed, $parsed['raw_query'] ?? $query, $searchGeo, $locale);
        $locationContext = $this->landmarks->locationContext($parsed);

        $pipeline[] = [
            'step' => 'ai_analyze',
            'status' => 'completed',
            'label' => 'AI understood product attributes',
        ];

        // Step 2: Location tiers + intelligent agent activation
        $locationTiers = $this->localTiers->tiersForSearch($searchGeo, $parsed, $locationScope);
        $expanded = $this->expansion->expand($parsed, $searchGeo, $locale);
        $expanded['location_tiers'] = $locationTiers;
        $expanded['location_scope'] = $locationScope;
        $dynamicFilters = $this->expansion->buildDynamicFilters($parsed, $locale);
        $dynamicFilters = $this->intentEnricher->syncDynamicFilterValues($dynamicFilters, $filters);
        $searchIntent = SearchIntentFactory::fromParsed($parsed, $expanded, $searchGeo);

        // Step 3: Federated search — dynamic agent pool (3–6 agents), parallel execution
        $search = $this->aggregator->searchAll($parsed, $expanded, $searchGeo);
        $products = $search['results'];
        $sourceReport = $search['report'];
        $agentPlan = $search['agent_plan'] ?? ['activated' => [], 'source_keys' => [], 'final_level' => 'country'];
        $valonPlan = $search['valon'] ?? [];

        $expanded['agent_plan'] = $agentPlan;
        $expanded['activated_agents'] = $agentPlan['activated'] ?? [];
        $expanded['activated_sources'] = $agentPlan['source_keys'] ?? [];
        $expanded['valon'] = $valonPlan;

        $workerCount = $valonPlan['workers_spawned'] ?? count($agentPlan['activated'] ?? []);

        $pipeline[] = [
            'step' => 'valon_orchestrate',
            'status' => 'completed',
            'label' => 'Valon AI spawned '.$workerCount.' parallel workers',
        ];

        $pipeline[] = [
            'step' => 'agent_activation',
            'status' => 'completed',
            'label' => 'Task split across '.count($valonPlan['workers'] ?? []).' platform roles',
        ];

        $pipeline[] = [
            'step' => 'federated_search',
            'status' => 'completed',
            'label' => 'Valon Workers searched in parallel (location: '.($valonPlan['final_location_level'] ?? $agentPlan['final_level'] ?? 'country').')',
        ];

        // Step 4: Product aggregation — normalize, unify attributes, dedupe
        $products = $this->aggregation->aggregate($products);
        $products = $this->preferDirectPlatformResults($products, $parsed);

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

        $pipeline[] = [
            'step' => 'internet_search',
            'status' => 'completed',
            'label' => LocalMarketplaceResolver::pipelineLabel($parsed, $expanded, $searchGeo),
        ];

        $products = $this->applyClientFilters($products, $filters, $parsed);
        $products = $this->stripInternalListingFields($products);
        $products = $this->ranking->rank($products, $this->intentEnricher->rankingContext($parsed, $geo));
        $products = $this->interleaveMarketplaceSources($products, $parsed);
        $products = $this->balanceMultiCountryResults($products, $parsed);
        $products = $this->dedupeListings($products);
        $pool = $this->resultPool->expand($products, $parsed);
        $pool = $this->applyClientFilters($pool, $filters, $parsed);
        $pool = $this->stripInternalListingFields($pool);
        $pool = $this->applySort($pool, $filters);
        $poolTotal = count($pool);

        $page = max(1, $page);
        $maxPerPage = WebServicesIntentParser::isActive($parsed) ? 48 : 36;
        $perPage = max(6, min($maxPerPage, $perPage));
        $offset = ($page - 1) * $perPage;
        $pageResults = array_slice($pool, $offset, $perPage);
        $returnedSoFar = min($offset + count($pageResults), count($pool));

        $pipeline[] = [
            'step' => 'rank_results',
            'status' => 'completed',
            'label' => $this->rankResultsLabel($filters),
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
                'total' => $poolTotal,
                'pool_size' => $poolTotal,
                'page' => $page,
                'per_page' => $perPage,
                'has_more' => $returnedSoFar < count($pool),
                'returned' => count($pageResults),
                'search_intent' => $searchIntent->toArray(),
                'sources_queried' => $expanded['marketplaces'] ?? [],
                'marketplace_labels' => $expanded['marketplace_labels'] ?? [],
                'marketplace_labels_by_country' => $expanded['marketplace_labels_by_country'] ?? [],
                'source_report' => $sourceReport,
                'location_tiers' => $locationTiers,
                'location_scope' => $locationScope,
                'location' => $this->intentEnricher->locationMeta($parsed, $geo, $searchGeo),
                'processing_ms' => $processingMs,
                'parser' => $parsed['parser'] ?? 'semantic',
                'has_image' => (bool) $imageBase64,
                'internet_search' => [
                    'ebay_live' => $this->ebayOAuth->isConfigured(),
                    'google_shopping_live' => $this->serpApi->isConfigured(),
                    'channel3_live' => config('channel3.enabled') && ! empty(config('channel3.api_key')),
                    'live_sources' => count(array_filter($sourceReport, fn ($r) => ($r['mode'] ?? '') === 'live')),
                    'federated' => true,
                    'connectors_queried' => count($sourceReport),
                ],
                'agent_plan' => $agentPlan,
                'agents_activated' => count($agentPlan['activated'] ?? []),
                'location_expansion_level' => $valonPlan['final_location_level'] ?? $agentPlan['final_level'] ?? null,
                'valon' => ($valonPlan !== [] && $valonPlan !== null) ? [
                    'orchestrator' => $valonPlan['orchestrator'] ?? 'Valon AI',
                    'workers_spawned' => $valonPlan['workers_spawned'] ?? 0,
                    'workers' => $valonPlan['workers'] ?? [],
                    'results_merged' => $valonPlan['results_merged'] ?? 0,
                    'search_intent' => $valonPlan['search_intent'] ?? $searchIntent->toArray(),
                    'provider_discovery' => $valonPlan['provider_discovery'] ?? null,
                    'intent' => $valonPlan['intent'] ?? null,
                ] : null,
            ],
            'platform' => [
                'type' => 'federated_meta_search',
                'positioning' => [
                    'ai_semantic_search',
                    'federated_product_discovery',
                    'intelligent_marketplace_aggregator',
                    'ai_meta_search',
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
    private function applyClientFilters(array $products, array $filters, array $parsed = []): array
    {
        if (empty($filters)) {
            return $products;
        }

        return array_values(array_filter($products, function (array $product) use ($filters, $parsed) {
            $isTravel = CategoryCatalog::normalize($parsed['category'] ?? '') === 'travel';
            $isWebServices = WebServicesIntentParser::isActive($parsed);

            if (isset($filters['price_min']) && ($product['price'] ?? 0) < (float) $filters['price_min']) {
                return false;
            }
            if (isset($filters['price_max']) && ($product['price'] ?? PHP_INT_MAX) > (float) $filters['price_max']) {
                return false;
            }
            if (isset($filters['year_min']) && $filters['year_min'] !== ''
                || isset($filters['year_max']) && $filters['year_max'] !== '') {
                $yearBounds = AutomotiveModelResolver::clientYearBounds($parsed, $filters);
                if ($yearBounds !== null) {
                    [$minYear, $maxYear] = $yearBounds;
                    $productYear = isset($product['year']) ? (int) $product['year'] : null;
                    $title = (string) ($product['title'] ?? '');

                    if ($productYear !== null) {
                        if ($productYear < $minYear || $productYear > $maxYear) {
                            return false;
                        }
                    } elseif (CategoryCatalog::isAutomotive($parsed['category'] ?? '')) {
                        if (! AutomotiveModelResolver::matchesYearRange($productYear, $title, [
                            'year_min' => $minYear,
                            'year_max' => $maxYear,
                        ], true)) {
                            return false;
                        }
                    }
                }
            }
            if (isset($filters['year']) && $filters['year'] !== '' && ! isset($filters['year_min']) && ! isset($filters['year_max'])) {
                $wantedYear = (int) $filters['year'];
                $productYear = isset($product['year']) ? (int) $product['year'] : null;
                if ($productYear !== null) {
                    if ($productYear !== $wantedYear) {
                        return false;
                    }
                } elseif (! str_contains($product['title'] ?? '', (string) $wantedYear)) {
                    return false;
                }
            }
            if (isset($filters['max_km']) && $filters['max_km'] !== '') {
                $limit = (int) $filters['max_km'];
                $mileage = isset($product['mileage']) ? (int) $product['mileage'] : null;
                if ($mileage !== null && $mileage > $limit) {
                    return false;
                }
            }
            if (isset($filters['color']) && $filters['color'] !== '') {
                $wantedColors = (array) ($filters['colors'] ?? $parsed['colors'] ?? []);
                if (! $this->productMatchesColor($product, (string) $filters['color'], $wantedColors, $parsed)) {
                    return false;
                }
            }
            if (isset($filters['fuel']) && $filters['fuel'] !== '') {
                $fuel = AutomotiveIntentParser::normalizeFuel((string) $filters['fuel']);
                $title = mb_strtolower($product['title'] ?? '');
                $tags = array_map('mb_strtolower', $product['tags'] ?? []);
                $needles = match ($fuel) {
                    'diesel' => ['diesel', 'tdi', 'dizell', 'disel'],
                    'petrol' => ['petrol', 'benzin', 'tfsi', 'gasoline', 'tsi'],
                    'electric' => ['electric', 'ev', 'elektrik'],
                    default => [$fuel],
                };
                $matchesFuel = false;
                foreach ($needles as $needle) {
                    if (str_contains($title, $needle) || in_array($needle, $tags, true)) {
                        $matchesFuel = true;
                        break;
                    }
                }
                if (! $matchesFuel && ! empty($product['fuel'])) {
                    $productFuel = AutomotiveIntentParser::normalizeFuel((string) $product['fuel']);
                    $matchesFuel = $productFuel === $fuel;
                }
                if (! $matchesFuel) {
                    return false;
                }
            }
            if (isset($filters['engine_liters']) && (float) $filters['engine_liters'] > 0) {
                $wantedEngine = (float) $filters['engine_liters'];
                $productEngine = isset($product['engine_liters']) ? (float) $product['engine_liters'] : null;
                if (! AutomotiveEngineResolver::matchesWanted(
                    $productEngine,
                    $wantedEngine,
                    (string) ($product['title'] ?? ''),
                    isset($filters['fuel']) ? (string) $filters['fuel'] : null,
                )) {
                    return false;
                }
            }
            if (! $isTravel && isset($filters['country']) && $filters['country'] !== '') {
                $targetCode = strtoupper((string) ($parsed['search_country_code'] ?? ''));
                $productCode = strtoupper((string) ($product['country_code'] ?? ''));
                $countryMatch = $targetCode !== '' && $productCode !== '' && $targetCode === $productCode;

                if (! $countryMatch && ! empty($parsed['search_countries']) && is_array($parsed['search_countries']) && $productCode !== '') {
                    foreach ($parsed['search_countries'] as $country) {
                        $code = strtoupper((string) ($country['search_country_code'] ?? ''));
                        if ($code !== '' && $code === $productCode) {
                            $countryMatch = true;
                            break;
                        }
                    }
                }

                if (! $countryMatch) {
                    $filterCode = SearchCountryResolver::codeFromCountryFilter((string) $filters['country']);
                    if ($filterCode !== null && $productCode !== '' && strtoupper($filterCode) === $productCode) {
                        $countryMatch = true;
                    }
                }

                if (! $countryMatch) {
                    $countryMatch = CountryMatcher::locationMatchesFilter(
                        (string) ($product['location'] ?? ''),
                        (string) $filters['country'],
                        $product['country_code'] ?? null,
                    );
                }
                if (! $countryMatch
                    && CategoryCatalog::isAutomotiveParts($parsed['category'] ?? '')
                    && in_array('google_shopping', (array) ($product['tags'] ?? []), true)) {
                    $countryMatch = true;
                }
                if (! $countryMatch
                    && in_array(CategoryCatalog::normalize($parsed['category'] ?? ''), ['fashion', 'sports_outdoor'], true)) {
                    if ($this->isBridgeAggregatorListing($product)) {
                        $countryMatch = true;
                    } elseif (($product['source_key'] ?? '') === 'ebay'
                        && strtoupper((string) ($parsed['search_country_code'] ?? '')) === 'US') {
                        $countryMatch = true;
                    }
                }
                if (! $countryMatch && ! empty($parsed['search_countries']) && is_array($parsed['search_countries'])) {
                    foreach ($parsed['search_countries'] as $country) {
                        if (CountryMatcher::locationMatchesFilter(
                            (string) ($product['location'] ?? ''),
                            (string) ($country['search_country'] ?? ''),
                            isset($product['country_code']) ? (string) $product['country_code'] : null,
                        )) {
                            $countryMatch = true;
                            break;
                        }
                    }
                }
                if (! $countryMatch) {
                    return false;
                }
            } elseif (! $isTravel && ! empty($parsed['search_countries']) && is_array($parsed['search_countries']) && count($parsed['search_countries']) > 1) {
                $countryMatch = false;
                $productCode = strtoupper((string) ($product['country_code'] ?? ''));
                foreach ($parsed['search_countries'] as $country) {
                    $code = strtoupper((string) ($country['search_country_code'] ?? ''));
                    if ($productCode !== '' && $code !== '' && $productCode === $code) {
                        $countryMatch = true;
                        break;
                    }
                    if ($productCode !== '' && $code !== '' && $productCode !== $code) {
                        continue;
                    }
                    if (CountryMatcher::locationMatchesFilter(
                        (string) ($product['location'] ?? ''),
                        (string) ($country['search_country'] ?? ''),
                        isset($product['country_code']) ? (string) $product['country_code'] : null,
                    )) {
                        $countryMatch = true;
                        break;
                    }
                    if (in_array(CategoryCatalog::normalize($parsed['category'] ?? ''), ['fashion', 'sports_outdoor'], true)
                        && $this->isBridgeAggregatorListing($product)) {
                        $countryMatch = true;
                        break;
                    }
                }
                if (! $countryMatch) {
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
                $store = (string) ($product['store'] ?? $product['source_key'] ?? '');
                // Keep local store listings visible — buyer verifies sizes on the shop page
                if (! KosovoMarketplaces::isKosovoPlatform($store)
                    && ! SwissFashionMarketplaces::isPlatform($store)) {
                    return false;
                }
            }
            if (isset($filters['brand']) && $filters['brand'] !== ''
                && ! CategoryCatalog::isAutomotiveParts($parsed['category'] ?? '')) {
                if (! $this->productMatchesBrand($product, (string) $filters['brand'])) {
                    return false;
                }
            }
            $wantedModel = $filters['model'] ?? $parsed['model'] ?? null;
            if (! empty($wantedModel) && CategoryCatalog::isElectronics($parsed['category'] ?? '')) {
                if (! ElectronicsIntentParser::productMatchesModel($product, (string) $wantedModel)) {
                    return false;
                }
            }
            if (isset($filters['gender']) && $filters['gender'] !== '' && ! $isWebServices) {
                if (! $this->productMatchesGender($product, (string) $filters['gender'], $parsed)) {
                    return false;
                }
            }
            if (isset($filters['product_type']) && $filters['product_type'] !== '') {
                $wantedType = FashionIntentParser::normalizeType((string) $filters['product_type']);
                if (CategoryCatalog::isBookSearch($parsed)) {
                    if (! BookIntentParser::productMatchesType($product, $wantedType)) {
                        return false;
                    }
                } elseif (CategoryCatalog::isElectronics($parsed['category'] ?? '')) {
                    if (! ElectronicsIntentParser::productMatchesType($product, $wantedType)) {
                        return false;
                    }
                } elseif (in_array(CategoryCatalog::normalize($parsed['category'] ?? ''), ['fashion', 'sports_outdoor'], true)) {
                    if (! $this->isBridgeAggregatorListing($product)
                        && ! FashionIntentParser::productMatchesType($product, $wantedType)) {
                        return false;
                    }
                }
            }
            if ($isWebServices && isset($filters['web_service_type']) && $filters['web_service_type'] !== '') {
                $wanted = mb_strtolower((string) $filters['web_service_type']);
                $actual = mb_strtolower((string) ($product['web_service_type'] ?? $product['product_type'] ?? ''));
                if ($actual !== $wanted) {
                    return false;
                }
            }
            if ($isWebServices && isset($filters['provider']) && $filters['provider'] !== '') {
                $needle = mb_strtolower((string) $filters['provider']);
                $haystack = mb_strtolower((string) ($product['source_key'] ?? ''));
                if (! str_contains($haystack, $needle)) {
                    return false;
                }
            }
            if ($isWebServices && isset($filters['billing']) && $filters['billing'] !== '') {
                $wantedBilling = mb_strtolower((string) $filters['billing']);
                $actualBilling = mb_strtolower((string) ($product['billing_period'] ?? ''));
                if ($wantedBilling === 'yearly' && $actualBilling === 'monthly') {
                    return false;
                }
                if ($wantedBilling === 'monthly' && $actualBilling === 'yearly') {
                    return false;
                }
            }
            if (isset($filters['genre']) && $filters['genre'] !== '' && CategoryCatalog::isBookSearch($parsed)) {
                if (! BookIntentParser::productMatchesGenre($product, (string) $filters['genre'])) {
                    return false;
                }
            }
            if (! empty($parsed['features']) && is_array($parsed['features'])) {
                $category = CategoryCatalog::normalize($parsed['category'] ?? '');
                if (CategoryCatalog::isElectronics($category) || $category === 'gaming_entertainment') {
                    if (! ElectronicsIntentParser::productMatchesFeatures($product, $parsed['features'])) {
                        return false;
                    }
                }
            }
            if (! $isTravel && ! empty($parsed['search_target'])) {
                $targetCountries = $parsed['search_countries'] ?? [];
                if (is_array($targetCountries) && count($targetCountries) > 1) {
                    $countryMatch = false;
                    foreach ($targetCountries as $country) {
                        $code = strtoupper((string) ($country['search_country_code'] ?? ''));
                        $productCode = strtoupper((string) ($product['country_code'] ?? ''));
                        if ($productCode !== '' && $code !== '' && $productCode === $code) {
                            $countryMatch = true;
                            break;
                        }
                        if ($productCode !== '' && $code !== '' && $productCode !== $code) {
                            continue;
                        }
                        if (CountryMatcher::locationMatchesFilter(
                            (string) ($product['location'] ?? ''),
                            (string) ($country['search_country'] ?? ''),
                            isset($product['country_code']) ? (string) $product['country_code'] : null,
                        )) {
                            $countryMatch = true;
                            break;
                        }
                    }
                    if (! $countryMatch) {
                        return false;
                    }
                } elseif (! empty($parsed['search_country']) && empty($filters['country'])) {
                    if (! CountryMatcher::locationMatchesFilter(
                        (string) ($product['location'] ?? ''),
                        (string) $parsed['search_country'],
                        isset($product['country_code']) ? (string) $product['country_code'] : null,
                    )) {
                        return false;
                    }
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
        $category = CategoryCatalog::normalize($product['category'] ?? '');
        $needles = match ($brand) {
            'apple' => ['apple', 'iphone', 'ipad', 'macbook', 'airpods'],
            'samsung' => ['samsung', 'galaxy'],
            default => in_array($category, ['fashion', 'sports_outdoor', 'luxury_collectibles'], true)
                ? FashionFilterCatalog::brandNeedles($brand)
                : [$brand],
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
    private function productMatchesGender(array $product, string $gender, array $parsed = []): bool
    {
        $category = CategoryCatalog::normalize($parsed['category'] ?? '');
        if (in_array($category, ['fashion', 'sports_outdoor'], true)) {
            return FashionIntentParser::matchesGender($product, $gender);
        }

        $gender = mb_strtolower(trim($gender));
        $itemGender = mb_strtolower((string) ($product['gender'] ?? ''));

        if ($itemGender === '') {
            return true;
        }

        return match ($gender) {
            'male', 'men' => in_array($itemGender, ['male', 'men', 'unisex'], true),
            'female', 'women' => in_array($itemGender, ['female', 'women', 'unisex'], true),
            default => $itemGender === $gender || $itemGender === 'unisex',
        };
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

    /**
     * @param  array<string, mixed>  $product
     */
    /**
     * @param  array<int, string>  $wantedColors
     */
    private function productMatchesColor(array $product, string $color, array $wantedColors = [], array $parsed = []): bool
    {
        $color = mb_strtolower(trim($color));
        $title = (string) ($product['title'] ?? '');
        $productColor = isset($product['color']) ? (string) $product['color'] : null;
        $tags = array_map('mb_strtolower', $product['tags'] ?? []);

        $store = strtolower((string) ($product['store'] ?? $product['source_key'] ?? ''));
        $category = CategoryCatalog::normalize($parsed['category'] ?? '');
        $isFashion = in_array($category, ['fashion', 'sports_outdoor'], true);
        $isAutomotive = CategoryCatalog::isAutomotive($category);
        $kosovoAutoLive = $isAutomotive && $this->isKosovoAutomotiveLiveStore($store);
        $swissAutoLive = $isAutomotive && $this->isSwissAutomotiveLiveStore($store);
        $allowUnknown = str_contains($store, 'kleinanzeigen') || $color === 'multicolor'
            || ($isFashion && (KosovoMarketplaces::isKosovoPlatform($store) || LivePlatformRegistry::isLivePlatform($store)))
            || $kosovoAutoLive
            || $swissAutoLive;

        if ($isFashion && $color !== 'multicolor') {
            return FashionIntentParser::matchesColor($product, $color, $allowUnknown);
        }

        if ($color === 'multicolor') {
            $tones = $wantedColors !== []
                ? array_map('mb_strtolower', $wantedColors)
                : ['black', 'white'];

            foreach ($tones as $tone) {
                if ($isFashion && FashionIntentParser::matchesColor($product, $tone, $allowUnknown)) {
                    return true;
                }
                if (AutomotiveColorResolver::matchesWanted($productColor, $tone, $title, $allowUnknown)) {
                    return true;
                }
            }

            return false;
        }

        if ($kosovoAutoLive) {
            if (AutomotiveColorResolver::matchesWanted($productColor, $color, $title, false)) {
                return true;
            }

            // List pages rarely include paint color — keep matches for ranking, not hard drop.
            return true;
        }

        if ($swissAutoLive) {
            if (AutomotiveColorResolver::matchesWanted($productColor, $color, $title, false)) {
                return true;
            }

            return true;
        }

        return AutomotiveColorResolver::matchesWanted($productColor, $color, $title, $allowUnknown);
    }

    private function isSwissAutomotiveLiveStore(string $store): bool
    {
        return str_contains($store, 'autolina')
            || str_contains($store, 'autogrid')
            || str_contains($store, 'autoscout24')
            || str_contains($store, 'car_trade24')
            || str_contains($store, 'carlando')
            || str_contains($store, 'carindex')
            || str_contains($store, 'ricardo')
            || str_contains($store, 'tutti')
            || str_contains($store, 'troovo')
            || str_contains($store, 'amag')
            || str_contains($store, 'motoauto');
    }

    private function isKosovoAutomotiveLiveStore(string $store): bool
    {
        return str_contains($store, 'merrjep')
            || str_contains($store, 'veturaneshitje')
            || str_contains($store, 'carvago');
    }

    /**
     * @param  array<int, string>  $tags
     */
    private function colorToneInProduct(string $color, string $title, array $tags): bool
    {
        $aliases = match ($color) {
            'black' => ['black', 'graphite', 'midnight', 'zez', 'zeze'],
            'white' => ['white', 'ivory', 'pearl', 'bardh', 'bardhe'],
            'grey', 'gray' => ['grey', 'gray', 'silver', 'graphite', 'hiri', 'gri'],
            'blue' => ['blue', 'navy', 'azure', 'kalter', 'kaltër', 'blu'],
            default => [$color],
        };

        foreach ($aliases as $alias) {
            if (str_contains($title, $alias) || in_array($alias, $tags, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Round-robin listings across Kosovo fashion stores so one shop does not fill the whole page.
     *
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<string, mixed>  $parsed
     * @return array<int, array<string, mixed>>
     */
    /**
     * Auto parts: keep listings scraped from registered stores; drop Google Shopping when any exist.
     *
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<string, mixed>  $parsed
     * @return array<int, array<string, mixed>>
     */
    private function preferDirectPlatformResults(array $products, array $parsed): array
    {
        $category = CategoryCatalog::normalize($parsed['category'] ?? '');
        if (! CategoryCatalog::isAutomotiveParts($category) && ! in_array($category, ['fashion', 'sports_outdoor'], true)) {
            return $products;
        }

        $direct = array_values(array_filter(
            $products,
            fn (array $product): bool => ! $this->isBridgeAggregatorListing($product),
        ));

        $shopping = array_values(array_filter(
            $products,
            fn (array $product): bool => $this->isBridgeAggregatorListing($product),
        ));

        if ($direct === []) {
            return $products;
        }

        if ($shopping === []) {
            return $direct;
        }

        if (CategoryCatalog::isAutomotiveParts($category)) {
            $rawQuery = (string) ($parsed['raw_query'] ?? '');
            $component = AutomotivePartsIntentParser::extractComponent($parsed, $rawQuery);
            if ($component === '') {
                return $direct;
            }

            $matchingDirect = array_values(array_filter(
                $direct,
                static fn (array $product): bool => AutomotivePartsIntentParser::matchesListing(
                    (string) ($product['title'] ?? ''),
                    $parsed,
                ),
            ));

            if (count($matchingDirect) < 3) {
                return $this->dedupeListings(array_merge($matchingDirect, $shopping));
            }

            return $matchingDirect;
        }

        $wantedType = FashionIntentParser::normalizeType((string) ($parsed['product_type'] ?? ''));
        $matchingDirect = array_values(array_filter(
            $direct,
            static fn (array $product): bool => $wantedType === ''
                || FashionIntentParser::productMatchesType($product, $wantedType),
        ));

        if (count($matchingDirect) < 6) {
            return $this->dedupeListings(array_merge($matchingDirect, $shopping));
        }

        return $matchingDirect;
    }

    private function interleaveMarketplaceSources(array $products, array $parsed): array
    {
        $category = CategoryCatalog::normalize($parsed['category'] ?? '');
        $countries = $parsed['search_countries'] ?? [];
        $isMultiCountry = is_array($countries) && count($countries) > 1;

        if (! $isMultiCountry && ! in_array($category, ['fashion', 'sports_outdoor'], true)) {
            return $products;
        }

        if (! $isMultiCountry && empty($parsed['search_target'])) {
            return $products;
        }

        if ($isMultiCountry && CategoryCatalog::isAutomotive($category)) {
            return $products;
        }

        $buckets = [];
        foreach ($products as $product) {
            $key = (string) ($product['source_key'] ?? $product['store'] ?? 'unknown');
            $buckets[$key][] = $product;
        }

        if (count($buckets) <= 1) {
            return $products;
        }

        $interleaved = [];
        $hasItems = true;

        while ($hasItems) {
            $hasItems = false;
            foreach ($buckets as &$bucket) {
                if ($bucket === []) {
                    continue;
                }
                $interleaved[] = array_shift($bucket);
                $hasItems = true;
            }
            unset($bucket);
        }

        return $interleaved;
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<string, mixed>  $parsed
     * @return array<int, array<string, mixed>>
     */
    private function balanceMultiCountryResults(array $products, array $parsed): array
    {
        $countries = $parsed['search_countries'] ?? [];
        if (! is_array($countries) || count($countries) <= 1 || $products === []) {
            return $products;
        }

        $buckets = [];
        foreach ($countries as $country) {
            $code = strtoupper((string) ($country['search_country_code'] ?? ''));
            if ($code !== '') {
                $buckets[$code] = [];
            }
        }

        if ($buckets === []) {
            return $products;
        }

        $overflow = [];

        foreach ($products as $product) {
            $placed = false;
            $productCode = strtoupper((string) ($product['country_code'] ?? ''));

            foreach ($countries as $country) {
                $code = strtoupper((string) ($country['search_country_code'] ?? ''));
                if ($code === '' || ! isset($buckets[$code])) {
                    continue;
                }

                if ($productCode !== '' && $productCode === $code) {
                    $buckets[$code][] = $product;
                    $placed = true;
                    break;
                }

                if ($productCode !== '' && $productCode !== $code) {
                    continue;
                }

                if (CountryMatcher::locationMatchesFilter(
                    (string) ($product['location'] ?? ''),
                    (string) ($country['search_country'] ?? ''),
                    $productCode !== '' ? $productCode : null,
                )) {
                    $buckets[$code][] = $product;
                    $placed = true;
                    break;
                }
            }

            if (! $placed) {
                $overflow[] = $product;
            }
        }

        $balanced = [];
        $codes = array_keys($buckets);
        $hasItems = true;

        while ($hasItems) {
            $hasItems = false;
            foreach ($codes as $code) {
                if ($buckets[$code] !== []) {
                    $balanced[] = array_shift($buckets[$code]);
                    $hasItems = true;
                }
            }
        }

        return array_merge($balanced, $overflow);
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function applySort(array $products, array $filters): array
    {
        $sort = (string) ($filters['sort'] ?? 'relevance');

        if ($sort === 'popularity') {
            usort($products, function ($a, $b) {
                $rankA = (int) ($a['provider_rank'] ?? 999);
                $rankB = (int) ($b['provider_rank'] ?? 999);
                if ($rankA !== $rankB) {
                    return $rankA <=> $rankB;
                }

                return ($b['match_score'] ?? 0) <=> ($a['match_score'] ?? 0);
            });
        } elseif ($sort === 'price_asc') {
            usort($products, fn ($a, $b) => $this->sortablePrice($a) <=> $this->sortablePrice($b));
        } elseif ($sort === 'price_desc') {
            usort($products, fn ($a, $b) => $this->sortablePrice($b) <=> $this->sortablePrice($a));
        }

        return $products;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function sortablePrice(array $product): float
    {
        $price = (float) ($product['best_price_eur'] ?? $product['price_eur'] ?? $product['price'] ?? 0);

        return $price > 0 ? $price : PHP_FLOAT_MAX;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function rankResultsLabel(array $filters): string
    {
        return match ((string) ($filters['sort'] ?? 'relevance')) {
            'popularity' => 'Sorted by provider popularity',
            'price_asc' => 'Sorted by price: lowest to highest',
            'price_desc' => 'Sorted by price: highest to lowest',
            default => 'Ranked by exact intent match and AI relevance',
        };
    }

    /**
     * Ensure targeted country is set — kitchen/furniture searches fail silently without a market.
     *
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function resolveSearchMarket(array $parsed, ?string $marketMode, ?string $marketCode, ?string $locale): array
    {
        if (! empty($parsed['search_country_code'])) {
            return $parsed;
        }

        $code = strtoupper(trim((string) $marketCode));
        $mode = strtolower(trim((string) $marketMode));

        if (in_array($mode, ['country', 'countries'], true) && $code !== '') {
            return $this->marketIntent->apply($parsed, $marketMode, $marketCode, $locale);
        }

        if (HomeFurnitureIntentParser::isKitchenSearch($parsed)
            || CategoryCatalog::normalize($parsed['category'] ?? '') === 'home_furniture') {
            if (in_array($mode, ['country', 'countries'], true)) {
                return $parsed;
            }

            $parsed['search_country_code'] = 'DE';
            $parsed['search_country'] = SearchCountryResolver::countryNameForCode('DE') ?? 'Germany';
            $parsed['search_target'] = true;
            $parsed['search_scope'] = 'targeted';
            $parsed['location_source'] = 'furniture_default_market';
        }

        return $parsed;
    }

    private function normalizeLocationScope(?string $scope): string
    {
        $scope = strtolower((string) $scope);
        $allowed = ['auto', 'city', 'local', 'country', 'region', 'continent', 'world', 'universal', 'global'];

        return in_array($scope, $allowed, true) ? $scope : 'auto';
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    private function stripInternalListingFields(array $products): array
    {
        return array_map(function (array $product) {
            unset($product['_marketplace_result_total']);

            return $product;
        }, $products);
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function isBridgeAggregatorListing(array $product): bool
    {
        $sourceKey = (string) ($product['source_key'] ?? '');
        $tags = (array) ($product['tags'] ?? []);

        return in_array($sourceKey, ['google_shopping', 'channel3'], true)
            || in_array('google_shopping', $tags, true)
            || in_array('channel3', $tags, true);
    }
}
