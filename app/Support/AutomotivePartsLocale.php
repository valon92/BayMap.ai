<?php

namespace App\Support;

/**
 * Country → localized auto-parts search terms (SerpAPI + live scrapers).
 */
class AutomotivePartsLocale
{
    /** @var array<string, string> ISO 3166-1 alpha-2 → search bucket */
    private const BUCKET = [
        'DE' => 'DE', 'AT' => 'DE', 'CH' => 'DE', 'LI' => 'DE',
        'FR' => 'FR', 'BE' => 'FR', 'LU' => 'FR', 'MC' => 'FR',
        'IT' => 'IT', 'SM' => 'IT', 'VA' => 'IT',
        'ES' => 'ES', 'MX' => 'ES', 'AR' => 'ES', 'CO' => 'ES', 'CL' => 'ES', 'PE' => 'ES',
        'PT' => 'PT', 'BR' => 'PT',
        'NL' => 'NL',
        'PL' => 'PL',
        'CZ' => 'CS', 'SK' => 'CS',
        'RO' => 'RO', 'MD' => 'RO',
        'HU' => 'HU',
        'GR' => 'GR', 'CY' => 'GR',
        'TR' => 'TR',
        'RU' => 'RU', 'UA' => 'RU', 'BY' => 'RU',
        'JP' => 'JP',
        'KR' => 'KR',
        'SE' => 'SV', 'NO' => 'NO', 'DK' => 'DA', 'FI' => 'FI',
        'XK' => 'DE', 'AL' => 'DE', 'MK' => 'DE', 'RS' => 'DE', 'BA' => 'DE',
        'ME' => 'DE', 'HR' => 'DE', 'SI' => 'DE', 'BG' => 'DE',
        'US' => 'EN', 'GB' => 'EN', 'UK' => 'EN', 'AU' => 'EN', 'NZ' => 'EN',
        'IE' => 'EN', 'CA' => 'EN', 'IN' => 'EN', 'ZA' => 'EN', 'SG' => 'EN',
        'FJ' => 'EN', 'PH' => 'EN', 'MY' => 'EN', 'AE' => 'EN', 'SA' => 'EN',
    ];

    /**
     * @param  array<string, string>  $search
     */
    public static function resolve(string $countryCode, array $search): string
    {
        $countryCode = strtoupper($countryCode);
        if ($countryCode !== '' && isset($search[$countryCode]) && $search[$countryCode] !== '') {
            return (string) $search[$countryCode];
        }

        $bucket = self::bucket($countryCode);
        if (isset($search[$bucket]) && $search[$bucket] !== '') {
            return (string) $search[$bucket];
        }

        return (string) ($search['default'] ?? $search['EN'] ?? '');
    }

    public static function bucket(string $countryCode): string
    {
        $countryCode = strtoupper($countryCode);

        return self::BUCKET[$countryCode] ?? 'EN';
    }

    public static function genericPartsTerm(string $countryCode): string
    {
        return match (self::bucket($countryCode)) {
            'DE' => 'autoteile',
            'FR' => 'pièces auto',
            'IT' => 'ricambi auto',
            'ES', 'PT' => 'recambios coche',
            'NL' => 'auto onderdelen',
            'PL' => 'części samochodowe',
            'CS' => 'náhradní díly',
            'RO' => 'piese auto',
            'HU' => 'autóalkatrész',
            'GR' => 'ανταλλακτικά αυτοκινήτου',
            'TR' => 'oto yedek parça',
            'RU' => 'автозапчасти',
            'JP' => '自動車部品',
            'EN' => 'car parts',
            default => 'car parts',
        };
    }

    /**
     * Expand DE/EN component labels to all major markets.
     *
     * @param  array<string, string>  $overrides  bucket or ISO keys, e.g. FR => moteur
     * @return array<string, string>
     */
    public static function searchMap(string $de, string $en, array $overrides = []): array
    {
        $fr = $overrides['FR'] ?? $overrides['fr'] ?? $en;
        $it = $overrides['IT'] ?? $overrides['it'] ?? $en;
        $es = $overrides['ES'] ?? $overrides['es'] ?? $en;
        $pt = $overrides['PT'] ?? $overrides['pt'] ?? $es;
        $nl = $overrides['NL'] ?? $overrides['nl'] ?? $en;
        $pl = $overrides['PL'] ?? $overrides['pl'] ?? $en;
        $cs = $overrides['CS'] ?? $overrides['cs'] ?? $en;
        $ro = $overrides['RO'] ?? $overrides['ro'] ?? $en;
        $hu = $overrides['HU'] ?? $overrides['hu'] ?? $en;
        $gr = $overrides['GR'] ?? $overrides['gr'] ?? $en;
        $tr = $overrides['TR'] ?? $overrides['tr'] ?? $en;
        $ru = $overrides['RU'] ?? $overrides['ru'] ?? $en;
        $jp = $overrides['JP'] ?? $overrides['jp'] ?? $en;

        $map = [
            'DE' => $de, 'AT' => $de, 'CH' => $de, 'LI' => $de,
            'FR' => $fr, 'BE' => $fr, 'LU' => $fr, 'MC' => $fr,
            'IT' => $it, 'SM' => $it, 'VA' => $it,
            'ES' => $es, 'MX' => $es, 'AR' => $es, 'CO' => $es, 'CL' => $es, 'PE' => $es,
            'PT' => $pt, 'BR' => $pt,
            'NL' => $nl,
            'PL' => $pl,
            'CZ' => $cs, 'SK' => $cs,
            'RO' => $ro, 'MD' => $ro,
            'HU' => $hu,
            'GR' => $gr, 'CY' => $gr,
            'TR' => $tr,
            'RU' => $ru, 'UA' => $ru, 'BY' => $ru,
            'JP' => $jp,
            'KR' => $overrides['KR'] ?? $overrides['kr'] ?? $en,
            'SE' => $overrides['SV'] ?? $overrides['sv'] ?? $en,
            'NO' => $overrides['NO'] ?? $overrides['no'] ?? $en,
            'DK' => $overrides['DA'] ?? $overrides['da'] ?? $en,
            'FI' => $overrides['FI'] ?? $overrides['fi'] ?? $en,
            'XK' => $de, 'AL' => $de, 'MK' => $de, 'RS' => $de, 'BA' => $de,
            'ME' => $de, 'HR' => $de, 'SI' => $de, 'BG' => $de,
            'US' => $en, 'GB' => $en, 'UK' => $en, 'AU' => $en, 'NZ' => $en,
            'IE' => $en, 'CA' => $en, 'IN' => $en, 'ZA' => $en, 'SG' => $en,
            'FJ' => $en, 'PH' => $en, 'MY' => $en, 'AE' => $en, 'SA' => $en,
            'EN' => $en,
            'default' => $en,
        ];

        foreach ($overrides as $key => $value) {
            if ($value !== '') {
                $map[strtoupper((string) $key)] = (string) $value;
            }
        }

        return $map;
    }
}
