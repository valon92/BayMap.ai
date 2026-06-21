<?php

namespace App\Support;

/**
 * Auto parts platform keys by country — local catalog + global connectors.
 */
class AutomotivePartsMarketplaces
{
    /** @var array<string, array<int, string>> Priority-ordered local platforms */
    private const ORDERED = [
        'DE' => [
            'autodoc_de', 'pro4matic_de', 'kfzteile24_de', 'atp_autoteile_de', 'mister_auto_de', 'oscaro_de',
            'motointegrator_de', 'amazon_automotive_de', 'ebay',
        ],
        'CH' => [
            'autodoc_ch', 'oscaro_ch', 'car_part_ch', 'allparts_ch', 'amazon_automotive_ch', 'ebay',
        ],
        'AT' => [
            'autodoc_at', 'autoparts24_at', 'mister_auto_at', 'willhaben_parts_at', 'ebay',
        ],
        'FR' => [
            'oscaro_fr', 'mister_auto_fr', 'autodoc_fr', 'norauto_fr', 'gsf_fr', 'amazon_automotive_fr', 'ebay',
        ],
        'IT' => [
            'autodoc_it', 'mister_auto_it', 'ricambi_auto_it', 'amazon_automotive_it', 'ebay',
        ],
        'ES' => [
            'autodoc_es', 'norauto_es', 'mister_auto_es', 'amazon_automotive_es', 'ebay',
        ],
        'PT' => ['autodoc_pt', 'norauto_pt', 'ebay'],
        'NL' => ['winparts_nl', 'autodoc_nl', 'marktplaats_parts_nl', 'amazon_automotive_nl', 'ebay'],
        'BE' => ['autodoc_be', 'oscaro_be', 'ebay'],
        'PL' => ['autodoc_pl', 'intercars_pl', 'motointegrator_pl', 'ebay'],
        'GB' => [
            'eurocarparts_uk', 'gsf_uk', 'halfords_uk', 'carparts4less_uk', 'autodoc_uk',
            'amazon_automotive_uk', 'ebay',
        ],
        'US' => [
            'rockauto_us', 'autozone_us', 'advanceautoparts_us', 'oreillyauto_us',
            'carparts_us', 'napaonline_us', 'amazon_automotive_us', 'ebay',
        ],
        'CA' => ['canadian_tire_auto_ca', 'amazon_automotive_ca', 'ebay'],
        'AU' => ['supercheap_auto_au', 'repco_au', 'ebay'],
        'TR' => ['autodoc_tr', 'n11_auto_tr', 'ebay'],
        'SE' => ['autodoc_se', 'ebay'],
        'NO' => ['autodoc_no', 'ebay'],
        'DK' => ['autodoc_dk', 'ebay'],
        'RO' => ['autodoc_ro', 'emag_auto_ro', 'ebay'],
        'BG' => ['emag_bg', 'ebay'],
        'CZ' => ['autodoc_cz', 'ebay'],
        'AL' => ['autodoc_al', 'ebay'],
        'XK' => ['merrjep_parts_xk', 'autopjes_online_xk', 'ebay'],
        'HU' => ['autodoc_hu', 'ebay'],
        'GR' => ['autodoc_gr', 'ebay'],
        'HR' => ['autodoc_hr', 'ebay'],
        'SI' => ['autodoc_si', 'ebay'],
        'SK' => ['autodoc_sk', 'ebay'],
        'IE' => ['autodoc_ie', 'ebay'],
        'FI' => ['autodoc_fi', 'ebay'],
        'LT' => ['autodoc_lt', 'ebay'],
        'LV' => ['autodoc_lv', 'ebay'],
        'EE' => ['autodoc_ee', 'ebay'],
        'UA' => ['autodoc_ua', 'ebay'],
        'LU' => ['autodoc_lu', 'ebay'],
        'RS' => ['autodoc_rs', 'ebay'],
        'JP' => ['autodoc_jp', 'ebay'],
        'IN' => ['autodoc_in', 'ebay'],
        'BR' => ['autodoc_br', 'ebay'],
        'MX' => ['autodoc_mx', 'ebay'],
        'ZA' => ['autodoc_za', 'ebay'],
        'MK' => ['ebay'],
        'BA' => ['ebay'],
        'ME' => ['ebay'],
    ];

    /** @var array<int, string> Always available worldwide connectors */
    private const GLOBAL = [
        'ebay',
        'ebay_motors_ww',
        'rockauto_ww',
        'amazon_automotive_ww',
    ];

    /** Autodoc TLD suffixes — platform key autodoc_{suffix} when present in catalog */
    private const AUTODOC_SUFFIX = [
        'DE', 'FR', 'IT', 'ES', 'PT', 'NL', 'BE', 'PL', 'AT', 'CH', 'GB', 'RO', 'BG', 'CZ', 'AL',
        'SE', 'NO', 'DK', 'TR', 'HU', 'GR', 'HR', 'SI', 'SK', 'IE', 'FI', 'LT', 'LV', 'EE', 'UA',
        'LU', 'RS',
    ];

    /**
     * @return array<int, string>
     */
    public static function keysFor(string $countryCode): array
    {
        $countryCode = strtoupper($countryCode);
        $keys = self::ORDERED[$countryCode] ?? [];

        if ($keys === []) {
            $keys = self::discoveredLocalKeys($countryCode);
        }

        if ($keys === []) {
            $keys = self::GLOBAL;
        } else {
            $keys = array_values(array_unique(array_merge($keys, self::GLOBAL)));
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return array<int, string>
     */
    private static function discoveredLocalKeys(string $countryCode): array
    {
        $keys = [];

        if (in_array($countryCode, self::AUTODOC_SUFFIX, true)) {
            $autodoc = 'autodoc_'.strtolower($countryCode);
            if (LivePlatformRegistry::isLivePlatform($autodoc)) {
                $keys[] = $autodoc;
            }
        }

        $amazon = match ($countryCode) {
            'DE' => 'amazon_automotive_de',
            'FR' => 'amazon_automotive_fr',
            'IT' => 'amazon_automotive_it',
            'ES' => 'amazon_automotive_es',
            'NL' => 'amazon_automotive_nl',
            'GB' => 'amazon_automotive_uk',
            'US' => 'amazon_automotive_us',
            'CA' => 'amazon_automotive_ca',
            default => null,
        };
        if ($amazon !== null && LivePlatformRegistry::isLivePlatform($amazon)) {
            $keys[] = $amazon;
        }

        $keys[] = 'ebay';

        return array_values(array_unique($keys));
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        $all = self::GLOBAL;
        foreach (self::ORDERED as $keys) {
            $all = array_merge($all, $keys);
        }

        return array_values(array_unique($all));
    }

    public static function label(string $key): string
    {
        return LivePlatformRegistry::label($key) ?: $key;
    }
}
