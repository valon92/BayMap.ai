<?php

namespace App\Support;

use App\Services\Marketplace\EbayOAuthService;

/**
 * Bridges legacy per-country marketplace catalogs into the universal live fan-out registry.
 *
 * Unified model (same for every country + category):
 * 1. Register platforms in config/live_platforms.php (country + categories)
 * 2. PlatformDiscoveryService / LivePlatformRegistry discover keys from parsed intent
 * 3. LocalMarketplaceResolver filters providers and excludes global connectors
 * 4. One Valon Worker per platform runs in parallel
 *
 * Legacy *Marketplaces.php classes remain as label/mock fallbacks until fully migrated to live_platforms.
 */
class PlatformCatalogBridge
{
    /**
     * @return array<int, string>
     */
    public static function keysFor(string $countryCode, string $category): array
    {
        $countryCode = strtoupper($countryCode);
        $category = CategoryCatalog::normalize($category);

        return match ($countryCode) {
            'CH' => match (true) {
                CategoryCatalog::isAutomotive($category) => SwissCarMarketplaces::keys(),
                CategoryCatalog::isElectronics($category) => SwissElectronicsMarketplaces::keys(),
                $category === 'real_estate' => SwissRealEstateMarketplaces::keys(),
                in_array($category, ['fashion', 'sports_outdoor'], true) => SwissFashionMarketplaces::keys(),
                default => [],
            },
            'NL' => CategoryCatalog::isAutomotive($category) ? DutchCarMarketplaces::keys() : [],
            'DE' => match (true) {
                CategoryCatalog::isAutomotive($category) => self::germanAutomotiveKeys(),
                CategoryCatalog::isElectronics($category) => GermanElectronicsMarketplaces::keys(),
                default => [],
            },
            'GB' => $category === 'real_estate' ? UKRealEstateMarketplaces::keys() : [],
            'XK' => CategoryCatalog::isAutomotive($category) ? KosovoCarMarketplaces::keys() : [],
            default => [],
        };
    }

    public static function label(string $key): string
    {
        $key = strtolower(trim($key));

        return KosovoCarMarketplaces::label($key)
            ?: SwissCarMarketplaces::label($key)
            ?: UKRealEstateMarketplaces::label($key)
            ?: SwissRealEstateMarketplaces::label($key)
            ?: DutchCarMarketplaces::label($key)
            ?: DutchElectronicsMarketplaces::label($key)
            ?: GermanCarMarketplaces::label($key)
            ?: GermanElectronicsMarketplaces::label($key)
            ?: SwissElectronicsMarketplaces::label($key)
            ?: SwissFashionMarketplaces::label($key)
            ?: '';
    }

    public static function hasPlatforms(string $countryCode, string $category): bool
    {
        return self::keysFor($countryCode, $category) !== [];
    }

    /**
     * @return array<int, string>
     */
    private static function germanAutomotiveKeys(): array
    {
        $keys = GermanCarMarketplaces::keys();

        if (app(EbayOAuthService::class)->isConfigured()) {
            $keys[] = 'ebay';
        }

        return array_values(array_unique($keys));
    }
}
