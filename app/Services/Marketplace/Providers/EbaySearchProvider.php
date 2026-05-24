<?php

namespace App\Services\Marketplace\Providers;

use App\Contracts\FederatedSearchProviderInterface;
use App\Services\Marketplace\EbayBrowseService;
use App\Services\Marketplace\EbayOAuthService;

class EbaySearchProvider implements FederatedSearchProviderInterface
{
    public function __construct(
        private EbayBrowseService $browse,
        private EbayOAuthService $oauth,
    ) {}

    public function sourceKey(): string
    {
        return 'ebay';
    }

    public function label(): string
    {
        return 'eBay';
    }

    public function mode(): string
    {
        return 'live';
    }

    public function isAvailable(): bool
    {
        return $this->oauth->isConfigured();
    }

    public function priority(): int
    {
        return 10;
    }

    public function supportsCategory(string $category): bool
    {
        return true;
    }

    public function getSourceName(): string
    {
        return $this->browse->getSourceName();
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $parsedQuery, array $expandedFilters): array
    {
        return $this->browse->search($parsedQuery, $expandedFilters);
    }
}
