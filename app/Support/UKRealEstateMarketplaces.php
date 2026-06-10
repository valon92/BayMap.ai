<?php

namespace App\Support;

/**
 * UK real-estate marketplace catalog for targeted GB property searches.
 */
class UKRealEstateMarketplaces
{
    /** @var array<string, array{label: string, url: string}> */
    private const CATALOG = [
        'rightmove_uk' => [
            'label' => 'Rightmove',
            'url' => 'https://www.rightmove.co.uk',
        ],
        'zoopla_uk' => [
            'label' => 'Zoopla',
            'url' => 'https://www.zoopla.co.uk',
        ],
        'onthemarket_uk' => [
            'label' => 'OnTheMarket',
            'url' => 'https://www.onthemarket.com',
        ],
        'primelocation_uk' => [
            'label' => 'PrimeLocation',
            'url' => 'https://www.primelocation.com',
        ],
        'openrent_uk' => [
            'label' => 'OpenRent',
            'url' => 'https://www.openrent.co.uk',
        ],
        'gumtree_property_uk' => [
            'label' => 'Gumtree Property',
            'url' => 'https://www.gumtree.com',
        ],
        'facebook_marketplace_uk' => [
            'label' => 'Facebook Marketplace UK',
            'url' => 'https://www.facebook.com/marketplace',
        ],
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::CATALOG);
    }

    /**
     * @return array<int, string>
     */
    public static function labels(): array
    {
        return array_values(array_map(fn (array $m) => $m['label'], self::CATALOG));
    }

    public static function label(string $source): string
    {
        $key = strtolower(str_replace(['.', ' '], '_', trim($source)));

        return self::CATALOG[$key]['label'] ?? '';
    }

    public static function url(string $source): ?string
    {
        $key = strtolower(str_replace(['.', ' '], '_', trim($source)));

        return self::CATALOG[$key]['url'] ?? null;
    }

    public static function isPlatform(string $source): bool
    {
        $key = strtolower(str_replace(['.', ' '], '_', trim($source)));

        return isset(self::CATALOG[$key]);
    }

    /**
     * @param  array<int, string>  $targets
     */
    public static function isTarget(string $source, array $targets): bool
    {
        $key = strtolower(str_replace(['.', ' '], '_', trim($source)));

        if (! isset(self::CATALOG[$key])) {
            return false;
        }

        if ($targets === []) {
            return true;
        }

        foreach ($targets as $target) {
            if (strtolower(str_replace(['.', ' '], '_', trim($target))) === $key) {
                return true;
            }
        }

        return false;
    }
}
