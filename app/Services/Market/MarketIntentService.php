<?php

namespace App\Services\Market;

/**
 * Applies explicit marketplace region selection from the UI to parsed search intent.
 */
class MarketIntentService
{
    public function __construct(private MarketCatalogService $catalog) {}

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public function apply(array $parsed, ?string $mode, ?string $code, ?string $locale = 'en'): array
    {
        $mode = strtolower(trim((string) $mode));
        if ($mode === '' || $mode === 'auto') {
            return $parsed;
        }

        $code = strtoupper(trim((string) $code));

        if ($mode === 'global') {
            $parsed['search_scope'] = 'universal';
            $parsed['search_country_code'] = 'WW';
            $parsed['search_country'] = 'Worldwide';
            $parsed['search_target'] = true;
            $parsed['location_source'] = 'market';
            unset($parsed['search_countries'], $parsed['search_continent_code']);

            return $parsed;
        }

        if (in_array($mode, ['country', 'countries'], true) && $code !== '') {
            $codes = $this->parseCountryCodes($code);
            if ($codes === []) {
                return $parsed;
            }

            $max = (int) config('search.max_selected_countries', 8);
            $codes = array_slice($codes, 0, max(1, $max));

            if (count($codes) === 1) {
                $single = $codes[0];
                $parsed['search_scope'] = 'targeted';
                $parsed['search_country_code'] = $single;
                $parsed['search_country'] = $this->catalog->countryName($single, $locale);
                $parsed['country'] = $parsed['search_country'];
                $parsed['search_target'] = true;
                $parsed['location_source'] = 'market';
                unset($parsed['search_countries'], $parsed['search_continent_code']);

                return $parsed;
            }

            $countries = [];
            foreach ($codes as $iso2) {
                $countries[] = [
                    'search_country_code' => $iso2,
                    'search_country' => $this->catalog->countryName($iso2, $locale),
                ];
            }

            $parsed['search_scope'] = 'targeted';
            $parsed['search_countries'] = $countries;
            $parsed['search_country_code'] = $countries[0]['search_country_code'];
            $parsed['search_country'] = implode(', ', array_column($countries, 'search_country'));
            $parsed['country'] = $parsed['search_country'];
            $parsed['search_target'] = true;
            $parsed['location_source'] = 'market';
            unset($parsed['search_continent_code']);

            return $parsed;
        }

        if ($mode === 'continent' && $code !== '') {
            $countries = $this->catalog->searchableCountriesInContinent($code, $locale);
            if ($countries === []) {
                return $parsed;
            }

            $parsed['search_scope'] = 'targeted';
            $parsed['search_continent_code'] = $code;
            $parsed['search_countries'] = $countries;
            $parsed['search_country_code'] = $countries[0]['search_country_code'];
            $parsed['search_country'] = implode(', ', array_column($countries, 'search_country'));
            $parsed['country'] = $parsed['search_country'];
            $parsed['search_target'] = true;
            $parsed['location_source'] = 'market';

            return $parsed;
        }

        return $parsed;
    }

    /**
     * @return array<int, string>
     */
    private function parseCountryCodes(string $code): array
    {
        $parts = preg_split('/[\s,;]+/', $code) ?: [];
        $codes = [];

        foreach ($parts as $part) {
            $part = strtoupper(trim($part));
            if ($part === '' || ! preg_match('/^[A-Z]{2}$/', $part)) {
                continue;
            }
            $codes[$part] = $part;
        }

        return array_values($codes);
    }
}
