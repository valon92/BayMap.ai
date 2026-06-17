<?php

namespace App\Services\Marketplace\Providers;

use App\Contracts\FederatedSearchProviderInterface;
use App\Services\Marketplace\MockMarketplaceService;
use App\Support\CategoryCatalog;

class MockSearchProvider implements FederatedSearchProviderInterface
{
    /** @var array<int, string> */
    private array $categories;

    /** @var array<int, string>|null */
    private ?array $countries;

    /**
     * @param  array<int, string>  $categories
     * @param  array<int, string>|null  $countries
     */
    public function __construct(
        private string $sourceKey,
        private string $sourceLabel,
        private int $priority,
        array $categories,
        ?array $countries = null,
    ) {
        $this->categories = $categories;
        $this->countries = $countries;
    }

    public function sourceKey(): string
    {
        return $this->sourceKey;
    }

    public function label(): string
    {
        return $this->sourceLabel;
    }

    public function mode(): string
    {
        return 'demo';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function supportsCategory(string $category): bool
    {
        $category = CategoryCatalog::normalize($category);

        if (in_array('*', $this->categories, true)) {
            return true;
        }

        return in_array($category, $this->categories, true);
    }

    /**
     * @param  array<string, mixed>  $geoCountryCode  passed via expanded filters
     */
    public function supportsCountry(?string $countryCode): bool
    {
        if ($this->countries === null || $this->countries === []) {
            return true;
        }

        return in_array(strtoupper((string) $countryCode), $this->countries, true);
    }

    public function getSourceName(): string
    {
        return $this->sourceLabel;
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $parsedQuery, array $expandedFilters): array
    {
        $mock = new MockMarketplaceService($this->sourceKey);

        return $mock->search($parsedQuery, $expandedFilters);
    }
}
