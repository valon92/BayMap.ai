<?php

namespace App\Support;

/**
 * Kosovo toy retailers — delegates to LivePlatformRegistry (XK + gaming_entertainment toys).
 */
class KosovoToyPlatforms
{
    /** @var array<int, string>|null */
    private static ?array $keys = null;

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        if (self::$keys === null) {
            self::$keys = array_values(array_filter(
                LivePlatformRegistry::keysFor('XK', 'gaming_entertainment'),
                fn (string $key) => (bool) (LivePlatformRegistry::platform($key)['toy_retailer'] ?? false),
            ));
        }

        return self::$keys;
    }

    public static function isPlatform(string $sourceKey): bool
    {
        return in_array(KosovoMarketplaces::normalizeKey($sourceKey), self::keys(), true);
    }

    public static function label(string $sourceKey): string
    {
        $key = KosovoMarketplaces::normalizeKey($sourceKey);

        return LivePlatformRegistry::label($key) ?: KosovoMarketplaces::label($key);
    }

    public static function url(string $sourceKey): ?string
    {
        $key = KosovoMarketplaces::normalizeKey($sourceKey);
        $platform = LivePlatformRegistry::platform($key);

        return $platform['base_url'] ?? KosovoMarketplaces::url($key);
    }

    public static function count(): int
    {
        return count(self::keys());
    }
}
