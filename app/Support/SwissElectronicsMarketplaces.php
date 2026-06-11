<?php

namespace App\Support;

/**
 * Swiss electronics & tech retailer catalog for targeted CH product searches.
 */
class SwissElectronicsMarketplaces
{
    /** @var array<string, array{label: string, url: string, categories: array<int, string>}> */
    private const CATALOG = [
        'digitec_ch' => [
            'label' => 'Digitec',
            'url' => 'https://www.digitec.ch',
            'categories' => ['electronics_tech', 'gaming_entertainment', 'home_appliances'],
        ],
        'galaxus_ch' => [
            'label' => 'Galaxus',
            'url' => 'https://www.galaxus.ch',
            'categories' => ['electronics_tech', 'gaming_entertainment', 'home_appliances', 'marketplace'],
        ],
        'interdiscount_ch' => [
            'label' => 'Interdiscount',
            'url' => 'https://www.interdiscount.ch',
            'categories' => ['electronics_tech', 'home_appliances'],
        ],
        'mediamarkt_ch' => [
            'label' => 'MediaMarkt Switzerland',
            'url' => 'https://www.mediamarkt.ch',
            'categories' => ['electronics_tech', 'home_appliances', 'gaming_entertainment'],
        ],
        'manor_ch' => [
            'label' => 'Manor',
            'url' => 'https://www.manor.ch',
            'categories' => ['electronics_tech', 'home_appliances', 'marketplace'],
        ],
        'apple_ch' => [
            'label' => 'Apple Store Switzerland',
            'url' => 'https://www.apple.com',
            'categories' => ['electronics_tech'],
        ],
        'steg_ch' => [
            'label' => 'Steg Electronics',
            'url' => 'https://www.steg-electronics.ch',
            'categories' => ['electronics_tech', 'gaming_entertainment'],
        ],
        'fust_ch' => [
            'label' => 'Fust',
            'url' => 'https://www.fust.ch',
            'categories' => ['electronics_tech', 'home_appliances'],
        ],
        'brack_ch' => [
            'label' => 'Brack.ch',
            'url' => 'https://www.brack.ch',
            'categories' => ['electronics_tech', 'gaming_entertainment'],
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
