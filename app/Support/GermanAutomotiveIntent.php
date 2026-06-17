<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Germany automotive search helpers — catalog fallback when live scrapers are blocked.
 */
class GermanAutomotiveIntent
{
    /** Platforms that block datacenter HTTP — catalog fills gaps only for these. */
    private const BLOCKED_PLATFORMS = [
        'mobile_de',
        'heycar_de',
        'facebook_marketplace_de',
        'wirkaufendeinauto',
    ];

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function isGermanSearch(array $parsed): bool
    {
        return CategoryCatalog::isAutomotive($parsed['category'] ?? '')
            && strtoupper((string) ($parsed['search_country_code'] ?? '')) === 'DE';
    }

    public static function supportsMultiPageScraping(string $platformKey): bool
    {
        $key = strtolower(trim($platformKey));

        return in_array($key, ['autoscout24_de', 'kleinanzeigen'], true);
    }

    public static function shouldUseCatalogFallback(string $platformKey): bool
    {
        return in_array(strtolower(trim($platformKey)), self::BLOCKED_PLATFORMS, true);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, array<string, mixed>>
     */
    public static function catalogFallback(string $platformKey, array $parsed): array
    {
        if (! self::isGermanSearch($parsed)) {
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
        $currency = strtoupper((string) ($parsed['currency'] ?? 'EUR'));

        $items = [];
        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            $store = strtolower((string) ($item['store'] ?? ''));
            if ($store !== '' && $store !== $platformKey) {
                continue;
            }

            if (! self::itemMatchesCountry($item, 'DE')) {
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

            $price = (float) ($item['price'] ?? 0);
            $itemCurrency = strtoupper((string) ($item['currency'] ?? 'EUR'));
            if ($maxPrice > 0 && $itemCurrency === $currency && $price > $maxPrice) {
                continue;
            }

            $item['source_key'] = $platformKey;
            $item['store'] = $platformKey;
            $item['live'] = false;
            $item['country_code'] = 'DE';
            $item['category'] = 'automotive';
            $items[] = $item;
        }

        return $items;
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

        return (bool) preg_match('/germany|deutschland|munich|münchen|berlin|frankfurt|hamburg|stuttgart|cologne|köln|düsseldorf|dusseldorf|hannover|leipzig|dresden/', $loc);
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
}
