<?php

namespace App\Services\Marketplace;

/**
 * Intelligent marketplace aggregator facade.
 * Delegates to the federated search coordinator (real-time multi-source, no local DB).
 */
class MarketplaceAggregator
{
    public function __construct(private FederatedSearchCoordinator $coordinator) {}

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @param  array<string, mixed>  $geo
     * @return array{results: array<int, array<string, mixed>>, report: array<int, array<string, mixed>>}
     */
    public function searchAll(array $parsedQuery, array $expandedFilters, array $geo = []): array
    {
        $countries = $parsedQuery['search_countries'] ?? [];
        if (! is_array($countries) || count($countries) <= 1) {
            $result = $this->coordinator->search($parsedQuery, $expandedFilters, $geo);

            return [
                'results' => $result['results'] ?? [],
                'report' => $result['report'] ?? [],
                'agent_plan' => $result['agent_plan'] ?? null,
                'valon' => $result['valon'] ?? null,
            ];
        }

        $results = [];
        $report = [];
        $workers = [];
        $agentPlans = [];
        $countryCount = count($countries);

        foreach ($countries as $country) {
            if (empty($country['search_country_code'])) {
                continue;
            }

            $perCountry = $parsedQuery;
            $perCountry['search_country_code'] = $country['search_country_code'];
            $perCountry['search_country'] = $country['search_country'] ?? '';
            unset($perCountry['search_countries']);

            $expanded = $expandedFilters;
            $expanded['search_country_code'] = $country['search_country_code'];
            $expanded['marketplaces'] = \App\Support\LivePlatformRegistry::keysFromParsed($perCountry);
            $expanded['_multi_country_search'] = true;
            $expanded['_multi_country_count'] = $countryCount;

            $batch = $this->coordinator->search($perCountry, $expanded, $geo);
            $results = array_merge($results, $batch['results'] ?? []);
            $report = array_merge($report, $batch['report'] ?? []);
            $workers = array_merge($workers, $batch['valon']['workers'] ?? []);
            $agentPlans[] = $batch['agent_plan'] ?? [];
        }

        $activated = [];
        $sourceKeys = [];
        foreach ($agentPlans as $plan) {
            $activated = array_merge($activated, $plan['activated'] ?? []);
            $sourceKeys = array_merge($sourceKeys, $plan['source_keys'] ?? []);
        }

        return [
            'results' => $results,
            'report' => $report,
            'agent_plan' => [
                'activated' => $activated,
                'source_keys' => array_values(array_unique($sourceKeys)),
                'count' => count($workers),
                'multi_country' => true,
            ],
            'valon' => [
                'workers_spawned' => count($workers),
                'workers' => $workers,
                'results_merged' => count($results),
                'multi_country' => true,
                'countries' => array_column($countries, 'search_country'),
            ],
        ];
    }
}
