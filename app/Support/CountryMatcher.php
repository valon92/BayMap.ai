<?php

namespace App\Support;

/**
 * Matches product locations against buyer country filters (handles Albanian/English variants).
 */
class CountryMatcher
{
    public static function locationMatchesFilter(
        string $location,
        string $countryFilter,
        ?string $productCountryCode = null,
    ): bool {
        $needle = mb_strtolower(trim($countryFilter));
        if ($needle === '' || str_contains($needle, 'world') || str_contains($needle, 'universal') || str_contains($needle, 'bot')) {
            return true;
        }

        if (str_contains($needle, ',')) {
            foreach (array_map('trim', explode(',', $needle)) as $part) {
                if ($part !== '' && self::locationMatchesFilter($location, $part, $productCountryCode)) {
                    return true;
                }
            }

            return false;
        }

        if ($productCountryCode !== null && $productCountryCode !== '') {
            $code = strtoupper($productCountryCode);
            if ($needle === strtolower($code) || self::needleMatchesCountryCode($needle, $code)) {
                return true;
            }
        }

        $loc = mb_strtolower($location);
        if (str_contains($loc, $needle)) {
            return true;
        }

        if (self::isKosovoNeedle($needle)) {
            if ($productCountryCode !== null && strtoupper($productCountryCode) === 'XK') {
                return true;
            }

            return (bool) preg_match(
                '/kosov[oeë]?|kosova|prishtin|pristin|ferizaj|pej[ëe]?|gjakov|mitrovic|gjilan|prizren|\bxk\b/u',
                $loc
            );
        }

        if (str_contains($needle, 'switzerland') || str_contains($needle, 'schweiz')
            || str_contains($needle, 'svizzera') || str_contains($needle, 'zvic') || $needle === 'ch') {
            if ($productCountryCode !== null && strtoupper($productCountryCode) === 'CH') {
                return true;
            }

            return (bool) preg_match('/switzerland|schweiz|suisse|svizzera|zürich|zurich|bern|geneva|basel|lausanne/u', $loc);
        }

        if (str_contains($needle, 'netherlands') || str_contains($needle, 'holland') || $needle === 'nl') {
            return (bool) preg_match('/netherlands|holland|nederland|amsterdam|rotterdam|utrecht|den haag|eindhoven|groningen/u', $loc);
        }

        if (str_contains($needle, 'germany') || str_contains($needle, 'deutschland') || str_contains($needle, 'gjermani') || $needle === 'de') {
            if ($productCountryCode !== null && strtoupper($productCountryCode) !== 'DE') {
                return false;
            }

            if ($productCountryCode !== null && strtoupper($productCountryCode) === 'DE') {
                return true;
            }

            return (bool) preg_match('/germany|deutschland|amazon de|ebay de|munich|münchen|berlin|frankfurt|hamburg|stuttgart|cologne|köln|düsseldorf|dusseldorf|hannover|leipzig|dresden/u', $loc);
        }

        if (str_contains($needle, 'united states') || $needle === 'us' || str_contains($needle, 'usa')) {
            return (bool) preg_match('/united states|usa|miami|new york|los angeles|california|texas|florida/u', $loc);
        }

        if (str_contains($needle, 'uae') || str_contains($needle, 'emirates') || str_contains($needle, 'dubai')) {
            return (bool) preg_match('/uae|dubai|abu dhabi|emirates/u', $loc);
        }

        if (str_contains($needle, 'united kingdom') || $needle === 'uk' || str_contains($needle, 'england')) {
            return (bool) preg_match('/united kingdom|england|london|manchester|uk/u', $loc);
        }

        if (str_contains($needle, 'albania') || str_contains($needle, 'shqip')) {
            return (bool) preg_match('/albania|shqip|tirana|tiranë|durrës|durres/u', $loc);
        }

        if (str_contains($needle, 'italy') || str_contains($needle, 'itali') || str_contains($needle, 'italia') || $needle === 'it') {
            if ($productCountryCode !== null && strtoupper($productCountryCode) === 'IT') {
                return true;
            }

            return (bool) preg_match('/italy|italia|milano|milan|roma|rome|torino|turin|napoli|naples|bologna|firenze|florence/u', $loc);
        }

        if (str_contains($needle, 'france') || str_contains($needle, 'franc') || str_contains($needle, 'frankreich') || $needle === 'fr') {
            if ($productCountryCode !== null && strtoupper($productCountryCode) === 'FR') {
                return true;
            }

            return (bool) preg_match('/france|frankreich|paris|lyon|marseille|toulouse|bordeaux|strasbourg|lille/u', $loc);
        }

        return false;
    }

    private static function needleMatchesCountryCode(string $needle, string $code): bool
    {
        return match ($code) {
            'DE' => str_contains($needle, 'germany') || str_contains($needle, 'deutschland') || str_contains($needle, 'gjermani'),
            'CH' => str_contains($needle, 'switzerland') || str_contains($needle, 'schweiz') || str_contains($needle, 'svizzera') || str_contains($needle, 'zvic'),
            'IT' => str_contains($needle, 'italy') || str_contains($needle, 'itali') || str_contains($needle, 'italia'),
            'FR' => str_contains($needle, 'france') || str_contains($needle, 'franc') || str_contains($needle, 'frankreich'),
            'NL' => str_contains($needle, 'netherlands') || str_contains($needle, 'holland'),
            'XK' => self::isKosovoNeedle($needle),
            default => false,
        };
    }

    public static function isKosovoNeedle(string $needle): bool
    {
        return str_contains($needle, 'kosov') || $needle === 'xk' || str_contains($needle, 'kosova');
    }
}
