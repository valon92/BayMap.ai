<?php

namespace App\Support;

/**
 * Engine displacement parsing and matching for automotive listings.
 */
class AutomotiveEngineResolver
{
    private const LITERS_TOLERANCE = 0.12;

    public static function parseFromQuery(string $query): ?float
    {
        $lower = mb_strtolower(trim($query));

        if (preg_match('/\b(?:motori|motor|engine)\s*(\d+[.,]\d+)\b/ui', $lower, $match)) {
            return self::normalizeLiters($match[1]);
        }

        if (preg_match('/\b(\d+[.,]\d+)\s*(?:l|litri|liter|litra)\b/ui', $lower, $match)) {
            return self::normalizeLiters($match[1]);
        }

        if (preg_match('/\b(\d+[.,]\d+)\s*(?:disel|diesel|dizel|tdi|benzin|petrol|tfsi|tsi)\b/ui', $lower, $match)) {
            return self::normalizeLiters($match[1]);
        }

        return null;
    }

    public static function litersFromCcm(?string $ccm): ?float
    {
        if ($ccm === null || $ccm === '') {
            return null;
        }

        if (preg_match('/(\d{3,4})/', str_replace(['.', ',', ' '], '', $ccm), $match)) {
            return round(((int) $match[1]) / 1000, 1);
        }

        return null;
    }

    public static function extractFromTitle(string $title, ?string $fuel = null): ?float
    {
        $lower = mb_strtolower($title);

        if (preg_match_all('/\b(\d+[.,]\d+)\s*(?:l|tdi|tsi|tfsi|gtd|cdi|dci)\b/ui', $lower, $matches)) {
            $values = array_map(fn ($v) => self::normalizeLiters($v), $matches[1]);

            return self::pickBestLiters($values);
        }

        if (preg_match('/\b(\d+[.,]\d+)\b/u', $lower, $match)) {
            $liters = self::normalizeLiters($match[1]);
            if ($liters >= 0.8 && $liters <= 8.0) {
                return $liters;
            }
        }

        if ($fuel !== null && preg_match('/\bgtd\b/i', $title) && self::isDieselFuel($fuel)) {
            return 2.0;
        }

        return null;
    }

    public static function matchesWanted(?float $productLiters, float $wantedLiters, string $title = '', ?string $fuel = null): bool
    {
        if ($productLiters === null) {
            $productLiters = self::extractFromTitle($title, $fuel);
        }

        if ($productLiters !== null) {
            return abs($productLiters - $wantedLiters) <= self::LITERS_TOLERANCE;
        }

        return ! self::mentionsConflictingEngine($title, $wantedLiters);
    }

    public static function mentionsConflictingEngine(string $title, float $wantedLiters): bool
    {
        $lower = mb_strtolower($title);
        if (! preg_match_all('/\b(\d+[.,]\d+)\s*(?:l|tdi|tsi|tfsi|gtd|cdi|dci)\b/ui', $lower, $matches)) {
            return false;
        }

        foreach ($matches[1] as $value) {
            $liters = self::normalizeLiters($value);
            if (abs($liters - $wantedLiters) > self::LITERS_TOLERANCE) {
                return true;
            }
        }

        return false;
    }

    public static function keywordForSearch(float $liters): string
    {
        return number_format($liters, 1, '.', '');
    }

    private static function normalizeLiters(string $value): float
    {
        $value = str_replace(',', '.', trim($value));

        return round((float) $value, 1);
    }

    /**
     * @param  array<int, float>  $values
     */
    private static function pickBestLiters(array $values): ?float
    {
        $values = array_values(array_filter($values, fn ($v) => $v >= 0.8 && $v <= 8.0));
        if ($values === []) {
            return null;
        }

        return max($values);
    }

    private static function isDieselFuel(string $fuel): bool
    {
        $fuel = mb_strtolower($fuel);

        return in_array($fuel, ['diesel', 'dizel', 'tdi', 'disel'], true);
    }
}
