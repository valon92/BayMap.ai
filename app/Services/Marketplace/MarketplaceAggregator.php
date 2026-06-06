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
        $result = $this->coordinator->search($parsedQuery, $expandedFilters, $geo);

        return [
            'results' => $result['results'] ?? [],
            'report' => $result['report'] ?? [],
            'agent_plan' => $result['agent_plan'] ?? null,
            'valon' => $result['valon'] ?? null,
        ];
    }
}
