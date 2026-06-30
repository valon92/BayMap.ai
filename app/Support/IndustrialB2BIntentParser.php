<?php

namespace App\Support;

/**
 * Industrial / B2B machinery intent — furniture production equipment, forklifts, etc.
 */
class IndustrialB2BIntentParser
{
    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function isIndustrialQuery(array $parsed, string $rawQuery = ''): bool
    {
        if (CategoryCatalog::normalize($parsed['category'] ?? '') === 'industrial_b2b') {
            return true;
        }

        $rawQuery = $rawQuery !== '' ? $rawQuery : (string) ($parsed['raw_query'] ?? '');
        $lower = mb_strtolower($rawQuery);

        if (preg_match('/\b(makineri|makinë|makine|machinery|machine|equipment|industrial)\b/ui', $lower)
            && preg_match('/\b(mobilje|mobileri|mobilja|furniture|woodworking|druri|panel|cnc|factory|fabrik|prodhim)\b/ui', $lower)) {
            return true;
        }

        if (preg_match('/\b(makineri|machinery)\b/ui', $lower)
            && preg_match('/\b(kinë|kine|china|chinese)\b/ui', $lower)) {
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
        if (! self::isIndustrialQuery($parsed, $rawQuery)) {
            return $parsed;
        }

        $parsed['category'] = 'industrial_b2b';
        $parsed['product_type'] = 'machinery';
        $parsed['item'] = 'machinery';

        $term = self::searchTerm($parsed, $rawQuery);
        if ($term !== '') {
            $parsed['search_query'] = $term;
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function searchTerm(array $parsed, string $rawQuery): string
    {
        $lower = mb_strtolower($rawQuery);
        $country = strtoupper((string) ($parsed['search_country_code'] ?? ''));

        if (preg_match('/\b(mobilje|mobileri|mobilja|furniture)\b/ui', $lower)) {
            return match ($country) {
                'CN' => 'furniture manufacturing machinery',
                default => 'furniture manufacturing machinery',
            };
        }

        if ($country === 'CN') {
            return 'industrial machinery';
        }

        return trim((string) ($parsed['search_query'] ?? $rawQuery)) ?: 'industrial machinery';
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function matchesListing(string $title, array $parsed): bool
    {
        if (CategoryCatalog::normalize($parsed['category'] ?? '') !== 'industrial_b2b') {
            return true;
        }

        $lower = mb_strtolower($title);
        $raw = mb_strtolower((string) ($parsed['raw_query'] ?? ''));

        $positive = [
            'machinery', 'machine', 'equipment', 'industrial', 'cnc', 'woodworking', 'furniture',
            'panel', 'saw', 'router', 'bander', 'press', 'lathe', 'makineri', 'fabrik',
        ];

        foreach ($positive as $term) {
            if (str_contains($lower, $term)) {
                return true;
            }
        }

        if (preg_match('/\b(mobilje|mobileri|furniture)\b/ui', $raw)) {
            return preg_match('/\b(tool|equipment|machine|machinery|production|manufacturing)\b/ui', $lower) === 1;
        }

        return strlen($title) >= 8;
    }
}
