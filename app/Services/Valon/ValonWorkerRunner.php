<?php

namespace App\Services\Valon;

use App\Contracts\FederatedSearchProviderInterface;
use Illuminate\Support\Facades\Log;

/**
 * Executes Valon Workers in parallel — stateless, isolated, independent timeouts.
 */
class ValonWorkerRunner
{
    public function __construct(private ValonResultNormalizer $normalizer) {}

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
        $timeout = (int) config('valon.worker_timeout_seconds', 15);
        $jobs = $this->buildJobs($workers, $locationTiers);
        $batch = $this->executeBatch($jobs, $timeout);

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
    private function executeBatch(array $jobs, int $timeoutSeconds): array
    {
        if ($jobs === []) {
            return [];
        }

        $live = array_values(array_filter($jobs, fn ($j) => $j['provider']->mode() === 'live'));
        $demo = array_values(array_filter($jobs, fn ($j) => $j['provider']->mode() !== 'live'));

        $results = [];
        foreach (array_merge($live, $demo) as $job) {
            $results[] = $this->runJob($job, $timeoutSeconds);
        }

        return $results;
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

        $previousLimit = ini_get('max_execution_time');
        @set_time_limit($timeoutSeconds + 5);

        try {
            $items = $provider->search($job['parsed_query'], $job['expanded_filters']);

            return [
                'worker_id' => $job['worker_id'],
                'role' => $job['role'],
                'platform' => $job['platform'],
                'platform_label' => $job['platform_label'],
                'items' => is_array($items) ? $items : [],
                'mode' => $provider->mode() === 'live' ? 'live' : 'demo',
                'status' => 'ok',
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'tier_label' => $job['tier_label'] ?? '',
                'error' => null,
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
            if ($previousLimit !== false) {
                @set_time_limit((int) $previousLimit);
            }
        }
    }
}
