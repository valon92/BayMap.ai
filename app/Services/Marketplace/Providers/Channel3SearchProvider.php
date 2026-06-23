<?php

namespace App\Services\Marketplace\Providers;

use App\Contracts\FederatedSearchProviderInterface;
use App\Services\Marketplace\Channel3SearchService;
use App\Services\Marketplace\Scrapers\ProductListingNormalizer;
use App\Support\CategoryCatalog;

class Channel3SearchProvider implements FederatedSearchProviderInterface
{
    public function __construct(private Channel3SearchService $channel3) {}

    public function sourceKey(): string
    {
        return 'channel3';
    }

    public function label(): string
    {
        return 'Channel3';
    }

    public function mode(): string
    {
        return 'live';
    }

    public function isAvailable(): bool
    {
        return $this->channel3->isConfigured();
    }

    public function priority(): int
    {
        return 12;
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
        return $this->channel3->getSourceName();
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $parsedQuery, array $expandedFilters): array
    {
        $items = $this->channel3->search($parsedQuery, $expandedFilters);

        return ProductListingNormalizer::filterForIntent($items, $parsedQuery);
    }
}
