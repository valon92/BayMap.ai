<?php

namespace App\Support;

use App\Services\Catalog\PlatformCatalogRepository;
use App\Services\Platform\PlatformDiscoveryService;

/**
 * Country + category live scraping registry.
 * Data source: DB global catalog (preferred) with config/live_platforms.php fallback.
 */
class LivePlatformRegistry
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $platforms = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$platforms === null) {
            self::$platforms = app(PlatformCatalogRepository::class)->allPlatforms();
        }

        return self::$platforms;
    }

    public static function flushCache(): void
    {
        self::$platforms = null;
        app(PlatformCatalogRepository::class)->flushCache();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function platform(string $key): ?array
    {
        $key = strtolower(trim($key));

        return self::all()[$key] ?? null;
    }

    public static function isLivePlatform(string $key): bool
    {
        return self::platform($key) !== null;
    }

    /**
     * Direct country+category lookup (no intent filters — used by intent helpers).
     *
     * @return array<int, string>
     */
    public static function keysFor(string $countryCode, string $category): array
    {
        $countryCode = strtoupper($countryCode);
        $category = CategoryCatalog::normalize($category);
        $keys = [];

        foreach (self::all() as $key => $meta) {
            if (strtoupper((string) ($meta['country'] ?? '')) !== $countryCode) {
                continue;
            }
            $cats = (array) ($meta['categories'] ?? []);
            $matches = in_array($category, $cats, true)
                || ($category === 'marketplace' && in_array('marketplace', $cats, true));
            if (! $matches) {
                continue;
            }
            $keys[] = $key;
        }

        $keys = array_values(array_unique(array_merge($keys, PlatformCatalogBridge::keysFor($countryCode, $category))));

        if ($countryCode === 'CH' && in_array($category, ['fashion', 'sports_outdoor'], true)) {
            return SwissFashionMarketplaces::ORDERED_KEYS;
        }

        usort($keys, fn ($a, $b) => self::priorityFor($a) <=> self::priorityFor($b));

        return $keys;
    }

    private static function priorityFor(string $key): int
    {
        return (int) (self::all()[$key]['priority'] ?? 50);
    }

    public static function countFor(string $countryCode, string $category): int
    {
        return count(self::keysFor($countryCode, $category));
    }

    /**
     * @return array<int, string>
     */
    public static function labelsFor(string $countryCode, string $category): array
    {
        return array_values(array_filter(array_map(
            fn (string $key) => (string) (self::platform($key)['label'] ?? ''),
            self::keysFor($countryCode, $category)
        )));
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{
     *   scope: string,
     *   country_code: string,
     *   city: ?string,
     *   category: string,
     *   keys: array<int, string>,
     *   platforms: array<int, array{key: string, label: string, country: string, source: string}>
     * }
     */
    public static function discover(array $parsed): array
    {
        return app(PlatformDiscoveryService::class)->discover($parsed);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    public static function keysFromParsed(array $parsed): array
    {
        return app(PlatformDiscoveryService::class)->keys($parsed);
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function shouldFanOut(array $parsed, string $countryCode): bool
    {
        return self::keysFromParsed($parsed) !== [];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function maxWorkersFor(array $parsed): int
    {
        $count = count(self::keysFromParsed($parsed));
        $cap = (int) config('live_platforms.max_workers_cap', 24);

        return $count > 0 ? min($count, $cap) : $cap;
    }

    /** @deprecated Use maxWorkersFor($parsed) */
    public static function maxWorkers(string $countryCode, string $category): int
    {
        $count = self::countFor($countryCode, $category);
        $cap = (int) config('live_platforms.max_workers_cap', 24);

        return $count > 0 ? min($count, $cap) : $cap;
    }

    public static function label(string $key): string
    {
        $fromConfig = (string) (self::platform($key)['label'] ?? '');
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        return PlatformCatalogBridge::label($key) ?: KosovoMarketplaces::label($key) ?: $key;
    }
}
