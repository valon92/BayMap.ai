<?php

namespace App\Support;

/**
 * Deep-link builders — BuyMap redirects to official booking sites (bridge model).
 */
class TravelBridgeUrls
{
    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, array<string, mixed>>
     */
    public static function groundOptions(array $parsed): array
    {
        $origin = (string) ($parsed['origin_city'] ?? $parsed['search_city'] ?? '');
        $destination = (string) ($parsed['destination_city'] ?? $parsed['destination'] ?? '');
        $date = (string) ($parsed['departure_date'] ?? '');

        if ($origin === '' || $destination === '' || $date === '') {
            return [];
        }

        $originEnc = rawurlencode($origin);
        $destEnc = rawurlencode($destination);
        $dateDot = self::dotDate($date);
        $travelers = max(1, (int) ($parsed['travelers'] ?? 1));
        $modes = self::requestedModes($parsed);

        $options = [];

        if (in_array('train', $modes, true)) {
            $options[] = self::option(
                'train',
                'Omio · Train',
                "https://www.omio.com/search?travelMode=train&departure={$originEnc}&arrival={$destEnc}&departureDate={$date}&passengers={$travelers}",
                'omio',
            );
            $options[] = self::option(
                'train',
                'Trainline',
                "https://www.thetrainline.com/book/results?origin={$originEnc}&destination={$destEnc}&outwardDate={$date}&passengers={$travelers}",
                'trainline',
            );
        }

        if (in_array('bus', $modes, true)) {
            $options[] = self::option(
                'bus',
                'FlixBus',
                "https://shop.flixbus.com/search?departureCity={$originEnc}&arrivalCity={$destEnc}&rideDate={$dateDot}",
                'flixbus',
            );
            $options[] = self::option(
                'bus',
                'Omio · Bus',
                "https://www.omio.com/search?travelMode=bus&departure={$originEnc}&arrival={$destEnc}&departureDate={$date}&passengers={$travelers}",
                'omio_bus',
            );
        }

        if (in_array('flight', $modes, true)) {
            $dep = (string) ($parsed['departure_airport'] ?? '');
            $arr = (string) ($parsed['arrival_airport'] ?? '');
            $q = $dep !== '' && $arr !== ''
                ? "Flights from {$dep} to {$arr} on {$date}"
                : "Flights from {$origin} to {$destination} on {$date}";
            $options[] = self::option(
                'flight',
                'Google Travel',
                'https://www.google.com/travel/flights?q='.rawurlencode($q),
                'google_travel',
            );
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    private static function requestedModes(array $parsed): array
    {
        $type = mb_strtolower((string) ($parsed['product_type'] ?? $parsed['travel_mode'] ?? ''));

        return match (true) {
            in_array($type, ['train', 'tren'], true) => ['train'],
            in_array($type, ['bus', 'autobus'], true) => ['bus'],
            in_array($type, ['flight', 'avion'], true) => ['flight'],
            default => ['flight', 'train', 'bus'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function option(string $mode, string $label, string $url, string $sourceKey): array
    {
        return [
            'travel_mode' => $mode,
            'label' => $label,
            'url' => $url,
            'source_key' => $sourceKey,
        ];
    }

    private static function dotDate(string $isoDate): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $isoDate, $m)) {
            return $isoDate;
        }

        return $m[3].'.'.$m[2].'.'.$m[1];
    }
}
