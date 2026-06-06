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

        if (str_contains($needle, 'switzerland') || $needle === 'ch') {
            return (bool) preg_match('/switzerland|schweiz|zürich|zurich|bern|geneva|basel|lausanne/u', $loc);
        }

        if (str_contains($needle, 'netherlands') || str_contains($needle, 'holland') || $needle === 'nl') {
            return (bool) preg_match('/netherlands|holland|nederland|amsterdam|rotterdam|utrecht|den haag|eindhoven|groningen/u', $loc);
        }

        if (str_contains($needle, 'germany') || str_contains($needle, 'deutschland') || $needle === 'de') {
            return (bool) preg_match('/germany|deutschland|munich|münchen|berlin|frankfurt|hamburg|stuttgart|cologne|köln|düsseldorf|dusseldorf|hannover|leipzig|dresden/u', $loc);
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

        return false;
    }

    public static function isKosovoNeedle(string $needle): bool
    {
        return str_contains($needle, 'kosov') || $needle === 'xk' || str_contains($needle, 'kosova');
    }
}
