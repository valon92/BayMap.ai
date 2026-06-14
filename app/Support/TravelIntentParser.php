<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Rule-based travel / flight intent (Albanian + English route phrases).
 */
class TravelIntentParser
{
    /** @var array<string, array{city: string, country_code: string, airport: string}> */
    private const CITY_AIRPORTS = [
        'geneva' => ['city' => 'Geneva', 'country_code' => 'CH', 'airport' => 'GVA'],
        'genève' => ['city' => 'Geneva', 'country_code' => 'CH', 'airport' => 'GVA'],
        'gjenev' => ['city' => 'Geneva', 'country_code' => 'CH', 'airport' => 'GVA'],
        'gjeneva' => ['city' => 'Geneva', 'country_code' => 'CH', 'airport' => 'GVA'],
        'zhenev' => ['city' => 'Geneva', 'country_code' => 'CH', 'airport' => 'GVA'],
        'zhenèv' => ['city' => 'Geneva', 'country_code' => 'CH', 'airport' => 'GVA'],
        'zenev' => ['city' => 'Geneva', 'country_code' => 'CH', 'airport' => 'GVA'],
        'gjenever' => ['city' => 'Geneva', 'country_code' => 'CH', 'airport' => 'GVA'],
        'gjeneev' => ['city' => 'Geneva', 'country_code' => 'CH', 'airport' => 'GVA'],
        'geneve' => ['city' => 'Geneva', 'country_code' => 'CH', 'airport' => 'GVA'],
        'zurich' => ['city' => 'Zurich', 'country_code' => 'CH', 'airport' => 'ZRH'],
        'zürich' => ['city' => 'Zurich', 'country_code' => 'CH', 'airport' => 'ZRH'],
        'bern' => ['city' => 'Bern', 'country_code' => 'CH', 'airport' => 'BRN'],
        'london' => ['city' => 'London', 'country_code' => 'GB', 'airport' => 'LHR'],
        'londer' => ['city' => 'London', 'country_code' => 'GB', 'airport' => 'LHR'],
        'londër' => ['city' => 'London', 'country_code' => 'GB', 'airport' => 'LHR'],
        'londra' => ['city' => 'London', 'country_code' => 'GB', 'airport' => 'LHR'],
        'prishtine' => ['city' => 'Pristina', 'country_code' => 'XK', 'airport' => 'PRN'],
        'prishtina' => ['city' => 'Pristina', 'country_code' => 'XK', 'airport' => 'PRN'],
        'prishtinë' => ['city' => 'Pristina', 'country_code' => 'XK', 'airport' => 'PRN'],
        'frankfurt' => ['city' => 'Frankfurt', 'country_code' => 'DE', 'airport' => 'FRA'],
        'berlin' => ['city' => 'Berlin', 'country_code' => 'DE', 'airport' => 'BER'],
        'munich' => ['city' => 'Munich', 'country_code' => 'DE', 'airport' => 'MUC'],
        'münchen' => ['city' => 'Munich', 'country_code' => 'DE', 'airport' => 'MUC'],
        'hamburg' => ['city' => 'Hamburg', 'country_code' => 'DE', 'airport' => 'HAM'],
        'dusseldorf' => ['city' => 'Düsseldorf', 'country_code' => 'DE', 'airport' => 'DUS'],
        'düsseldorf' => ['city' => 'Düsseldorf', 'country_code' => 'DE', 'airport' => 'DUS'],
        'cologne' => ['city' => 'Cologne', 'country_code' => 'DE', 'airport' => 'CGN'],
        'köln' => ['city' => 'Cologne', 'country_code' => 'DE', 'airport' => 'CGN'],
        'paris' => ['city' => 'Paris', 'country_code' => 'FR', 'airport' => 'CDG'],
        'parisi' => ['city' => 'Paris', 'country_code' => 'FR', 'airport' => 'CDG'],
        'parisit' => ['city' => 'Paris', 'country_code' => 'FR', 'airport' => 'CDG'],
        'zuriku' => ['city' => 'Zurich', 'country_code' => 'CH', 'airport' => 'ZRH'],
        'zurichu' => ['city' => 'Zurich', 'country_code' => 'CH', 'airport' => 'ZRH'],
        'amsterdam' => ['city' => 'Amsterdam', 'country_code' => 'NL', 'airport' => 'AMS'],
        'vienna' => ['city' => 'Vienna', 'country_code' => 'AT', 'airport' => 'VIE'],
        'wien' => ['city' => 'Vienna', 'country_code' => 'AT', 'airport' => 'VIE'],
        'rome' => ['city' => 'Rome', 'country_code' => 'IT', 'airport' => 'FCO'],
        'roma' => ['city' => 'Rome', 'country_code' => 'IT', 'airport' => 'FCO'],
        'milan' => ['city' => 'Milan', 'country_code' => 'IT', 'airport' => 'MXP'],
        'milano' => ['city' => 'Milan', 'country_code' => 'IT', 'airport' => 'MXP'],
        'madrid' => ['city' => 'Madrid', 'country_code' => 'ES', 'airport' => 'MAD'],
        'barcelona' => ['city' => 'Barcelona', 'country_code' => 'ES', 'airport' => 'BCN'],
        'istanbul' => ['city' => 'Istanbul', 'country_code' => 'TR', 'airport' => 'IST'],
        'dubai' => ['city' => 'Dubai', 'country_code' => 'AE', 'airport' => 'DXB'],
        'new york' => ['city' => 'New York', 'country_code' => 'US', 'airport' => 'JFK'],
        'nyc' => ['city' => 'New York', 'country_code' => 'US', 'airport' => 'JFK'],
        'los angeles' => ['city' => 'Los Angeles', 'country_code' => 'US', 'airport' => 'LAX'],
        'chicago' => ['city' => 'Chicago', 'country_code' => 'US', 'airport' => 'ORD'],
        'miami' => ['city' => 'Miami', 'country_code' => 'US', 'airport' => 'MIA'],
        'washington' => ['city' => 'Washington', 'country_code' => 'US', 'airport' => 'IAD'],
        'boston' => ['city' => 'Boston', 'country_code' => 'US', 'airport' => 'BOS'],
        'toronto' => ['city' => 'Toronto', 'country_code' => 'CA', 'airport' => 'YYZ'],
        'tirana' => ['city' => 'Tirana', 'country_code' => 'AL', 'airport' => 'TIA'],
        'tiranë' => ['city' => 'Tirana', 'country_code' => 'AL', 'airport' => 'TIA'],
    ];

