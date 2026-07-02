<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Switzerland automotive helpers — catalog fallback when live scrapers return empty.
 */
class SwissAutomotiveIntent
{
    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function isSwissSearch(array $parsed): bool
    {
        return CategoryCatalog::isAutomotive($parsed['category'] ?? '')
            && strtoupper((string) ($parsed['search_country_code'] ?? '')) === 'CH';
    }

    public static function shouldUseCatalogFallback(string $platformKey): bool
    {
        return SwissCarMarketplaces::isPlatform($platformKey);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, array<string, mixed>>
     */
    public static function catalogFallback(string $platformKey, array $parsed): array
    {
        if (! self::isSwissSearch($parsed)) {
            return [];
        }

        $path = storage_path('data/products/car.json');
        if (! File::exists($path)) {
            return [];
        }

        $data = json_decode(File::get($path), true);
        if (! is_array($data)) {
            return [];
        }

        $platformKey = strtolower(trim($platformKey));
        $brand = mb_strtolower((string) ($parsed['brand'] ?? ''));
        $model = mb_strtolower((string) ($parsed['model'] ?? ''));
        $maxPrice = (float) ($parsed['max_price'] ?? 0);
        $currency = strtoupper((string) ($parsed['currency'] ?? 'CHF'));
        $fuel = mb_strtolower((string) ($parsed['fuel'] ?? ''));
        $engineLiters = isset($parsed['engine_liters']) ? (float) $parsed['engine_liters'] : 0.0;
        $yearMin = isset($parsed['year_min']) ? (int) $parsed['year_min'] : null;
        $yearMax = isset($parsed['year_max']) ? (int) $parsed['year_max'] : null;

        $items = [];
        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (! self::itemMatchesPlatform($item, $platformKey)) {
                continue;
            }

            if (! self::itemMatchesCountry($item, 'CH')) {
                continue;
            }

            if ($brand !== '' && ! self::itemMatchesBrand($item, $brand)) {
                continue;
            }

            if ($model !== '' && ! AutomotiveModelResolver::matchesListing(
                (string) ($item['title'] ?? ''),
                isset($item['model']) ? (string) $item['model'] : null,
                $model,
                array_merge($parsed, ['year' => $item['year'] ?? null]),
                true,
            )) {
                continue;
            }

            if ($yearMin !== null || $yearMax !== null) {
                $itemYear = isset($item['year']) ? (int) $item['year'] : null;
                $min = $yearMin ?? $yearMax ?? 0;
                $max = $yearMax ?? $yearMin ?? $min;
                if ($itemYear !== null && ($itemYear < $min || $itemYear > $max)) {
                    continue;
                }
            }

            if ($fuel !== '' && ! self::itemMatchesFuel($item, $fuel)) {
                continue;
            }

            if ($engineLiters > 0 && ! AutomotiveEngineResolver::matchesWanted(
                isset($item['engine_liters']) ? (float) $item['engine_liters'] : null,
                $engineLiters,
                (string) ($item['title'] ?? ''),
                $fuel !== '' ? $fuel : null,
            )) {
                continue;
            }

            $price = (float) ($item['price'] ?? 0);
            $itemCurrency = strtoupper((string) ($item['currency'] ?? 'CHF'));
            if ($maxPrice > 0 && $itemCurrency === $currency && $price > $maxPrice) {
                continue;
            }

            $item['source_key'] = $platformKey;
            $item['store'] = $platformKey;
            $item['live'] = false;
            $item['country_code'] = 'CH';
            $item['category'] = 'automotive';
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function itemMatchesPlatform(array $item, string $platformKey): bool
    {
        $store = strtolower((string) ($item['store'] ?? ''));
        if ($store !== '') {
            return SwissCarMarketplaces::normalizeKey($store) === SwissCarMarketplaces::normalizeKey($platformKey);
        }

        $url = mb_strtolower((string) ($item['url'] ?? ''));
        $catalogUrl = SwissCarMarketplaces::url($platformKey);
        if ($catalogUrl === null || $url === '') {
            return false;
        }

        $host = parse_url($catalogUrl, PHP_URL_HOST);
        if (is_string($host) && $host !== '' && str_contains($url, mb_strtolower($host))) {
            return true;
        }

        return str_contains($url, mb_strtolower(str_replace('_', '', $platformKey)));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function itemMatchesCountry(array $item, string $code): bool
    {
        $itemCode = strtoupper((string) ($item['country_code'] ?? ''));
        if ($itemCode === strtoupper($code)) {
            return true;
        }

        $loc = mb_strtolower($item['location'] ?? '');

        return (bool) preg_match('/switzerland|schweiz|suisse|svizzera|zurich|zürich|bern|geneva|genève|basel|lausanne|winterthur|luzern|lugano/', $loc);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function itemMatchesBrand(array $item, string $brand): bool
    {
        $brand = mb_strtolower($brand);
        $title = mb_strtolower($item['title'] ?? '');
        $tags = array_map('mb_strtolower', $item['tags'] ?? []);

        return str_contains($title, $brand) || in_array($brand, $tags, true);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function itemMatchesFuel(array $item, string $fuel): bool
    {
        $fuel = AutomotiveIntentParser::normalizeFuel($fuel) ?? mb_strtolower($fuel);
        $title = mb_strtolower((string) ($item['title'] ?? ''));
        $tags = array_map('mb_strtolower', $item['tags'] ?? []);
        $needles = match ($fuel) {
            'diesel' => ['diesel', 'tdi', 'dizell', 'disel', 'disell'],
            'petrol' => ['petrol', 'benzin', 'tfsi', 'gasoline', 'tsi'],
            'electric' => ['electric', 'ev', 'elektrik'],
            default => [$fuel],
        };

        foreach ($needles as $needle) {
            if (str_contains($title, $needle) || in_array($needle, $tags, true)) {
                return true;
            }
        }

        return false;
    }
}
