<?php

namespace App\Support;

/**
 * Normalizes automotive make/model for marketplace catalog URLs and listing filters.
 */
class AutomotiveModelResolver
{
    /** @var array<string, string> */
    private const BRAND_SLUGS = [
        'vw' => 'volkswagen',
        'volkswagen' => 'volkswagen',
        'mercedes' => 'mercedes-benz',
        'mercedes benz' => 'mercedes-benz',
    ];

    /**
     * Live marketplace search URLs already encode make/model (e.g. /lst/bmw/5).
     *
     * @param  array<string, mixed>  $parsed
     */
    public static function shouldTrustPlatformScope(string $storeKey, array $parsed): bool
    {
        if (! CategoryCatalog::isAutomotive($parsed['category'] ?? '')) {
            return false;
        }

        if (empty($parsed['brand']) || empty($parsed['model'])) {
            return false;
        }

        $store = strtolower(trim($storeKey));

        return str_contains($store, 'autoscout24')
            || $store === 'kleinanzeigen';
    }

    /**
     * Catalog path slug for live scrapers (e.g. golf-7 → golf).
     */
    public static function catalogSlug(string $brand, string $model): string
    {
        $brandSlug = self::BRAND_SLUGS[mb_strtolower(trim($brand))] ?? mb_strtolower(trim($brand));
        $brandSlug = str_replace(' ', '-', $brandSlug);
        $baseModel = self::baseModelName($model);
        $modelSlug = str_replace(' ', '-', $baseModel);

        return $modelSlug !== '' ? $brandSlug.'/'.$modelSlug : $brandSlug;
    }

