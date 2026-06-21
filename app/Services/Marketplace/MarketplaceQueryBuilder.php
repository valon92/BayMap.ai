<?php

namespace App\Services\Marketplace;

class MarketplaceQueryBuilder
{
    public function build(array $parsedQuery, array $geo = [], ?string $locationSuffix = null): string
    {
        if (($parsedQuery['category'] ?? '') === 'real_estate') {
            return $this->buildRealEstateQuery($parsedQuery, $geo, $locationSuffix);
        }

        if (! empty($parsedQuery['search_query'])) {
            $base = $parsedQuery['search_query'];
        } else {
            $parts = array_filter([
                $parsedQuery['brand'] ?? null,
                $parsedQuery['model'] ?? null,
                $parsedQuery['genre'] ?? null,
                $parsedQuery['product_type'] ?? null,
                $parsedQuery['color'] ?? null,
                $parsedQuery['style'] ?? null,
                isset($parsedQuery['year']) ? (string) $parsedQuery['year'] : null,
            ]);

            if (! empty($parsedQuery['raw_query'])) {
                $parts[] = $parsedQuery['raw_query'];
            }

            if (! empty($parsedQuery['description'])) {
                $parts[] = $parsedQuery['description'];
            }

            $base = trim(implode(' ', array_unique($parts)));

            if ($base === '') {
                $keywords = $parsedQuery['keywords'] ?? [];
                $base = is_array($keywords) && count($keywords)
                    ? implode(' ', $keywords)
                    : 'products';
            }
        }

        if ($locationSuffix) {
            $base = trim($base.' '.$locationSuffix);
        }

        return $base;
    }

    private function buildRealEstateQuery(array $parsedQuery, array $geo, ?string $locationSuffix): string
    {
        if (! empty($parsedQuery['search_query'])) {
            $base = $parsedQuery['search_query'];
        } else {
            $parts = array_filter([
                $parsedQuery['property_type'] ?? 'apartment',
                'banes',
                isset($parsedQuery['min_sqm']) ? ($parsedQuery['min_sqm'].'m2') : null,
                $parsedQuery['city'] ?? $geo['city'] ?? null,
                $parsedQuery['landmark_label'] ?? $parsedQuery['landmark'] ?? null,
            ]);

            if (! empty($parsedQuery['nearby_streets']) && is_array($parsedQuery['nearby_streets'])) {
                $parts[] = implode(' ', array_slice($parsedQuery['nearby_streets'], 0, 5));
            }

            if (! empty($parsedQuery['raw_query'])) {
                $parts[] = $parsedQuery['raw_query'];
            }

            $base = trim(implode(' ', $parts));
        }

        if ($locationSuffix) {
            $base = trim($base.' '.$locationSuffix);
        }

        return $base !== '' ? $base : 'apartment Ferizaj';
    }
}
