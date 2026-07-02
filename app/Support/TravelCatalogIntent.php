<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Demo flight catalog when SerpAPI / Google Flights is unavailable.
 */
class TravelCatalogIntent
{
    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function shouldUseCatalogFallback(array $parsed): bool
    {
        if (CategoryCatalog::normalize($parsed['category'] ?? '') !== 'travel') {
            return false;
        }

        if (! config('marketplaces.demo_fallback_when_empty', true)) {
            return false;
        }

        $mode = mb_strtolower((string) ($parsed['travel_mode'] ?? $parsed['product_type'] ?? 'flight'));

        return in_array($mode, ['flight', ''], true);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, array<string, mixed>>
     */
    public static function catalogFallback(array $parsed): array
    {
        if (! self::shouldUseCatalogFallback($parsed)) {
            return [];
        }

        $path = storage_path('data/products/travel.json');
        if (! File::exists($path)) {
            return [];
        }

        $data = json_decode(File::get($path), true);
        if (! is_array($data)) {
            return [];
        }

        $departure = strtoupper((string) ($parsed['departure_airport'] ?? ''));
        $arrival = strtoupper((string) ($parsed['arrival_airport'] ?? ''));
        $date = (string) ($parsed['departure_date'] ?? '');
        $items = [];

        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            if ($departure !== '' && strtoupper((string) ($item['departure_airport'] ?? '')) !== $departure) {
                continue;
            }

            if ($arrival !== '' && strtoupper((string) ($item['arrival_airport'] ?? '')) !== $arrival) {
                continue;
            }

            if ($date !== '' && ! empty($item['departure_date']) && (string) $item['departure_date'] !== $date) {
                continue;
            }

            $item['live'] = false;
            $item['category'] = 'travel';
            $item['source_key'] = (string) ($item['store'] ?? 'google_flights');
            $item['source'] = $item['source'] ?? 'BuyMap Travel';
            $items[] = $item;
        }

        if ($items === [] && $departure !== '' && $arrival !== '') {
            foreach ($data as $item) {
                if (! is_array($item)) {
                    continue;
                }
                if (strtoupper((string) ($item['departure_airport'] ?? '')) === $departure
                    && strtoupper((string) ($item['arrival_airport'] ?? '')) === $arrival) {
                    $item['live'] = false;
                    $item['category'] = 'travel';
                    $item['source_key'] = (string) ($item['store'] ?? 'google_flights');
                    $item['source'] = $item['source'] ?? 'BuyMap Travel';
                    $items[] = $item;
                }
            }
        }

        return $items;
    }
}
