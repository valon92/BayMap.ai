<?php

namespace App\Support;

/**
 * Dutch electronics & tech retailer catalog for targeted NL product searches.
 */
class DutchElectronicsMarketplaces
{
    /** @var array<string, array{label: string, url: string, categories: array<int, string>}> */
    private const CATALOG = [
        'coolblue_nl' => [
            'label' => 'Coolblue',
            'url' => 'https://www.coolblue.nl',
            'categories' => ['electronics_tech', 'home_appliances', 'gaming_entertainment'],
        ],
        'bol_com' => [
            'label' => 'bol.com',
            'url' => 'https://www.bol.com',
            'categories' => ['electronics_tech', 'gaming_entertainment', 'marketplace'],
        ],
        'mediamarkt_nl' => [
            'label' => 'MediaMarkt NL',
            'url' => 'https://www.mediamarkt.nl',
            'categories' => ['electronics_tech', 'home_appliances', 'gaming_entertainment'],
        ],
        'bcc_nl' => [
            'label' => 'BCC',
            'url' => 'https://www.bcc.nl',
            'categories' => ['electronics_tech', 'home_appliances'],
        ],
        'alternate_nl' => [
            'label' => 'Alternate.nl',
            'url' => 'https://www.alternate.nl',
            'categories' => ['electronics_tech', 'gaming_entertainment'],
        ],
        'azerty_nl' => [
            'label' => 'Azerty',
            'url' => 'https://www.azerty.nl',
            'categories' => ['electronics_tech'],
        ],
        'paradigit_nl' => [
            'label' => 'Paradigit',
            'url' => 'https://www.paradigit.nl',
            'categories' => ['electronics_tech', 'gaming_entertainment'],
        ],
        'centralpoint_nl' => [
            'label' => 'Centralpoint',
            'url' => 'https://www.centralpoint.nl',
            'categories' => ['electronics_tech'],
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
        return array_values(array_map(fn (array $meta) => $meta['label'], self::CATALOG));
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
