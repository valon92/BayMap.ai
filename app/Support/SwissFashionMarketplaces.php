<?php

namespace App\Support;

/**
 * Swiss fashion retailer catalog — one FashionWorker per store (CH targeted searches).
 */
class SwissFashionMarketplaces
{
    /** @var array<int, string> */
    public const ORDERED_KEYS = [
        'zalando_ch',
        'aboutyou_ch',
        'manor_ch',
        'pkz_ch',
        'ochsner_shoes_ch',
        'dosenbach_ch',
        'jelmoli_ch',
        'globus_ch',
        'bonprix_ch',
        'hm_ch',
        'ca_ch',
        'mango_ch',
        'galaxus_ch',
        'ricardo_ch',
        'anibis_ch',
    ];

    /** @var array<string, array{label: string, url: string, categories: array<int, string>}> */
    private const CATALOG = [
        'zalando_ch' => [
            'label' => 'Zalando CH',
            'url' => 'https://www.zalando.ch',
            'categories' => ['fashion', 'sports_outdoor'],
        ],
        'aboutyou_ch' => [
            'label' => 'ABOUT YOU CH',
            'url' => 'https://www.aboutyou.ch',
            'categories' => ['fashion', 'sports_outdoor'],
        ],
        'manor_ch' => [
            'label' => 'Manor',
            'url' => 'https://www.manor.ch',
            'categories' => ['fashion', 'sports_outdoor', 'marketplace'],
        ],
        'pkz_ch' => [
            'label' => 'PKZ',
            'url' => 'https://www.pkz.ch',
            'categories' => ['fashion', 'sports_outdoor'],
        ],
        'ochsner_shoes_ch' => [
            'label' => 'Ochsner Shoes',
            'url' => 'https://www.ochsner-shoes.ch',
            'categories' => ['fashion', 'sports_outdoor'],
        ],
        'dosenbach_ch' => [
            'label' => 'Dosenbach',
            'url' => 'https://www.dosenbach.ch',
            'categories' => ['fashion', 'sports_outdoor'],
        ],
        'jelmoli_ch' => [
            'label' => 'Jelmoli',
            'url' => 'https://www.jelmoli.ch',
            'categories' => ['fashion', 'sports_outdoor'],
        ],
        'globus_ch' => [
            'label' => 'Globus',
            'url' => 'https://www.globus.ch',
            'categories' => ['fashion', 'sports_outdoor'],
        ],
        'bonprix_ch' => [
            'label' => 'Bonprix',
            'url' => 'https://www.bonprix.ch',
            'categories' => ['fashion', 'sports_outdoor'],
        ],
        'hm_ch' => [
            'label' => 'H&M CH',
            'url' => 'https://www2.hm.com',
            'categories' => ['fashion', 'sports_outdoor'],
        ],
        'ca_ch' => [
            'label' => 'C&A CH',
            'url' => 'https://www.c-and-a.com',
            'categories' => ['fashion', 'sports_outdoor'],
        ],
        'mango_ch' => [
            'label' => 'Mango CH',
            'url' => 'https://shop.mango.com',
            'categories' => ['fashion', 'sports_outdoor'],
        ],
        'galaxus_ch' => [
            'label' => 'Galaxus',
            'url' => 'https://www.galaxus.ch',
            'categories' => ['fashion', 'sports_outdoor', 'marketplace'],
        ],
        'ricardo_ch' => [
            'label' => 'Ricardo',
            'url' => 'https://www.ricardo.ch',
            'categories' => ['fashion', 'sports_outdoor', 'marketplace'],
        ],
        'anibis_ch' => [
            'label' => 'Anibis',
            'url' => 'https://www.anibis.ch',
            'categories' => ['fashion', 'sports_outdoor', 'marketplace'],
        ],
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return self::ORDERED_KEYS;
    }

    /**
     * @return array<int, array{name: string, initial: string, key: string}>
     */
    public static function workerPreviews(): array
    {
        $workers = [];
        foreach (self::ORDERED_KEYS as $index => $key) {
            $label = self::label($key);
            $workers[] = [
                'key' => $key,
                'name' => $label,
                'initial' => self::initials($label),
                'worker_id' => 'FashionWorker-'.($index + 1),
            ];
        }

        return $workers;
    }

    public static function workerIdFor(string $source): ?string
    {
        $key = self::normalizeKey($source);
        $pos = array_search($key, self::ORDERED_KEYS, true);
        if ($pos === false) {
            return null;
        }

        return 'FashionWorker-'.($pos + 1);
    }

    public static function label(string $source): string
    {
        $key = self::normalizeKey($source);

        return self::CATALOG[$key]['label'] ?? '';
    }

    public static function url(string $source): ?string
    {
        $key = self::normalizeKey($source);

        return self::CATALOG[$key]['url'] ?? null;
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

    private static function normalizeKey(string $source): string
    {
        return strtolower(str_replace(['.', ' '], '_', trim($source)));
    }

    private static function initials(string $label): string
    {
        $parts = preg_split('/\s+/u', trim($label)) ?: [];
        if ($parts === []) {
            return 'CH';
        }

        $initials = '';
        foreach ($parts as $part) {
            if ($part === '' || in_array(strtoupper($part), ['CH', 'H&M'], true)) {
                continue;
            }
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
            if (strlen($initials) >= 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : mb_strtoupper(mb_substr($label, 0, 2));
    }
}
