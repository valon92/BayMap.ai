<?php

namespace App\Support;

use App\Services\Marketplace\MockMarketplaceService;

/**
 * Industrial B2B catalog fallback when live scrapers return empty (anti-bot / no API).
 */
class IndustrialB2BIntent
{
    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function isIndustrialSearch(array $parsed): bool
    {
        return CategoryCatalog::isIndustrialB2B($parsed['category'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function shouldUseCatalogFallback(string $platformKey, array $parsed): bool
    {
        if (! self::isIndustrialSearch($parsed)) {
            return false;
        }

        if (! config('marketplaces.demo_fallback_when_empty', true)) {
            return false;
        }

        return IndustrialB2BMarketplaces::isPlatform($platformKey);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, array<string, mixed>>
     */
    public static function catalogFallback(string $platformKey, array $parsed): array
    {
        if (! self::shouldUseCatalogFallback($platformKey, $parsed)) {
            return [];
        }

        $mock = new MockMarketplaceService(strtolower(trim($platformKey)));

        return $mock->search($parsed, []);
    }
}
