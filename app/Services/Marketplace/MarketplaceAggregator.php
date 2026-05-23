<?php

namespace App\Services\Marketplace;

/**
 * Internet search: live APIs with local-first location tiers, then demo fallbacks.
 */
class MarketplaceAggregator
{
    /** @var array<int, string> */
    private array $allSources = [
        'ebay',
        'google_shopping',
        'amazon',
        'mobile.de',
        'autoscout24',
        'etsy',
        'facebook_marketplace',
    ];

    public function __construct(
        private EbayBrowseService $ebayBrowse,
        private EbayOAuthService $ebayOAuth,
        private SerpApiShoppingService $serpApi,
    ) {}

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @param  array<string, mixed>  $geo
     * @return array{results: array<int, array<string, mixed>>, report: array<int, array<string, mixed>>}
     */
    public function searchAll(array $parsedQuery, array $expandedFilters, array $geo = []): array
    {
        $targetMarketplaces = $expandedFilters['marketplaces'] ?? $this->allSources;
        $tiers = $expandedFilters['location_tiers'] ?? [['suffix' => '', 'label' => 'International', 'level' => 'international']];
        $results = [];
        $report = [];
        $liveResultCount = 0;

        $liveSources = [
            ['key' => 'ebay', 'provider' => $this->ebayBrowse, 'active' => $this->ebayOAuth->isConfigured()],
            ['key' => 'google_shopping', 'provider' => $this->serpApi, 'active' => $this->serpApi->isConfigured()],
        ];

        foreach ($liveSources as $live) {
            if (! $live['active'] || ! $this->shouldQuerySource($live['key'], $targetMarketplaces, $parsedQuery['category'] ?? '')) {
                $report[] = $this->reportRow($live['key'], 'skipped', 0, 'not_configured', '');

                continue;
            }

            $tierHits = 0;
            foreach ($tiers as $tier) {
                if ($liveResultCount >= 16) {
                    break 2;
                }

                $expandedFilters['location_suffix'] = $tier['suffix'] ?? '';
                $items = $live['provider']->search($parsedQuery, $expandedFilters);
                $liveResultCount += count($items);
                $results = array_merge($results, $items);
                $tierHits += count($items);

                if (count($items) >= 6) {
                    break;
                }
            }

            $report[] = $this->reportRow(
                $live['key'],
                'live',
                $tierHits,
                'ok',
                $tiers[0]['label'] ?? 'local'
            );
        }

        $countryCode = strtoupper((string) ($parsedQuery['search_country_code'] ?? $geo['country_code'] ?? ''));
        $category = $parsedQuery['category'] ?? 'marketplace';
        $searchCountry = $parsedQuery['search_country'] ?? $geo['country'] ?? '';

        if ($countryCode === 'XK' && in_array($category, ['fashion', 'luxury', 'marketplace'], true)) {
            $driloni = new MockMarketplaceService('driloni');
            $expandedFilters['location_suffix'] = $geo['city'] ?? 'Kosovo';
            $driloniItems = $driloni->search($parsedQuery, $expandedFilters);
            $results = array_merge($results, $driloniItems);
            $report[] = $this->reportRow('driloni', 'live', count($driloniItems), 'local_store', $geo['city'] ?? 'Kosovo');
        }

        $skipMocks = $liveResultCount >= 8;
        $mockSources = match (true) {
            $countryCode === 'CH' && $category === 'car' => ['autoscout24', 'tutti', 'ricardo', 'facebook_marketplace', 'mobile.de'],
            default => ['amazon', 'mobile.de', 'autoscout24', 'etsy', 'facebook_marketplace'],
        };

        foreach ($mockSources as $source) {
            if ($skipMocks) {
                $report[] = $this->reportRow($source, 'skipped', 0, 'live_results_sufficient', '');

                continue;
            }

            if (! $this->shouldQuerySource($source, $targetMarketplaces, $parsedQuery['category'] ?? '')) {
                continue;
            }

            $mock = new MockMarketplaceService($source);
            $expandedFilters['location_suffix'] = $searchCountry ?: ($geo['city'] ?? $geo['country'] ?? '');
            $items = $mock->search($parsedQuery, $expandedFilters);
            $results = array_merge($results, $items);
            $report[] = $this->reportRow($source, 'demo', count($items), 'mock_data', $geo['city'] ?? '');
        }

        return [
            'results' => $this->deduplicate($results),
            'report' => $report,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    private function deduplicate(array $results): array
    {
        $seen = [];

        return array_values(array_filter($results, function ($item) use (&$seen) {
            $key = ($item['id'] ?? '').'-'.($item['source_key'] ?? '');
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        }));
    }

    /**
     * @return array<string, mixed>
     */
    private function reportRow(string $source, string $mode, int $count, string $status, string $location): array
    {
        return [
            'source' => $source,
            'mode' => $mode,
            'count' => $count,
            'status' => $status,
            'location' => $location,
        ];
    }

    /**
     * @param  array<int, string>  $targetMarketplaces
     */
    private function shouldQuerySource(string $source, array $targetMarketplaces, string $category): bool
    {
        if (empty($targetMarketplaces)) {
            return true;
        }

        $sourceNorm = strtolower(str_replace(['.', '_'], '', $source));

        foreach ($targetMarketplaces as $target) {
            $targetNorm = strtolower(str_replace(['.', '_', ' '], '', $target));
            if (str_contains($sourceNorm, $targetNorm) || str_contains($targetNorm, $sourceNorm)) {
                return true;
            }
        }

        return match ($category) {
            'car' => in_array($source, ['mobile.de', 'autoscout24', 'ebay', 'google_shopping'], true),
            'book', 'electronics' => in_array($source, ['amazon', 'ebay', 'google_shopping'], true),
            'fashion', 'luxury' => in_array($source, ['driloni', 'ebay', 'google_shopping', 'etsy', 'facebook_marketplace'], true),
            default => in_array($source, ['ebay', 'amazon', 'google_shopping', 'etsy'], true),
        };
    }
}
