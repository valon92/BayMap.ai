<?php

namespace App\Contracts;

/**
 * Contract for marketplace search providers.
 * Implement this interface when plugging in real APIs (mobile.de, eBay, etc.).
 */
interface MarketplaceSearchInterface
{
    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $parsedQuery, array $expandedFilters): array;

    public function getSourceName(): string;
}
