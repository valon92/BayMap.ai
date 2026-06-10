<?php

namespace App\Support;

/**
 * Kosovo automotive marketplace catalog — car-only platforms (no fashion/electronics stores).
 */
class KosovoCarMarketplaces
{
    /** @var array<string, array{label: string, url: string}> */
    private const CATALOG = [
        'merrjep_auto' => [
            'label' => 'MerrJep Auto',
            'url' => 'https://www.merrjep.com/auto',
        ],
        'auto24' => [
            'label' => 'Auto24 Kosovo',
            'url' => 'https://auto24-ks.com',
        ],
        'kosova_motors' => [
            'label' => 'Kosova Motors',
            'url' => 'https://kosovamotors.com',
        ],
        'merrjep' => [
            'label' => 'MerrJep Kosovo',
            'url' => 'https://www.merrjep.com',
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

        return self::CATALOG[$key]['label'] ?? KosovoMarketplaces::label($source);
    }

    public static function url(string $source): ?string
    {
        $key = strtolower(str_replace(['.', ' '], '_', trim($source)));

        return self::CATALOG[$key]['url'] ?? KosovoMarketplaces::url($source);
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
            return KosovoMarketplaces::isTarget($source, $targets);
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
