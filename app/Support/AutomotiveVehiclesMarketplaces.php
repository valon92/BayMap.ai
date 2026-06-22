<?php

namespace App\Support;

/**
 * Live vehicle marketplace keys by country — local catalog + discovery fallback.
 */
class AutomotiveVehiclesMarketplaces
{
    /** @var array<string, array<int, string>> Priority-ordered local vehicle platforms */
    private const ORDERED = [
        'AU' => ['carsales_au', 'drive_au', 'gumtree_cars_au', 'carsguide_au'],
        'NZ' => ['trademe_nz', 'autotrader_nz'],
        'FJ' => ['facebook_marketplace_fj'],
        'ES' => ['coches_net_es', 'wallapop_es', 'autoscout24_es'],
        'PL' => ['otomoto_pl', 'autoscout24_pl'],
        'BE' => ['autoscout24_be', '2dehands_be'],
        'PT' => ['standvirtual_pt', 'autoscout24_pt'],
        'CA' => ['autotrader_ca', 'kijiji_cars_ca'],
        'TR' => ['sahibinden_tr'],
        'SE' => ['blocket_se', 'autoscout24_se'],
        'NO' => ['finn_no'],
        'DK' => ['bilbasen_dk', 'autoscout24_dk'],
        'RO' => ['autovit_ro'],
        'BG' => ['mobile_bg'],
        'CZ' => ['sauto_cz'],
        'HU' => ['hasznaltauto_hu', 'autoscout24_hu'],
        'GR' => ['car_gr'],
        'HR' => ['njuskalo_hr'],
        'SI' => ['avto_net_si'],
        'SK' => ['autobazar_sk'],
        'IE' => ['donedeal_ie', 'autoscout24_ie'],
        'FI' => ['nettiauto_fi'],
        'LT' => ['autoplius_lt'],
        'LV' => ['ss_lv'],
        'EE' => ['auto24_ee'],
        'UA' => ['auto_ria_ua'],
        'LU' => ['autoscout24_lu'],
        'RS' => ['polovniautomobili_rs'],
        'JP' => ['carsensor_jp', 'goo_net_jp'],
        'IN' => ['cardekho_in', 'carwale_in'],
        'BR' => ['webmotors_br', 'olx_cars_br'],
        'MX' => ['mercadolibre_cars_mx', 'kavak_mx'],
        'ZA' => ['autotrader_za', 'cars_co_za'],
        'MK' => ['pazar3_mk', 'reklama5_mk'],
        'AL' => ['njoftime_al'],
        'BA' => ['olx_ba'],
        'ME' => ['mojauto_me'],
        'FR' => ['leboncoin_fr', 'lacentrale_fr', 'autoscout24_fr'],
        'IT' => ['autoscout24_it', 'subito_it'],
        'AT' => ['willhaben_at', 'autoscout24_at'],
        'GB' => ['autotrader_uk', 'cargurus_uk', 'gumtree_cars_uk'],
        'US' => ['cars_com_us', 'autotrader_us', 'cargurus_us', 'carfax_us'],
    ];

    /**
     * @return array<int, string>
     */
    public static function keysFor(string $countryCode): array
    {
        $countryCode = strtoupper($countryCode);

        $candidates = match ($countryCode) {
            'DE' => GermanCarMarketplaces::keys(),
            'CH' => SwissCarMarketplaces::keys(),
            'NL' => DutchCarMarketplaces::keys(),
            'XK' => KosovoCarMarketplaces::keys(),
            default => self::ORDERED[$countryCode] ?? [],
        };

        $keys = array_values(array_filter(
            $candidates,
            fn (string $key) => LivePlatformRegistry::isLivePlatform($key),
        ));

        if ($keys === []) {
            $keys = self::discoveredLocalKeys($countryCode);
        }

        return $keys;
    }

    /**
     * @return array<int, string>
     */
    private static function discoveredLocalKeys(string $countryCode): array
    {
        $keys = [];

        foreach (LivePlatformRegistry::all() as $key => $meta) {
            if (strtoupper((string) ($meta['country'] ?? '')) !== $countryCode) {
                continue;
            }
            if (LivePlatformRegistry::isGlobalOnlyPlatform($key)) {
                continue;
            }
            $cats = (array) ($meta['categories'] ?? []);
            if (! in_array('automotive', $cats, true)) {
                continue;
            }
            $keys[] = $key;
        }

        usort($keys, function (string $a, string $b) {
            $pa = (int) (LivePlatformRegistry::platform($a)['priority'] ?? 50);
            $pb = (int) (LivePlatformRegistry::platform($b)['priority'] ?? 50);

            return $pa <=> $pb;
        });

        return array_values(array_unique($keys));
    }

    public static function label(string $key): string
    {
        $fromConfig = (string) (LivePlatformRegistry::platform($key)['label'] ?? '');
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        return GermanCarMarketplaces::label($key)
            ?: SwissCarMarketplaces::label($key)
            ?: DutchCarMarketplaces::label($key)
            ?: KosovoCarMarketplaces::label($key)
            ?: $key;
    }
}