    /**
     * Split make/model path for URL templates: {make}/{model}.
     *
     * @return array{0: string, 1: string}
     */
    public static function makeModelSlugs(string $brand, string $model): array
    {
        $make = self::BRAND_SLUGS[mb_strtolower(trim($brand))] ?? mb_strtolower(trim($brand));
        $make = str_replace(' ', '-', $make);
        $baseModel = self::baseModelName($model);
        $modelSlug = str_replace(' ', '-', $baseModel);

        return [$make, $modelSlug !== '' ? $modelSlug : 'all'];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function matchesListing(string $title, ?string $itemModel, string $queryModel, array $parsed = [], bool $allowUnknownYear = false): bool
    {
        $queryModel = mb_strtolower(trim($queryModel));
        if ($queryModel === '') {
            return true;
        }

        $titleLower = mb_strtolower($title);
        $itemModelLower = mb_strtolower((string) ($itemModel ?? ''));
        $base = self::baseModelName($queryModel);
        $generation = self::generationFromModel($queryModel);

        if ($base !== '') {
            $brand = mb_strtolower((string) ($parsed['brand'] ?? ''));
            $baseMatch = self::titleMatchesBaseModel($titleLower, $itemModelLower, $base, $brand);
            if (! $baseMatch) {
                return false;
            }
        }

        $year = isset($parsed['year']) ? (int) $parsed['year'] : null;
        if ($year === null && preg_match('/\b(19|20)\d{2}\b/', $title, $yearMatch)) {
            $year = (int) $yearMatch[0];
        }

        if (! self::matchesYearRange($year, $title, $parsed, $allowUnknownYear)) {
            return false;
        }

        if ($generation === null) {
            return true;
        }

        if (self::mentionsConflictingGeneration($titleLower, $generation)) {
            return false;
        }

        if (self::mentionsGeneration($titleLower, $generation)) {
            return true;
        }

        if ($year !== null) {
            $range = self::generationYearRange($base, $generation);

            return $range !== null && $year >= $range[0] && $year <= $range[1];
        }

        if ($itemModelLower !== '' && $base !== '') {
            if (preg_match('/^\d{1,2}$/', $base)) {
                if (self::titleMatchesNumericSeries($itemModelLower, $base, mb_strtolower((string) ($parsed['brand'] ?? '')))) {
                    return true;
                }
            } elseif (str_contains($itemModelLower, $base)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function matchesYearRange(?int $year, string $title, array $parsed, bool $allowUnknownYear = false): bool
    {
        $minYear = ! empty($parsed['year_min']) ? (int) $parsed['year_min'] : null;
        $maxYear = ! empty($parsed['year_max']) ? (int) $parsed['year_max'] : null;

        if ($minYear === null && $maxYear === null) {
            return true;
        }

        $min = $minYear ?? $maxYear;
        $max = $maxYear ?? $minYear;

        if ($year !== null) {
            return $year >= $min && $year <= $max;
        }

        if ($allowUnknownYear) {
            if (preg_match_all('/\b(19|20)\d{2}\b/', $title, $years)) {
                foreach ($years[1] as $foundYear) {
                    $found = (int) $foundYear;
                    if ($found >= $min && $found <= $max) {
                        return true;
                    }
                }

                return false;
            }

            return true;
        }

        if ($min === $max && preg_match('/\b'.$min.'\b/', $title) === 1) {
            return true;
        }

        return false;
    }

    public static function baseModelName(string $model): string
    {
        $model = mb_strtolower(trim($model));
        $model = preg_replace('/\s+(?:sportback|variant|avant|coupe|cabriolet)\b.*/iu', '', $model) ?? $model;
        $model = preg_replace('/\s+(?:mk\s*)?(?:\d{1,2}|i{1,3}|iv|v|vi{1,3}|vii{0,3}|ix|x)\b\s*$/iu', '', $model) ?? $model;
        $model = trim(preg_replace('/\s+/', ' ', $model) ?? $model);

        return $model;
    }

    public static function generationFromModel(string $model): ?int
    {
        $model = mb_strtolower(trim($model));
        // Bare digits are model series (BMW 5, VW Golf without generation) — not MK/generation.
        if (preg_match('/^\d{1,2}$/', $model)) {
            return null;
        }

        if (preg_match('/\b(?:mk\s*)?(\d{1,2})\s*$/i', $model, $match)) {
            return (int) $match[1];
        }

        if (preg_match('/\b(mk\s*)?(i{1,3}|iv|v|vi{1,3}|vii{0,3}|ix|x)\s*$/iu', $model, $match)) {
            return self::romanToInt($match[2]);
        }

        return null;
    }

    private static function mentionsGeneration(string $titleLower, int $generation): bool
    {
        $roman = self::intToRoman($generation);
        $patterns = [
            '/\bmk\s*'.$generation.'\b(?!\.\d)/i',
            '/\bgolf\s*(?:mk\s*)?'.$generation.'\b(?!\.\d)/i',
            '/\b'.$generation.'\.?\s+golf\b/i',
        ];

        if (strlen($roman) > 1) {
            $patterns[] = '/\bgolf\s*'.$roman.'\b/i';
            $patterns[] = '/\b'.$roman.'\s+golf\b/i';
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $titleLower)) {
                return true;
            }
        }

        return false;
    }

    private static function mentionsConflictingGeneration(string $titleLower, int $wanted): bool
    {
        for ($gen = 1; $gen <= 9; $gen++) {
            if ($gen === $wanted) {
                continue;
            }
            if (self::mentionsGeneration($titleLower, $gen)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private static function generationYearRange(string $baseModel, int $generation): ?array
    {
        $baseModel = mb_strtolower($baseModel);

        return match (true) {
            $baseModel === 'golf' && $generation === 7 => [2012, 2019],
            $baseModel === 'golf' && $generation === 8 => [2019, 2025],
            $baseModel === 'golf' && $generation === 6 => [2008, 2013],
            default => null,
        };
    }

    private static function romanToInt(string $roman): ?int
    {
        $roman = mb_strtolower($roman);

        return match ($roman) {
            'i' => 1,
            'ii' => 2,
            'iii' => 3,
            'iv' => 4,
            'v' => 5,
            'vi' => 6,
            'vii' => 7,
            'viii' => 8,
            'ix' => 9,
            'x' => 10,
            default => is_numeric($roman) ? (int) $roman : null,
        };
    }

    private static function intToRoman(int $number): string
    {
        return match ($number) {
            6 => 'vi',
            7 => 'vii',
            8 => 'viii',
            default => (string) $number,
        };
    }

    private static function titleMatchesBaseModel(
        string $titleLower,
        string $itemModelLower,
        string $base,
        string $brand,
    ): bool {
        if ($itemModelLower !== '') {
            if (preg_match('/^\d{1,2}$/', $base)) {
                if (self::titleMatchesNumericSeries($itemModelLower, $base, $brand)) {
                    return true;
                }
            } elseif (str_contains($itemModelLower, $base) || str_contains($itemModelLower, str_replace(' ', '', $base))) {
                return true;
            }
        }

        if (preg_match('/^\d{1,2}$/', $base)) {
            return self::titleMatchesNumericSeries($titleLower, $base, $brand);
        }

        $basePattern = '/\b'.preg_quote(str_replace('-', ' ', $base), '/').'\b/u';

        return preg_match($basePattern, $titleLower) === 1;
    }

    private static function titleMatchesNumericSeries(string $titleLower, string $series, string $brand): bool
    {
        if (preg_match('/\b[mxzi]\s*'.preg_quote($series, '/').'\b/u', $titleLower) === 1) {
            return false;
        }

        if (preg_match('/\b'.preg_quote($series, '/').'\s*series\b/u', $titleLower) === 1) {
            return true;
        }

        if (preg_match('/\b'.preg_quote($series, '/').'er\b/u', $titleLower) === 1) {
            return true;
        }

        if (preg_match('/(?<![a-z])'.preg_quote($series, '/').'\d{2}[a-z]{0,3}\b/u', $titleLower) === 1) {
            return true;
        }

        if (($brand === 'bmw' || str_contains($titleLower, 'bmw'))
            && preg_match('/\bbmw\s+'.preg_quote($series, '/').'(?:\s+series|\s|$)/u', $titleLower) === 1) {
            return true;
        }

        return false;
    }
}
