<?php

namespace App\Services\Search;

use App\Data\SearchListing;
use App\Services\Marketplace\Normalizers\ListingNormalizer;

/**
 * Product aggregation layer — standardizes, deduplicates, and unifies federated responses.
 */
class ProductAggregationService
{
    public function __construct(private ListingNormalizer $normalizer) {}

    /**
     * @param  array<int, array<string, mixed>>  $rawResults
     * @return array<int, array<string, mixed>>
     */
    public function aggregate(array $rawResults): array
    {
        $listings = $this->normalizer->normalizeMany($rawResults);

        return $this->normalizer->toArrays($this->deduplicateListings($listings));
    }

    /**
     * @param  array<int, SearchListing>  $listings
     * @return array<int, SearchListing>
     */
    private function deduplicateListings(array $listings): array
    {
        $seen = [];
        $unique = [];

        foreach ($listings as $listing) {
            $key = $listing->id.'|'.$listing->sourceKey;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $listing;
        }

        return $unique;
    }
}
