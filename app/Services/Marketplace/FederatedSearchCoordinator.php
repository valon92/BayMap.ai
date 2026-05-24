<?php

namespace App\Services\Marketplace;

use App\Contracts\FederatedSearchProviderInterface;
use App\Support\CategoryCatalog;
use App\Support\DutchCarMarketplaces;
use App\Support\KosovoMarketplaces;
use App\Support\SwissCarMarketplaces;

/**
 * Real-time federated search coordinator.
 * Queries multiple marketplace connectors in parallel tiers without local DB storage.
 */
class FederatedSearchCoordinator
{
    public function __construct(private ProviderRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @param  array<string, mixed>  $geo
     * @return array{results: array<int, array<string, mixed>>, report: array<int, array<string, mixed>>}
     */
    public function search(array $parsedQuery, array $expandedFilters, array $geo = []): array
    {
        $providers = $this->registry->forSearch($parsedQuery, $expandedFilters, $geo);
        $tiers = $expandedFilters['location_tiers'] ?? [['suffix' => '', 'label' => 'International', 'level' => 'international']];
        $searchCountry = $parsedQuery['search_country'] ?? $geo['country'] ?? '';

        $results = [];
        $report = [];
        $liveResultCount = 0;
        $liveCap = (int) config('marketplaces.live_result_cap', 16);
        $skipMockAt = (int) config('marketplaces.skip_mock_when_live_at_least', 8);

        foreach ($providers as $provider) {
            if ($provider->mode() === 'demo' && $liveResultCount >= $skipMockAt) {
                $report[] = $this->reportRow($provider, 'skipped', 0, 'live_results_sufficient', '');

                continue;
            }

            if ($provider->mode() === 'live' && ! $provider->isAvailable()) {
                $report[] = $this->reportRow($provider, 'skipped', 0, 'not_configured', '');

                continue;
            }

            $tierHits = 0;
            $mode = $provider->mode() === 'live' ? 'live' : 'demo';
            $status = $provider->mode() === 'live' ? 'ok' : match (strtoupper((string) ($parsedQuery['search_country_code'] ?? $geo['country_code'] ?? ''))) {
                'CH' => CategoryCatalog::isAutomotive($parsedQuery['category'] ?? '') ? 'swiss_car_marketplace' : 'mock_data',
                'NL' => CategoryCatalog::isAutomotive($parsedQuery['category'] ?? '') ? 'dutch_car_marketplace' : 'mock_data',
                'XK' => KosovoMarketplaces::isKosovoPlatform($provider->sourceKey()) ? 'kosovo_marketplace' : 'mock_data',
                default => 'mock_data',
            };

            foreach ($tiers as $tier) {
                if ($provider->mode() === 'live' && $liveResultCount >= $liveCap) {
                    break 2;
                }

                $expandedFilters['location_suffix'] = $tier['suffix'] ?? '';
                $expandedFilters['location_tier'] = $tier;

                $started = microtime(true);
                $items = $provider->search($parsedQuery, $expandedFilters);
                $latencyMs = (int) round((microtime(true) - $started) * 1000);

                $tierHits += count($items);
                if ($provider->mode() === 'live') {
                    $liveResultCount += count($items);
                }

                foreach ($items as $item) {
                    $item['_provider_latency_ms'] = $latencyMs;
                    $results[] = $item;
                }

                if ($provider->mode() === 'live' && count($items) >= 6) {
                    break;
                }
            }

            $report[] = $this->reportRow(
                $provider,
                $mode,
                $tierHits,
                $status,
                $searchCountry ?: ($geo['city'] ?? $geo['country'] ?? ''),
            );
        }

        return [
            'results' => $results,
            'report' => $report,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportRow(
        FederatedSearchProviderInterface $provider,
        string $mode,
        int $count,
        string $status,
        string $location,
    ): array {
        $key = $provider->sourceKey();

        return [
            'source' => $key,
            'label' => KosovoMarketplaces::label($key) ?: DutchCarMarketplaces::label($key) ?: SwissCarMarketplaces::label($key) ?: $provider->label(),
            'mode' => $mode,
            'count' => $count,
            'status' => $status,
            'location' => $location,
        ];
    }
}
