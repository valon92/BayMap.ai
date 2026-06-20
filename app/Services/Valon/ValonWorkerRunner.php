<?php

namespace App\Services\Valon;

use App\Contracts\FederatedSearchProviderInterface;
use App\Services\Orchestration\ParallelWorkerExecutor;
use App\Services\Providers\ProviderIntelligenceService;
use App\Support\LivePlatformRegistry;
use Illuminate\Support\Facades\Log;

/**
 * Executes Valon Workers in parallel — stateless, isolated, independent timeouts.
 */
class ValonWorkerRunner
{
    public function __construct(
        private ValonResultNormalizer $normalizer,
        private ParallelWorkerExecutor $executor,
        private ProviderIntelligenceService $intelligence,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $workers
     * @param  array<int, array<string, mixed>>  $locationTiers
     * @return array{
     *   results: array<int, array<string, mixed>>,
     *   report: array<int, array<string, mixed>>,
     *   workers: array<int, array<string, mixed>>
     * }
     */
    public function runParallel(array $workers, array $locationTiers): array
    {
        $defaultTimeout = (int) config('valon.worker_timeout_seconds', 15);
        $jobs = $this->buildJobs($workers, $locationTiers);
        $batch = $this->executeBatch($jobs, $defaultTimeout);

        $results = [];
        $report = [];
        $workerMeta = [];

        foreach ($batch as $entry) {
            $workerId = $entry['worker_id'];
            $platform = $entry['platform'];
            $normalized = $this->normalizer->normalizeMany(
                $entry['items'],
                $workerId,
                $platform,
                $entry['platform_label'],
            );

            foreach ($normalized as $item) {
                $results[] = $item;
            }

            $report[] = [
                'worker_id' => $workerId,
                'role' => $entry['role'],
                'platform' => $platform,
                'platform_label' => $entry['platform_label'],
                'count' => count($entry['items']),
                'status' => $entry['status'],
                'mode' => $entry['mode'],
                'latency_ms' => $entry['latency_ms'],
                'error' => $entry['error'],
                'location_tier' => $entry['tier_label'] ?? '',
            ];

            $workerMeta[] = [
                'id' => $workerId,
                'role' => $entry['role'],
                'platform' => $platform,
                'platform_label' => $entry['platform_label'],
                'status' => $entry['status'],
                'results' => count($entry['items']),
                'latency_ms' => $entry['latency_ms'],
            ];

            $this->recordProviderMetric($entry, $workers);
        }

        return [
            'results' => $results,
            'report' => $report,
            'workers' => $workerMeta,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $workers
     * @param  array<int, array<string, mixed>>  $tiers
     * @return array<int, array<string, mixed>>
     */
    private function buildJobs(array $workers, array $tiers): array
    {
        $jobs = [];
        $primaryTier = $tiers[0] ?? ['suffix' => '', 'label' => 'International', 'level' => 'international'];

        foreach ($workers as $worker) {
            /** @var FederatedSearchProviderInterface $provider */
            $provider = $worker['provider'];
            $expanded = $worker['expanded_filters'] ?? [];
            $expanded['location_suffix'] = $primaryTier['suffix'] ?? '';
            $expanded['location_tier'] = $primaryTier;
            $expanded['valon_worker_id'] = $worker['worker_id'];

            $jobs[] = [
                'worker_id' => $worker['worker_id'],
                'role' => $worker['role'],
                'platform' => $worker['platform'],
                'platform_label' => $worker['platform_label'],
                'provider' => $provider,
                'parsed_query' => $worker['task']['parsed_query'] ?? [],
                'expanded_filters' => $expanded,
                'tier' => $primaryTier,
                'tier_label' => $primaryTier['label'] ?? '',
            ];
        }

        return $jobs;
    }

    /**
     * @param  array<int, array<string, mixed>>  $jobs
     * @return array<int, array<string, mixed>>
     */
    private function executeBatch(array $jobs, int $defaultTimeoutSeconds): array
    {
        if ($jobs === []) {
            return [];
        }

        return $this->executor->execute(
            $jobs,
            fn (array $job) => $this->runJob($job, $this->timeoutForJob($job, $defaultTimeoutSeconds)),
        );
    }

    private function resolveWorkerStatus(string $platform, int $count, string $mode): string
    {
        if ($count > 0) {
            return 'ok';
        }

        if ($mode === 'live' && $this->isAntiBotPlatform($platform)) {
            return 'blocked';
        }

        return 'ok';
    }

    private function isAntiBotPlatform(string $platform): bool
    {
        return in_array(strtolower($platform), [
            'mobile_de',
            'heycar_de',
            'facebook_marketplace_de',
            'wirkaufendeinauto',
            'zalando_ch',
            'zalando_de',
            'galaxus_ch',
            'digitec_ch',
            'aboutyou_ch',
            'aboutyou_de',
            'decathlon_ch',
            'bonprix_de',
            'peek_cloppenburg_de',
            'asos_de',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $job
     */
    private function timeoutForJob(array $job, int $defaultTimeoutSeconds): int
    {
        $platform = strtolower((string) ($job['platform'] ?? ''));

        if ($this->isAntiBotPlatform($platform)) {
            return (int) config('valon.anti_bot_timeout_seconds', 10);
        }

        if (in_array($platform, ['melodiapx', 'driloni'], true)) {
            return (int) config('valon.melodiapx_timeout_seconds', 35);
        }

        if (LivePlatformRegistry::isLivePlatform($platform)) {
            return (int) config('valon.live_platform_timeout_seconds', 18);
        }

        return $defaultTimeoutSeconds;
    }

    /**
     * @param  array<string, mixed>  $job
     * @return array<string, mixed>
     */
    private function runJob(array $job, int $timeoutSeconds): array
    {
        /** @var FederatedSearchProviderInterface $provider */
        $provider = $job['provider'];
        $started = microtime(true);

        if ($provider->mode() === 'live' && ! $provider->isAvailable()) {
            return [
                'worker_id' => $job['worker_id'],
                'role' => $job['role'],
                'platform' => $job['platform'],
                'platform_label' => $job['platform_label'],
                'items' => [],
                'mode' => 'live',
                'status' => 'not_configured',
                'latency_ms' => 0,
                'tier_label' => $job['tier_label'] ?? '',
                'error' => 'not_configured',
            ];
        }

        $jobLimit = max($timeoutSeconds, (int) config('valon.live_platform_timeout_seconds', 18)) + 8;
        @set_time_limit($jobLimit);

        try {
            $items = $provider->search($job['parsed_query'], $job['expanded_filters']);

            $count = is_array($items) ? count($items) : 0;

            return [
                'worker_id' => $job['worker_id'],
                'role' => $job['role'],
                'platform' => $job['platform'],
                'platform_label' => $job['platform_label'],
                'items' => is_array($items) ? $items : [],
                'mode' => $provider->mode() === 'live' ? 'live' : 'demo',
                'status' => $this->resolveWorkerStatus((string) $job['platform'], $count, $provider->mode()),
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'tier_label' => $job['tier_label'] ?? '',
                'error' => $count === 0 && $this->isAntiBotPlatform((string) $job['platform']) ? 'anti_bot' : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Valon worker failed', [
                'worker' => $job['worker_id'],
                'platform' => $job['platform'],
                'error' => $e->getMessage(),
            ]);

            return [
                'worker_id' => $job['worker_id'],
                'role' => $job['role'],
                'platform' => $job['platform'],
                'platform_label' => $job['platform_label'],
                'items' => [],
                'mode' => $provider->mode() === 'live' ? 'live' : 'demo',
                'status' => 'error',
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'tier_label' => $job['tier_label'] ?? '',
                'error' => $e->getMessage(),
            ];
        } finally {
            @set_time_limit((int) config('search.max_execution_seconds', 300));
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<int, array<string, mixed>>  $workers
     */
    private function recordProviderMetric(array $entry, array $workers): void
    {
        $platform = (string) ($entry['platform'] ?? '');
        if ($platform === '') {
            return;
        }

        $parsed = [];
        foreach ($workers as $worker) {
            if (($worker['platform'] ?? '') === $platform) {
                $parsed = (array) ($worker['task']['parsed_query'] ?? []);
                break;
            }
        }

        $this->intelligence->record(
            $platform,
            (int) ($entry['latency_ms'] ?? 0),
            count($entry['items'] ?? []),
            in_array($entry['status'] ?? '', ['ok', 'blocked'], true),
            isset($parsed['category']) ? (string) $parsed['category'] : null,
            isset($parsed['search_country_code']) ? (string) $parsed['search_country_code'] : null,
            isset($entry['error']) ? (string) $entry['error'] : null,
        );
    }
}
