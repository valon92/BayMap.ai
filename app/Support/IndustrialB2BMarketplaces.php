<?php

namespace App\Support;

/**
 * Industrial / B2B marketplace keys by country.
 */
class IndustrialB2BMarketplaces
{
    /** @var array<string, array<int, string>> */
    private const ORDERED = [
        'CN' => [
            'alibaba_ww', 'global_sources', 'machinio_ww', 'machinio_parts_ww', 'amazon_business', 'ebay',
        ],
        'US' => [
            'thomasnet_us', 'amazon_business', 'machinio_ww', 'alibaba_ww', 'ebay',
        ],
        'DE' => [
            'wlw_de', 'industrystock_de', 'europages_ww', 'machinio_ww', 'alibaba_ww', 'ebay',
        ],
    ];

    /** @var array<int, string> */
    private const GLOBAL = [
        'alibaba_ww',
        'global_sources',
        'machinio_ww',
        'machinio_parts_ww',
        'amazon_business',
        'europages_ww',
        'ebay',
    ];

    /**
     * @return array<int, string>
     */
    public static function keysFor(string $countryCode): array
    {
        $countryCode = strtoupper($countryCode);
        $keys = self::ORDERED[$countryCode] ?? [];

        if ($keys === []) {
            $keys = self::discoveredKeys($countryCode);
        }

        return array_values(array_unique(array_merge($keys, self::GLOBAL)));
    }

    /**
     * @return array<int, string>
     */
    private static function discoveredKeys(string $countryCode): array
    {
        $keys = [];
        foreach (LivePlatformRegistry::all() as $key => $meta) {
            $platformCountry = strtoupper((string) ($meta['country'] ?? ''));
            $cats = (array) ($meta['categories'] ?? []);
            if (! in_array('industrial_b2b', $cats, true)) {
                continue;
            }
            if ($platformCountry === $countryCode || in_array($platformCountry, ['WW', 'GLOBAL', '*'], true)) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    public static function label(string $key): string
    {
        return LivePlatformRegistry::label($key) ?: $key;
    }
}
