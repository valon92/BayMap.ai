<?php

namespace App\Services\Search;

use App\Support\PriceIntentParser;
use App\Support\SearchCountryResolver;

/**
 * Merges rule-based location/price/model intent into AI-parsed attributes.
 * Query text wins over visitor geo when the buyer names a country, currency, or model.
 */
class QueryIntentEnricher
{
    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public function enrich(array $parsed, string $rawQuery): array
    {
        $countryFromQuery = SearchCountryResolver::fromQuery($rawQuery);
        if (! empty($countryFromQuery['search_country_code'])) {
            $parsed = array_merge($parsed, $countryFromQuery);
            $parsed['country'] = $countryFromQuery['search_country'];
        }

        $priceFromQuery = PriceIntentParser::fromQuery($rawQuery);
        if (! empty($priceFromQuery['max_price'])) {
            $parsed['max_price'] = $priceFromQuery['max_price'];
        }
        if (! empty($priceFromQuery['currency'])) {
            $parsed['currency'] = $priceFromQuery['currency'];
        }

        if (preg_match('/\b(q\d|x\d|a\d)\b/i', $rawQuery, $m)) {
            $parsed['model'] = strtoupper($m[1]);
        }

        if (($parsed['category'] ?? '') === 'car' && empty($parsed['currency'])) {
            $parsed['currency'] = 'EUR';
        }

        return array_filter($parsed, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $visitorGeo
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public function searchGeo(array $visitorGeo, array $parsed): array
    {
        if (empty($parsed['search_country_code'])) {
            return $visitorGeo;
        }

        return array_merge($visitorGeo, [
            'country' => $parsed['search_country'] ?? $visitorGeo['country'],
            'country_code' => $parsed['search_country_code'],
            'city' => null,
            'search_target' => true,
        ]);
    }
}
