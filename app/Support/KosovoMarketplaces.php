<?php

namespace App\Support;

/**
 * Kosovo online marketplace & retailer catalog for local federated product discovery.
 */
class KosovoMarketplaces
{
    /** @var array<string, array{label: string, url: string, categories: array<int, string>}> */
    private const CATALOG = [
        // Marketplace / Classifieds
        'merrjep' => ['label' => 'MerrJep Kosovo', 'url' => 'https://www.merrjep.com', 'categories' => ['*']],
        'dyqani' => ['label' => 'Dyqani.app', 'url' => 'https://dyqani.app', 'categories' => ['*']],
        'pazar3' => ['label' => 'Pazar3 Kosovo', 'url' => 'https://www.pazar3.mk', 'categories' => ['*']],
        'gjirafa50' => ['label' => 'Gjirafa50', 'url' => 'https://gjirafa50.com', 'categories' => ['*', 'electronics_tech', 'gaming_entertainment', 'home_appliances']],
        'tregu' => ['label' => 'Tregu.com', 'url' => 'https://tregu.com', 'categories' => ['*']],

        // Electronics
        'neptun' => ['label' => 'Neptun Kosovo', 'url' => 'https://www.neptun-ks.com', 'categories' => ['electronics_tech', 'home_appliances', 'gaming_entertainment']],
        'aza_electronics' => ['label' => 'Aza Electronics', 'url' => 'https://aza-ks.com', 'categories' => ['electronics_tech']],
        'pcstore' => ['label' => 'PC Store Kosovo', 'url' => 'https://pcstore-ks.com', 'categories' => ['electronics_tech', 'gaming_entertainment']],
        'focus_electronics' => ['label' => 'FOCUS Electronics', 'url' => 'https://focus-ks.com', 'categories' => ['electronics_tech']],

        // Fashion / Sport
        'melodiapx' => ['label' => 'Melodia Px', 'url' => 'https://www.melodiapx.com/meshkuj/', 'categories' => ['fashion', 'sports_outdoor', 'marketplace']],
        'driloni' => ['label' => 'Driloni Sportswear', 'url' => 'https://driloni-ks.com', 'categories' => ['fashion', 'sports_outdoor', 'marketplace']],
        'butiku_regina' => ['label' => 'Butiku Regina', 'url' => 'https://www.butikuregina.com', 'categories' => ['fashion', 'sports_outdoor']],
        'vedude_fashion' => ['label' => 'Vedude Fashion', 'url' => 'https://vedudefashion.com', 'categories' => ['fashion']],
        'arjana_shop' => ['label' => 'Arjana Shop', 'url' => 'https://arjanashop.com', 'categories' => ['fashion']],
        'ssprint_fashion' => ['label' => "S'Sprint Fashion", 'url' => 'https://ssprintfashion.com', 'categories' => ['fashion', 'sports_outdoor']],
        'am_fashion' => ['label' => 'A&M Fashion', 'url' => 'https://amfashion-ks.com', 'categories' => ['fashion']],
        'waikiki_kosovo' => ['label' => 'Waikiki Kosovo', 'url' => 'https://www.lcwaikiki.com/en-XK', 'categories' => ['fashion', 'sports_outdoor']],
        'minimax_fashion' => ['label' => 'Minimax Fashion', 'url' => 'https://www.minimax-ks.com', 'categories' => ['fashion', 'marketplace']],
        'mona_fashion' => ['label' => 'Mona Fashion Kosovo', 'url' => 'https://mona-ks.com', 'categories' => ['fashion']],
        'fashion_group' => ['label' => 'Fashion Group Kosovo', 'url' => 'https://fashiongroup-ks.com', 'categories' => ['fashion']],
        'sport_vision' => ['label' => 'Sport Vision Kosovo', 'url' => 'https://sportvision-ks.com', 'categories' => ['fashion', 'sports_outdoor']],
        'buzz_sneakers' => ['label' => 'Buzz Sneakers Kosovo', 'url' => 'https://buzzsneakers-ks.com', 'categories' => ['fashion', 'sports_outdoor']],
        'nsport' => ['label' => 'N Sport Kosovo', 'url' => 'https://nsport-ks.com', 'categories' => ['fashion', 'sports_outdoor']],

        // Food & Retail
        'baboon' => ['label' => 'Baboon Delivery', 'url' => 'https://baboon-ks.com', 'categories' => ['grocery']],
        'foodba' => ['label' => 'FoodBA', 'url' => 'https://foodba.com', 'categories' => ['grocery']],
        'gjirafafood' => ['label' => 'GjirafaFood', 'url' => 'https://gjirafafood.com', 'categories' => ['grocery']],
        'interex' => ['label' => 'Interex Kosovo', 'url' => 'https://interex-ks.com', 'categories' => ['grocery', 'marketplace']],
        'viva_fresh' => ['label' => 'Viva Fresh Store', 'url' => 'https://vivafresh-ks.com', 'categories' => ['grocery']],
        'meridian' => ['label' => 'Meridian Express', 'url' => 'https://meridianexpress-ks.com', 'categories' => ['grocery']],
        'etc_kosovo' => ['label' => 'ETC Kosovo', 'url' => 'https://etc-ks.com', 'categories' => ['grocery', 'marketplace']],

        // Home / Construction
        'lesna' => ['label' => 'Lesna Kosovo', 'url' => 'https://lesna-ks.com', 'categories' => ['home_furniture', 'construction']],
        'bau_market' => ['label' => 'Bau Market Kosovo', 'url' => 'https://baumarket-ks.com', 'categories' => ['construction', 'home_furniture']],
        'mobileria_emra' => ['label' => 'Mobileria Emra', 'url' => 'https://mobileriaemra.com', 'categories' => ['home_furniture']],
        'fola_bedding' => ['label' => 'Fola Bedding', 'url' => 'https://fola-ks.com', 'categories' => ['home_furniture']],

        // Automotive
        'merrjep_auto' => ['label' => 'MerrJep Auto', 'url' => 'https://www.merrjep.com/auto', 'categories' => ['automotive']],
        'auto24' => ['label' => 'Auto24 Kosovo', 'url' => 'https://auto24-ks.com', 'categories' => ['automotive']],
        'kosova_motors' => ['label' => 'Kosova Motors', 'url' => 'https://kosovamotors.com', 'categories' => ['automotive']],

        // Health / Pharmacy
        'pharma_leader' => ['label' => 'Pharma Leader', 'url' => 'https://pharmaleader-ks.com', 'categories' => ['health_wellness', 'beauty']],
        'farmaci_online' => ['label' => 'Farmaci Online', 'url' => 'https://farmacionline-ks.com', 'categories' => ['health_wellness', 'beauty']],

        // Books / Education
        'dukagjini' => ['label' => 'Dukagjini Bookstore', 'url' => 'https://dukagjini.com', 'categories' => ['online_education', 'marketplace']],
        'libraria_albas' => ['label' => 'Libraria Albas', 'url' => 'https://librariaalbas.com', 'categories' => ['online_education']],

        // Travel
        'flyks' => ['label' => 'FlyKS', 'url' => 'https://flyks.com', 'categories' => ['travel']],
        'airprishtina' => ['label' => 'AirPrishtina', 'url' => 'https://www.airprishtina.com', 'categories' => ['travel']],
        'merrbus' => ['label' => 'MerrBus', 'url' => 'https://merrbus.com', 'categories' => ['travel']],

        // Digital / General retail
        'sparkle_shop' => ['label' => 'Sparkle Online Shop', 'url' => 'https://sparkle-ks.com', 'categories' => ['marketplace', 'fashion', 'electronics_tech']],
        'albi_online' => ['label' => 'Albi Online', 'url' => 'https://albionline.com', 'categories' => ['marketplace', 'fashion']],
    ];

