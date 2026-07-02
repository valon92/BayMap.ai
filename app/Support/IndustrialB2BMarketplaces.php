<?php

namespace App\Support;

/**
 * Industrial B2B platform keys by country — Alibaba, Machinio, etc.
 */
class IndustrialB2BMarketplaces
{
    /** @var array<int, string> */
    private const GLOBAL = [
        'alibaba_ww',
        'machinio_ww',
        'global_sources',
        'amazon_business',
        'europages_ww',
    ];

    /** @var array<string, array<int, string>> */
    private const ORDERED = [
        'CN' => ['alibaba_ww', 'global_sources', 'machinio_ww', 'amazon_business'],
        'DE' => ['wlw_de', 'industrystock_de', 'alibaba_ww', 'machinio_ww', 'europages_ww'],
        'US' => ['thomasnet_us', 'amazon_business', 'machinio_ww', 'alibaba_ww'],
        'GB' => ['europages_ww', 'amazon_business', 'machinio_ww', 'alibaba_ww'],
        'CH' => ['industrystock_de', 'wlw_de', 'machinio_ww', 'alibaba_ww'],
    ];

    /**
     * @return array<int, string>
     */
    public static function keysFor(string $countryCode): array
    {
        $countryCode = strtoupper($countryCode);
        $keys = self::ORDERED[$countryCode] ?? [];

        return array_values(array_unique(array_merge($keys, self::GLOBAL)));
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        $all = self::GLOBAL;
        foreach (self::ORDERED as $keys) {
            $all = array_merge($all, $keys);
        }

        return array_values(array_unique($all));
    }

    public static function isPlatform(string $key): bool
    {
        return in_array(strtolower(trim($key)), self::keys(), true);
    }

    public static function label(string $key): string
    {
        return LivePlatformRegistry::label($key) ?: $key;
    }
}
