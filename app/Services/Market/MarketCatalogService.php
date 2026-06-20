<?php

namespace App\Services\Market;

use App\Services\Catalog\PlatformCatalogRepository;
use App\Support\LivePlatformRegistry;

/**
 * Continents and countries for the marketplace region picker.
 */
class MarketCatalogService
{
    /** @var array<string, array<string, string>> */
    private const CONTINENT_LABELS_SQ = [
        'AF' => 'Afrika',
        'AN' => 'Antarktida',
        'AS' => 'Azia',
        'EU' => 'Evropa',
        'NA' => 'Amerika e Veriut',
        'OC' => 'Oqeania',
        'SA' => 'Amerika e Jugut',
    ];

    /** @var array<string, string> */
    private const COUNTRY_LABELS_EN = [
        'XK' => 'Kosovo',
        'NL' => 'Netherlands',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'DE' => 'Germany',
        'CH' => 'Switzerland',
        'AL' => 'Albania',
        'IT' => 'Italy',
        'FR' => 'France',
        'AT' => 'Austria',
        'BE' => 'Belgium',
        'MK' => 'North Macedonia',
        'RS' => 'Serbia',
        'ME' => 'Montenegro',
        'GR' => 'Greece',
        'TR' => 'Turkey',
        'PL' => 'Poland',
        'ES' => 'Spain',
        'PT' => 'Portugal',
        'SE' => 'Sweden',
        'NO' => 'Norway',
        'DK' => 'Denmark',
        'FI' => 'Finland',
        'IE' => 'Ireland',
        'IN' => 'India',
        'AE' => 'UAE',
        'CA' => 'Canada',
        'AU' => 'Australia',
        'BY' => 'Belarus',
    ];

    /** @var array<string, string> */
    private const COUNTRY_LABELS_SQ = [
        'XK' => 'Kosovë',
        'AL' => 'Shqipëri',
        'DE' => 'Gjermani',
        'CH' => 'Zvicër',
        'AT' => 'Austri',
        'IT' => 'Itali',
        'FR' => 'Francë',
        'NL' => 'Holandë',
        'GB' => 'Britani e Madhe',
        'US' => 'Shtetet e Bashkuara',
        'MK' => 'Maqedonia e Veriut',
        'RS' => 'Serbi',
        'ME' => 'Mali i Zi',
        'GR' => 'Greqi',
        'TR' => 'Turqi',
        'BE' => 'Belgjikë',
        'PL' => 'Poloni',
        'ES' => 'Spanjë',
        'PT' => 'Portugali',
        'SE' => 'Suedi',
        'NO' => 'Norvegji',
        'DK' => 'Danimarkë',
        'FI' => 'Finlandë',
        'IE' => 'Irlandë',
        'IN' => 'Indi',
        'AE' => 'Emiratet e Bashkuara Arabe',
        'CA' => 'Kanada',
        'AU' => 'Australi',
    ];

    public function __construct(private PlatformCatalogRepository $catalog) {}

    /**
     * @return array<int, array{code: string, name: string, countries: array<int, array{code: string, name: string}>}>
     */
    public function continentsWithCountries(?string $locale = 'en'): array
    {
        $countries = $this->catalog->countries();
        if ($countries === []) {
            $countries = $this->fallbackCountries();
        }

        $continents = config('catalog.continents', []);
        usort($continents, fn (array $a, array $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        /** @var array<string, array{code: string, name: string, countries: array<int, array{code: string, name: string}>}> $grouped */
        $grouped = [];
        foreach ($continents as $continent) {
            $code = (string) $continent['code'];
            $grouped[$code] = [
                'code' => $code,
                'name' => $this->continentLabel($code, (string) $continent['name'], $locale),
                'countries' => [],
            ];
        }

        foreach ($countries as $country) {
            $continentCode = (string) ($country['continent_code'] ?? '');
            if (! isset($grouped[$continentCode])) {
                continue;
            }

            $iso2 = strtoupper((string) ($country['iso2'] ?? ''));
            if ($iso2 === '') {
                continue;
            }

            $grouped[$continentCode]['countries'][] = [
                'code' => $iso2,
                'name' => $this->countryLabel($iso2, (string) ($country['name'] ?? $iso2), $locale),
            ];
        }

        foreach ($grouped as &$continent) {
            usort(
                $continent['countries'],
                fn (array $a, array $b) => strcasecmp($a['name'], $b['name'])
            );
        }
        unset($continent);

        return array_values(array_filter(
            $grouped,
            fn (array $continent) => $continent['countries'] !== []
        ));
    }

    public function countryName(string $code, ?string $locale = 'en'): string
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return '';
        }

        foreach ($this->catalog->countries() as $country) {
            if (strtoupper((string) ($country['iso2'] ?? '')) === $code) {
                return $this->countryLabel($code, (string) $country['name'], $locale);
            }
        }

        foreach ($this->fallbackCountries() as $country) {
            if (strtoupper((string) ($country['iso2'] ?? '')) === $code) {
                return $this->countryLabel($code, (string) $country['name'], $locale);
            }
        }

        return $this->countryLabel($code, $code, $locale);
    }

