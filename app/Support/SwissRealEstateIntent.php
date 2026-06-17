<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Switzerland real-estate search helpers — buy/rent intent and catalog fallback when live sites block bots.
 */
class SwissRealEstateIntent
{
    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function isSwissSearch(array $parsed): bool
    {
        return CategoryCatalog::normalize($parsed['category'] ?? '') === 'real_estate'
            && strtoupper((string) ($parsed['search_country_code'] ?? '')) === 'CH';
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function listingType(array $parsed): string
    {
        $explicit = mb_strtolower((string) ($parsed['listing_type'] ?? ''));
        if (in_array($explicit, ['sale', 'buy', 'kaufen', 'blerje', 'shitje'], true)) {
            return 'sale';
        }
        if (in_array($explicit, ['rent', 'mieten', 'qira', 'me qira'], true)) {
            return 'rent';
        }

        $raw = mb_strtolower((string) ($parsed['raw_query'] ?? $parsed['search_query'] ?? ''));
        if (preg_match('/\b(qira|me qira|mieten|rent|vermiet)\b/u', $raw)) {
            return 'rent';
        }

        if (preg_match('/\b(blerje|shitje|kaufen|buy|sale|milion|million|frang|chf)\b/u', $raw)) {
            return 'sale';
        }

        $maxPrice = (float) ($parsed['max_price'] ?? 0);
        $currency = strtoupper((string) ($parsed['currency'] ?? ''));

        return ($maxPrice >= 150000 || $currency === 'CHF') ? 'sale' : 'rent';
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

        $path = storage_path('data/products/real_estate_ch.json');
        if (! File::exists($path)) {
            return [];
        }

        $data = json_decode(File::get($path), true);
        if (! is_array($data)) {
            return [];
        }

        $platformKey = strtolower(trim($platformKey));
        $maxPrice = (float) ($parsed['max_price'] ?? 0);
        $currency = strtoupper((string) ($parsed['currency'] ?? 'CHF'));
        $propertyType = mb_strtolower((string) ($parsed['property_type'] ?? ''));

        $items = [];
        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            $store = strtolower((string) ($item['store'] ?? ''));
            if ($store !== '' && $store !== $platformKey && $store !== 'general') {
                continue;
            }

            $price = (float) ($item['price'] ?? 0);
            $itemCurrency = strtoupper((string) ($item['currency'] ?? 'CHF'));
            if ($maxPrice > 0 && $itemCurrency === $currency && $price > $maxPrice) {
                continue;
            }

            if ($propertyType !== '' && ! empty($item['property_type'])) {
                if (mb_strtolower((string) $item['property_type']) !== $propertyType) {
                    continue;
                }
            }

            $item['source_key'] = $platformKey;
            $item['live'] = false;
            $item['country_code'] = 'CH';
            $item['category'] = 'real_estate';
            $items[] = $item;
        }

        return $items;
    }
}