    /** Default hub airports when the user names a country, not a city. */
    private const COUNTRY_HUBS = [
        'DE' => ['city' => 'Frankfurt', 'country_code' => 'DE', 'airport' => 'FRA'],
        'US' => ['city' => 'New York', 'country_code' => 'US', 'airport' => 'JFK'],
        'GB' => ['city' => 'London', 'country_code' => 'GB', 'airport' => 'LHR'],
        'CH' => ['city' => 'Zurich', 'country_code' => 'CH', 'airport' => 'ZRH'],
        'FR' => ['city' => 'Paris', 'country_code' => 'FR', 'airport' => 'CDG'],
        'IT' => ['city' => 'Rome', 'country_code' => 'IT', 'airport' => 'FCO'],
        'NL' => ['city' => 'Amsterdam', 'country_code' => 'NL', 'airport' => 'AMS'],
        'AT' => ['city' => 'Vienna', 'country_code' => 'AT', 'airport' => 'VIE'],
        'ES' => ['city' => 'Madrid', 'country_code' => 'ES', 'airport' => 'MAD'],
        'XK' => ['city' => 'Pristina', 'country_code' => 'XK', 'airport' => 'PRN'],
        'AL' => ['city' => 'Tirana', 'country_code' => 'AL', 'airport' => 'TIA'],
        'TR' => ['city' => 'Istanbul', 'country_code' => 'TR', 'airport' => 'IST'],
        'AE' => ['city' => 'Dubai', 'country_code' => 'AE', 'airport' => 'DXB'],
        'CA' => ['city' => 'Toronto', 'country_code' => 'CA', 'airport' => 'YYZ'],
        'IN' => ['city' => 'Delhi', 'country_code' => 'IN', 'airport' => 'DEL'],
    ];

