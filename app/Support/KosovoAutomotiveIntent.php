<?php

namespace App\Support;

/**
 * Kosovo live automotive platforms (MerrJep Auto, Veturaneshitje, etc.).
 */
class KosovoAutomotiveIntent
{
    /**
     * @return array<int, string>
     */
    public static function livePlatformKeys(): array
    {
        return array_values(array_filter(
            LivePlatformRegistry::keysFor('XK', 'automotive'),
            fn (string $key) => (bool) (LivePlatformRegistry::platform($key)['automotive_live'] ?? false),
        ));
    }

    public static function hasLivePlatforms(): bool
    {
        return self::livePlatformKeys() !== [];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function shouldSkipDemoFallback(array $parsed): bool
    {
        if (CategoryCatalog::normalize($parsed['category'] ?? '') !== 'automotive') {
            return false;
        }

        $country = strtoupper((string) ($parsed['search_country_code'] ?? ''));

        return $country === 'XK' && self::hasLivePlatforms();
    }
}
