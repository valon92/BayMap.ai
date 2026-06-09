<?php

namespace App\Support;

/**
 * Melodia Px (Kosovo) catalog URL builder — CS-Cart category + feature_hash filters.
 */
class MelodiaPxCatalog
{
    public const BASE_URL = 'https://www.melodiapx.com';

    /** @var array<string, string> brand slug => features_hash fragment (filter 8 = brand) */
    private const BRAND_HASH = [
        'nike' => 'features_hash=8-689',
        'puma' => 'features_hash=8-2557',
        'adidas' => 'features_hash=8-519',
        'reebok' => 'features_hash=8-767',
        'new_balance' => 'features_hash=8-488',
        'under_armour' => 'features_hash=8-1879',
        'timberland' => 'features_hash=8-176843',
    ];

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function catalogUrl(array $parsed): string
    {
        $gender = mb_strtolower((string) ($parsed['gender'] ?? ''));
        $path = match (true) {
            in_array($gender, ['male', 'men', 'meshkuj'], true) => '/meshkuj/',
            in_array($gender, ['female', 'women', 'femra'], true) => '/femra/',
            default => '/meshkuj/',
        };

        $brand = mb_strtolower((string) ($parsed['brand'] ?? ''));
        $query = self::BRAND_HASH[$brand] ?? '';

        $url = rtrim(self::BASE_URL, '/').$path;
        if ($query !== '') {
            $url .= '?'.$query;
        }

        return $url;
    }

    public static function hasBrandHash(string $brand): bool
    {
        return isset(self::BRAND_HASH[mb_strtolower(trim($brand))]);
    }

    public static function isMelodiaSource(string $source): bool
    {
        return in_array(strtolower($source), ['melodiapx', 'melodia_px'], true);
    }
}
