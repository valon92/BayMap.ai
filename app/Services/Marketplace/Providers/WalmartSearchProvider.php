<?php

namespace App\Services\Marketplace\Providers;

use App\Contracts\FederatedSearchProviderInterface;
use App\Services\Marketplace\Scrapers\ProductListingNormalizer;
use App\Services\Marketplace\WalmartCatalogService;
use App\Services\Marketplace\WalmartOAuthService;
use App\Support\CategoryCatalog;

class WalmartSearchProvider implements FederatedSearchProviderInterface
{
    public function __construct(
        private WalmartCatalogService $catalog,
        private WalmartOAuthService $oauth,
    ) {}

    public function sourceKey(): string
    {
        return 'walmart_us';
    }

    public function label(): string
    {
        return 'Walmart';
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
        return 5;
    }

    public function supportsCategory(string $category): bool
    {
        return ! in_array(CategoryCatalog::normalize($category), [
            'travel',
            'automotive',
            'automotive_parts',
            'real_estate',
        ], true);
    }

    public function getSourceName(): string
    {
        return $this->catalog->getSourceName();
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $parsedQuery, array $expandedFilters): array
    {
        $items = $this->catalog->search($parsedQuery, $expandedFilters);

        return ProductListingNormalizer::filterForIntent($items, $parsedQuery);
    }
}
