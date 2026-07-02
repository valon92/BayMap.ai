<?php

namespace App\Support;

/**
 * Human-readable buyer summaries from structured intent (sq/en).
 */
class IntentDescriptionBuilder
{
    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function build(array $parsed, ?string $locale = 'en'): string
    {
        $sq = SearchLocale::isAlbanian($locale);
        $category = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');
        $query = trim((string) ($parsed['raw_query'] ?? ''));

        $built = match ($category) {
            'industrial_b2b' => self::industrial($parsed, $sq),
            'automotive' => self::automotive($parsed, $sq),
            'automotive_parts' => self::automotiveParts($parsed, $sq),
            'fashion', 'sports_outdoor' => self::fashion($parsed, $sq),
            'electronics_tech', 'gaming_entertainment', 'home_appliances' => self::electronics($parsed, $sq),
            'real_estate' => self::realEstate($parsed, $sq),
            'travel' => self::travel($parsed, $sq),
            'online_education' => self::books($parsed, $sq),
            'home_furniture' => self::furniture($parsed, $sq),
            default => '',
        };

        if ($built !== '') {
            return $built;
        }

        return $sq
            ? 'Kërkim produktesh: '.$query
            : 'Product search: '.$query;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private static function industrial(array $parsed, bool $sq): string
    {
        $lower = mb_strtolower((string) ($parsed['raw_query'] ?? ''));

        if (preg_match('/\bplastik|plastic|plastike\b/u', $lower)) {
            return $sq
                ? 'Makineri industriale për prodhimin e plastikës'
                : 'Industrial machinery for plastic production';
        }

        if (preg_match('/\b(prodhim|manufactur|fabrik)\b/u', $lower)) {
            return $sq
                ? 'Pajisje dhe makineri për prodhim industrial'
                : 'Industrial production equipment and machinery';
        }

        return $sq ? 'Makineri dhe pajisje industriale B2B' : 'Industrial B2B machinery and equipment';
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private static function automotive(array $parsed, bool $sq): string
    {
        $brand = trim((string) ($parsed['brand'] ?? ''));
        $model = trim((string) ($parsed['model'] ?? ''));
        $yearMin = $parsed['year_min'] ?? $parsed['year'] ?? null;
        $yearMax = $parsed['year_max'] ?? null;
        $fuel = trim((string) ($parsed['fuel'] ?? ''));

        $parts = array_filter([
            $brand,
            $model,
            self::yearRangeLabel($yearMin, $yearMax, $sq),
            $fuel !== '' ? self::fuelLabel($fuel, $sq) : '',
        ]);

        $core = implode(' ', $parts);

        if ($core !== '') {
            return $sq ? 'Veturë '.$core : 'Car '.$core;
        }

        return $sq ? 'Veturë ose automjet' : 'Car or vehicle';
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private static function automotiveParts(array $parsed, bool $sq): string
    {
        $item = trim((string) ($parsed['item'] ?? $parsed['product_type'] ?? ''));
        $brand = trim((string) ($parsed['brand'] ?? ''));
        $model = trim((string) ($parsed['model'] ?? ''));

        if ($item === 'machinery') {
            return $sq ? 'Makineri / pajisje industriale' : 'Machinery / industrial equipment';
        }

        $parts = array_filter([$item, $brand, $model]);

        if ($parts !== []) {
            return $sq
                ? 'Autopjesë: '.implode(' ', $parts)
                : 'Auto part: '.implode(' ', $parts);
        }

        return $sq ? 'Autopjesë ose komponent automjeti' : 'Auto part or vehicle component';
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private static function fashion(array $parsed, bool $sq): string
    {
        $brand = trim((string) ($parsed['brand'] ?? ''));
        $type = trim((string) ($parsed['product_type'] ?? ''));
        $size = trim((string) ($parsed['size'] ?? ''));
        $color = trim((string) ($parsed['color'] ?? ''));

        $parts = array_filter([$brand, $type, $color !== '' ? $color : '', $size !== '' ? ($sq ? 'nr '.$size : 'size '.$size) : '']);

        if ($parts !== []) {
            return $sq ? 'Veshje / këpucë: '.implode(', ', $parts) : 'Fashion: '.implode(', ', $parts);
        }

        return $sq ? 'Veshje, këpucë ose aksesorë' : 'Clothing, shoes or accessories';
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private static function electronics(array $parsed, bool $sq): string
    {
        $brand = trim((string) ($parsed['brand'] ?? ''));
        $type = trim((string) ($parsed['product_type'] ?? ''));
        $model = trim((string) ($parsed['model'] ?? ''));

        $parts = array_filter([$brand, $model, $type]);

        if ($parts !== []) {
            return $sq ? 'Elektronikë: '.implode(' ', $parts) : 'Electronics: '.implode(' ', $parts);
        }

        return $sq ? 'Produkt elektronik' : 'Electronics product';
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private static function realEstate(array $parsed, bool $sq): string
    {
        $city = trim((string) ($parsed['city'] ?? ''));
        $bedrooms = $parsed['bedrooms'] ?? null;
        $sqm = $parsed['min_sqm'] ?? null;

        $parts = [];
        if ($bedrooms) {
            $parts[] = $sq ? "{$bedrooms} dhoma" : "{$bedrooms} bedrooms";
        }
        if ($sqm) {
            $parts[] = $sq ? "{$sqm} m²" : "{$sqm} sqm";
        }
        if ($city !== '') {
            $parts[] = $city;
        }

        if ($parts !== []) {
            return $sq ? 'Patundshmëri: '.implode(', ', $parts) : 'Property: '.implode(', ', $parts);
        }

        return $sq ? 'Banesë, shtëpi ose pronë' : 'Apartment, house or property';
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private static function travel(array $parsed, bool $sq): string
    {
        $from = trim((string) ($parsed['origin_city'] ?? ''));
        $to = trim((string) ($parsed['destination_city'] ?? $parsed['destination'] ?? ''));

        if ($from !== '' && $to !== '') {
            return $sq ? "Udhëtim {$from} → {$to}" : "Trip {$from} → {$to}";
        }

        return $sq ? 'Fluturim, hotel ose paketë udhëtimi' : 'Flight, hotel or travel package';
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private static function books(array $parsed, bool $sq): string
    {
        $genre = trim((string) ($parsed['genre'] ?? ''));

        if ($genre !== '') {
            return $sq ? "Libër — zhanri {$genre}" : "Book — {$genre} genre";
        }

        return $sq ? 'Libër, kurs ose materiale edukative' : 'Book, course or educational material';
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private static function furniture(array $parsed, bool $sq): string
    {
        $room = trim((string) ($parsed['room'] ?? ''));
        $item = trim((string) ($parsed['item'] ?? ''));

        if ($room === 'kitchen' || $item === 'kitchen') {
            return $sq ? 'Kuzhinë ose mobilje kuzhine' : 'Kitchen or kitchen furniture';
        }

        $parts = array_filter([$item, $room]);

        if ($parts !== []) {
            return $sq ? 'Mobilje: '.implode(', ', $parts) : 'Furniture: '.implode(', ', $parts);
        }

        return $sq ? 'Mobilje ose dekor shtëpie' : 'Furniture or home decor';
    }

    private static function yearRangeLabel(mixed $min, mixed $max, bool $sq): string
    {
        if ($min && $max && (int) $min !== (int) $max) {
            return $sq ? "viti {$min}–{$max}" : "{$min}–{$max}";
        }
        if ($min) {
            return (string) $min;
        }

        return '';
    }

    private static function fuelLabel(string $fuel, bool $sq): string
    {
        return match (mb_strtolower($fuel)) {
            'diesel' => $sq ? 'diesel' : 'diesel',
            'petrol' => $sq ? 'benzinë' : 'petrol',
            'electric' => $sq ? 'elektrike' : 'electric',
            'hybrid' => $sq ? 'hibride' : 'hybrid',
            default => $fuel,
        };
    }
}
