<?php

namespace App\Services\Marketplace;

use App\Contracts\MarketplaceSearchInterface;
use App\Support\TravelBridgeUrls;
use App\Support\TravelIntentParser;
use App\Support\UniversalMarketplaceBridge;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BuyMap travel bridge — live flights (SerpAPI) + deep links for train/bus booking sites.
 *
 * @see https://serpapi.com/google-flights-api
 */
class SerpApiTravelBridgeService implements MarketplaceSearchInterface
{
    public function getSourceName(): string
    {
        return 'BuyMap Travel';
    }

    public function isConfigured(): bool
    {
        return config('serpapi.enabled') && ! empty(config('serpapi.api_key'));
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $parsedQuery, array $expandedFilters): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $parsedQuery = TravelIntentParser::ensureTravelEndpoints($parsedQuery);

        $items = [];

        if ($this->canSearchFlights($parsedQuery)) {
            $items = array_merge($items, $this->searchFlights($parsedQuery, $expandedFilters));
        }

        if (($parsedQuery['product_type'] ?? '') !== 'hotel') {
            $items = array_merge($items, $this->groundBridgeCards($parsedQuery));
        }

        return $this->sortTravelItems($items, $parsedQuery);
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     */
    private function canSearchFlights(array $parsedQuery): bool
    {
        $type = mb_strtolower((string) ($parsedQuery['product_type'] ?? $parsedQuery['travel_mode'] ?? ''));

        if (in_array($type, ['train', 'tren', 'bus', 'autobus', 'hotel'], true)) {
            return false;
        }

        return ($parsedQuery['departure_airport'] ?? '') !== ''
            && ($parsedQuery['arrival_airport'] ?? '') !== ''
            && ($parsedQuery['departure_date'] ?? '') !== '';
    }

