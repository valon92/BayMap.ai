<?php

namespace App\Support;

/**
 * Kosovo fashion retailers — delegates to LivePlatformRegistry (XK + fashion).
 */
class KosovoFashionPlatforms
{
    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return LivePlatformRegistry::keysFor('XK', 'fashion');
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

    /**
     * @return array<int, string>
     */
    public static function labels(): array
    {
        return LivePlatformRegistry::labelsFor('XK', 'fashion');
    }

    public static function count(): int
    {
        return LivePlatformRegistry::countFor('XK', 'fashion');
    }
}
