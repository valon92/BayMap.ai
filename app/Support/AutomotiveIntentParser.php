<?php

namespace App\Support;

/**
 * Rule-based automotive intent from Albanian/English/Dutch car queries.
 */
class AutomotiveIntentParser
{
    /** @var array<string, string> */
    private const COLORS = [
        'e zezë' => 'black',
        'e zeze' => 'black',
        'zezë' => 'black',
        'zeze' => 'black',
        'e bardhë' => 'white',
        'e bardhe' => 'white',
        'e bardh' => 'white',
        'bardhë' => 'white',
        'bardhe' => 'white',
        'bardh' => 'white',
        'hiri' => 'grey',
        'gri' => 'grey',
        'grey' => 'grey',
        'gray' => 'grey',
        'black' => 'black',
        'white' => 'white',
    ];

    public static function isCarQuery(string $query): bool
    {
        if (ProductCategoryResolver::isChildrenToyVehicleQuery($query)) {
            return false;
        }

        $lower = mb_strtolower(trim($query));

        return (bool) preg_match(
            '/\b(vetur[aëe]?|vetura|veture|makina|automobil|automotive|car|cars|vehicle|vehicles|auto)\b/u',
            $lower
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromQuery(string $query): array
    {
        $lower = mb_strtolower(trim($query));
        $result = [];

        if (self::isCarQuery($query)) {
            $result['product_type'] = 'car';
        }

        if (preg_match('/\b(audi|bmw|mercedes|mercedes-benz|volkswagen|vw|toyota|honda|ford|porsche|skoda|seat)\b/u', $lower, $m)) {
            $brand = strtolower($m[1]);
            $result['brand'] = match ($brand) {
                'vw' => 'Volkswagen',
                'mercedes', 'mercedes-benz' => 'Mercedes-Benz',
                default => ucfirst($brand),
            };
        }

        if (preg_match('/\b(gle|glc|gla|gls|glb|eqc|eqe|eqs|cls|ml|sl|amg)\b/i', $query, $m)) {
            $result['model'] = strtoupper($m[1]);
        } elseif (preg_match('/\b(q[3578]|x[13567]|a[34678])\b/i', $query, $m)) {
            $result['model'] = strtoupper($m[1]);
        } elseif (preg_match('/\b([ecsa])[-\s]?class\b/i', $query, $m)) {
            $result['model'] = strtoupper($m[1]).'-Class';
        } elseif (preg_match('/\bgolf[t]?\s*([0-9]{1,2}|i{1,3}|iv|v|vi{1,3}|vii{0,3})\b/i', $query, $m)) {
            $gen = strtolower($m[1]);
            $gen = match ($gen) {
                'vii', '7' => '7',
                'viii', '8' => '8',
                'vi', '6' => '6',
                default => $gen,
            };
            $result['model'] = 'golf '.$gen;
        }

        $result = array_merge($result, self::parseYearFields($query));

        if (preg_match('/\b(\d+)\s*k\s*(?:km|klm|kilomet)/ui', $lower, $m)) {
            $result['max_km'] = (int) $m[1] * 1000;
        } elseif (preg_match('/\b(?:deri|up to|max|under)\s*(?:ne|në|in)?\s*(\d+)\s*(?:k\s*)?(?:km|klm)/ui', $lower, $m)) {
            $km = (int) $m[1];
            $result['max_km'] = $km < 1000 ? $km * 1000 : $km;
        }

        if (preg_match('/\b(dizell|diesel|dizel|disel|disell|tdi)\b/ui', $lower)) {
            $result['fuel'] = 'diesel';
        } elseif (preg_match('/\b(benzin|petrol|benzinë|benzine|tfsi)\b/ui', $lower)) {
            $result['fuel'] = 'petrol';
        } elseif (preg_match('/\b(elektrik|electric|ev)\b/ui', $lower)) {
            $result['fuel'] = 'electric';
        }

        $engineLiters = AutomotiveEngineResolver::parseFromQuery($query);
        if ($engineLiters !== null) {
            $result['engine_liters'] = $engineLiters;
        }

        $colors = [];
        foreach (self::COLORS as $needle => $canonical) {
            if (str_contains($lower, $needle)) {
                $colors[] = $canonical;
            }
        }
        $colors = array_values(array_unique($colors));
        if (count($colors) === 1) {
            $result['color'] = $colors[0];
        } elseif (count($colors) > 1) {
            $result['color'] = 'multicolor';
            $result['colors'] = $colors;
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    public static function parseYearFields(string $query): array
    {
        if (preg_match('/\b(?:prej|nga|from)\s*(20\d{2}|19\d{2})\s*[-–—]\s*(20\d{2}|19\d{2})\b/ui', $query, $m)) {
            $min = min((int) $m[1], (int) $m[2]);
            $max = max((int) $m[1], (int) $m[2]);

            return ['year' => $min, 'year_min' => $min, 'year_max' => $max];
        }

        if (preg_match('/\b(20\d{2}|19\d{2})\s*[-–—]\s*(20\d{2}|19\d{2})\b/u', $query, $m)) {
            $min = min((int) $m[1], (int) $m[2]);
            $max = max((int) $m[1], (int) $m[2]);

            return ['year' => $min, 'year_min' => $min, 'year_max' => $max];
        }

        if (preg_match('/\b(20\d{2}|19\d{2})\s*(?:deri|to|until|bis)\s*(?:ne|në|in)?\s*(20\d{2}|19\d{2})\b/ui', $query, $m)) {
            $min = min((int) $m[1], (int) $m[2]);
            $max = max((int) $m[1], (int) $m[2]);

            return ['year' => $min, 'year_min' => $min, 'year_max' => $max];
        }

        if (preg_match('/\b(?:viti|vitit|year)\s*(20\d{2}|19\d{2})\s*(?:deri|to|until|bis)\s*(20\d{2}|19\d{2})\b/ui', $query, $m)) {
            $min = min((int) $m[1], (int) $m[2]);
            $max = max((int) $m[1], (int) $m[2]);

            return ['year' => $min, 'year_min' => $min, 'year_max' => $max];
        }

        if (preg_match('/\b(?:viti(?:i|t)?\s*(?:i\s*)?prodhimit|viti|vitit)\b.*?(20\d{2}|19\d{2}).*?(?:deri|deri\s+ne|deri\s+në|to|until|bis)\s*(?:ne|në|in)?\s*(20\d{2}|19\d{2})\b/ui', $query, $m)) {
            $min = min((int) $m[1], (int) $m[2]);
            $max = max((int) $m[1], (int) $m[2]);

            return ['year' => $min, 'year_min' => $min, 'year_max' => $max];
        }

        if (preg_match('/\b(20\d{2}|19\d{2})\b/', $query, $m)) {
            $year = (int) $m[1];

            return ['year' => $year, 'year_min' => $year, 'year_max' => $year];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public static function normalizeYearFields(array $parsed): array
    {
        if (! empty($parsed['year_min']) || ! empty($parsed['year_max'])) {
            $min = (int) ($parsed['year_min'] ?? $parsed['year'] ?? 0);
            $max = (int) ($parsed['year_max'] ?? $parsed['year'] ?? $min);
            if ($min > $max) {
                [$min, $max] = [$max, $min];
            }
            $parsed['year_min'] = $min;
            $parsed['year_max'] = $max;
            $parsed['year'] = $min;

            return $parsed;
        }

        if (! empty($parsed['year'])) {
            $year = (int) $parsed['year'];
            $parsed['year_min'] = $year;
            $parsed['year_max'] = $year;
        }

        return $parsed;
    }

    public static function normalizeFuel(string $fuel): string
    {
        $fuel = mb_strtolower(trim($fuel));

        return match (true) {
            in_array($fuel, ['benzin', 'benzinë', 'benzine', 'petrol', 'gasoline', 'tfsi', 'tsi'], true) => 'petrol',
            in_array($fuel, ['diesel', 'dizel', 'disel', 'tdi'], true) => 'diesel',
            in_array($fuel, ['elektrik', 'electric', 'ev'], true) => 'electric',
            in_array($fuel, ['hybrid', 'hibrid'], true) => 'hybrid',
            default => $fuel,
        };
    }
}
