<?php

namespace App\Services\Agents;

use App\Contracts\FederatedSearchProviderInterface;
use Illuminate\Support\Facades\Log;

/**
 * Parallel agent execution with per-agent timeout, error isolation, and fallback support.
 */
class AgentExecutionEngine
{
    /**
     * @param  array<int, FederatedSearchProviderInterface>  $providers
     * @param  array<int, array<string, mixed>>  $tiers
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @return array{results: array<int, array<string, mixed>>, report: array<int, array<string, mixed>>}
     */
    public function execute(
        array $providers,
        array $tiers,
        array $parsedQuery,
        array $expandedFilters,
    ): array {
        $timeout = (int) config('agent_pools.per_agent_timeout_seconds', config('marketplaces.timeout_seconds', 15));
        $jobs = $this->buildJobs($providers, $tiers, $parsedQuery, $expandedFilters);

        $batchResults = $this->runBatch($jobs, $timeout);

        $results = [];
        $report = [];
        $liveResultCount = 0;
        $liveCap = (int) config('marketplaces.live_result_cap', 16);
        $skipMockAt = (int) config('marketplaces.skip_mock_when_live_at_least', 8);

        foreach ($batchResults as $batch) {
            $provider = $batch['provider'];
            $count = count($batch['items']);

            if ($provider->mode() === 'demo' && $liveResultCount >= $skipMockAt) {
                $report[] = $this->reportRow($provider, 'skipped', 0, 'live_results_sufficient', $batch['error'] ?? '');

                continue;
            }

            if ($provider->mode() === 'live') {
                $liveResultCount += $count;
            }

            foreach ($batch['items'] as $item) {
                $item['_provider_latency_ms'] = $batch['latency_ms'];
                $item['_agent_id'] = $batch['agent_id'] ?? null;
                $results[] = $item;
            }

            $report[] = $this->reportRow(
                $provider,
                $batch['mode'],
                $count,
                $batch['status'],
                $batch['error'] ?? '',
                $batch['latency_ms'],
                $batch['tier']['label'] ?? '',
            );

            if ($liveResultCount >= $liveCap) {
                break;
            }
        }

        return ['results' => $results, 'report' => $report];
    }

    /**
     * @param  array<int, array<string, mixed>>  $jobs
     * @return array<int, array<string, mixed>>
     */
    private function runBatch(array $jobs, int $timeoutSeconds): array
    {
        if ($jobs === []) {
            return [];
        }

        if (function_exists('pcntl_fork') && count($jobs) > 1 && PHP_SAPI === 'cli') {
            return $this->runForkedBatch($jobs, $timeoutSeconds);
        }

        return $this->runConcurrentBatch($jobs, $timeoutSeconds);
    }

    /**
     * Concurrent batch — isolated jobs; live providers run first in parallel wave.
     *
     * @param  array<int, array<string, mixed>>  $jobs
     * @return array<int, array<string, mixed>>
     */
    private function runConcurrentBatch(array $jobs, int $timeoutSeconds): array
    {
        $liveJobs = array_values(array_filter($jobs, fn ($j) => ($j['provider']->mode() ?? '') === 'live'));
        $demoJobs = array_values(array_filter($jobs, fn ($j) => ($j['provider']->mode() ?? '') !== 'live'));

        $results = [];

        foreach ($liveJobs as $job) {
            $results[] = $this->runSingleJob($job, $timeoutSeconds);
        }

        $demoChunks = array_chunk($demoJobs, (int) config('agent_pools.max_agents', 6));
        foreach ($demoChunks as $chunk) {
            foreach ($chunk as $job) {
                $results[] = $this->runSingleJob($job, $timeoutSeconds);
            }
        }

        return $results;
    }

    /**
     * @param  array<int, array<string, mixed>>  $jobs
     * @return array<int, array<string, mixed>>
     */
    private function runForkedBatch(array $jobs, int $timeoutSeconds): array
    {
        $results = [];
        $chunks = array_chunk($jobs, (int) config('agent_pools.max_agents', 6));

        foreach ($chunks as $chunk) {
            $pids = [];
            $tempFiles = [];

            foreach ($chunk as $index => $job) {
                $tempFile = tempnam(sys_get_temp_dir(), 'buymap_agent_');
                $tempFiles[$index] = $tempFile;
                $payload = base64_encode(serialize([
                    'parsed' => $job['parsed_query'],
                    'filters' => $job['expanded_filters'],
                    'source' => $job['provider']->sourceKey(),
                    'timeout' => $timeoutSeconds,
                ]));

                $pid = pcntl_fork();
                if ($pid === -1) {
                    $results[] = $this->runSingleJob($job, $timeoutSeconds);
                } elseif ($pid === 0) {
                    $result = $this->runSingleJob($job, $timeoutSeconds);
                    file_put_contents($tempFile, serialize($result));
                    exit(0);
                } else {
                    $pids[$index] = $pid;
                }
            }

            foreach ($pids as $index => $pid) {
                pcntl_waitpid($pid, $status);
                if (isset($tempFiles[$index]) && is_readable($tempFiles[$index])) {
                    $raw = file_get_contents($tempFiles[$index]);
                    $decoded = @unserialize($raw);
                    if (is_array($decoded)) {
                        $results[] = $decoded;
                    }
                    @unlink($tempFiles[$index]);
                }
            }
        }

        return $results !== [] ? $results : array_map(
            fn ($job) => $this->runSingleJob($job, $timeoutSeconds),
            $jobs
        );
    }

