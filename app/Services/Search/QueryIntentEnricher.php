<?php

namespace App\Services\Search;

use App\Support\AutomotiveIntentParser;
use App\Support\BookIntentParser;
use App\Support\CategoryCatalog;
use App\Support\ElectronicsIntentParser;
use App\Support\FashionIntentParser;
use App\Support\PriceIntentParser;
use App\Support\SearchCountryResolver;

/**
 * Location policy: query-named country wins; otherwise visitor IP geo.
 */
class QueryIntentEnricher
{
    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public function enrich(array $parsed, string $rawQuery): array
    {
        $countryFromQuery = SearchCountryResolver::fromQuery($rawQuery);
        if (! empty($countryFromQuery['search_country_code'])) {
            $parsed = array_merge($parsed, $countryFromQuery);
            $parsed['country'] = $countryFromQuery['search_country'];
        } elseif (! empty($parsed['search_country_code'])) {
            $parsed['search_target'] = true;
            $parsed['country'] = $parsed['search_country'] ?? $parsed['country'] ?? null;
        }

        $parsed['search_target'] = ! empty($parsed['search_country_code']);
        $parsed['location_source'] = $parsed['search_target'] ? 'query' : 'ip';

        $priceFromQuery = PriceIntentParser::fromQuery($rawQuery);
        if (! empty($priceFromQuery['max_price'])) {
            $parsed['max_price'] = $priceFromQuery['max_price'];
        }
        if (! empty($priceFromQuery['currency'])) {
            $parsed['currency'] = $priceFromQuery['currency'];
        }

        if (preg_match('/\b(q\d|x\d|a\d)\b/i', $rawQuery, $m)) {
            $parsed['model'] = strtoupper($m[1]);
        }

        if (CategoryCatalog::isAutomotive($parsed['category'] ?? '') && empty($parsed['currency'])) {
            $parsed['currency'] = 'EUR';
        }

        $parsed = self::mergeBookIntent($parsed, $rawQuery);
        if (CategoryCatalog::isBookSearch($parsed)) {
            $parsed['category'] = 'online_education';
        }

        $parsed = self::mergeFashionIntent($parsed, $rawQuery);
        $parsed = self::mergeElectronicsIntent($parsed, $rawQuery);
        $parsed = self::mergeAutomotiveIntent($parsed, $rawQuery);

        if (CategoryCatalog::isBookSearch($parsed)) {
            $parsed['category'] = 'online_education';
        }

        if (! empty($parsed['model'])) {
            $parsed['model'] = ElectronicsIntentParser::normalizeIphoneModel((string) $parsed['model'])
                ?? $parsed['model'];
        }

        if (! empty($parsed['gender'])) {
            $parsed['gender'] = CategoryCatalog::normalizeGender((string) $parsed['gender']);
        }

        if (CategoryCatalog::isAutomotive($parsed['category'] ?? '')) {
            $parsed = AutomotiveIntentParser::normalizeYearFields($parsed);
            if (! empty($parsed['year_min']) && ! empty($parsed['year_max']) && (int) $parsed['year_min'] !== (int) $parsed['year_max']) {
                unset($parsed['year']);
            }
        }

        return array_filter($parsed, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private static function mergeBookIntent(array $parsed, string $rawQuery): array
    {
        $book = BookIntentParser::fromQuery($rawQuery);
        if ($book === []) {
            $type = mb_strtolower((string) ($parsed['product_type'] ?? ''));
            if (in_array($type, ['book', 'libër', 'liber', 'librin'], true)) {
                $parsed['category'] = 'online_education';
                $parsed['product_type'] = 'book';
            }

            return $parsed;
        }

        foreach ($book as $key => $value) {
            if (empty($parsed[$key])) {
                $parsed[$key] = $value;
            }
        }

        $parsed['category'] = 'online_education';
        $parsed['product_type'] = 'book';

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private static function mergeAutomotiveIntent(array $parsed, string $rawQuery): array
    {
        $auto = AutomotiveIntentParser::fromQuery($rawQuery);
        if ($auto === []) {
            return $parsed;
        }

        foreach ($auto as $key => $value) {
            if (empty($parsed[$key])) {
                $parsed[$key] = $value;
            }
        }

        $yearFromQuery = AutomotiveIntentParser::parseYearFields($rawQuery);
        if (
            ! empty($yearFromQuery['year_min'])
            && ! empty($yearFromQuery['year_max'])
            && $yearFromQuery['year_max'] !== $yearFromQuery['year_min']
        ) {
            $parsed = array_merge($parsed, $yearFromQuery);
        } elseif ($yearFromQuery !== [] && empty($parsed['year_min'])) {
            $parsed = array_merge($parsed, $yearFromQuery);
        }

        if (CategoryCatalog::normalize($parsed['category'] ?? 'marketplace') === 'marketplace') {
            $parsed['category'] = 'automotive';
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private static function mergeElectronicsIntent(array $parsed, string $rawQuery): array
    {
        $electronics = ElectronicsIntentParser::fromQuery($rawQuery);
        if ($electronics === []) {
            return $parsed;
        }

        foreach ($electronics as $key => $value) {
            if ($key === 'features') {
                $parsed['features'] = array_values(array_unique(array_merge(
                    is_array($parsed['features'] ?? null) ? $parsed['features'] : [],
                    is_array($value) ? $value : [$value],
                )));

                continue;
            }

            if (empty($parsed[$key])) {
                $parsed[$key] = $value;
            }
        }

        if (! empty($parsed['color']) && in_array($parsed['color'], ['zezë', 'zeze', 'e zezë', 'e zeze'], true)) {
            $parsed['color'] = 'black';
        }

        $category = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');
        if ($category === 'marketplace') {
            $parsed['category'] = 'electronics_tech';
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private static function mergeFashionIntent(array $parsed, string $rawQuery): array
    {
        $fashion = FashionIntentParser::fromQuery($rawQuery);
        if ($fashion === []) {
            return $parsed;
        }

        foreach ($fashion as $key => $value) {
            if (empty($parsed[$key])) {
                $parsed[$key] = $value;
            }
        }

        $category = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');
        if ($category === 'marketplace' || $category === 'sports_outdoor') {
            $parsed['category'] = 'fashion';
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $visitorGeo
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public function searchGeo(array $visitorGeo, array $parsed): array
    {
        if (empty($parsed['search_target'])) {
            return $visitorGeo;
        }

        return array_merge($visitorGeo, [
            'country' => $parsed['search_country'] ?? $visitorGeo['country'],
            'country_code' => $parsed['search_country_code'],
            'city' => null,
            'search_target' => true,
            'location_source' => 'query',
        ]);
    }

    /**
     * Parsed attributes used for ranking (IP country when buyer did not name a place).
     *
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $visitorGeo
     * @return array<string, mixed>
     */
    public function rankingContext(array $parsed, array $visitorGeo): array
    {
        if (! empty($parsed['search_target'])) {
            return $parsed;
        }

        return array_merge($parsed, array_filter([
            'search_country' => $visitorGeo['country'] ?? null,
            'search_country_code' => $visitorGeo['country_code'] ?? null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $visitorGeo
     * @param  array<string, mixed>  $searchGeo
     * @return array<string, mixed>
     */
    public function locationMeta(array $parsed, array $visitorGeo, array $searchGeo): array
    {
        if (! empty($parsed['search_target'])) {
            return [
                'mode' => 'query',
                'label' => $parsed['search_country'] ?? $searchGeo['country'] ?? '',
                'target_country' => $parsed['search_country'] ?? null,
                'target_country_code' => $parsed['search_country_code'] ?? null,
                'visitor_city' => $visitorGeo['city'] ?? null,
                'visitor_country' => $visitorGeo['country'] ?? null,
            ];
        }

        $city = $visitorGeo['city'] ?? null;
        $country = $visitorGeo['country'] ?? null;

        return [
            'mode' => 'ip',
            'label' => trim(($city ? $city.', ' : '').($country ?? '')),
            'target_country' => null,
            'target_country_code' => null,
            'visitor_city' => $city,
            'visitor_country' => $country,
        ];
    }

    /**
     * Default client filters merged before search (country lock when buyer named a place).
     *
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $clientFilters
     * @return array<string, mixed>
     */
    public function mergeDefaultFilters(array $parsed, array $clientFilters): array
    {
        $defaults = [];

        if (! empty($parsed['search_target']) && ! empty($parsed['search_country'])) {
            $defaults['country'] = $parsed['search_country'];
        }

        if (! empty($parsed['max_price'])
            && ! isset($clientFilters['price_max'])
            && ! isset($clientFilters['price'])) {
            $defaults['price_max'] = (float) $parsed['max_price'];
        }

        foreach (['brand', 'size', 'product_type', 'color', 'fuel', 'year_min', 'year_max', 'model'] as $key) {
            if (! empty($parsed[$key]) && ! isset($clientFilters[$key])) {
                $defaults[$key] = $parsed[$key];
            }
        }

        if (! empty($parsed['gender']) && ! isset($clientFilters['gender'])) {
            $defaults['gender'] = CategoryCatalog::normalizeGender((string) $parsed['gender']);
        }

        return array_merge($defaults, $clientFilters);
    }
}
