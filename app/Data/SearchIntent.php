<?php

namespace App\Data;

use App\Support\CategoryCatalog;

/**
 * Structured Search Intent Object — AI understanding layer output.
 * BuyMap does not sell products; it matches user intent to external providers.
 */
final class SearchIntent
{
    /**
     * @param  array<string, mixed>  $specifications
     * @param  array<string, mixed>  $location
     * @param  array<int, string>  $keywords
     * @param  array<string, mixed>  $optionalRequirements
     * @param  array<string, mixed>  $parsed
     */
    public function __construct(
        public readonly string $rawQuery,
        public readonly string $category,
        public readonly ?string $subcategory = null,
        public readonly ?string $productType = null,
        public readonly ?string $brand = null,
        public readonly ?string $model = null,
        public readonly array $specifications = [],
        public readonly ?string $color = null,
        public readonly ?string $size = null,
        public readonly ?float $maxPrice = null,
        public readonly ?float $minPrice = null,
        public readonly ?string $currency = null,
        public readonly ?string $condition = null,
        public readonly array $location = [],
        public readonly array $keywords = [],
        public readonly array $optionalRequirements = [],
        public readonly string $searchScope = 'targeted',
        public readonly bool $searchTarget = false,
        public readonly array $parsed = [],
    ) {}

    public function countryCode(): ?string
    {
        $code = strtoupper((string) ($this->location['country_code'] ?? ''));

        return $code !== '' ? $code : null;
    }

    public function countryName(): ?string
    {
        return isset($this->location['country']) ? (string) $this->location['country'] : null;
    }

    /**
     * @return array<int, array{search_country: string, search_country_code: string}>
     */
    public function countries(): array
    {
        $multi = $this->parsed['search_countries'] ?? [];

        return is_array($multi) ? $multi : [];
    }

    public function isMultiCountry(): bool
    {
        return count($this->countries()) > 1;
    }

    public function isUniversal(): bool
    {
        return $this->searchScope === 'universal'
            || in_array($this->countryCode() ?? '', ['WW', 'GLOBAL', '*'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'raw_query' => $this->rawQuery,
            'category' => $this->category,
            'subcategory' => $this->subcategory,
            'product_type' => $this->productType,
            'brand' => $this->brand,
            'model' => $this->model,
            'specifications' => $this->specifications,
            'color' => $this->color,
            'size' => $this->size,
            'max_price' => $this->maxPrice,
            'min_price' => $this->minPrice,
            'currency' => $this->currency,
            'condition' => $this->condition,
            'location' => $this->location,
            'keywords' => $this->keywords,
            'optional_requirements' => $this->optionalRequirements,
            'search_scope' => $this->searchScope,
            'search_target' => $this->searchTarget,
        ];
    }

    /**
     * Execution-ready parsed query (backward compatible with existing providers).
     *
     * @return array<string, mixed>
     */
    public function toParsedQuery(): array
    {
        return array_merge($this->parsed, [
            'raw_query' => $this->rawQuery,
            'category' => CategoryCatalog::normalize($this->category),
            'product_type' => $this->productType,
            'brand' => $this->brand,
            'model' => $this->model,
            'color' => $this->color,
            'size' => $this->size,
            'max_price' => $this->maxPrice,
            'min_price' => $this->minPrice,
            'currency' => $this->currency,
            'condition' => $this->condition,
            'search_scope' => $this->searchScope,
            'search_target' => $this->searchTarget,
            'search_country_code' => $this->location['country_code'] ?? null,
            'search_country' => $this->location['country'] ?? null,
        ]);
    }
}
