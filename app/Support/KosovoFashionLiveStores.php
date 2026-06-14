<?php

namespace App\Support;

/**
 * Kosovo fashion retailers with live web catalog scraping (not mock JSON).
 */
class KosovoFashionLiveStores
{
    public static function isLiveStore(string $sourceKey): bool
    {
        return LivePlatformRegistry::isLivePlatform($sourceKey)
            && in_array(strtolower($sourceKey), LivePlatformRegistry::keysFor('XK', 'fashion'), true);
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function driloniSearchUrl(array $parsed): string
    {
        $type = mb_strtolower((string) ($parsed['product_type'] ?? ''));
        $raw = mb_strtolower(trim((string) ($parsed['raw_query'] ?? $parsed['search_query'] ?? '')));
        $isAccessory = KosovoFashionIntent::isAccessoryType($type) || KosovoFashionIntent::queryMentionsAccessory($raw);

        if ($isAccessory) {
            $search = ! empty($parsed['brand'])
                ? trim((string) $parsed['brand'])
                : 'kapela';

            return KosovoMarketplaces::url('driloni')
                .'/product-category/lloji-i-produktit/kapela/?s='.rawurlencode($search)
                .'&post_type=product&dgwt_wcas=1&filter=1';
        }

        if (! empty($parsed['brand'])) {
            $search = (string) $parsed['brand'];
        } else {
            $search = KosovoFashionIntent::isFootwearType($type)
                ? 'atlete'
                : self::compactSearchTerm($parsed);
        }

        return KosovoMarketplaces::url('driloni').'/?s='.rawurlencode($search).'&post_type=product';
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private static function compactSearchTerm(array $parsed): string
    {
        $search = trim((string) ($parsed['search_query'] ?? $parsed['raw_query'] ?? 'fashion'));
        $search = preg_replace('/\b(?:size|numer|nr|madh[eë]sia)\s*\d+(?:\.\d+)?\b/ui', '', $search) ?? $search;
        $search = preg_replace('/\b(?:deri|max|up to)\s*(?:ne|në|to)?\s*\d+\s*(?:euro|eur|€)\b/ui', '', $search) ?? $search;
        $search = preg_replace('/\b(?:black|white|blue|red|green|grey|gray|zez[aë]?|bardh[aë]?)\b/ui', '', $search) ?? $search;
        $search = trim(preg_replace('/\s+/', ' ', $search) ?? $search);

        return $search !== '' ? $search : 'fashion';
    }
}
