<?php

namespace App\Support;

/**
 * German car marketplace catalog for targeted DE vehicle searches.
 */
class GermanCarMarketplaces
{
    /** @var array<string, array{label: string, url: string}> */
    private const CATALOG = [
        'mobile_de' => [
            'label' => 'mobile.de',
            'url' => 'https://www.mobile.de',
        ],
        'autoscout24_de' => [
            'label' => 'AutoScout24 Germany',
            'url' => 'https://www.autoscout24.de',
        ],
        'kleinanzeigen' => [
            'label' => 'Kleinanzeigen',
            'url' => 'https://www.kleinanzeigen.de',
        ],
        'heycar_de' => [
            'label' => 'heycar',
            'url' => 'https://www.heycar.de',
        ],
        'autoplenum' => [
            'label' => 'Autoplenum.de',
            'url' => 'https://www.autoplenum.de',
        ],
        'pkw_de' => [
            'label' => 'PKW.de',
            'url' => 'https://www.pkw.de',
        ],
        'wirkaufendeinauto' => [
            'label' => 'wirkaufendeinauto.de',
            'url' => 'https://www.wirkaufendeinauto.de',
        ],
        'facebook_marketplace_de' => [
            'label' => 'Facebook Marketplace DE',
            'url' => 'https://www.facebook.com/marketplace',
        ],
    ];

    /** @var array<string, string> */
    private const ALIASES = [
        'mobile.de' => 'mobile_de',
        'autoscout24' => 'autoscout24_de',
        'facebook_marketplace' => 'facebook_marketplace_de',
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

        return self::ALIASES[$key] ?? self::ALIASES[str_replace('_', '.', $key)] ?? $key;
    }

    public static function label(string $source): string
    {
        $key = self::normalizeKey($source);
        $meta = self::CATALOG[$key] ?? null;

        return $meta['label'] ?? '';
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
    public static function isPlatform(string $source): bool
    {
        return isset(self::CATALOG[self::normalizeKey($source)]);
    }

    public static function isTarget(string $source, array $targets): bool
    {
        $key = self::normalizeKey($source);

        if (! isset(self::CATALOG[$key]) && ! in_array($source, ['mobile.de', 'autoscout24'], true)) {
            return false;
        }

        if ($targets === []) {
            return true;
        }

        foreach ($targets as $target) {
            if (self::normalizeKey($target) === $key) {
                return true;
            }
            if ($source === $target || str_replace('.', '_', $source) === str_replace('.', '_', $target)) {
                return true;
            }
        }

        return in_array($source, ['mobile.de', 'autoscout24'], true)
            && (in_array('mobile_de', $targets, true) || in_array('autoscout24_de', $targets, true));
    }
}