    /**
     * @param  array<string, mixed>  $job
     * @return array<string, mixed>
     */
    private function runSingleJob(array $job, int $timeoutSeconds): array
    {
        /** @var FederatedSearchProviderInterface $provider */
        $provider = $job['provider'];
        $started = microtime(true);

        if ($provider->mode() === 'live' && ! $provider->isAvailable()) {
            return [
                'provider' => $provider,
                'items' => [],
                'mode' => 'live',
                'status' => 'not_configured',
                'latency_ms' => 0,
                'tier' => $job['tier'] ?? [],
                'agent_id' => $job['agent_id'] ?? null,
                'error' => 'not_configured',
            ];
        }

        $previousLimit = ini_get('max_execution_time');
        @set_time_limit($timeoutSeconds + 5);

        try {
            $items = $provider->search($job['parsed_query'], $job['expanded_filters']);
            $latencyMs = (int) round((microtime(true) - $started) * 1000);

            return [
                'provider' => $provider,
                'items' => is_array($items) ? $items : [],
                'mode' => $provider->mode() === 'live' ? 'live' : 'demo',
                'status' => 'ok',
                'latency_ms' => $latencyMs,
                'tier' => $job['tier'] ?? [],
                'agent_id' => $job['agent_id'] ?? null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Agent execution failed', [
                'source' => $provider->sourceKey(),
                'error' => $e->getMessage(),
            ]);

            return [
                'provider' => $provider,
                'items' => [],
                'mode' => $provider->mode() === 'live' ? 'live' : 'demo',
                'status' => 'error',
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'tier' => $job['tier'] ?? [],
                'agent_id' => $job['agent_id'] ?? null,
                'error' => $e->getMessage(),
            ];
        } finally {
            if ($previousLimit !== false) {
                @set_time_limit((int) $previousLimit);
            }
        }
    }

    /**
     * @param  array<int, FederatedSearchProviderInterface>  $providers
     * @param  array<int, array<string, mixed>>  $tiers
     * @return array<int, array<string, mixed>>
     */
    private function buildJobs(
        array $providers,
        array $tiers,
        array $parsedQuery,
        array $expandedFilters,
    ): array {
        $jobs = [];
        $agentMap = $expandedFilters['activated_agents'] ?? [];

        foreach ($providers as $provider) {
            $agentId = $this->agentIdForProvider($provider->sourceKey(), $agentMap);
            $tierList = $tiers;

            if ($provider->mode() === 'live') {
                $tierList = array_slice($tiers, 0, 2);
            }

            foreach ($tierList as $tier) {
                $filters = $expandedFilters;
                $filters['location_suffix'] = $tier['suffix'] ?? '';
                $filters['location_tier'] = $tier;

                $jobs[] = [
                    'provider' => $provider,
                    'parsed_query' => $parsedQuery,
                    'expanded_filters' => $filters,
                    'tier' => $tier,
                    'agent_id' => $agentId,
                ];

                if ($provider->mode() === 'live') {
                    break;
                }
            }
        }

        return $jobs;
    }

    /**
     * @param  array<int, array<string, mixed>>  $agentMap
     */
    private function agentIdForProvider(string $sourceKey, array $agentMap): ?string
    {
        foreach ($agentMap as $agent) {
            foreach ($agent['sources'] ?? [] as $source) {
                if (strcasecmp($source, $sourceKey) === 0 || str_contains($sourceKey, $source)) {
                    return $agent['id'] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function reportRow(
        FederatedSearchProviderInterface $provider,
        string $mode,
        int $count,
        string $status,
        string $error = '',
        int $latencyMs = 0,
        string $tierLabel = '',
    ): array {
        return [
            'source' => $provider->sourceKey(),
            'label' => $provider->label(),
            'mode' => $mode,
            'count' => $count,
            'status' => $status,
            'error' => $error !== '' ? $error : null,
            'latency_ms' => $latencyMs,
            'tier' => $tierLabel,
        ];
    }
}
