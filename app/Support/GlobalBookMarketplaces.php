<?php

namespace App\Support;

/**
 * Global online book retailers for federated book discovery.
 */
class GlobalBookMarketplaces
{
    /** @var array<string, array{label: string, url: string, categories: array<int, string>}> */
    private const CATALOG = [
        'amazon' => [
            'label' => 'Amazon Books',
            'url' => 'https://www.amazon.com',
            'categories' => ['online_education'],
        ],
        'ebay' => [
            'label' => 'eBay Books',
            'url' => 'https://www.ebay.com',
            'categories' => ['online_education'],
        ],
        'google_shopping' => [
            'label' => 'Google Shopping Books',
            'url' => 'https://shopping.google.com',
            'categories' => ['online_education'],
        ],
        'book_depository' => [
            'label' => 'Book Depository',
            'url' => 'https://www.bookdepository.com',
            'categories' => ['online_education'],
        ],
        'waterstones' => [
            'label' => 'Waterstones',
            'url' => 'https://www.waterstones.com',
            'categories' => ['online_education'],
        ],
        'audible' => [
            'label' => 'Audible',
            'url' => 'https://www.audible.com',
            'categories' => ['online_education'],
        ],
    ];

    /** @var array<int, string> */
    private const KOSOVO_BOOK_KEYS = ['dukagjini', 'libraria_albas', 'merrjep'];

    /**
     * @return array<string, array{label: string, url: string, categories: array<int, string>}>
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
    public static function kosovoKeys(): array
    {
        return self::KOSOVO_BOOK_KEYS;
    }

    /**
     * @return array<int, string>
     */
    public static function keysForCountry(string $countryCode): array
    {
        $code = strtoupper($countryCode);
        if ($code === 'XK') {
            return array_values(array_unique(array_merge(self::keys(), self::kosovoKeys())));
        }

        return self::keys();
    }

    /**
     * @return array<int, string>
     */
    public static function labels(): array
    {
        return array_values(array_map(fn (array $meta) => $meta['label'], self::CATALOG));
    }

    public static function label(string $key): string
    {
        return self::CATALOG[$key]['label'] ?? KosovoMarketplaces::label($key);
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

        if (isset(self::CATALOG[$key])) {
            if ($targets === []) {
                return true;
            }

            foreach ($targets as $target) {
                if (strtolower(str_replace(['.', ' '], '_', $target)) === $key) {
                    return true;
                }
            }
        }

        if (KosovoMarketplaces::isTarget($source, self::kosovoKeys())) {
            return $targets === [] || KosovoMarketplaces::isTarget($source, $targets);
        }

        return false;
    }
}