    public function continentName(string $code, ?string $locale = 'en'): string
    {
        $code = strtoupper(trim($code));
        foreach (config('catalog.continents', []) as $continent) {
            if ((string) ($continent['code'] ?? '') === $code) {
                return $this->continentLabel($code, (string) ($continent['name'] ?? $code), $locale);
            }
        }

        return $this->continentLabel($code, $code, $locale);
    }

    /**
     * Countries in a continent that have at least one live platform (capped for worker budget).
     *
     * @return array<int, array{search_country: string, search_country_code: string}>
     */
    public function searchableCountriesInContinent(string $continentCode, ?string $locale = 'en', int $limit = 12): array
    {
        $continentCode = strtoupper(trim($continentCode));
        $platformCounts = $this->platformCountsByCountry();
        $entries = [];

        foreach ($this->continentsWithCountries($locale) as $continent) {
            if ($continent['code'] !== $continentCode) {
                continue;
            }

            foreach ($continent['countries'] as $country) {
                $code = strtoupper((string) ($country['code'] ?? ''));
                if ($code === '' || empty($platformCounts[$code])) {
                    continue;
                }

                $entries[] = [
                    'search_country_code' => $code,
                    'search_country' => $country['name'],
                    '_platform_count' => $platformCounts[$code],
                ];
            }
            break;
        }

        usort($entries, fn (array $a, array $b) => ($b['_platform_count'] ?? 0) <=> ($a['_platform_count'] ?? 0));

        return array_map(
            fn (array $entry) => [
                'search_country_code' => $entry['search_country_code'],
                'search_country' => $entry['search_country'],
            ],
            array_slice($entries, 0, max(1, $limit))
        );
    }

    /**
     * @return array<int, array{iso2: string, name: string, continent_code: string}>
     */
    private function fallbackCountries(): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = require database_path('data/world_countries.php');

        $list = array_map(fn (array $row) => [
            'iso2' => (string) ($row['iso2'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'continent_code' => (string) ($row['continent'] ?? ''),
        ], $rows);

        $list[] = [
            'iso2' => 'XK',
            'name' => 'Kosovo',
            'continent_code' => 'EU',
        ];

        return $list;
    }

    private function continentLabel(string $code, string $fallback, ?string $locale): string
    {
        if ($locale === 'sq') {
            return self::CONTINENT_LABELS_SQ[$code] ?? $fallback;
        }

        return $fallback;
    }

    private function countryLabel(string $code, string $fallback, ?string $locale): string
    {
        $code = strtoupper($code);

        if ($locale === 'sq') {
            return self::COUNTRY_LABELS_SQ[$code] ?? $fallback;
        }

        return self::COUNTRY_LABELS_EN[$code] ?? $fallback;
    }

    /**
     * @return array<string, int>
     */
    private function platformCountsByCountry(): array
    {
        $counts = [];

        foreach (LivePlatformRegistry::all() as $meta) {
            $code = strtoupper((string) ($meta['country'] ?? ''));
            if ($code === '') {
                continue;
            }
            $counts[$code] = ($counts[$code] ?? 0) + 1;
        }

        return $counts;
    }
}
