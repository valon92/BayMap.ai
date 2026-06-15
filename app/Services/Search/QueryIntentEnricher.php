<?php

namespace App\Services\Search;

use App\Support\AutomotiveIntentParser;
use App\Support\BookIntentParser;
use App\Support\CategoryCatalog;
use App\Support\ElectronicsIntentParser;
use App\Support\FashionIntentParser;
use App\Support\PriceIntentParser;
use App\Support\ProductCategoryResolver;
use App\Support\SearchCountryResolver;
use App\Support\SearchScopeResolver;
use App\Support\TravelIntentParser;
use App\Support\WebServicesIntentParser;

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
        $countriesFromQuery = SearchCountryResolver::allFromQuery($rawQuery);
        if (count($countriesFromQuery) > 1) {
            $parsed['search_countries'] = $countriesFromQuery;
            $parsed['search_country'] = implode(', ', array_column($countriesFromQuery, 'search_country'));
            $parsed['search_country_code'] = $countriesFromQuery[0]['search_country_code'];
            $parsed['country'] = $parsed['search_country'];
        } elseif (count($countriesFromQuery) === 1) {
            $parsed = array_merge($parsed, $countriesFromQuery[0]);
            $parsed['country'] = $countriesFromQuery[0]['search_country'];
            unset($parsed['search_countries']);
        } elseif ($countriesFromQuery === [] && ! empty($parsed['search_country_code'])) {
            unset($parsed['search_countries']);
        }

        $parsed = self::mergeCityHint($parsed, $rawQuery);
        $parsed = SearchScopeResolver::applyFromQuery($parsed, $rawQuery);
        $parsed = ProductCategoryResolver::enrich($parsed, $rawQuery);
        $parsed['raw_query'] = $rawQuery;

        if ($countriesFromQuery === [] && ! empty($parsed['search_country_code'])) {
            $parsed['search_target'] = true;
            $parsed['country'] = $parsed['search_country'] ?? $parsed['country'] ?? null;
        }

        if (SearchScopeResolver::isUniversal($parsed)) {
            $parsed['search_target'] = true;
            $parsed['location_source'] = 'query';
        } else {
            $parsed['search_target'] = ! empty($parsed['search_country_code']);
            $parsed['location_source'] = $parsed['search_target'] ? 'query' : 'ip';
        }

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
        $parsed = self::mergeRealEstateIntent($parsed, $rawQuery);
        $parsed = TravelIntentParser::fromQuery($rawQuery, $parsed);
        $parsed = WebServicesIntentParser::fromQuery($rawQuery, $parsed);

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

        if (! CategoryCatalog::isElectronics($parsed['category'] ?? '')
            && CategoryCatalog::normalize($parsed['category'] ?? '') !== 'gaming_entertainment') {
            unset($parsed['features']);
        }

        if (in_array(CategoryCatalog::normalize($parsed['category'] ?? ''), ['fashion', 'sports_outdoor'], true)
            && ! empty($parsed['product_type'])) {
            $parsed['product_type'] = FashionIntentParser::normalizeType((string) $parsed['product_type']);
        }

        if (CategoryCatalog::normalize($parsed['category'] ?? '') === 'travel') {
            unset($parsed['gender'], $parsed['year'], $parsed['year_min'], $parsed['year_max']);
        }

        if (WebServicesIntentParser::isActive($parsed)) {
            $parsed = WebServicesIntentParser::finalize($parsed);
        }

        if (CategoryCatalog::isAutomotive($parsed['category'] ?? '')) {
            $parsed = AutomotiveIntentParser::normalizeYearFields($parsed);
            if (! empty($parsed['year_min']) && ! empty($parsed['year_max']) && (int) $parsed['year_min'] !== (int) $parsed['year_max']) {
                unset($parsed['year']);
            }
        }

        if (! empty($parsed['color']) && in_array($parsed['color'], ['zezë', 'zeze', 'e zezë', 'e zeze'], true)) {
            $parsed['color'] = 'black';
        }
        if (! empty($parsed['color']) && in_array($parsed['color'], ['bardhë', 'bardhe', 'e bardhë', 'e bardhe'], true)) {
            $parsed['color'] = 'white';
        }
        if (empty($parsed['fuel'])) {
            $rawFuel = mb_strtolower($rawQuery);
            if (preg_match('/\b(disel|diesel|dizel|tdi)\b/ui', $rawFuel)) {
                $parsed['fuel'] = 'diesel';
            } elseif (preg_match('/\b(benzin|petrol|tfsi|benzinë)\b/ui', $rawFuel)) {
                $parsed['fuel'] = 'petrol';
            }
        } elseif (! empty($parsed['fuel'])) {
            $parsed['fuel'] = AutomotiveIntentParser::normalizeFuel((string) $parsed['fuel']);
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
        if (CategoryCatalog::normalize($parsed['category'] ?? '') === 'travel') {
            return $parsed;
        }

        if (AutomotiveIntentParser::isCarQuery($rawQuery)) {
            $parsed['category'] = 'automotive';
        }

        $auto = AutomotiveIntentParser::fromQuery($rawQuery);
        if ($auto === [] && ! AutomotiveIntentParser::isCarQuery($rawQuery)) {
            return $parsed;
        }

        foreach ($auto as $key => $value) {
            if (empty($parsed[$key])) {
                $parsed[$key] = $value;
            }
        }

        $yearFromQuery = AutomotiveIntentParser::parseYearFields($rawQuery);
        if (
            CategoryCatalog::isAutomotive($parsed['category'] ?? '')
            && ! empty($yearFromQuery['year_min'])
            && ! empty($yearFromQuery['year_max'])
            && $yearFromQuery['year_max'] !== $yearFromQuery['year_min']
        ) {
            $parsed = array_merge($parsed, $yearFromQuery);
        } elseif (CategoryCatalog::isAutomotive($parsed['category'] ?? '') && $yearFromQuery !== [] && empty($parsed['year_min'])) {
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
    private static function mergeRealEstateIntent(array $parsed, string $rawQuery): array
    {
        if (CategoryCatalog::normalize($parsed['category'] ?? '') !== 'real_estate') {
            return $parsed;
        }

        if (preg_match('/(\d{2,4})\s*(m2|m²|sqm|metra|meter)/ui', $rawQuery, $m)) {
            $parsed['min_sqm'] = (int) $m[1];
        }

        if (empty($parsed['currency']) && strtoupper((string) ($parsed['search_country_code'] ?? '')) === 'GB') {
            $parsed['currency'] = 'GBP';
        }

        if (empty($parsed['property_type'])) {
            $parsed['property_type'] = preg_match('/\b(banes|banesa|apartament|apartment|flat)\b/ui', $rawQuery)
                ? 'apartment'
                : ($parsed['property_type'] ?? null);
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private static function mergeCityHint(array $parsed, string $rawQuery): array
    {
        $lower = mb_strtolower($rawQuery);

        $cities = [
            'new york' => ['city' => 'New York', 'zip' => '10001'],
            'newyork' => ['city' => 'New York', 'zip' => '10001'],
            'nyc' => ['city' => 'New York', 'zip' => '10001'],
            'washington dc' => ['city' => 'Washington', 'zip' => '20001'],
            'washington' => ['city' => 'Washington', 'zip' => '20001'],
            'zurich' => ['city' => 'Zurich', 'zip' => '8001'],
            'zürich' => ['city' => 'Zurich', 'zip' => '8001'],
            'bern' => ['city' => 'Bern', 'zip' => '3001'],
            'geneva' => ['city' => 'Geneva', 'zip' => '1201'],
            'genève' => ['city' => 'Geneva', 'zip' => '1201'],
            'london' => ['city' => 'London', 'zip' => ''],
            'londer' => ['city' => 'London', 'zip' => ''],
            'londër' => ['city' => 'London', 'zip' => ''],
            'londra' => ['city' => 'London', 'zip' => ''],
        ];

        foreach ($cities as $needle => $meta) {
            if (str_contains($lower, $needle)) {
                $parsed['search_city'] = $meta['city'];
                $parsed['search_zip'] = $meta['zip'];
                if (empty($parsed['search_country_code']) && in_array($meta['city'], ['New York', 'Washington'], true)) {
                    $parsed['search_country_code'] = 'US';
                    $parsed['search_country'] = 'United States';
                }
                if (empty($parsed['search_country_code']) && in_array($meta['city'], ['Zurich', 'Bern', 'Geneva'], true)) {
                    $parsed['search_country_code'] = 'CH';
                    $parsed['search_country'] = 'Switzerland';
                }
                if (empty($parsed['search_country_code']) && $meta['city'] === 'London') {
                    $parsed['search_country_code'] = 'GB';
                    $parsed['search_country'] = 'United Kingdom';
                }
                break;
            }
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
        if (CategoryCatalog::normalize($parsed['category'] ?? '') === 'travel') {
            return $parsed;
        }

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
            'country_code' => $parsed['search_country_code'] ?? $visitorGeo['country_code'] ?? null,
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
            if (CategoryCatalog::normalize($parsed['category'] ?? '') === 'travel') {
                $origin = (string) ($parsed['origin_city'] ?? $parsed['search_country'] ?? '');
                $destination = (string) ($parsed['destination_city'] ?? $parsed['destination'] ?? '');
                $routeLabel = trim($origin.' → '.$destination, " →\t");

                if ($routeLabel !== '') {
                    return [
                        'mode' => 'query',
                        'label' => $routeLabel,
                        'target_country' => $routeLabel,
                        'target_country_code' => $parsed['origin_country_code'] ?? $parsed['search_country_code'] ?? null,
                        'search_countries' => $parsed['search_countries'] ?? null,
                        'visitor_city' => $visitorGeo['city'] ?? null,
                        'visitor_country' => $visitorGeo['country'] ?? null,
                        'travel_route' => true,
                    ];
                }
            }

            $multiCountries = ! empty($parsed['search_countries']) && is_array($parsed['search_countries'])
                ? implode(', ', array_column($parsed['search_countries'], 'search_country'))
                : null;

            return [
                'mode' => 'query',
                'label' => $multiCountries ?? ($parsed['search_country'] ?? $searchGeo['country'] ?? ''),
                'target_country' => $multiCountries ?? ($parsed['search_country'] ?? null),
                'target_country_code' => $parsed['search_country_code'] ?? null,
                'search_countries' => $parsed['search_countries'] ?? null,
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

        if (! empty($parsed['search_target'])
            && CategoryCatalog::normalize($parsed['category'] ?? '') !== 'travel'
            && ! WebServicesIntentParser::isActive($parsed)) {
            $multi = $parsed['search_countries'] ?? [];
            if (! is_array($multi) || count($multi) <= 1) {
                if (! empty($parsed['search_country'])) {
                    $defaults['country'] = $parsed['search_country'];
                }
            }
        }

        if (! empty($parsed['max_price'])
            && ! isset($clientFilters['price_max'])
            && ! isset($clientFilters['price'])) {
            $defaults['price_max'] = (float) $parsed['max_price'];
        }

        foreach (['brand', 'size', 'product_type', 'color', 'colors', 'fuel', 'engine_liters', 'year_min', 'year_max', 'model'] as $key) {
            if (! empty($parsed[$key]) && ! isset($clientFilters[$key])) {
                $value = $parsed[$key];
                if ($key === 'product_type' && in_array(CategoryCatalog::normalize($parsed['category'] ?? ''), ['fashion', 'sports_outdoor'], true)) {
                    $value = FashionIntentParser::normalizeType((string) $value);
                }
                $defaults[$key] = $value;
            }
        }

        if (! empty($parsed['gender']) && ! isset($clientFilters['gender'])
            && ! WebServicesIntentParser::isActive($parsed)) {
            $defaults['gender'] = CategoryCatalog::normalizeGender((string) $parsed['gender']);
        }

        return array_merge($defaults, $clientFilters);
    }
}
