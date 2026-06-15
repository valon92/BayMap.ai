<?php

namespace App\Services\Marketplace\Providers;

use App\Contracts\FederatedSearchProviderInterface;
use App\Services\Marketplace\LivePlatformScraperService;
use App\Support\CategoryCatalog;
use App\Support\KosovoFashionPlatforms;
use App\Support\KosovoToyIntent;
use App\Support\KosovoToyPlatforms;
use App\Support\LivePlatformRegistry;
use App\Support\ProductCategoryResolver;

class LivePlatformSearchProvider implements FederatedSearchProviderInterface
{
    /** @param  array<string, mixed>  $platformMeta */
    public function __construct(
        private string $platformKey,
        private array $platformMeta,
        private LivePlatformScraperService $scraper,
    ) {}

    public function sourceKey(): string
    {
        return $this->platformKey;
    }

    public function label(): string
    {
        return (string) ($this->platformMeta['label'] ?? $this->platformKey);
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
        return (int) ($this->platformMeta['priority'] ?? 50);
    }

    public function supportsCategory(string $category): bool
    {
        $category = CategoryCatalog::normalize($category);
        $cats = (array) ($this->platformMeta['categories'] ?? []);

        return in_array($category, $cats, true) || in_array('marketplace', $cats, true);
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
        if (KosovoFashionPlatforms::isPlatform($this->platformKey)
            && ! ProductCategoryResolver::isFashionPlatformRelevant($parsedQuery)) {
            return [];
        }

        if (KosovoToyPlatforms::isPlatform($this->platformKey)
            && ! KosovoToyIntent::isToySearch($parsedQuery)) {
            return [];
        }

        $items = $this->scraper->search($this->platformKey, $parsedQuery);

        return array_map(function (array $item) {
            $item['source'] = $this->label();
            $item['source_key'] = $this->platformKey;
            $item['affiliate_ready'] = false;
            $item['sponsored'] = false;

            return $item;
        }, $items);
    }
}
