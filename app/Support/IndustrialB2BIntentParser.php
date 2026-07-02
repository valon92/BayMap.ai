<?php

namespace App\Support;

/**
 * Industrial / manufacturing machinery intent — not automotive spare parts.
 */
class IndustrialB2BIntentParser
{
    /** @var array<int, string> */
    private const PRODUCTION_SIGNALS = [
        'prodhim', 'prodhimi', 'prodhimin', 'manufactur', 'production', 'producing', 'fabrik', 'factory', 'plant',
    ];

    /** @var array<int, string> */
    private const MATERIAL_SIGNALS = [
        'plastik', 'plastic', 'plastike', 'plastikes', 'metal', 'tekstil', 'textile', 'ushqim', 'food', 'goma',
        'rubber', 'paper', 'letër', 'leter',
    ];

    /** @var array<int, string> */
    private const EQUIPMENT_SIGNALS = [
        'makineri', 'makine', 'machinery', 'machine', 'pajisje', 'equipment', 'ekstruder', 'extruder', 'injektim',
        'injection', 'molding', 'moulding', 'press', 'presë', 'pres', 'linjë', 'linje', 'line',
    ];

    /** @var array<int, string> */
    private const INDUSTRIAL_SIGNALS = [
        'industrial', 'industri', 'industriale', 'b2b', 'wholesale', 'fabrikë', 'fabrike',
    ];

    public static function isIndustrialQuery(string $query): bool
    {
        $lower = mb_strtolower(trim($query));

        if ($lower === '' || AutomotivePartsIntentParser::isVehiclePurchaseQuery([], $query)) {
            return false;
        }

        if (preg_match('/\b(autopjes|autopjese|pjesë|pjese|ricambi|ersatzteil|spare part|brake pad|fren)\b/u', $lower)) {
            return false;
        }

        $hasProduction = self::matchesAny($lower, self::PRODUCTION_SIGNALS);
        $hasMaterial = self::matchesAny($lower, self::MATERIAL_SIGNALS);
        $hasEquipment = self::matchesAny($lower, self::EQUIPMENT_SIGNALS);
        $hasIndustrial = self::matchesAny($lower, self::INDUSTRIAL_SIGNALS);

        if ($hasProduction && ($hasMaterial || $hasEquipment)) {
            return true;
        }

        if ($hasIndustrial && $hasEquipment) {
            return true;
        }

        if (preg_match('/\b(injektim|injection molding|extruder|ekstruder|blow molding|thermoforming)\b/u', $lower)) {
            return true;
        }

        if ($hasEquipment && preg_match('/\b(per|për|for)\b/u', $lower)
            && ($hasMaterial || $hasProduction || preg_match('/\b(prodhim|manufactur|fabrik)\b/u', $lower))) {
            return true;
        }

        if (preg_match('/\b(tractor|excavator|forklift|bulldozer|bagger|stapler)\b/u', $lower)
            && ! preg_match('/\b(engine|motorri?|turbo|filter|brake)\b/u', $lower)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public static function merge(array $parsed, string $rawQuery): array
    {
        if (! self::isIndustrialQuery($rawQuery)) {
            return $parsed;
        }

        $parsed['category'] = 'industrial_b2b';
        $parsed['equipment_type'] = 'machinery';
        $parsed['industry'] = self::detectIndustry($rawQuery);
        $parsed['product_type'] = 'machinery';

        $term = self::searchTerm($parsed, $rawQuery);
        if ($term !== '') {
            $parsed['search_query'] = $term;
        }

        unset($parsed['item'], $parsed['year_min'], $parsed['year_max'], $parsed['mileage'], $parsed['fuel']);

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function searchTerm(array $parsed, string $rawQuery): string
    {
        $lower = mb_strtolower($rawQuery);

        if (preg_match('/\bplastik|plastic|plastike|plastikes\b/u', $lower)) {
            if (preg_match('/\bprodhim|production|manufactur\b/u', $lower)) {
                return 'plastic production machinery injection molding';
            }

            return 'plastic machinery injection molding';
        }

        if (! empty($parsed['search_query']) && is_string($parsed['search_query'])) {
            return trim($parsed['search_query']);
        }

        return trim($rawQuery);
    }

    private static function detectIndustry(string $query): string
    {
        $lower = mb_strtolower($query);

        if (preg_match('/\b(plastik|plastic|plastike)\b/u', $lower)) {
            return 'manufacturing';
        }

        if (preg_match('/\b(food|ushqim|textile|tekstil|construction|ndertim|ndërtim)\b/u', $lower)) {
            return match (true) {
                preg_match('/\b(food|ushqim)\b/u', $lower) => 'food',
                preg_match('/\b(textile|tekstil)\b/u', $lower) => 'textile',
                default => 'construction',
            };
        }

        return 'manufacturing';
    }

    /**
     * @param  array<int, string>  $needles
     */
    private static function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (preg_match('/\b'.preg_quote($needle, '/').'\b/u', $haystack)) {
                return true;
            }
        }

        return false;
    }
}