    /** @var array<string, string> */
    private const ALIASES = [
        'gjirafa50com' => 'gjirafa50',
        'merrjepcom' => 'merrjep',
    ];

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
    public static function labels(): array
    {
        return array_values(array_map(fn (array $meta) => $meta['label'], self::CATALOG));
    }

    /**
     * Platforms relevant to a product category (always includes core classifieds).
     *
     * @return array<int, string>
     */
    public static function keysForCategory(string $category): array
    {
        $category = CategoryCatalog::normalize($category);
        $keys = [];

        foreach (self::CATALOG as $key => $meta) {
            $cats = $meta['categories'];
            if (in_array('*', $cats, true) || in_array($category, $cats, true)) {
                $keys[] = $key;
            }
        }

        foreach (['merrjep', 'dyqani', 'pazar3', 'gjirafa50'] as $core) {
            if (! in_array($core, $keys, true)) {
                $keys[] = $core;
            }
        }

        if (CategoryCatalog::isAutomotive($category)) {
            return KosovoCarMarketplaces::keys();
        }

        if (in_array($category, ['fashion', 'sports_outdoor'], true)) {
            $priority = array_values(array_filter(KosovoFashionPlatforms::keys(), fn ($k) => in_array($k, $keys, true)));
            if ($priority !== []) {
                $keys = array_values(array_unique(array_merge($priority, $keys)));
            }
        }

        return array_values(array_unique($keys));
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

    public static function isKosovoPlatform(string $source): bool
    {
        return isset(self::CATALOG[self::normalizeKey($source)]);
    }
}
