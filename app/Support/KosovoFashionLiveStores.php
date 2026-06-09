<?php

namespace App\Support;

/**
 * Kosovo fashion retailers with live web catalog scraping (not mock JSON).
 */
class KosovoFashionLiveStores
{
    /** @var array<int, string> */
    public const KEYS = ['melodiapx', 'driloni'];

    public static function isLiveStore(string $sourceKey): bool
    {
        return in_array(strtolower($sourceKey), self::KEYS, true);
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function driloniSearchUrl(array $parsed): string
    {
        if (! empty($parsed['brand'])) {
            $search = (string) $parsed['brand'];
        } else {
            $search = trim((string) ($parsed['raw_query'] ?? 'fashion'));
        }

        return KosovoMarketplaces::url('driloni').'/?s='.rawurlencode($search).'&post_type=product';
    }
}
