<?php

namespace App\Services\Providers;

use App\Models\Catalog\ProviderMetric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks provider quality metrics and adjusts routing priority dynamically.
 *
 * Metrics: response time, result count, success rate, trust score, freshness.
 */
class ProviderIntelligenceService
{
    private const SUMMARY_CACHE = 'provider:intelligence:';

    public function isEnabled(): bool
    {
        if (! (bool) config('providers.intelligence.enabled', true)) {
            return false;
        }

        try {
            return Schema::hasTable('provider_metrics');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Record outcome of a worker search against a provider.
     */
    public function record(
        string $platformSlug,
        int $latencyMs,
        int $resultCount,
        bool $success,
        ?string $category = null,
        ?string $countryCode = null,
        ?string $error = null,
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        ProviderMetric::query()->create([
            'platform_slug' => strtolower(trim($platformSlug)),
            'category' => $category,
            'country_code' => $countryCode !== null ? strtoupper($countryCode) : null,
            'latency_ms' => max(0, $latencyMs),
            'result_count' => max(0, $resultCount),
            'success' => $success,
            'error_message' => $error !== null ? mb_substr($error, 0, 255) : null,
            'searched_at' => now(),
        ]);

        Cache::forget(self::SUMMARY_CACHE.strtolower(trim($platformSlug)));
    }

    /**
     * @return array{
     *   samples: int,
     *   success_rate: float,
     *   avg_latency_ms: float,
     *   avg_result_count: float,
     *   freshness_score: float,
     *   composite_score: float
     * }
     */
    public function summary(string $platformSlug): array
    {
        $slug = strtolower(trim($platformSlug));
        if (! $this->isEnabled()) {
            return $this->emptySummary();
        }

        $ttl = (int) config('catalog.routing_cache_ttl_seconds', 300);

        return Cache::remember(self::SUMMARY_CACHE.$slug, $ttl, function () use ($slug) {
            $windowHours = (int) config('providers.intelligence.metrics_window_hours', 168);
            $since = now()->subHours($windowHours);

            $metrics = ProviderMetric::query()
                ->where('platform_slug', $slug)
                ->where('searched_at', '>=', $since)
                ->get(['success', 'latency_ms', 'result_count', 'searched_at']);

            if ($metrics->isEmpty()) {
                return $this->emptySummary();
            }

            $samples = $metrics->count();
            $successRate = $metrics->where('success', true)->count() / max(1, $samples);
            $avgLatency = (float) $metrics->avg('latency_ms');
            $avgResults = (float) $metrics->avg('result_count');
            $lastSearch = $metrics->max('searched_at');
            $freshness = $lastSearch
                ? max(0, 100 - min(100, now()->diffInHours($lastSearch) * 2))
                : 50.0;

            $weights = (array) config('providers.intelligence.weights', []);
            $latencyScore = max(0, 100 - min(100, $avgLatency / 50));
            $resultScore = min(100, $avgResults * 10);

            $composite = (
                ($successRate * 100) * (float) ($weights['success_rate'] ?? 0.35)
                + $latencyScore * (float) ($weights['latency'] ?? 0.25)
                + $resultScore * (float) ($weights['result_count'] ?? 0.20)
                + $freshness * (float) ($weights['trust_score'] ?? 0.20)
            );

            return [
                'samples' => $samples,
                'success_rate' => round($successRate, 3),
                'avg_latency_ms' => round($avgLatency, 1),
                'avg_result_count' => round($avgResults, 1),
                'freshness_score' => round($freshness, 1),
                'composite_score' => round($composite, 1),
            ];
        });
    }

    /**
     * Lower effective_priority = higher routing preference.
     */
    public function effectivePriority(string $platformSlug, int $basePriority, int $trustScore): int
    {
        $minSamples = (int) config('providers.intelligence.min_samples_for_adjustment', 5);
        $intel = $this->summary($platformSlug);

        if (($intel['samples'] ?? 0) < $minSamples) {
            return max(1, $basePriority - (int) floor($trustScore / 20));
        }

        $adjustment = (int) round((50 - ($intel['composite_score'] ?? 50)) / 5);

        return max(1, $basePriority + $adjustment);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function topProviders(int $limit = 20): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $windowHours = (int) config('providers.intelligence.metrics_window_hours', 168);
        $since = now()->subHours($windowHours);

        return ProviderMetric::query()
            ->selectRaw('platform_slug, COUNT(*) as samples, AVG(latency_ms) as avg_latency, AVG(result_count) as avg_results, SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) / COUNT(*) as success_rate')
            ->where('searched_at', '>=', $since)
            ->groupBy('platform_slug')
            ->orderByDesc('success_rate')
            ->orderBy('avg_latency')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'platform_slug' => $row->platform_slug,
                'samples' => (int) $row->samples,
                'success_rate' => round((float) $row->success_rate, 3),
                'avg_latency_ms' => round((float) $row->avg_latency, 1),
                'avg_result_count' => round((float) $row->avg_results, 1),
            ])
            ->all();
    }

    /**
     * @return array{
     *   samples: int,
     *   success_rate: float,
     *   avg_latency_ms: float,
     *   avg_result_count: float,
     *   freshness_score: float,
     *   composite_score: float
     * }
     */
    private function emptySummary(): array
    {
        return [
            'samples' => 0,
            'success_rate' => 0.0,
            'avg_latency_ms' => 0.0,
            'avg_result_count' => 0.0,
            'freshness_score' => 50.0,
            'composite_score' => 50.0,
        ];
    }
}
