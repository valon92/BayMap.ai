<?php

namespace App\Support;

/**
 * Dutch car marketplace catalog for targeted NL vehicle searches.
 */
class DutchCarMarketplaces
{
    /** @var array<string, array{label: string, url: string}> */
    private const CATALOG = [
        'marktplaats' => [
            'label' => 'Marktplaats',
            'url' => 'https://www.marktplaats.nl',
        ],
        'autoscout24_nl' => [
            'label' => 'AutoScout24 Netherlands',
            'url' => 'https://www.autoscout24.nl',
        ],
        'autotrack_nl' => [
            'label' => 'Autotrack.nl',
            'url' => 'https://www.autotrack.nl',
        ],
        'gaspedaal' => [
            'label' => 'Gaspedaal.com',
            'url' => 'https://www.gaspedaal.com',
        ],
        'autowereld' => [
            'label' => 'Autowereld.nl',
            'url' => 'https://www.autowereld.nl',
        ],
        'vakgarage' => [
            'label' => 'Vakgarage',
            'url' => 'https://www.vakgarage.nl',
        ],
        'occasion_nl' => [
            'label' => 'Occasion.nl',
            'url' => 'https://www.occasion.nl',
        ],
        'autodealers_nl' => [
            'label' => 'Autodealers.nl',
            'url' => 'https://www.autodealers.nl',
        ],
        'facebook_marketplace_nl' => [
            'label' => 'Facebook Marketplace NL',
            'url' => 'https://www.facebook.com/marketplace',
        ],
    ];

    /** @var array<string, string> */
    private const ALIASES = [
        'autoscout24' => 'autoscout24_nl',
        'autoscout24nl' => 'autoscout24_nl',
        'facebook_marketplace' => 'facebook_marketplace_nl',
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

        return '';
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
}
