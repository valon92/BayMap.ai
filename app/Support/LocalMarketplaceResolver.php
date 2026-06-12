<?php

namespace App\Support;

use App\Contracts\FederatedSearchProviderInterface;

/**
 * Unified local marketplace logic for every country + product category.
 * Same flow as CH/DE cars & electronics: targeted query → discover local stores → fan-out workers.
 */
class LocalMarketplaceResolver
{
    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function isTargeted(array $parsed): bool
    {
        if (SearchScopeResolver::isUniversal($parsed)) {
            return false;
        }

        if (empty($parsed['search_target'])) {
            return false;
        }

        $code = strtoupper((string) ($parsed['search_country_code'] ?? ''));

        return $code !== '' && ! in_array($code, ['WW', 'GLOBAL', '*'], true);
    }

    /**
     * @return array<int, string>
     */
    public static function keys(string $countryCode, string $category): array
    {
        return LivePlatformRegistry::keysFor(strtoupper($countryCode), CategoryCatalog::normalize($category));
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    public static function keysFromParsed(array $parsed): array
    {
        return LivePlatformRegistry::keysFromParsed($parsed);
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function hasLocalPlatforms(array $parsed): bool
    {
        return self::keysFromParsed($parsed) !== [];
    }

    /**
     * @param  array<int, string>  $targets
     */
    public static function isTarget(string $sourceKey, array $targets): bool
    {
        if ($targets === []) {
            return true;
        }

        if (in_array($sourceKey, $targets, true)) {
            return true;
        }

        foreach (self::legacyCatalogClasses() as $class) {
            if ($class::isTarget($sourceKey, $targets)) {
                return true;
            }
        }

        $sourceNorm = strtolower(str_replace(['.', '_'], '', $sourceKey));
        foreach ($targets as $target) {
            $targetNorm = strtolower(str_replace(['.', '_', ' '], '', $target));
            if ($sourceNorm !== '' && ($sourceNorm === $targetNorm
                || str_contains($sourceNorm, $targetNorm)
                || str_contains($targetNorm, $sourceNorm))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a provider should participate in this targeted local search.
     *
     * @param  array<string, mixed>  $parsed
     * @param  array<int, string>  $targets
     */
    public static function allowsProvider(
        string $sourceKey,
        FederatedSearchProviderInterface $provider,
        array $parsed,
        array $targets,
        string $countryCode,
        string $category,
    ): bool {
        if (! self::isTargeted($parsed)) {
            return true;
        }

        $localKeys = $targets !== [] ? $targets : self::keys($countryCode, $category);
        if ($localKeys === []) {
            return true;
        }

        if (self::isTarget($sourceKey, $localKeys)) {
            return true;
        }

        if ($sourceKey === 'ebay' && self::ebayAllowedFor($countryCode, $category, $provider)) {
            return true;
        }

        if (in_array($sourceKey, self::excludedGlobalProviders($countryCode, $category), true)) {
            return false;
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public static function excludedGlobalProviders(string $countryCode, string $category): array
    {
        $countryCode = strtoupper($countryCode);
        $category = CategoryCatalog::normalize($category);
        $default = (array) config('live_platforms.local_search.exclude_global', [
            'amazon', 'ebay', 'google_shopping', 'etsy', 'facebook_marketplace',
        ]);

        $byCountry = (array) config('live_platforms.local_search.by_country', []);
        $countryRules = (array) ($byCountry[$countryCode] ?? []);
        $excluded = array_values(array_unique(array_merge(
            $default,
            (array) ($countryRules['exclude_global'] ?? []),
        )));

        $allowEbay = (array) config('live_platforms.local_search.allow_ebay_categories', [
            'DE:automotive',
        ]);
        $ebayKey = $countryCode.':'.$category;
        if (in_array($ebayKey, $allowEbay, true)) {
            $excluded = array_values(array_filter($excluded, fn (string $key) => $key !== 'ebay'));
        }

        return $excluded;
    }

    public static function label(string $key): string
    {
        return LivePlatformRegistry::label($key);
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function pipelineLabel(array $parsed, array $expanded, array $searchGeo): string
    {
        $count = count($expanded['marketplaces'] ?? []);

        if (CategoryCatalog::isBookSearch($parsed)) {
            return "Searched {$count} online bookstores & retailers";
        }

        $multi = $parsed['search_countries'] ?? [];
        if (is_array($multi) && count($multi) > 1) {
            $names = implode(', ', array_column($multi, 'search_country'));

            return "Searched {$count} local marketplaces in {$names}";
        }

        if (self::isTargeted($parsed) && self::hasLocalPlatforms($parsed)) {
            $country = (string) ($parsed['search_country'] ?? $searchGeo['country'] ?? 'local');

            return "Searched {$count} local marketplaces in {$country}";
        }

        if (strtoupper((string) ($parsed['search_country_code'] ?? $searchGeo['country_code'] ?? '')) === 'XK') {
            return "Searched {$count} Kosovo online stores & marketplaces";
        }

        $region = (string) ($parsed['search_country'] ?? $searchGeo['country'] ?? 'local');

        return "Searched web: {$region} → regional";
    }

    /**
     * @return array<int, class-string>
     */
    private static function legacyCatalogClasses(): array
    {
        return [
            SwissCarMarketplaces::class,
            SwissElectronicsMarketplaces::class,
            SwissRealEstateMarketplaces::class,
            GermanCarMarketplaces::class,
            GermanElectronicsMarketplaces::class,
            DutchCarMarketplaces::class,
            DutchElectronicsMarketplaces::class,
            KosovoCarMarketplaces::class,
            KosovoMarketplaces::class,
            UKRealEstateMarketplaces::class,
            GlobalBookMarketplaces::class,
        ];
    }

    private static function ebayAllowedFor(
        string $countryCode,
        string $category,
        FederatedSearchProviderInterface $provider,
    ): bool {
        $allowEbay = (array) config('live_platforms.local_search.allow_ebay_categories', ['DE:automotive']);
        $ebayKey = strtoupper($countryCode).':'.CategoryCatalog::normalize($category);

        return in_array($ebayKey, $allowEbay, true) && $provider->isAvailable();
    }
}
