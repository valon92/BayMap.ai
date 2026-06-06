<?php

namespace App\Services\Marketplace;

use App\Services\Valon\ValonOrchestrator;

/**
 * Federated search facade — delegates to Valon AI multi-agent orchestrator.
 */
class FederatedSearchCoordinator
{
    public function __construct(private ValonOrchestrator $valon) {}

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @param  array<string, mixed>  $geo
     * @return array{results: array<int, array<string, mixed>>, report: array<int, array<string, mixed>>, agent_plan?: array<string, mixed>, valon?: array<string, mixed>}
     */
    public function search(array $parsedQuery, array $expandedFilters, array $geo = []): array
    {
        return $this->valon->orchestrate($parsedQuery, $expandedFilters, $geo);
    }
}
