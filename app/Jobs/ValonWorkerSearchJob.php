<?php

namespace App\Jobs;

use App\Contracts\FederatedSearchProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Ephemeral Valon Worker job — one provider search per dispatch.
 * Used when ORCHESTRATION_USE_QUEUE=true with Redis + Horizon for horizontal scaling.
 */
class ValonWorkerSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     */
    public function __construct(
        public string $batchId,
        public string $workerId,
        public string $providerClass,
        public array $parsedQuery,
        public array $expandedFilters,
        public string $platform,
        public string $platformLabel,
    ) {
        $this->timeout = (int) config('orchestration.worker_timeout_seconds', 15);
        $this->onConnection((string) config('orchestration.queue_connection', 'redis'));
    }

    public function handle(): void
    {
        /** @var FederatedSearchProviderInterface $provider */
        $provider = app($this->providerClass);

        try {
            $items = $provider->search($this->parsedQuery, $this->expandedFilters);
            $payload = [
                'worker_id' => $this->workerId,
                'platform' => $this->platform,
                'platform_label' => $this->platformLabel,
                'items' => is_array($items) ? $items : [],
                'mode' => $provider->mode() === 'live' ? 'live' : 'demo',
                'status' => is_array($items) && count($items) > 0 ? 'ok' : 'ok',
                'latency_ms' => 0,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('ValonWorkerSearchJob failed', [
                'worker' => $this->workerId,
                'platform' => $this->platform,
                'error' => $e->getMessage(),
            ]);

            $payload = [
                'worker_id' => $this->workerId,
                'platform' => $this->platform,
                'platform_label' => $this->platformLabel,
                'items' => [],
                'mode' => 'live',
                'status' => 'error',
                'latency_ms' => 0,
                'error' => $e->getMessage(),
            ];
        }

        Cache::put("valon_batch:{$this->batchId}:{$this->workerId}", $payload, now()->addMinutes(10));
    }
}
