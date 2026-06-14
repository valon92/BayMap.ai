<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Browse AI REST client — runs trained robots for anti-bot marketplaces.
 *
 * @see https://www.browse.ai/docs/api/v2
 */
class BrowseAiClient
{
    public function isConfigured(): bool
    {
        return (bool) config('browse_ai.enabled')
            && ! empty(config('browse_ai.api_key'));
    }

    /**
     * @param  array<string, string|int|float|bool|array<int, string>>  $inputParameters
     * @return array<string, mixed>|null
     */
    public function runRobotAndWait(string $robotId, array $inputParameters): ?array
    {
        if (! $this->isConfigured() || $robotId === '') {
            return null;
        }

        $taskId = $this->createTask($robotId, $inputParameters);
        if ($taskId === null) {
            return null;
        }

        return $this->waitForTask($robotId, $taskId);
    }

    /**
     * @param  array<string, string|int|float|bool|array<int, string>>  $inputParameters
     */
    private function createTask(string $robotId, array $inputParameters): ?string
    {
        try {
            $response = $this->request()
                ->post($this->endpoint("/robots/{$robotId}/tasks"), [
                    'inputParameters' => $inputParameters,
                ]);

            if (! $response->successful()) {
                Log::warning('Browse AI task create failed', [
                    'robot_id' => $robotId,
                    'status' => $response->status(),
                    'body' => substr((string) $response->body(), 0, 400),
                ]);

                return null;
            }

            $taskId = (string) ($response->json('result.id') ?? $response->json('task.id') ?? '');

            return $taskId !== '' ? $taskId : null;
        } catch (\Throwable $e) {
            Log::warning('Browse AI task create error', [
                'robot_id' => $robotId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function waitForTask(string $robotId, string $taskId): ?array
    {
        $deadline = microtime(true) + (int) config('browse_ai.max_wait_seconds', 55);
        $intervalMs = (int) config('browse_ai.poll_interval_ms', 2000);

        while (microtime(true) < $deadline) {
            usleep(max(250, $intervalMs) * 1000);

            try {
                $response = $this->request()
                    ->get($this->endpoint("/robots/{$robotId}/tasks/{$taskId}"));

                if (! $response->successful()) {
                    continue;
                }

                $task = (array) ($response->json('result') ?? $response->json('task') ?? []);
                $status = strtolower((string) ($task['status'] ?? ''));

                if (in_array($status, ['successful', 'succeeded', 'finished', 'completed'], true)) {
                    return $this->resolveCapturedPayload($task);
                }

                if (in_array($status, ['failed', 'error', 'cancelled', 'canceled'], true)) {
                    Log::info('Browse AI task failed', [
                        'robot_id' => $robotId,
                        'task_id' => $taskId,
                        'status' => $status,
                    ]);

                    return null;
                }
            } catch (\Throwable $e) {
                Log::warning('Browse AI poll error', [
                    'robot_id' => $robotId,
                    'task_id' => $taskId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Browse AI task timed out', [
            'robot_id' => $robotId,
            'task_id' => $taskId,
        ]);

        return null;
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    private function resolveCapturedPayload(array $task): array
    {
        $captured = (array) ($task['capturedData'] ?? $task['captured_data'] ?? []);
        if ($captured !== []) {
            return $captured;
        }

        $temporaryUrl = (string) ($task['capturedDataTemporaryUrl'] ?? $task['captured_data_temporary_url'] ?? '');
        if ($temporaryUrl === '') {
            return [
                'capturedTexts' => (array) ($task['capturedTexts'] ?? []),
                'capturedLists' => (array) ($task['capturedLists'] ?? []),
            ];
        }

        try {
            $response = Http::timeout(20)->get($temporaryUrl);
            if (! $response->successful()) {
                return [
                    'capturedTexts' => (array) ($task['capturedTexts'] ?? []),
                    'capturedLists' => (array) ($task['capturedLists'] ?? []),
                ];
            }

            $json = $response->json();
            if (is_array($json)) {
                return $json;
            }
        } catch (\Throwable $e) {
            Log::warning('Browse AI temporary payload fetch failed', ['error' => $e->getMessage()]);
        }

        return [
            'capturedTexts' => (array) ($task['capturedTexts'] ?? []),
            'capturedLists' => (array) ($task['capturedLists'] ?? []),
        ];
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('browse_ai.base_url', 'https://api.browse.ai/v2'), '/').$path;
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout((int) config('browse_ai.timeout', 60))
            ->withHeaders([
                'Authorization' => 'Bearer '.(string) config('browse_ai.api_key'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);
    }
}
