<?php

namespace App\Services\Valon;

use App\Services\Agents\AgentActivationService;
use App\Services\Marketplace\ProviderRegistry;
use App\Services\Orchestration\ProviderDiscoveryEngine;
use App\Services\Orchestration\SearchIntentFactory;
use App\Services\Search\LocationExpansionEngine;
use App\Support\CategoryCatalog;
use App\Support\GermanCarMarketplaces;
use App\Support\KosovoMarketplaces;
use App\Support\LivePlatformRegistry;
use App\Support\LocalMarketplaceResolver;

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
                    'category' => $providerDiscovery['category'],
                    'providers_found' => count($providerDiscovery['keys']),
                    'platforms' => array_slice($providerDiscovery['platforms'], 0, 12),
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
     * @param  array<int, array<string, mixed>>  $workerReports
     * @return array<int, array<string, mixed>>
     */
    private function toLegacyReport(array $workerReports, array $parsedQuery, array $geo): array
    {
        $code = strtoupper((string) ($parsedQuery['search_country_code'] ?? $geo['country_code'] ?? ''));

        return array_map(function (array $row) use ($code, $parsedQuery) {
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
