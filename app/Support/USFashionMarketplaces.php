<?php

namespace App\Support;

/**
 * US fashion retailer fan-out — one Valon Worker per store (targeted US searches).
 */
class USFashionMarketplaces
{
    /** @var array<int, string> Priority-ordered platform keys */
    public const ORDERED_KEYS = [
        'zara_us',
        'hm_us',
        'target_us',
        'gap_us',
        'old_navy_us',
        'asos_us',
        'fashion_nova_us',
        'nordstrom_us',
        'macys_us',
        'shein_ww',
        'temu_ww',
        'amazon_us',
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        $keys = [];
        foreach (self::ORDERED_KEYS as $key) {
            if (LivePlatformRegistry::isLivePlatform($key)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public static function label(string $key): string
    {
        return LivePlatformRegistry::label($key) ?: $key;
    }

    public static function isPlatform(string $key): bool
    {
        return in_array(strtolower(trim($key)), self::ORDERED_KEYS, true);
    }
}
