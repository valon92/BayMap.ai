<?php

namespace App\Services\Search;

use App\Support\CategoryCatalog;

/**
 * Caps ranked federated matches for pagination.
 */
class SearchResultPoolService
{

    /**
     * @param  array<int, array<string, mixed>>  $ranked
     * @param  array<string, mixed>  $parsed
     * @return array<int, array<string, mixed>>
     */
    public function expand(array $ranked, array $parsed): array
    {
        if ($ranked === []) {
            return [];
        }

        $target = $this->poolSize($parsed);

        // Only return real federated matches — never fabricate duplicate listings.
        return array_slice($ranked, 0, $target);
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function poolSize(array $parsed): int
    {
        $category = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');

        if (in_array($category, ['fashion', 'sports_outdoor'], true)) {
            $brand = mb_strtolower((string) ($parsed['brand'] ?? ''));
            $gender = mb_strtolower((string) ($parsed['gender'] ?? ''));
            if ($brand === 'puma' || in_array($gender, ['male', 'men', 'meshkuj'], true)) {
                return 300;
            }

            return 120;
        }

        return match ($category) {
            'automotive' => 200,
            'luxury_collectibles' => 72,
            default => 48,
        };
    }
}
