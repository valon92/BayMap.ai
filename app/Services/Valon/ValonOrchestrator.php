<?php

namespace App\Services\Valon;

use App\Services\Agents\AgentActivationService;
use App\Services\Marketplace\ProviderRegistry;
use App\Services\Search\LocationExpansionEngine;
use App\Support\CategoryCatalog;
use App\Support\DutchCarMarketplaces;
use App\Support\GermanCarMarketplaces;
use App\Support\KosovoMarketplaces;
use App\Support\SwissCarMarketplaces;

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

            if ($locationEngine->shouldExpand(count($results))) {
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
                $status = match ($code) {
                    'CH' => CategoryCatalog::isAutomotive($parsedQuery['category'] ?? '') ? 'swiss_car_marketplace' : 'mock_data',
                    'NL' => CategoryCatalog::isAutomotive($parsedQuery['category'] ?? '') ? 'dutch_car_marketplace' : 'mock_data',
                    'DE' => CategoryCatalog::isAutomotive($parsedQuery['category'] ?? '') ? 'german_car_marketplace' : 'mock_data',
                    'XK' => KosovoMarketplaces::isKosovoPlatform($platform) ? 'kosovo_marketplace' : 'mock_data',
                    default => 'mock_data',
                };
            }

            return [
                'source' => $platform,
                'label' => KosovoMarketplaces::label($platform)
                    ?: DutchCarMarketplaces::label($platform)
                    ?: SwissCarMarketplaces::label($platform)
                    ?: GermanCarMarketplaces::label($platform)
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
