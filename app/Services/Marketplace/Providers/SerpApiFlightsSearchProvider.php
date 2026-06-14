<?php

namespace App\Services\Marketplace\Providers;

use App\Contracts\FederatedSearchProviderInterface;
use App\Services\Marketplace\SerpApiTravelBridgeService;
use App\Support\CategoryCatalog;

class SerpApiFlightsSearchProvider implements FederatedSearchProviderInterface
{
    public function __construct(private SerpApiTravelBridgeService $travel) {}

    public function sourceKey(): string
    {
        return 'google_flights';
    }

    public function label(): string
    {
        return 'BuyMap Travel';
    }

    public function mode(): string
    {
        return 'live';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function priority(): int
    {
        return 18;
    }

    public function supportsCategory(string $category): bool
    {
        return CategoryCatalog::normalize($category) === 'travel';
    }

    public function getSourceName(): string
    {
        return $this->travel->getSourceName();
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $parsedQuery, array $expandedFilters): array
    {
        return $this->travel->search($parsedQuery, $expandedFilters);
    }
}