    /**
     * Skip train/bus bridge cards for intercontinental routes (not meaningful).
     *
     * @param  array<string, mixed>  $parsedQuery
     */
    private function isLongHaulRoute(array $parsedQuery): bool
    {
        $origin = strtoupper((string) ($parsedQuery['origin_country_code'] ?? ''));
        $destination = strtoupper((string) ($parsedQuery['destination_country_code'] ?? ''));

        if ($origin === '' || $destination === '' || $origin === $destination) {
            return false;
        }

        $regions = [
            'US' => 'NA', 'CA' => 'NA', 'MX' => 'NA',
            'DE' => 'EU', 'FR' => 'EU', 'IT' => 'EU', 'ES' => 'EU', 'NL' => 'EU', 'AT' => 'EU', 'CH' => 'EU', 'GB' => 'EU', 'XK' => 'EU', 'AL' => 'EU',
            'AE' => 'ME', 'TR' => 'ME',
            'IN' => 'AS',
        ];

        $originRegion = $regions[$origin] ?? $origin;
        $destinationRegion = $regions[$destination] ?? $destination;

        return $originRegion !== $destinationRegion;
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @return array<int, array<string, mixed>>
     */
    private function searchFlights(array $parsedQuery, array $expandedFilters): array
    {
        $departure = (string) ($parsedQuery['departure_airport'] ?? '');
        $arrival = (string) ($parsedQuery['arrival_airport'] ?? '');
        $date = (string) ($parsedQuery['departure_date'] ?? '');

        if ($departure === '' || $arrival === '' || $date === '') {
            return [];
        }

        try {
            $geo = UniversalMarketplaceBridge::serpGeo($parsedQuery, $expandedFilters);
            $travelType = (string) ($parsedQuery['travel_type'] ?? 'one_way');
            $type = $travelType === 'round_trip' ? 1 : 2;
            $adults = max(1, (int) ($parsedQuery['travelers'] ?? 1));

            $params = array_filter([
                'engine' => 'google_flights',
                'departure_id' => $departure,
                'arrival_id' => $arrival,
                'outbound_date' => $date,
                'type' => $type,
                'adults' => $adults,
                'currency' => UniversalMarketplaceBridge::currencyForCountry($geo['country_code']),
                'hl' => $geo['hl'],
                'api_key' => config('serpapi.api_key'),
            ]);

            if ($travelType === 'round_trip' && ! empty($parsedQuery['return_date'])) {
                $params['return_date'] = (string) $parsedQuery['return_date'];
            }

            $response = Http::timeout(config('serpapi.timeout', 20))->get('https://serpapi.com/search', $params);

            if (! $response->successful()) {
                Log::warning('SerpAPI flights failed', ['status' => $response->status()]);

                return [];
            }

            $flights = array_merge(
                (array) ($response->json('best_flights') ?? []),
                (array) ($response->json('other_flights') ?? []),
            );

            $items = [];
            $limit = (int) config('serpapi.travel_flight_limit', config('serpapi.limit', 12));

            foreach (array_slice($flights, 0, $limit) as $flight) {
                if (! is_array($flight)) {
                    continue;
                }
                $normalized = $this->normalizeFlight($flight, $parsedQuery, $geo['country_code']);
                if ($normalized !== null) {
                    $items[] = $normalized;
                }
            }

            return $this->filterByTimeWindow($items, $parsedQuery);
        } catch (\Throwable $e) {
            Log::warning('SerpAPI flights exception', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, array<string, mixed>>
     */
    private function groundBridgeCards(array $parsedQuery): array
    {
        if ($this->isLongHaulRoute($parsedQuery)) {
            return [];
        }

        $origin = (string) ($parsedQuery['origin_city'] ?? '');
        $destination = (string) ($parsedQuery['destination_city'] ?? $parsedQuery['destination'] ?? '');
        $countryCode = strtoupper((string) ($parsedQuery['origin_country_code'] ?? $parsedQuery['search_country_code'] ?? 'CH'));

        $items = [];
        foreach (TravelBridgeUrls::groundOptions(array_merge($parsedQuery, ['product_type' => 'train'])) as $option) {
            if (($option['travel_mode'] ?? '') !== 'train') {
                continue;
            }
            $items[] = $this->bridgeCard($option, $parsedQuery, $countryCode, $origin, $destination);
        }
        foreach (TravelBridgeUrls::groundOptions(array_merge($parsedQuery, ['product_type' => 'bus'])) as $option) {
            if (($option['travel_mode'] ?? '') !== 'bus') {
                continue;
            }
            $items[] = $this->bridgeCard($option, $parsedQuery, $countryCode, $origin, $destination);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $option
     * @param  array<string, mixed>  $parsedQuery
     * @return array<string, mixed>
     */
    private function bridgeCard(array $option, array $parsedQuery, string $countryCode, string $origin, string $destination): array
    {
        $mode = (string) ($option['travel_mode'] ?? 'train');
        $label = (string) ($option['label'] ?? 'Travel');
        $title = "{$label}: {$origin} → {$destination}";

        return [
            'id' => 'travel-bridge-'.md5($title.($option['url'] ?? '')),
            'title' => $title,
            'subtitle' => self::modeLabel($mode).' · '.($parsedQuery['departure_date'] ?? ''),
            'image' => self::modeImage($mode),
            'price' => 0.0,
            'price_on_request' => true,
            'currency' => UniversalMarketplaceBridge::currencyForCountry($countryCode),
            'location' => $origin.' → '.$destination,
            'country_code' => $countryCode,
            'category' => 'travel',
            'product_type' => $mode,
            'travel_mode' => $mode,
            'condition' => 'new',
            'url' => (string) ($option['url'] ?? '#'),
            'source' => $label,
            'source_key' => (string) ($option['source_key'] ?? 'travel_bridge'),
            'affiliate_ready' => true,
            'sponsored' => false,
            'tags' => [$mode, 'travel', 'bridge', 'live'],
            'live' => true,
            'origin_city' => $origin,
            'destination_city' => $destination,
            'departure_date' => $parsedQuery['departure_date'] ?? null,
            'departure_time' => (string) ($parsedQuery['departure_time_from'] ?? ''),
            'arrival_time' => (string) ($parsedQuery['departure_time_to'] ?? ''),
            'departure_airport' => $origin,
            'arrival_airport' => $destination,
        ];
    }

    /**
     * @param  array<string, mixed>  $flight
     * @return array<string, mixed>|null
     */
    private function normalizeFlight(array $flight, array $parsedQuery, string $countryCode): ?array
    {
        $price = (float) ($flight['price'] ?? 0);
        if ($price <= 0) {
            return null;
        }

        $legs = array_values(array_filter((array) ($flight['flights'] ?? []), 'is_array'));
        if ($legs === []) {
            return null;
        }

        $firstLeg = $legs[0];
        $lastLeg = $legs[array_key_last($legs)];

        $departureAirport = (array) ($firstLeg['departure_airport'] ?? []);
        $arrivalAirport = (array) ($lastLeg['arrival_airport'] ?? []);

        $departureTime = self::extractTime((string) ($departureAirport['time'] ?? ''));
        $arrivalTime = self::extractTime((string) ($arrivalAirport['time'] ?? ''));
        $departureCode = (string) ($departureAirport['id'] ?? $parsedQuery['departure_airport'] ?? '');
        $arrivalCode = (string) ($arrivalAirport['id'] ?? $parsedQuery['arrival_airport'] ?? '');

        $origin = (string) ($parsedQuery['origin_city'] ?? ($departureAirport['name'] ?? 'Origin'));
        $destination = (string) ($parsedQuery['destination_city'] ?? ($arrivalAirport['name'] ?? 'Destination'));

        $airline = (string) ($firstLeg['airline'] ?? 'Airline');
        $flightNumber = (string) ($firstLeg['flight_number'] ?? '');
        $travelClass = (string) ($firstLeg['travel_class'] ?? '');
        $stops = max(0, count($legs) - 1);
        $durationMinutes = (int) ($flight['total_duration'] ?? 0);

        $title = trim("{$airline}: {$origin} → {$destination}");
        $subtitle = trim(implode(' · ', array_filter([
            $flightNumber !== '' ? $flightNumber : null,
            $travelClass !== '' ? $travelClass : null,
            $departureTime !== '' ? $departureTime.'–'.$arrivalTime : null,
        ])));

        $bookingUrl = (string) ($flight['booking_token'] ?? '');
        if ($bookingUrl !== '' && ! str_starts_with($bookingUrl, 'http')) {
            $bookingUrl = 'https://www.google.com/travel/flights/booking?token='.rawurlencode($bookingUrl);
        }
        if ($bookingUrl === '') {
            $bookingUrl = 'https://www.google.com/travel/flights?q='.rawurlencode(
                "Flights from {$departureCode} to {$arrivalCode} on ".($parsedQuery['departure_date'] ?? ''),
            );
        }

        $airlineLogo = (string) ($firstLeg['airline_logo'] ?? $flight['airline_logo'] ?? '');
        $carbon = (array) ($flight['carbon_emissions'] ?? []);

        return [
            'id' => 'flight-'.md5($title.$price.$departureTime.$flightNumber),
            'title' => $title,
            'subtitle' => $subtitle,
            'image' => $airlineLogo !== '' ? $airlineLogo : self::modeImage('flight'),
            'price' => $price,
            'currency' => UniversalMarketplaceBridge::currencyForCountry($countryCode),
            'location' => $origin.' → '.$destination,
            'country_code' => strtoupper($countryCode),
            'category' => 'travel',
            'product_type' => 'flight',
            'travel_mode' => 'flight',
            'condition' => 'new',
            'url' => $bookingUrl,
            'source' => 'Google Flights',
            'source_key' => 'google_flights',
            'affiliate_ready' => true,
            'sponsored' => false,
            'tags' => ['flight', 'travel', 'google_flights', 'live', 'bridge'],
            'live' => true,
            'departure_time' => $departureTime,
            'arrival_time' => $arrivalTime,
            'departure_airport' => $departureCode,
            'arrival_airport' => $arrivalCode,
            'origin_city' => $origin,
            'destination_city' => $destination,
            'departure_date' => $parsedQuery['departure_date'] ?? null,
            'duration_minutes' => $durationMinutes,
            'duration_label' => self::formatDuration($durationMinutes),
            'stops' => $stops,
            'stops_label' => $stops === 0 ? 'Direct' : ($stops === 1 ? '1 stop' : "{$stops} stops"),
            'airline' => $airline,
            'flight_number' => $flightNumber,
            'travel_class' => $travelClass,
            'carbon_kg' => isset($carbon['this_flight']) ? (int) $carbon['this_flight'] : null,
            'legs' => array_map(fn (array $leg) => [
                'airline' => (string) ($leg['airline'] ?? ''),
                'flight_number' => (string) ($leg['flight_number'] ?? ''),
                'from' => (string) (($leg['departure_airport']['id'] ?? '') ?: ($leg['departure_airport']['name'] ?? '')),
                'to' => (string) (($leg['arrival_airport']['id'] ?? '') ?: ($leg['arrival_airport']['name'] ?? '')),
                'departure' => self::extractTime((string) ($leg['departure_airport']['time'] ?? '')),
                'arrival' => self::extractTime((string) ($leg['arrival_airport']['time'] ?? '')),
                'duration_minutes' => (int) ($leg['duration'] ?? 0),
            ], $legs),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, array<string, mixed>>
     */
    private function sortTravelItems(array $items, array $parsedQuery): array
    {
        usort($items, function (array $a, array $b) use ($parsedQuery) {
            $aFlight = ($a['travel_mode'] ?? '') === 'flight' && empty($a['price_on_request']);
            $bFlight = ($b['travel_mode'] ?? '') === 'flight' && empty($b['price_on_request']);
            if ($aFlight !== $bFlight) {
                return $bFlight <=> $aFlight;
            }
            if ($aFlight && $bFlight) {
                $aInWindow = $this->isInTimeWindow((string) ($a['departure_time'] ?? ''), $parsedQuery) ? 1 : 0;
                $bInWindow = $this->isInTimeWindow((string) ($b['departure_time'] ?? ''), $parsedQuery) ? 1 : 0;
                if ($aInWindow !== $bInWindow) {
                    return $bInWindow <=> $aInWindow;
                }

                return ((float) ($a['price'] ?? 0)) <=> ((float) ($b['price'] ?? 0));
            }

            return strcmp((string) ($a['travel_mode'] ?? ''), (string) ($b['travel_mode'] ?? ''));
        });

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, array<string, mixed>>
     */
    private function filterByTimeWindow(array $items, array $parsedQuery): array
    {
        $from = (string) ($parsedQuery['departure_time_from'] ?? '');
        $to = (string) ($parsedQuery['departure_time_to'] ?? '');
        if ($from === '' && $to === '') {
            return $items;
        }

        $inWindow = array_values(array_filter($items, fn (array $item) => $this->isInTimeWindow(
            (string) ($item['departure_time'] ?? ''),
            $parsedQuery,
        )));

        return $inWindow !== [] ? $inWindow : $items;
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     */
    private function isInTimeWindow(string $time, array $parsedQuery): bool
    {
        $from = (string) ($parsedQuery['departure_time_from'] ?? '');
        $to = (string) ($parsedQuery['departure_time_to'] ?? '');
        if ($time === '' || ($from === '' && $to === '')) {
            return true;
        }
        if ($from !== '' && $time < $from) {
            return false;
        }
        if ($to !== '' && $time > $to) {
            return false;
        }

        return true;
    }

    private static function extractTime(string $raw): string
    {
        if (preg_match('/(\d{1,2}:\d{2})/', $raw, $match)) {
            return $match[1];
        }

        return $raw;
    }

    private static function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return $hours > 0
            ? ($mins > 0 ? "{$hours}h {$mins}m" : "{$hours}h")
            : "{$mins}m";
    }

    private static function modeLabel(string $mode): string
    {
        return match ($mode) {
            'train' => 'Train',
            'bus' => 'Bus',
            default => 'Flight',
        };
    }

    private static function modeImage(string $mode): string
    {
        return match ($mode) {
            'train' => 'https://images.unsplash.com/photo-1474487548417-781cb786bc38?w=800&q=80',
            'bus' => 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=800&q=80',
            default => 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?w=800&q=80',
        };
    }
}
