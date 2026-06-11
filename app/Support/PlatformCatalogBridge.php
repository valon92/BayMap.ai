<?php

namespace App\Support;

use App\Services\Marketplace\EbayOAuthService;

/**
 * Bridges legacy per-country marketplace catalogs into the universal live fan-out registry.
 * Any country + category with platforms here gets one Valon Worker per store automatically.
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
