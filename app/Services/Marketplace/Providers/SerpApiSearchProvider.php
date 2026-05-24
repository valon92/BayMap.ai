<?php

namespace App\Services\Marketplace\Providers;

use App\Contracts\FederatedSearchProviderInterface;
use App\Services\Marketplace\SerpApiShoppingService;

class SerpApiSearchProvider implements FederatedSearchProviderInterface
{
    public function __construct(private SerpApiShoppingService $serpApi) {}

    public function sourceKey(): string
    {
        return 'google_shopping';
    }

    public function label(): string
    {
        return 'Google Shopping';
    }

    public function mode(): string
    {
        return 'live';
    }

    public function isAvailable(): bool
    {
        return $this->serpApi->isConfigured();
    }

    public function priority(): int
    {
        return 20;
    }

    public function supportsCategory(string $category): bool
    {
        return true;
    }

    public function getSourceName(): string
    {
        return $this->serpApi->getSourceName();
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $parsedQuery, array $expandedFilters): array
    {
        return $this->serpApi->search($parsedQuery, $expandedFilters);
    }
}
