<?php

namespace App\Support;

/**
 * Swiss fashion & sportswear retailer catalog for targeted CH product searches.
 */
class SwissFashionMarketplaces
{
    /** @var array<string, array{label: string, url: string, categories: array<int, string>}> */
    private const CATALOG = [
        'zalando_ch' => [
            'label' => 'Zalando Switzerland',
            'url' => 'https://www.zalando.ch',
            'categories' => ['fashion', 'sports_outdoor'],
        ],
        'aboutyou_ch' => [
            'label' => 'ABOUT YOU Switzerland',
            'url' => 'https://www.aboutyou.ch',
            'categories' => ['fashion', 'sports_outdoor'],
        ],
        'ochsnersport_ch' => [
            'label' => 'Ochsner Sport',
            'url' => 'https://www.ochsnersport.com',
            'categories' => ['fashion', 'sports_outdoor'],
        ],
        'intersport_ch' => [
            'label' => 'Intersport Switzerland',
            'url' => 'https://www.intersport.ch',
            'categories' => ['fashion', 'sports_outdoor'],
        ],
        'sportxx_ch' => [
            'label' => 'SportXX',
            'url' => 'https://www.sportxx.ch',
            'categories' => ['fashion', 'sports_outdoor'],
        ],
        'decathlon_ch' => [
            'label' => 'Decathlon Switzerland',
            'url' => 'https://www.decathlon.ch',
            'categories' => ['fashion', 'sports_outdoor'],
        ],
        'hm_ch' => [
            'label' => 'H&M Switzerland',
            'url' => 'https://www2.hm.com',
            'categories' => ['fashion', 'sports_outdoor'],
        ],
        'manor_ch' => [
            'label' => 'Manor',
            'url' => 'https://www.manor.ch',
            'categories' => ['fashion', 'sports_outdoor', 'marketplace'],
        ],
        'galaxus_ch' => [
            'label' => 'Galaxus',
            'url' => 'https://www.galaxus.ch',
            'categories' => ['fashion', 'sports_outdoor', 'marketplace'],
        ],
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::CATALOG);
    }

    public static function label(string $source): string
    {
        $key = strtolower(str_replace(['.', ' '], '_', trim($source)));

        return self::CATALOG[$key]['label'] ?? '';
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
