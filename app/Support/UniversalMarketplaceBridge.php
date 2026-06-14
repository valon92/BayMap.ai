<?php

namespace App\Support;

/**
 * Global connector layer — Google Shopping (SerpAPI) + eBay for any country.
 * Complements live platform scrapers; never replaces the federated worker model.
 */
class UniversalMarketplaceBridge
{
    /** @var array<string, string> ISO 3166-1 alpha-2 → SerpAPI gl */
    private const SERP_GL = [
        'US' => 'us',
        'GB' => 'uk',
        'UK' => 'uk',
        'DE' => 'de',
        'CH' => 'ch',
        'AT' => 'at',
        'FR' => 'fr',
        'IT' => 'it',
        'ES' => 'es',
        'NL' => 'nl',
        'BE' => 'be',
        'PL' => 'pl',
        'SE' => 'se',
        'NO' => 'no',
        'DK' => 'dk',
        'FI' => 'fi',
        'IN' => 'in',
        'BR' => 'br',
        'MX' => 'mx',
        'CA' => 'ca',
        'AU' => 'au',
        'JP' => 'jp',
        'KR' => 'kr',
        'SG' => 'sg',
        'AE' => 'ae',
        'SA' => 'sa',
        'ZA' => 'za',
        'TR' => 'tr',
        'RU' => 'ru',
        'XK' => 'al',
        'AL' => 'al',
        'MK' => 'mk',
        'RS' => 'rs',
    ];

    /** @var array<string, string> */
    private const SERP_HL = [
        'DE' => 'de',
        'CH' => 'de',
        'AT' => 'de',
        'FR' => 'fr',
        'IT' => 'it',
        'ES' => 'es',
        'NL' => 'nl',
        'PL' => 'pl',
        'IN' => 'en',
        'BR' => 'pt',
        'MX' => 'es',
        'JP' => 'ja',
        'KR' => 'ko',
        'XK' => 'sq',
        'AL' => 'sq',
    ];

    /** @var array<string, string> */
    private const CURRENCY = [
        'US' => 'USD',
        'CH' => 'CHF',
        'GB' => 'GBP',
        'UK' => 'GBP',
        'IN' => 'INR',
        'XK' => 'EUR',
        'BR' => 'BRL',
        'JP' => 'JPY',
        'AU' => 'AUD',
        'CA' => 'CAD',
    ];

    /** @var array<string, string> */
    private const EBAY_MARKETPLACE = [
        'US' => 'EBAY_US',
        'GB' => 'EBAY_GB',
        'UK' => 'EBAY_GB',
        'DE' => 'EBAY_DE',
        'CH' => 'EBAY_CH',
        'AT' => 'EBAY_AT',
        'FR' => 'EBAY_FR',
        'IT' => 'EBAY_IT',
        'ES' => 'EBAY_ES',
        'NL' => 'EBAY_NL',
        'BE' => 'EBAY_BE',
        'PL' => 'EBAY_PL',
        'IN' => 'EBAY_IN',
        'AU' => 'EBAY_AU',
        'CA' => 'EBAY_CA',
        'IE' => 'EBAY_IE',
        'HK' => 'EBAY_HK',
        'SG' => 'EBAY_SG',
    ];

    public static function enabled(): bool
    {
        return (bool) config('live_platforms.universal_bridge.enabled', true);
    }

    /**
     * @return array<int, string>
     */
    public static function providerKeys(): array
    {
        $keys = (array) config('live_platforms.universal_bridge.providers', [
            'google_shopping',
            'ebay',
        ]);

        return array_values(array_filter($keys, fn (string $key) => match ($key) {
            'google_shopping' => config('serpapi.enabled') && ! empty(config('serpapi.api_key')),
            'google_flights' => config('serpapi.enabled') && ! empty(config('serpapi.api_key')),
            'ebay' => config('ebay.enabled') && ! empty(config('ebay.client_id')),
            default => false,
        }));
    }

    public static function isBridgeProvider(string $sourceKey): bool
    {
        return in_array($sourceKey, (array) config('live_platforms.universal_bridge.providers', [
            'google_shopping',
            'google_flights',
            'ebay',
        ]), true);
    }

    public static function allowsBridge(string $sourceKey, string $countryCode, string $category): bool
    {
        if (! self::enabled() || ! self::isBridgeProvider($sourceKey)) {
            return false;
        }

        if (! in_array($sourceKey, self::providerKeys(), true)) {
            return false;
        }

        $category = CategoryCatalog::normalize($category);

        return match ($sourceKey) {
            'google_flights' => $category === 'travel',
            'ebay', 'google_shopping' => $category !== 'travel',
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     */
    public static function resolveCountryCode(array $parsedQuery, array $expandedFilters = []): string
    {
        $code = strtoupper((string) (
            $parsedQuery['search_country_code']
            ?? $expandedFilters['search_country_code']
            ?? $expandedFilters['original']['search_country_code']
            ?? ''
        ));

        return $code !== '' ? $code : 'US';
    }

    /**
     * @return array{gl: string, hl: string, country_code: string}
     */
    public static function serpGeo(array $parsedQuery, array $expandedFilters = []): array
    {
        $countryCode = self::resolveCountryCode($parsedQuery, $expandedFilters);
        $gl = self::SERP_GL[$countryCode]
            ?? strtolower((string) config('serpapi.gl', 'us'));
        $hl = self::SERP_HL[$countryCode]
            ?? (string) config('serpapi.hl', 'en');

        return [
            'gl' => $gl,
            'hl' => $hl,
            'country_code' => $countryCode,
        ];
    }

    public static function currencyForCountry(string $countryCode): string
    {
        $countryCode = strtoupper($countryCode);

        return self::CURRENCY[$countryCode] ?? 'EUR';
    }

    public static function ebayMarketplaceId(string $countryCode): string
    {
        $countryCode = strtoupper($countryCode);

        return self::EBAY_MARKETPLACE[$countryCode]
            ?? (string) config('ebay.marketplace_id', 'EBAY_US');
    }

    public static function shouldAugmentLocalSearch(): bool
    {
        return self::enabled()
            && (bool) config('live_platforms.universal_bridge.always_with_local', true);
    }

    public static function useWhenNoLocalPlatforms(): bool
    {
        return self::enabled()
            && (bool) config('live_platforms.universal_bridge.when_no_local', true);
    }
}
