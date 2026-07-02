<?php

namespace App\Support;

/**
 * Swiss car marketplace catalog for targeted CH vehicle searches.
 */
class SwissCarMarketplaces
{
    /** @var array<string, array{label: string, url: string}> */
    private const CATALOG = [
        'autoscout24_ch' => [
            'label' => 'AutoScout24 Switzerland',
            'url' => 'https://www.autoscout24.ch',
        ],
        'autolina' => [
            'label' => 'Autolina',
            'url' => 'https://www.autolina.ch',
        ],
        'amag' => [
            'label' => 'AMAG',
            'url' => 'https://www.amag.ch',
        ],
        'carlando' => [
            'label' => 'Carlando',
            'url' => 'https://www.carlando.ch',
        ],
        'carindex' => [
            'label' => 'Carindex',
            'url' => 'https://www.carindex.ch',
        ],
        'troovo' => [
            'label' => 'Troovo Auto',
            'url' => 'https://www.troovo.ch',
        ],
        'car_trade24' => [
            'label' => 'Car Trade24',
            'url' => 'https://www.cartrade24.ch',
        ],
        'motoauto_ch' => [
            'label' => 'MotoAuto Switzerland',
            'url' => 'https://www.motoauto.ch',
        ],
        'autogrid_ch' => [
            'label' => 'Autogrid Switzerland',
            'url' => 'https://www.autogrid.ch',
        ],
        'ricardo' => [
            'label' => 'Ricardo.ch',
            'url' => 'https://www.ricardo.ch',
        ],
        'tutti' => [
            'label' => 'Tutti.ch',
            'url' => 'https://www.tutti.ch',
        ],
        'facebook_marketplace' => [
            'label' => 'Facebook Marketplace',
            'url' => 'https://www.facebook.com/marketplace',
        ],
    ];

    /** @var array<string, string> */
    private const ALIASES = [
        'autoscout24' => 'autoscout24_ch',
        'autoscout24ch' => 'autoscout24_ch',
        'ricardoch' => 'ricardo',
        'tuttich' => 'tutti',
        'motoauto' => 'motoauto_ch',
        'autogrid' => 'autogrid_ch',
        'cartrade24' => 'car_trade24',
    ];

    /**
     * @return array<string, array{label: string, url: string}>
     */
    public static function catalog(): array
    {
        return self::CATALOG;
    }

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
        return array_values(array_map(fn (array $meta) => $meta['label'], self::CATALOG));
    }

    public static function normalizeKey(string $source): string
    {
        $key = strtolower(str_replace(['.', ' '], '_', trim($source)));

        return self::ALIASES[$key] ?? $key;
    }

    public static function label(string $source): string
    {
        $key = self::normalizeKey($source);
        $meta = self::CATALOG[$key] ?? null;

        if ($meta) {
            return $meta['label'];
        }

        return ucfirst(str_replace('_', ' ', $source));
    }

    public static function url(string $source): ?string
    {
        $key = self::normalizeKey($source);
        $meta = self::CATALOG[$key] ?? null;

        return $meta['url'] ?? null;
    }

    /**
     * @param  array<int, string>  $targets
     */
    public static function isTarget(string $source, array $targets): bool
    {
        $key = self::normalizeKey($source);

        if (! isset(self::CATALOG[$key])) {
            return false;
        }

        if ($targets === []) {
            return true;
        }

        foreach ($targets as $target) {
            if (self::normalizeKey($target) === $key) {
                return true;
            }
        }

        return false;
    }

    public static function isPlatform(string $source): bool
    {
        return isset(self::CATALOG[self::normalizeKey($source)]);
    }
}
