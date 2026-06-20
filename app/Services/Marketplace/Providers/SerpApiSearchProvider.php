<?php

namespace App\Services\Marketplace\Providers;

use App\Contracts\FederatedSearchProviderInterface;
use App\Services\Marketplace\SerpApiShoppingService;
use App\Services\Marketplace\Scrapers\ProductListingNormalizer;
use App\Support\CategoryCatalog;
use App\Support\HomeFurnitureIntentParser;

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
        $items = $this->serpApi->search($parsedQuery, $expandedFilters);
        $items = ProductListingNormalizer::filterForIntent($items, $parsedQuery);

        if (CategoryCatalog::normalize($parsedQuery['category'] ?? '') !== 'home_furniture') {
            return $items;
        }

        $countryCode = strtoupper((string) ($parsedQuery['search_country_code'] ?? ''));

        return array_map(function (array $item) use ($parsedQuery, $countryCode): array {
            $title = (string) ($item['title'] ?? '');
            $item['category'] = 'home_furniture';
            $item['product_type'] = HomeFurnitureIntentParser::isKitchenSearch($parsedQuery)
                ? 'kitchen'
                : (HomeFurnitureIntentParser::productTypeFromTitle($title) ?? 'furniture');
            if ($countryCode !== '' && empty($item['country_code'])) {
                $item['country_code'] = $countryCode;
            }

            return $item;
        }, $items);
    }
}
