<?php

namespace App\Services\Marketplace\Providers;

use App\Contracts\FederatedSearchProviderInterface;
use App\Services\Marketplace\MelodiaPxScraperService;
use App\Support\CategoryCatalog;
use App\Support\MelodiaPxCatalog;

class MelodiaPxSearchProvider implements FederatedSearchProviderInterface
{
    public function __construct(private MelodiaPxScraperService $scraper) {}

    public function sourceKey(): string
    {
        return 'melodiapx';
    }

    public function label(): string
    {
        return 'Melodia Px';
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
        return 3;
    }

    public function supportsCategory(string $category): bool
    {
        $category = CategoryCatalog::normalize($category);

        return in_array($category, ['fashion', 'sports_outdoor', 'marketplace'], true);
    }

    public function getSourceName(): string
    {
        return $this->label();
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $parsedQuery, array $expandedFilters): array
    {
        $items = $this->scraper->search($parsedQuery);

        return array_map(function (array $item) {
            $item['source'] = $this->label();
            $item['source_key'] = $this->sourceKey();
            $item['affiliate_ready'] = false;
            $item['sponsored'] = false;

            return $item;
        }, $items);
    }
}
