<?php

namespace App\Support;

use App\Services\Marketplace\EbayOAuthService;

/**
 * Bridges legacy per-country marketplace catalogs into the universal live fan-out registry.
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

        if (CategoryCatalog::isAutomotiveParts($category)) {
            return AutomotivePartsMarketplaces::keysFor($countryCode);
        }

        if (CategoryCatalog::normalize($category) === 'industrial_b2b') {
            return IndustrialB2BMarketplaces::keysFor($countryCode);
        }

        if (CategoryCatalog::isAutomotive($category)) {
            return self::automotiveVehicleKeys($countryCode);
        }

        return match ($countryCode) {
            'US' => match (true) {
                in_array($category, ['fashion', 'sports_outdoor'], true) => USFashionMarketplaces::keys(),
                default => [],
            },
            'CH' => match (true) {
                CategoryCatalog::isElectronics($category) => SwissElectronicsMarketplaces::keys(),
                $category === 'real_estate' => SwissRealEstateMarketplaces::keys(),
                in_array($category, ['fashion', 'sports_outdoor'], true) => SwissFashionMarketplaces::keys(),
                default => [],
            },
            'DE' => CategoryCatalog::isElectronics($category) ? GermanElectronicsMarketplaces::keys() : [],
            'GB' => $category === 'real_estate' ? UKRealEstateMarketplaces::keys() : [],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private static function automotiveVehicleKeys(string $countryCode): array
    {
        $keys = AutomotiveVehiclesMarketplaces::keysFor($countryCode);

        if ($countryCode === 'DE' && app(EbayOAuthService::class)->isConfigured()) {
            $keys[] = 'ebay';
        }

        return array_values(array_unique($keys));
    }

    public static function label(string $key): string
    {
        $key = strtolower(trim($key));

        return AutomotiveVehiclesMarketplaces::label($key)
            ?: KosovoCarMarketplaces::label($key)
            ?: USFashionMarketplaces::label($key)
            ?: SwissFashionMarketplaces::label($key)
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
}
