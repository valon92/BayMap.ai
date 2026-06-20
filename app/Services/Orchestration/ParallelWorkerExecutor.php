<?php

namespace App\Services\Orchestration;

use Illuminate\Support\Facades\Log;

/**
 * Parallel execution engine for ephemeral Valon Workers.
 * Workers are stateless and exist only for the current search session.
 */
class ParallelWorkerExecutor
{
    /**
     * @param  array<int, array<string, mixed>>  $jobs
     * @param  callable(array<string, mixed>): array<string, mixed>  $runner
     * @return array<int, array<string, mixed>>
     */
    public function execute(array $jobs, callable $runner): array
    {
        if ($jobs === []) {
            return [];
        }

        $timeout = (int) config('orchestration.worker_timeout_seconds', config('valon.worker_timeout_seconds', 15));
        $concurrency = (int) config('orchestration.max_concurrency', 6);

        if ($this->canFork() && count($jobs) > 1) {
            return $this->executeForked($jobs, $runner, $timeout, $concurrency);
        }

        return $this->executeSequential($jobs, $runner, $concurrency);
    }

    /**
     * @param  array<int, array<string, mixed>>  $jobs
     * @param  callable(array<string, mixed>): array<string, mixed>  $runner
     * @return array<int, array<string, mixed>>
     */
    private function executeSequential(array $jobs, callable $runner, int $concurrency): array
    {
        @set_time_limit((int) config('search.max_execution_seconds', 300));

        $live = array_values(array_filter($jobs, fn ($j) => ($j['provider']->mode() ?? '') === 'live'));
        $demo = array_values(array_filter($jobs, fn ($j) => ($j['provider']->mode() ?? '') !== 'live'));
        $ordered = array_merge($live, $demo);
        $results = [];

        foreach (array_chunk($ordered, max(1, $concurrency)) as $chunk) {
            foreach ($chunk as $job) {
                try {
                    $results[] = $runner($job);
                } catch (\Throwable $e) {
                    Log::warning('Parallel worker chunk failed', [
                        'worker' => $job['worker_id'] ?? '?',
                        'error' => $e->getMessage(),
                    ]);
                    $results[] = $this->failedJobResult($job, $e->getMessage());
                }
            }
        }

        return $results;
    }

    /**
     * @param  array<int, array<string, mixed>>  $jobs
     * @param  callable(array<string, mixed>): array<string, mixed>  $runner
     * @return array<int, array<string, mixed>>
     */
    private function executeForked(array $jobs, callable $runner, int $timeoutSeconds, int $concurrency): array
    {
        $results = [];

        foreach (array_chunk($jobs, max(1, $concurrency)) as $chunk) {
            $pids = [];
            $tempFiles = [];

            foreach ($chunk as $index => $job) {
                $tempFile = tempnam(sys_get_temp_dir(), 'buymap_worker_');
                $tempFiles[$index] = $tempFile;

                $pid = pcntl_fork();
                if ($pid === -1) {
                    $results[] = $runner($job);
                    continue;
                }

                if ($pid === 0) {
                    $previousLimit = ini_get('max_execution_time');
                    @set_time_limit($timeoutSeconds + 5);
                    try {
                        $result = $runner($job);
                    } catch (\Throwable $e) {
                        $result = $this->failedJobResult($job, $e->getMessage());
                    } finally {
                        if ($previousLimit !== false) {
                            @set_time_limit((int) $previousLimit);
                        }
                    }
                    file_put_contents($tempFile, serialize($result));
                    exit(0);
                }

                $pids[$index] = $pid;
            }

            foreach ($pids as $index => $pid) {
                pcntl_waitpid($pid, $status);
                if (isset($tempFiles[$index]) && is_readable($tempFiles[$index])) {
                    $decoded = @unserialize((string) file_get_contents($tempFiles[$index]));
                    if (is_array($decoded)) {
                        $results[] = $decoded;
                    }
                    @unlink($tempFiles[$index]);
                }
            }
        }

        return $results;
    }

    private function canFork(): bool
    {
        if (! (bool) config('orchestration.enable_fork', false)) {
            return false;
        }

        return function_exists('pcntl_fork') && PHP_SAPI === 'cli';
    }

    /**
     * @param  array<string, mixed>  $job
     * @return array<string, mixed>
     */
    private function failedJobResult(array $job, string $message): array
    {
        return [
            'worker_id' => $job['worker_id'] ?? 'ValonWorker',
            'role' => $job['role'] ?? '',
            'platform' => $job['platform'] ?? '',
            'platform_label' => $job['platform_label'] ?? '',
            'items' => [],
            'mode' => 'live',
            'status' => 'error',
            'latency_ms' => 0,
            'tier_label' => $job['tier_label'] ?? '',
            'error' => $message,
        ];
    }
}
