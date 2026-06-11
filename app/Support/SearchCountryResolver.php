<?php

namespace App\Support;

/**
 * Detects target country from natural-language queries (Albanian, German, English, etc.).
 */
class SearchCountryResolver
{
    /** @var array<string, array{code: string, name: string}> */
    private const ALIASES = [
        'switzerland' => ['code' => 'CH', 'name' => 'Switzerland'],
        'swiss' => ['code' => 'CH', 'name' => 'Switzerland'],
        'schweiz' => ['code' => 'CH', 'name' => 'Switzerland'],
        'suisse' => ['code' => 'CH', 'name' => 'Switzerland'],
        'svizzera' => ['code' => 'CH', 'name' => 'Switzerland'],
        'zvicerr' => ['code' => 'CH', 'name' => 'Switzerland'],
        'zvicra' => ['code' => 'CH', 'name' => 'Switzerland'],
        'zvicër' => ['code' => 'CH', 'name' => 'Switzerland'],
        'zuerich' => ['code' => 'CH', 'name' => 'Switzerland'],
        'zürich' => ['code' => 'CH', 'name' => 'Switzerland'],
        'zurich' => ['code' => 'CH', 'name' => 'Switzerland'],
        'bern' => ['code' => 'CH', 'name' => 'Switzerland'],
        'geneva' => ['code' => 'CH', 'name' => 'Switzerland'],
        'genève' => ['code' => 'CH', 'name' => 'Switzerland'],
        'kosovo' => ['code' => 'XK', 'name' => 'Kosovo'],
        'kosovë' => ['code' => 'XK', 'name' => 'Kosovo'],
        'kosove' => ['code' => 'XK', 'name' => 'Kosovo'],
        'kosova' => ['code' => 'XK', 'name' => 'Kosovo'],
        'shqiperi' => ['code' => 'AL', 'name' => 'Albania'],
        'shqipëri' => ['code' => 'AL', 'name' => 'Albania'],
        'albania' => ['code' => 'AL', 'name' => 'Albania'],
        'germany' => ['code' => 'DE', 'name' => 'Germany'],
        'german' => ['code' => 'DE', 'name' => 'Germany'],
        'gjermani' => ['code' => 'DE', 'name' => 'Germany'],
        'deutschland' => ['code' => 'DE', 'name' => 'Germany'],
        'italy' => ['code' => 'IT', 'name' => 'Italy'],
        'itali' => ['code' => 'IT', 'name' => 'Italy'],
        'italia' => ['code' => 'IT', 'name' => 'Italy'],
        'france' => ['code' => 'FR', 'name' => 'France'],
        'austria' => ['code' => 'AT', 'name' => 'Austria'],
        'netherlands' => ['code' => 'NL', 'name' => 'Netherlands'],
        'holland' => ['code' => 'NL', 'name' => 'Netherlands'],
        'holandes' => ['code' => 'NL', 'name' => 'Netherlands'],
        'holandës' => ['code' => 'NL', 'name' => 'Netherlands'],
        'holandë' => ['code' => 'NL', 'name' => 'Netherlands'],
        'holande' => ['code' => 'NL', 'name' => 'Netherlands'],
        'holand' => ['code' => 'NL', 'name' => 'Netherlands'],
        'holan' => ['code' => 'NL', 'name' => 'Netherlands'],
        'hollanda' => ['code' => 'NL', 'name' => 'Netherlands'],
        'nederland' => ['code' => 'NL', 'name' => 'Netherlands'],
        'nederlands' => ['code' => 'NL', 'name' => 'Netherlands'],
        'dutch' => ['code' => 'NL', 'name' => 'Netherlands'],
        'united states' => ['code' => 'US', 'name' => 'United States'],
        'usa' => ['code' => 'US', 'name' => 'United States'],
        'america' => ['code' => 'US', 'name' => 'United States'],
        'amerik' => ['code' => 'US', 'name' => 'United States'],
        'amerike' => ['code' => 'US', 'name' => 'United States'],
        'amerikë' => ['code' => 'US', 'name' => 'United States'],
        'shtetet e bashkuara' => ['code' => 'US', 'name' => 'United States'],
        'new york' => ['code' => 'US', 'name' => 'United States'],
        'newyork' => ['code' => 'US', 'name' => 'United States'],
        'nyc' => ['code' => 'US', 'name' => 'United States'],
        'united kingdom' => ['code' => 'GB', 'name' => 'United Kingdom'],
        'england' => ['code' => 'GB', 'name' => 'United Kingdom'],
        'london' => ['code' => 'GB', 'name' => 'United Kingdom'],
        'londer' => ['code' => 'GB', 'name' => 'United Kingdom'],
        'londër' => ['code' => 'GB', 'name' => 'United Kingdom'],
        'londra' => ['code' => 'GB', 'name' => 'United Kingdom'],
        'uk' => ['code' => 'GB', 'name' => 'United Kingdom'],
        'britani' => ['code' => 'GB', 'name' => 'United Kingdom'],
        'britaninë' => ['code' => 'GB', 'name' => 'United Kingdom'],
        'britanine' => ['code' => 'GB', 'name' => 'United Kingdom'],
        'angleze' => ['code' => 'GB', 'name' => 'United Kingdom'],
        'angli' => ['code' => 'GB', 'name' => 'United Kingdom'],
    ];

    /**
     * @return array{search_country: string, search_country_code: string}|array{}
     */
    public static function fromQuery(string $query): array
    {
        $all = self::allFromQuery($query);

        return $all[0] ?? [];
    }

    /**
     * All countries mentioned in a query (e.g. Germany + Netherlands + Switzerland).
     *
     * @return array<int, array{search_country: string, search_country_code: string}>
     */
    public static function allFromQuery(string $query): array
    {
        $lower = mb_strtolower($query);
        $found = [];
        $seen = [];

        foreach (self::ALIASES as $needle => $meta) {
            if (! str_contains($lower, $needle)) {
                continue;
            }
            if (isset($seen[$meta['code']])) {
                continue;
            }
            $seen[$meta['code']] = true;
            $found[] = [
                'search_country' => $meta['name'],
                'search_country_code' => $meta['code'],
            ];
        }

        if ($found === [] && preg_match('/\b(?:in|në|ne|en|à|a)\s+([a-zëçáéíóúäöü\s,]{3,80})\b/ui', $lower, $m)) {
            $phrase = trim($m[1]);
            foreach (self::ALIASES as $needle => $meta) {
                if ((str_contains($phrase, $needle) || str_contains($needle, $phrase)) && ! isset($seen[$meta['code']])) {
                    $seen[$meta['code']] = true;
                    $found[] = [
                        'search_country' => $meta['name'],
                        'search_country_code' => $meta['code'],
                    ];
                }
            }
        }

        return $found;
    }
}
