<?php

namespace App\Support;

/**
 * German electronics & tech retailer catalog for targeted DE product searches.
 */
class GermanElectronicsMarketplaces
{
    /** @var array<string, array{label: string, url: string, focus: string, categories: array<int, string>}> */
    private const CATALOG = [
        'mediamarkt' => [
            'label' => 'MediaMarkt',
            'url' => 'https://www.mediamarkt.de',
            'focus' => 'general_electronics',
            'categories' => ['electronics_tech', 'home_appliances', 'gaming_entertainment'],
        ],
        'saturn' => [
            'label' => 'Saturn',
            'url' => 'https://www.saturn.de',
            'focus' => 'general_electronics',
            'categories' => ['electronics_tech', 'home_appliances', 'gaming_entertainment'],
        ],
        'cyberport' => [
            'label' => 'Cyberport',
            'url' => 'https://www.cyberport.de',
            'focus' => 'apple_laptops',
            'categories' => ['electronics_tech', 'gaming_entertainment'],
        ],
        'alternate' => [
            'label' => 'Alternate',
            'url' => 'https://www.alternate.de',
            'focus' => 'gaming_pc',
            'categories' => ['electronics_tech', 'gaming_entertainment'],
        ],
        'conrad' => [
            'label' => 'Conrad Electronic',
            'url' => 'https://www.conrad.de',
            'focus' => 'components_iot',
            'categories' => ['electronics_tech', 'home_appliances'],
        ],
        'notebooksbilliger' => [
            'label' => 'Notebooksbilliger',
            'url' => 'https://www.notebooksbilliger.de',
            'focus' => 'laptops_phones',
            'categories' => ['electronics_tech'],
        ],
        'mindfactory' => [
            'label' => 'Mindfactory',
            'url' => 'https://www.mindfactory.de',
            'focus' => 'pc_gaming',
            'categories' => ['electronics_tech', 'gaming_entertainment'],
        ],
        'caseking' => [
            'label' => 'Caseking',
            'url' => 'https://www.caseking.de',
            'focus' => 'gaming_hardware',
            'categories' => ['electronics_tech', 'gaming_entertainment'],
        ],
        'computeruniverse' => [
            'label' => 'Computeruniverse',
            'url' => 'https://www.computeruniverse.net',
            'focus' => 'it_electronics',
            'categories' => ['electronics_tech'],
        ],
        'reichelt' => [
            'label' => 'Reichelt Elektronik',
            'url' => 'https://www.reichelt.de',
            'focus' => 'components',
            'categories' => ['electronics_tech'],
        ],
        'euronics' => [
            'label' => 'Euronics',
            'url' => 'https://www.euronics.de',
            'focus' => 'general_electronics',
            'categories' => ['electronics_tech', 'home_appliances'],
        ],
        'expert_de' => [
            'label' => 'Expert',
            'url' => 'https://www.expert.de',
            'focus' => 'tv_electronics',
            'categories' => ['electronics_tech', 'home_appliances'],
        ],
        'pearl' => [
            'label' => 'Pearl',
            'url' => 'https://www.pearl.de',
            'focus' => 'gadgets_smart_home',
            'categories' => ['electronics_tech'],
        ],
        'jacob' => [
            'label' => 'Jacob Elektronik',
            'url' => 'https://www.jacob.de',
            'focus' => 'it_business',
            'categories' => ['electronics_tech'],
        ],
        'voelkner' => [
            'label' => 'Voelkner',
            'url' => 'https://www.voelkner.de',
            'focus' => 'general_electronics',
            'categories' => ['electronics_tech'],
        ],
        'apple_de' => [
            'label' => 'Apple Store Germany',
            'url' => 'https://www.apple.com/de',
            'focus' => 'apple_devices',
            'categories' => ['electronics_tech'],
        ],
    ];

    /** @var array<string, string> */
    private const ALIASES = [
        'expert' => 'expert_de',
        'expert.de' => 'expert_de',
        'notebooksbilliger.de' => 'notebooksbilliger',
        'computeruniverse.net' => 'computeruniverse',
    ];

    /**
     * @return array<string, array{label: string, url: string, focus: string, categories: array<int, string>}>
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

    /**
     * Phone / Apple relevant retailers — preferred for smartphone searches.
     *
     * @return array<int, string>
     */
    public static function phoneRetailerKeys(): array
    {
        return [
            'mediamarkt', 'saturn', 'cyberport', 'notebooksbilliger',
            'computeruniverse', 'euronics', 'expert_de', 'voelkner',
        ];
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

        return $meta['label'] ?? '';
    }

    public static function url(string $source): ?string
    {
        $key = self::normalizeKey($source);
        $meta = self::CATALOG[$key] ?? null;

        return $meta['url'] ?? null;
    }

    public static function isPlatform(string $source): bool
    {
        return isset(self::CATALOG[self::normalizeKey($source)]);
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
