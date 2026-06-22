<?php

namespace App\Services\Valon;

use App\Contracts\FederatedSearchProviderInterface;
use App\Services\Agents\AgentActivationService;
use App\Services\Marketplace\ProviderRegistry;
use App\Services\Marketplace\Providers\MockSearchProvider;
use App\Services\Orchestration\ProviderDiscoveryEngine;
use App\Services\Orchestration\SearchIntentFactory;
use App\Services\Search\LocationExpansionEngine;
use App\Support\AutomotivePartsIntentParser;
use App\Support\CategoryCatalog;
use App\Support\GermanCarMarketplaces;
use App\Support\KosovoAutomotiveIntent;
use App\Support\KosovoMarketplaces;
use App\Support\KosovoToyIntent;
use App\Support\LivePlatformRegistry;
use App\Support\LocalMarketplaceResolver;
use App\Support\SwissFashionMarketplaces;
use App\Support\UniversalMarketplaceBridge;
use App\Support\WebServicesIntentParser;

/**
 * Valon AI — role-based multi-agent orchestrator.
 * Spawns parallel Valon Workers per platform; never runs as a single worker.
 */
class ValonOrchestrator
{
    public function __construct(
        private ValonIntentEngine $intentEngine,
        private ValonTaskSplitter $taskSplitter,
        private ValonWorkerRunner $workerRunner,
        private ValonAggregationEngine $aggregation,
        private AgentActivationService $agentActivation,
        private ProviderRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @param  array<string, mixed>  $geo
     * @return array{
     *   results: array<int, array<string, mixed>>,
     *   report: array<int, array<string, mixed>>,
     *   agent_plan: array<string, mixed>,
     *   valon: array<string, mixed>
     * }
     */
    public function orchestrate(array $parsedQuery, array $expandedFilters, array $geo = []): array
    {
        $allTiers = $expandedFilters['location_tiers'] ?? [
            ['suffix' => '', 'label' => 'International', 'level' => 'international'],
        ];

        $intent = $this->intentEngine->analyze($parsedQuery, $expandedFilters, $geo);
        $searchIntent = SearchIntentFactory::fromParsed($parsedQuery, $expandedFilters, $geo);
        $providerDiscovery = app(ProviderDiscoveryEngine::class)->discover($searchIntent);
        $activation = $this->agentActivation->activate($parsedQuery, $expandedFilters, $geo);

        if (($activation['providers'] ?? []) === []) {
            $activation['providers'] = array_slice(
                $this->registry->forSearch($parsedQuery, $expandedFilters, $geo),
                0,
                (int) config('valon.max_workers', 6)
            );
        }

        $workers = $this->taskSplitter->split($intent, $activation, $expandedFilters);

        $locationEngine = new LocationExpansionEngine;
        $locationEngine->initialize($allTiers);

        $results = [];
        $workerReports = [];
        $workerMeta = [];
        $expansionLog = [];

        do {
            $activeTiers = $locationEngine->activeTiers();
            $batch = $this->workerRunner->runParallel($workers, $activeTiers);

            $results = $this->aggregation->merge($results, $batch['results']);
            $workerReports = array_merge($workerReports, $batch['report']);
            $workerMeta = $batch['workers'];

            $expansionLog[] = [
                'level' => $locationEngine->currentLevel(),
                'results' => count($results),
                'workers' => count($workers),
            ];

            if ($locationEngine->shouldExpand(count($results), $parsedQuery)) {
                $locationEngine->expand();
            } else {
                break;
            }
        } while ($locationEngine->canExpand());

        $category = CategoryCatalog::normalize($parsedQuery['category'] ?? 'marketplace');
        $countryCode = strtoupper((string) ($parsedQuery['search_country_code'] ?? $geo['country_code'] ?? ''));

        if ($results === []
            && config('live_platforms.fashion_demo_fallback', true)
            && in_array($category, ['fashion', 'sports_outdoor'], true)
            && $countryCode !== ''
            && $countryCode !== 'XK'
            && LocalMarketplaceResolver::isTargeted($parsedQuery)
            && ! $this->shouldSkipFashionDemoFallback($countryCode, $category)) {
            $catalogFallback = $this->runCatalogFashionFallback(
                $parsedQuery,
                $expandedFilters,
                $geo,
                $countryCode,
                $category,
                count($workerMeta),
            );
            if ($catalogFallback['results'] !== []) {
                $results = $catalogFallback['results'];
                $workerReports = $catalogFallback['report'];
                $workerMeta = $catalogFallback['workers'];
            }
        }

        if (($results === []
                || (CategoryCatalog::isAutomotiveParts($category)
                    && AutomotivePartsIntentParser::needsShoppingSupplement($results, $parsedQuery)))
            && UniversalMarketplaceBridge::allowsGoogleShoppingFallback($countryCode, $category)) {
            $shoppingFallback = $this->runGoogleShoppingFallback(
                $parsedQuery,
                $expandedFilters,
                $geo,
                count($workerMeta),
                $category,
            );
            if ($shoppingFallback['results'] !== []) {
                $results = $results === []
                    ? $shoppingFallback['results']
                    : array_merge($results, $shoppingFallback['results']);
                $workerReports = array_merge($workerReports, $shoppingFallback['report']);
                $workerMeta = array_merge($workerMeta, $shoppingFallback['workers']);
            }
        }

        if ($results === [] && config('marketplaces.demo_fallback_when_empty', true)
            && ! WebServicesIntentParser::isActive($parsedQuery)
            && ! KosovoToyIntent::shouldSkipDemoFallback($parsedQuery)
            && ! KosovoAutomotiveIntent::shouldSkipDemoFallback($parsedQuery)) {
            $fallback = $this->runDemoFallback($parsedQuery, $expandedFilters, $geo, count($workerMeta));
            if ($fallback['results'] !== []) {
                $results = $fallback['results'];
                $workerReports = array_merge($workerReports, $fallback['report']);
                $workerMeta = array_merge($workerMeta, $fallback['workers']);
            }
        }

        [$workerReports, $workerMeta] = $this->consolidateWorkerTelemetry($workerReports, $workerMeta, $results);

        $legacyReport = $this->toLegacyReport($workerReports, $parsedQuery, $geo);

        return [
            'results' => $results,
            'report' => $legacyReport,
            'agent_plan' => [
                'activated' => $activation['agents'],
                'source_keys' => $activation['source_keys'],
                'count' => count($workers),
                'location_expansion' => $expansionLog,
                'final_level' => $locationEngine->currentLevel(),
            ],
            'valon' => [
                'orchestrator' => config('valon.orchestrator_name', 'Valon AI'),
                'search_intent' => $intent['search_intent'] ?? $searchIntent->toArray(),
                'provider_discovery' => [
                    'scope' => $providerDiscovery['scope'],
                    'country_code' => $providerDiscovery['country_code'],
                    'country_codes' => $providerDiscovery['country_codes'] ?? [$providerDiscovery['country_code']],
                    'category' => $providerDiscovery['category'],
                    'subcategory' => $providerDiscovery['subcategory'] ?? null,
                    'providers_found' => count($providerDiscovery['keys']),
                    'workers_planned' => $providerDiscovery['workers_planned'] ?? count($providerDiscovery['keys']),
                    'discovery_engine' => $providerDiscovery['discovery_engine'] ?? null,
                    'providers' => array_slice(
                        $providerDiscovery['providers'] ?? $providerDiscovery['platforms'] ?? [],
                        0,
                        12,
                    ),
                ],
                'intent' => [
                    'category' => $intent['category'],
                    'attributes' => $intent['attributes'],
                    'price_range' => $intent['price_range'],
                    'keywords' => $intent['keywords'],
                    'location_levels' => array_map(
                        fn ($t) => $t['level'] ?? '',
                        $intent['location_priority']
                    ),
                ],
                'workers_spawned' => count($workers),
                'workers' => $workerMeta,
                'worker_reports' => $workerReports,
                'results_merged' => count($results),
                'location_expansion' => $expansionLog,
                'final_location_level' => $locationEngine->currentLevel(),
            ],
        ];
    }

    /**
     * Surface blocked DE automotive platforms in the worker panel (anti-bot / no public API).
     *
     * @param  array<int, array<string, mixed>>  $workerMeta
     * @param  array<int, array<string, mixed>>  $workerSpecs
     * @return array<int, array<string, mixed>>
     */
    private function appendUnavailableGermanAutomotiveWorkers(
        array $workerMeta,
        array $parsedQuery,
        array $workerSpecs,
    ): array {
        $country = strtoupper((string) ($parsedQuery['search_country_code'] ?? ''));
        $category = CategoryCatalog::normalize($parsedQuery['category'] ?? '');

        if ($country !== 'DE' || ! CategoryCatalog::isAutomotive($category)) {
            return $workerMeta;
        }

        $wanted = LivePlatformRegistry::keysFromParsed($parsedQuery);
        $active = array_map(
            fn (array $spec) => strtolower((string) ($spec['platform'] ?? '')),
            $workerSpecs,
        );
        $prefix = config('valon.worker_prefix', 'ValonWorker');
        $index = count($workerMeta) + 1;

        foreach ($wanted as $key) {
            if (in_array(strtolower($key), $active, true)) {
                continue;
            }
            if (! GermanCarMarketplaces::isPlatform($key)) {
                continue;
            }

            $workerMeta[] = [
                'id' => "{$prefix}-{$index}",
                'role' => 'Platform blocked (anti-bot)',
                'platform' => $key,
                'platform_label' => GermanCarMarketplaces::label($key),
                'status' => 'blocked',
                'results' => 0,
                'latency_ms' => 0,
            ];
            $index++;
        }

        return $workerMeta;
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @param  array<string, mixed>  $geo
     * @return array{
     *   results: array<int, array<string, mixed>>,
     *   report: array<int, array<string, mixed>>,
     *   workers: array<int, array<string, mixed>>
     * }
     */
    private function runDemoFallback(array $parsedQuery, array $expandedFilters, array $geo, int $workerOffset = 0): array
    {
        $category = CategoryCatalog::normalize($parsedQuery['category'] ?? 'marketplace');
        $countryCode = strtoupper((string) ($parsedQuery['search_country_code'] ?? $geo['country_code'] ?? ''));

        $providers = array_values(array_filter(
            $this->registry->all(),
            function (FederatedSearchProviderInterface $provider) use ($category, $countryCode) {
                if ($provider->mode() !== 'demo' || ! $provider->supportsCategory($category)) {
                    return false;
                }

                if ($provider instanceof MockSearchProvider) {
                    return $provider->supportsCountry($countryCode !== '' ? $countryCode : null);
                }

                return true;
            },
        ));

        if ($providers === []) {
            return ['results' => [], 'report' => [], 'workers' => []];
        }

        $providers = $this->prioritizeDemoProviders($providers, $countryCode);

        $maxProviders = (int) config('marketplaces.demo_fallback_max_providers', 8);
        $targetResults = (int) config('marketplaces.demo_fallback_target_results', 8);
        $providers = array_slice($providers, 0, max(1, $maxProviders));

        $demoFilters = $expandedFilters;
        unset($demoFilters['marketplaces']);

        $results = [];
        $report = [];
        $workers = [];
        $prefix = config('valon.worker_prefix', 'ValonWorker');
        $index = $workerOffset + 1;

        foreach ($providers as $provider) {
            $started = microtime(true);

            try {
                $items = $provider->search($parsedQuery, $demoFilters);
            } catch (\Throwable) {
                $items = [];
            }

            $count = is_array($items) ? count($items) : 0;
            if ($count === 0) {
                continue;
            }

            $latencyMs = (int) round((microtime(true) - $started) * 1000);
            $platform = $provider->sourceKey();

            foreach ($items as $item) {
                $results[] = $item;
            }

            $report[] = [
                'worker_id' => "{$prefix}-{$index}",
                'platform' => $platform,
                'platform_label' => $provider->label(),
                'role' => 'Local catalog',
                'mode' => 'demo',
                'count' => $count,
                'status' => 'ok',
                'location_tier' => $geo['city'] ?? '',
                'latency_ms' => $latencyMs,
                'error' => null,
            ];

            $workers[] = [
                'id' => "{$prefix}-{$index}",
                'role' => 'Local catalog',
                'platform' => $platform,
                'platform_label' => $provider->label(),
                'status' => 'ok',
                'results' => $count,
                'latency_ms' => $latencyMs,
            ];

            $index++;

            if (count($results) >= $targetResults) {
                break;
            }
        }

        return [
            'results' => $results,
            'report' => $report,
            'workers' => $workers,
        ];
    }

    /**
     * Country fashion catalog fallback — one demo worker per registered local store when live scrapers fail.
     *
     * @return array{
     *   results: array<int, array<string, mixed>>,
     *   report: array<int, array<string, mixed>>,
     *   workers: array<int, array<string, mixed>>
     * }
     */
    private function runCatalogFashionFallback(
        array $parsedQuery,
        array $expandedFilters,
        array $geo,
        string $countryCode,
        string $category,
        int $workerOffset = 0,
    ): array {
        $providers = $this->registry->catalogFashionDemoProviders($countryCode, $category);
        if ($providers === []) {
            return ['results' => [], 'report' => [], 'workers' => []];
        }

        $demoFilters = $expandedFilters;
        unset($demoFilters['marketplaces']);

        $targetResults = max(
            24,
            (int) config('marketplaces.demo_fallback_target_results', 8) * 3,
        );

        $results = [];
        $report = [];
        $workers = [];
        $prefix = $this->workerPrefixForCategory($category);
        $index = 1;

        foreach ($providers as $provider) {
            $started = microtime(true);

            try {
                $items = $provider->search($parsedQuery, $demoFilters);
            } catch (\Throwable) {
                $items = [];
            }

            $count = is_array($items) ? count($items) : 0;
            $latencyMs = (int) round((microtime(true) - $started) * 1000);
            $platform = $provider->sourceKey();
            $workerId = SwissFashionMarketplaces::workerIdFor($platform) ?? "{$prefix}-{$index}";
            $platformLabel = SwissFashionMarketplaces::label($platform) ?: $provider->label();

            if ($count > 0) {
                foreach ($items as $item) {
                    $results[] = $item;
                }
            }

            $report[] = [
                'worker_id' => $workerId,
                'platform' => $platform,
                'platform_label' => $platformLabel,
                'role' => 'Local catalog',
                'mode' => 'demo',
                'count' => $count,
                'status' => $count > 0 ? 'ok' : 'ok',
                'location_tier' => $geo['city'] ?? '',
                'latency_ms' => $latencyMs,
                'error' => null,
            ];

            $workers[] = [
                'id' => $workerId,
                'role' => 'Local catalog',
                'platform' => $platform,
                'platform_label' => $platformLabel,
                'status' => $count > 0 ? 'ok' : 'ok',
                'results' => $count,
                'latency_ms' => $latencyMs,
            ];

            $index++;

            if (count($results) >= $targetResults) {
                break;
            }
        }

        return [
            'results' => $results,
            'report' => $report,
            'workers' => $workers,
        ];
    }

    /**
     * SerpAPI Google Shopping when registered CH/DE/… parts stores scrape 0 listings.
     *
     * @return array{
     *   results: array<int, array<string, mixed>>,
     *   report: array<int, array<string, mixed>>,
     *   workers: array<int, array<string, mixed>>
     * }
     */
    private function runGoogleShoppingFallback(
        array $parsedQuery,
        array $expandedFilters,
        array $geo,
        int $workerOffset = 0,
        string $category = 'marketplace',
    ): array {
        $provider = null;
        foreach ($this->registry->all() as $candidate) {
            if ($candidate->sourceKey() === 'google_shopping' && $candidate->isAvailable()) {
                $provider = $candidate;
                break;
            }
        }

        if (! $provider instanceof FederatedSearchProviderInterface) {
            return ['results' => [], 'report' => [], 'workers' => []];
        }

        $started = microtime(true);

        try {
            $items = $provider->search($parsedQuery, $expandedFilters);
        } catch (\Throwable) {
            $items = [];
        }

        $count = is_array($items) ? count($items) : 0;
        if ($count === 0) {
            return ['results' => [], 'report' => [], 'workers' => []];
        }

        $maxResults = (int) config('marketplaces.google_shopping_parts_fallback_max', 48);
        $items = array_slice($items, 0, max(12, $maxResults));

        $latencyMs = (int) round((microtime(true) - $started) * 1000);
        $prefix = $this->workerPrefixForCategory(CategoryCatalog::normalize($category));
        $workerId = "{$prefix}-".($workerOffset + 1);
        $role = CategoryCatalog::isAutomotive($category) ? 'Google Shopping cars' : 'Google Shopping fallback';

        return [
            'results' => $items,
            'report' => [[
                'worker_id' => $workerId,
                'platform' => 'google_shopping',
                'platform_label' => $provider->label(),
                'role' => $role,
                'mode' => 'live',
                'count' => count($items),
                'status' => 'ok',
                'location_tier' => $geo['city'] ?? '',
                'latency_ms' => $latencyMs,
                'error' => null,
            ]],
            'workers' => [[
                'id' => $workerId,
                'role' => $role,
                'platform' => 'google_shopping',
                'platform_label' => $provider->label(),
                'status' => 'ok',
                'results' => count($items),
                'latency_ms' => $latencyMs,
            ]],
        ];
    }

    /**
     * @param  array<int, FederatedSearchProviderInterface>  $providers
     * @return array<int, FederatedSearchProviderInterface>
     */
    private function prioritizeDemoProviders(array $providers, string $countryCode): array
    {
        $local = [];
        $global = [];

        foreach ($providers as $provider) {
            if ($countryCode !== ''
                && $provider instanceof MockSearchProvider
                && $provider->supportsCountry($countryCode)) {
                $local[] = $provider;

                continue;
            }

            $global[] = $provider;
        }

        usort($local, fn ($a, $b) => $a->priority() <=> $b->priority());
        usort($global, fn ($a, $b) => $a->priority() <=> $b->priority());

        return array_merge($local, $global);
    }

    private function shouldSkipFashionDemoFallback(string $countryCode, string $category): bool
    {
        if (! UniversalMarketplaceBridge::allowsBridge('google_shopping', $countryCode, $category)) {
            return false;
        }

        return in_array('google_shopping', UniversalMarketplaceBridge::providerKeys(), true);
    }

    private function workerPrefixForCategory(string $category): string
    {
        $map = (array) config('providers.worker_prefix_by_category', []);
        $normalized = CategoryCatalog::normalize($category);

        return (string) ($map[$normalized] ?? config('valon.worker_prefix', 'ValonWorker'));
    }

    /**
     * Drop redundant worker rows when catalog fallback succeeded after blocked live scrapers.
     *
     * @param  array<int, array<string, mixed>>  $reports
     * @param  array<int, array<string, mixed>>  $workers
     * @param  array<int, array<string, mixed>>  $results
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function consolidateWorkerTelemetry(array $reports, array $workers, array $results): array
    {
        $catalogSuccess = [];
        foreach ($reports as $row) {
            $platform = strtolower((string) ($row['platform'] ?? ''));
            if ($platform === '') {
                continue;
            }
            if (($row['count'] ?? 0) > 0 && ($row['mode'] ?? '') === 'demo') {
                $catalogSuccess[$platform] = true;
            }
        }

        $hasResults = $results !== [];

        $filter = function (array $row) use ($catalogSuccess, $hasResults): bool {
            $platform = strtolower((string) ($row['platform'] ?? ''));
            $status = (string) ($row['status'] ?? 'ok');
            $count = (int) ($row['count'] ?? $row['results'] ?? 0);
            $mode = (string) ($row['mode'] ?? '');

            if ($status === 'blocked' && isset($catalogSuccess[$platform])) {
                return false;
            }

            if ($mode === 'live' && $count === 0 && isset($catalogSuccess[$platform])) {
                return false;
            }

            if ($hasResults && $count === 0) {
                return false;
            }

            return true;
        };

        $reports = array_values(array_filter($reports, $filter));
        $workers = array_values(array_filter($workers, $filter));

        return [$reports, $workers];
    }

    /**
     * @param  array<int, array<string, mixed>>  $workerReports
     * @return array<int, array<string, mixed>>
     */
    private function toLegacyReport(array $workerReports, array $parsedQuery, array $geo): array
    {
        $code = strtoupper((string) ($parsedQuery['search_country_code'] ?? $geo['country_code'] ?? ''));

        return array_map(function (array $row) use ($parsedQuery) {
            $platform = $row['platform'] ?? '';
            $status = $row['status'] ?? 'ok';

            if ($status === 'ok' && ($row['mode'] ?? '') === 'demo') {
                $status = LocalMarketplaceResolver::hasLocalPlatforms($parsedQuery)
                    ? 'local_marketplace'
                    : (KosovoMarketplaces::isKosovoPlatform($platform) ? 'kosovo_marketplace' : 'mock_data');
            }

            return [
                'source' => $platform,
                'label' => LivePlatformRegistry::label($platform)
                    ?: KosovoMarketplaces::label($platform)
                    ?: SwissFashionMarketplaces::label($platform)
                    ?: ($row['platform_label'] ?? $platform),
                'mode' => $row['mode'] ?? 'demo',
                'count' => $row['count'] ?? 0,
                'status' => $status,
                'location' => $row['location_tier'] ?? '',
                'valon_worker_id' => $row['worker_id'] ?? null,
                'valon_role' => $row['role'] ?? null,
                'latency_ms' => $row['latency_ms'] ?? 0,
                'error' => $row['error'] ?? null,
            ];
        }, $workerReports);
    }
}