    public static function isTravelQuery(string $query): bool
    {
        $lower = mb_strtolower(trim($query));

        return (bool) preg_match(
            '/\b(flight|flights|avion|avioni|bilet[aë]?|udh[eë]tim|udhetim|hotel|resort|vacation|pushim|tren|train|autobus|bus)\b/u',
            $lower,
        );
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public static function fromQuery(string $query, array $parsed = []): array
    {
        $lower = mb_strtolower(trim($query));
        $result = [];

        if (self::isTravelQuery($query) || CategoryCatalog::normalize($parsed['category'] ?? '') === 'travel') {
            $result['category'] = 'travel';
        } else {
            return $parsed;
        }

        if (preg_match('/\b(?:nga|from|prej|von|de)\s+([a-zëçáéíóúäöü\- ]{2,40}?)\s+(?:ne|në|to|deri|nach|à|a)\s+([a-zëçáéíóúäöü\- ]{2,40})\b/ui', $lower, $route)) {
            $origin = self::resolvePlace(trim($route[1])) ?? self::resolveCountryHub(trim($route[1]));
            $destination = self::resolvePlace(trim($route[2])) ?? self::resolveCountryHub(trim($route[2]));
            if ($origin !== null) {
                self::applyEndpoint($result, 'origin', $origin);
            }
            if ($destination !== null) {
                self::applyEndpoint($result, 'destination', $destination);
            }
        }

        if (preg_match('/\b(\d{1,2})[\.\/\-](\d{1,2})[\.\/\-](20\d{2})\b/u', $query, $dateMatch)) {
            $day = str_pad($dateMatch[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($dateMatch[2], 2, '0', STR_PAD_LEFT);
            $result['departure_date'] = "{$dateMatch[3]}-{$month}-{$day}";
        } elseif (preg_match('/\b(20\d{2})[\.\/\-](\d{1,2})[\.\/\-](\d{1,2})\b/u', $query, $dateMatch)) {
            $result['departure_date'] = sprintf('%s-%02d-%02d', $dateMatch[1], (int) $dateMatch[2], (int) $dateMatch[3]);
        }

        $result = self::applyRelativeSchedule($result, $lower);

        if (preg_match('/\b(?:ora|at)\s*(\d{1,2})(?:[:\.]?(\d{2}))?\s*(?:e\s+)?(?:mengjesit|morning|am)\b/ui', $lower, $time)) {
            $result['departure_time_from'] = sprintf('%02d:%02d', (int) $time[1], (int) ($time[2] ?? 0));
        }
        if (preg_match('/\b(?:deri|until|to)\s*(\d{1,2})(?:[:\.]?(\d{2}))?\s*(?:paradite|am|morning)?\b/ui', $lower, $time)) {
            $result['departure_time_to'] = sprintf('%02d:%02d', (int) $time[1], (int) ($time[2] ?? 0));
        }

        if (preg_match('/\b(round\s*trip|roundtrip|vajtje\s*[- ]?ardhje|rt|kthy[së]?e|kthyes|kthese|bilet[aë]?\s+(?:avioni\s+)?kthy)\b/ui', $lower)) {
            $result['travel_type'] = 'round_trip';
        } elseif (empty($parsed['travel_type']) && empty($result['travel_type'])) {
            $result['travel_type'] = 'one_way';
        }

        if (preg_match('/\b(?:kthy[së]?e|return|ardhje)\b.*?(?:afersisht|rreth|about|approx(?:imately)?)?\s*(\d+)\s*(jav[eë]?|javë|jav|week|weeks|dit[eë]?|dit|day|days)\b/ui', $lower, $relativeReturn)) {
            $result['travel_type'] = 'round_trip';
            $result['return_offset_value'] = max(1, (int) $relativeReturn[1]);
            $unit = mb_strtolower($relativeReturn[2]);
            $result['return_offset_unit'] = preg_match('/^(dit|day)/u', $unit) ? 'days' : 'weeks';
        } elseif (preg_match('/\b(?:pas|rreth|after|in)\s*(\d+)\s*(jav[eë]?|javë|jav|week|weeks|dit[eë]?|dit|day|days)\b/ui', $lower, $relativeReturn)) {
            $result['travel_type'] = 'round_trip';
            $result['return_offset_value'] = max(1, (int) $relativeReturn[1]);
            $unit = mb_strtolower($relativeReturn[2]);
            $result['return_offset_unit'] = preg_match('/^(dit|day)/u', $unit) ? 'days' : 'weeks';
        }

        if (preg_match('/\b(?:kthim|return|ardhje)\s*(?:me|on)?\s*(\d{1,2})[\.\/\-](\d{1,2})[\.\/\-](20\d{2})\b/ui', $query, $ret)) {
            $result['return_date'] = sprintf('%s-%02d-%02d', $ret[3], (int) $ret[2], (int) $ret[1]);
            $result['travel_type'] = 'round_trip';
        }

        if (preg_match('/\b(\d+)\s*(?:udh[eë]tar|traveler|passenger|pasagjer)\b/ui', $lower, $travelers)) {
            $result['travelers'] = max(1, (int) $travelers[1]);
        } elseif (empty($parsed['travelers'])) {
            $result['travelers'] = 1;
        }

        if (preg_match('/\b(tren|train|hekurudh[aë]|hekurudhe)\b/ui', $lower)) {
            $result['product_type'] = 'train';
            $result['travel_mode'] = 'train';
        } elseif (preg_match('/\b(autobus|bus|coach)\b/ui', $lower)) {
            $result['product_type'] = 'bus';
            $result['travel_mode'] = 'bus';
        } elseif (preg_match('/\b(flight|avion|avioni|bilet[aë]?\s*(?:avioni|udh[eë]timi)?)\b/ui', $lower)) {
            $result['product_type'] = 'flight';
            $result['travel_mode'] = 'flight';
        } elseif (preg_match('/\b(hotel|akomodim|room)\b/ui', $lower)) {
            $result['product_type'] = 'hotel';
            $result['travel_mode'] = 'hotel';
        }

        foreach ($result as $key => $value) {
            if ($value !== null && $value !== '') {
                $parsed[$key] = $value;
            }
        }

        return self::finalize($parsed);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public static function finalize(array $parsed): array
    {
        if (CategoryCatalog::normalize($parsed['category'] ?? '') !== 'travel') {
            return $parsed;
        }

        if (empty($parsed['travelers'])) {
            $parsed['travelers'] = 1;
        }

        $parsed = self::ensureTravelEndpoints($parsed);
        $parsed = self::applyRelativeSchedule($parsed, mb_strtolower((string) ($parsed['raw_query'] ?? '')));
        $parsed = self::normalizeTravelType($parsed);
        $parsed = self::resolveReturnDate($parsed);

        unset(
            $parsed['year'],
            $parsed['year_min'],
            $parsed['year_max'],
            $parsed['gender'],
            $parsed['model'],
            $parsed['brand'],
            $parsed['max_km'],
            $parsed['fuel'],
            $parsed['engine_liters'],
            $parsed['search_countries'],
        );

        if (! empty($parsed['origin_country_code'])) {
            $parsed['search_country_code'] = strtoupper((string) $parsed['origin_country_code']);
            $parsed['search_country'] = self::countryName($parsed['search_country_code']);
            $parsed['search_target'] = true;
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public static function ensureTravelEndpoints(array $parsed): array
    {
        if (CategoryCatalog::normalize($parsed['category'] ?? '') !== 'travel') {
            return $parsed;
        }

        if (empty($parsed['departure_airport'])) {
            $code = strtoupper((string) ($parsed['origin_country_code'] ?? ''));
            if ($code !== '' && isset(self::COUNTRY_HUBS[$code])) {
                self::applyEndpoint($parsed, 'origin', self::COUNTRY_HUBS[$code]);
            }
        }

        if (empty($parsed['arrival_airport'])) {
            $code = strtoupper((string) ($parsed['destination_country_code'] ?? ''));
            if ($code !== '' && isset(self::COUNTRY_HUBS[$code])) {
                self::applyEndpoint($parsed, 'destination', self::COUNTRY_HUBS[$code]);
            }
        }

        $countries = $parsed['search_countries'] ?? [];
        if (is_array($countries) && count($countries) >= 2) {
            if (empty($parsed['departure_airport'])) {
                $code = strtoupper((string) ($countries[0]['search_country_code'] ?? ''));
                if (isset(self::COUNTRY_HUBS[$code])) {
                    self::applyEndpoint($parsed, 'origin', self::COUNTRY_HUBS[$code]);
                }
            }
            if (empty($parsed['arrival_airport'])) {
                $code = strtoupper((string) ($countries[1]['search_country_code'] ?? ''));
                if (isset(self::COUNTRY_HUBS[$code])) {
                    self::applyEndpoint($parsed, 'destination', self::COUNTRY_HUBS[$code]);
                }
            }
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public static function applyRelativeSchedule(array $parsed, string $query): array
    {
        $query = mb_strtolower(trim($query));

        if ($query !== '' && empty($parsed['departure_date'])) {
            if (preg_match('/\b(jav[eë]n?\s+e\s+ardhshme|java\s+e\s+ardhme|next\s+week)\b/u', $query)) {
                $parsed['departure_date'] = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeek()->format('Y-m-d');
            } elseif (preg_match('/\b(k[eë]t[eë]\s+jav[eë]|this\s+week)\b/u', $query)) {
                $parsed['departure_date'] = Carbon::now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
                if (Carbon::parse($parsed['departure_date'])->isPast()) {
                    $parsed['departure_date'] = Carbon::tomorrow()->format('Y-m-d');
                }
            } elseif (preg_match('/\b(fund\s*jav[eë]s|fundjav[eë]s|weekend)\b/u', $query)) {
                $parsed['departure_date'] = Carbon::now()->next(Carbon::SATURDAY)->format('Y-m-d');
            } elseif (preg_match('/\b(?:pas|in|brenda|after)\s+(\d+)\s+(dit[eë]?|days?)\b/u', $query, $offset)) {
                $parsed['departure_date'] = Carbon::today()->addDays(max(1, (int) $offset[1]))->format('Y-m-d');
            } elseif (preg_match('/\b(?:pas|in|brenda|after)\s+(\d+)\s+(jav[eë]?|weeks?)\b/u', $query, $offset)) {
                $parsed['departure_date'] = Carbon::today()->addWeeks(max(1, (int) $offset[1]))->format('Y-m-d');
            } elseif (preg_match('/\b(pasnes[eë]r|pas\s+nes[eë]r|day\s+after\s+tomorrow)\b/u', $query)) {
                $parsed['departure_date'] = Carbon::tomorrow()->addDay()->format('Y-m-d');
            } elseif (preg_match('/\b(nes[eë]r|nesra|tomorrow|next\s+day)\b/u', $query)) {
                $parsed['departure_date'] = Carbon::tomorrow()->format('Y-m-d');
            } elseif (preg_match('/\b(sot|today)\b/u', $query)) {
                $parsed['departure_date'] = Carbon::today()->format('Y-m-d');
            }
        }

        if ($query !== '' && empty($parsed['departure_time_from']) && empty($parsed['departure_time_to'])) {
            if (preg_match('/\b(paradite|afternoon)\b/u', $query)) {
                $parsed['departure_time_from'] = '12:00';
                $parsed['departure_time_to'] = '17:00';
            } elseif (preg_match('/\b(mbasdite|mbasdite|midday|noon)\b/u', $query)) {
                $parsed['departure_time_from'] = '11:00';
                $parsed['departure_time_to'] = '15:00';
            } elseif (preg_match('/\b(m[eë]ngjes|morning|mengjesit|am)\b/u', $query)) {
                $parsed['departure_time_from'] = '06:00';
                $parsed['departure_time_to'] = '12:00';
            } elseif (preg_match('/\b(mbr[eë]mje|dark[eë]|evening|night)\b/u', $query)) {
                $parsed['departure_time_from'] = '17:00';
                $parsed['departure_time_to'] = '22:00';
            }
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public static function normalizeTravelType(array $parsed): array
    {
        $type = mb_strtolower(trim((string) ($parsed['travel_type'] ?? '')));

        if (in_array($type, ['return', 'roundtrip', 'round trip', 'kthyse', 'kthyes', 'kthese', 'vajtje ardhje', 'vajtje-ardhje'], true)) {
            $parsed['travel_type'] = 'round_trip';
        } elseif ($type === '') {
            $parsed['travel_type'] = 'one_way';
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public static function resolveReturnDate(array $parsed): array
    {
        if (($parsed['travel_type'] ?? '') !== 'round_trip' || empty($parsed['departure_date'])) {
            unset($parsed['return_offset_value'], $parsed['return_offset_unit']);

            return $parsed;
        }

        if (empty($parsed['return_date'])) {
            $offsetValue = (int) ($parsed['return_offset_value'] ?? 0);
            $offsetUnit = (string) ($parsed['return_offset_unit'] ?? 'weeks');
            $departure = Carbon::parse((string) $parsed['departure_date']);

            if ($offsetValue > 0) {
                $parsed['return_date'] = $offsetUnit === 'days'
                    ? $departure->copy()->addDays($offsetValue)->format('Y-m-d')
                    : $departure->copy()->addWeeks($offsetValue)->format('Y-m-d');
            } else {
                $parsed['return_date'] = $departure->copy()->addWeeks(2)->format('Y-m-d');
            }
        }

        unset($parsed['return_offset_value'], $parsed['return_offset_unit']);

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $target
     * @param  array{city: string, country_code: string, airport: string}  $meta
     */
    private static function applyEndpoint(array &$target, string $role, array $meta): void
    {
        if ($role === 'origin') {
            $target['origin_city'] = $meta['city'];
            $target['origin_country_code'] = $meta['country_code'];
            $target['departure_airport'] = $meta['airport'];
            $target['search_city'] = $meta['city'];
            $target['search_country_code'] = $meta['country_code'];
            $target['search_country'] = self::countryName($meta['country_code']);
            $target['search_target'] = true;

            return;
        }

        $target['destination_city'] = $meta['city'];
        $target['destination_country_code'] = $meta['country_code'];
        $target['arrival_airport'] = $meta['airport'];
        $target['destination'] = $meta['city'];
    }

    /**
     * @return array{city: string, country_code: string, airport: string}|null
     */
    private static function resolveCountryHub(string $phrase): ?array
    {
        $phrase = trim(mb_strtolower($phrase));
        if ($phrase === '') {
            return null;
        }

        $countries = SearchCountryResolver::allFromQuery($phrase);
        if ($countries !== []) {
            $code = strtoupper((string) $countries[0]['search_country_code']);

            return self::COUNTRY_HUBS[$code] ?? null;
        }

        return null;
    }

    /**
     * @return array{city: string, country_code: string, airport: string}|null
     */
    private static function resolvePlace(string $phrase): ?array
    {
        $phrase = trim(mb_strtolower($phrase));
        if ($phrase === '') {
            return null;
        }

        foreach (self::CITY_AIRPORTS as $needle => $meta) {
            if ($phrase === $needle || str_contains($phrase, $needle) || str_contains($needle, $phrase)) {
                return $meta;
            }
        }

        return null;
    }

    private static function countryName(string $code): string
    {
        return match (strtoupper($code)) {
            'CH' => 'Switzerland',
            'GB' => 'United Kingdom',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'XK' => 'Kosovo',
            'US' => 'United States',
            default => $code,
        };
    }
}
