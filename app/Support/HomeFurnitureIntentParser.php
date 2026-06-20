<?php

namespace App\Support;

/**
 * Parses kitchen / furniture dimensions and builds marketplace search terms.
 */
class HomeFurnitureIntentParser
{
    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public static function merge(array $parsed, string $rawQuery): array
    {
        if (CategoryCatalog::normalize($parsed['category'] ?? '') !== 'home_furniture') {
            return $parsed;
        }

        $lower = mb_strtolower($rawQuery);

        if (preg_match('/\b(kuzhina|kuzhinë|kuzhine|kitchen|küche|kuche|einbauküche|einbaukuche)\b/ui', $rawQuery)) {
            $parsed['room'] = 'kitchen';
            $parsed['item'] = 'kitchen';
            $parsed['product_type'] = 'kitchen';
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*m\s*(?:deri|to|until|bis|–|—|-)\s*(\d+(?:[.,]\d+)?)\s*m?\b/ui', $rawQuery, $match)) {
            $parsed['min_length_m'] = self::parseMeter($match[1]);
            $parsed['max_length_m'] = self::parseMeter($match[2]);
            unset($parsed['min_sqm']);
        } elseif (preg_match('/\b(\d+(?:[.,]\d+)?)\s*m\b/ui', $rawQuery, $match) && self::isKitchenSearch($parsed)) {
            $length = self::parseMeter($match[1]);
            $parsed['min_length_m'] = $length;
            $parsed['max_length_m'] = $length;
            unset($parsed['min_sqm']);
        }

        if (self::isKitchenSearch($parsed) && isset($parsed['min_sqm']) && (int) $parsed['min_sqm'] < 20) {
            unset($parsed['min_sqm']);
        }

        $term = self::searchTerm($parsed, ['country' => $parsed['search_country_code'] ?? '']);
        if ($term !== '') {
            $parsed['search_query'] = $term;
        }

        return $parsed;
    }

    /**
     * Hardware / DIY stores are poor sources for fitted kitchen searches.
     *
     * @param  array<string, mixed>  $parsed
     */
    public static function skipPlatform(string $platformKey, array $parsed): bool
    {
        if (! self::isKitchenSearch($parsed)) {
            return false;
        }

        $key = strtolower($platformKey);

        foreach (['obi_', 'hornbach_', 'leroy_merlin_', 'home_depot_', 'lowes_', 'bauhaus_'] as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function searchTerm(array $parsed, array $platform = []): string
    {
        if (self::isKitchenSearch($parsed)) {
            $country = strtoupper((string) ($platform['country'] ?? $parsed['search_country_code'] ?? ''));
            $term = match ($country) {
                'FR' => 'cuisine équipée',
                'IT' => 'cucina componibile',
                'CH', 'AT', 'DE' => 'Einbauküche',
                default => 'kitchen unit',
            };

            if (! empty($parsed['min_length_m'])) {
                $lengthCm = (int) round((float) $parsed['min_length_m'] * 100);
                $term .= ' '.$lengthCm.' cm';
            }

            return $term;
        }

        $raw = trim((string) ($parsed['raw_query'] ?? ''));
        $raw = preg_replace('/\b(?:deri|to|until|bis)\s*(?:ne|në|in)?\s*\d+(?:[.,]\d+)?\s*m\b/ui', '', $raw) ?? $raw;
        $raw = preg_replace('/\b\d+(?:[.,]\d+)?\s*m\b/ui', '', $raw) ?? $raw;
        $raw = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);

        return $raw;
    }

    /**
     * Extra SerpAPI queries to widen kitchen coverage beyond a single keyword.
     *
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    public static function serpSearchQueries(array $parsed): array
    {
        if (! self::isKitchenSearch($parsed)) {
            $term = self::searchTerm($parsed, ['country' => $parsed['search_country_code'] ?? '']);

            return $term !== '' ? [$term] : [];
        }

        $country = strtoupper((string) ($parsed['search_country_code'] ?? 'DE'));
        $minCm = ! empty($parsed['min_length_m']) ? (int) round((float) $parsed['min_length_m'] * 100) : null;
        $maxCm = ! empty($parsed['max_length_m']) ? (int) round((float) $parsed['max_length_m'] * 100) : null;

        $queries = [];
        if ($minCm !== null) {
            $queries[] = match ($country) {
                'FR' => "cuisine équipée {$minCm} cm",
                'IT' => "cucina componibile {$minCm} cm",
                default => "Einbauküche {$minCm} cm",
            };
            $queries[] = match ($country) {
                'FR' => "cuisine {$minCm} cm",
                'IT' => "cucina {$minCm} cm",
                default => "Küchenzeile {$minCm} cm",
            };
        }

        if ($maxCm !== null && $maxCm !== $minCm) {
            $queries[] = match ($country) {
                'FR' => "cuisine équipée {$maxCm} cm",
                'IT' => "cucina componibile {$maxCm} cm",
                default => "Einbauküche {$maxCm} cm",
            };
        }

        if ($queries === []) {
            $queries[] = self::searchTerm($parsed, ['country' => $country]);
        }

        return array_values(array_unique(array_filter($queries)));
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function isKitchenSearch(array $parsed): bool
    {
        foreach (['room', 'item', 'product_type'] as $key) {
            if (mb_strtolower((string) ($parsed[$key] ?? '')) === 'kitchen') {
                return true;
            }
        }

        return preg_match('/\b(kuzhina|kuzhinë|kuzhine|kitchen|küche|kuche)\b/ui', (string) ($parsed['raw_query'] ?? '')) === 1;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function matchesListing(string $title, array $parsed): bool
    {
        if (! self::isKitchenSearch($parsed)) {
            return true;
        }

        $lower = mb_strtolower($title);

        foreach (self::excludedTitleTerms() as $term) {
            if (str_contains($lower, $term)) {
                return false;
            }
        }

        foreach (self::kitchenTitleTerms() as $term) {
            if (str_contains($lower, $term)) {
                return true;
            }
        }

        $min = isset($parsed['min_length_m']) ? (float) $parsed['min_length_m'] : null;
        $max = isset($parsed['max_length_m']) ? (float) $parsed['max_length_m'] : null;

        if ($min !== null && $max !== null) {
            if (! self::titleHasLengthInRange($title, $min, $max)) {
                return false;
            }

            foreach (self::kitchenFurnitureTerms() as $term) {
                if (str_contains($lower, $term)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private static function kitchenFurnitureTerms(): array
    {
        return [
            'einbauküche', 'einbaukuche', 'küchenzeile', 'kuchenzeile', 'küchenblock', 'kuchenblock',
            'küchenmöbel', 'kuchenmobel', 'kücheneinheit', 'kucheneinheit', 'küchenzeilen',
            'kitchen unit', 'kitchen cabinet', 'modular kitchen',
        ];
    }

    public static function productTypeFromTitle(string $title): ?string
    {
        $lower = mb_strtolower($title);

        if (preg_match('/\b(küche|kuche|kitchen|cucina|cuisine|küchenzeile|einbauküche)\b/ui', $lower)) {
            return 'kitchen';
        }

        return 'furniture';
    }

    private static function parseMeter(string $value): float
    {
        $normalized = str_replace(',', '.', trim($value));

        return (float) $normalized;
    }

    private static function titleHasLengthInRange(string $title, float $min, float $max): bool
    {
        $tolerance = 0.35;
        $minAllowed = max(0.5, $min - $tolerance);
        $maxAllowed = $max + $tolerance;

        if (preg_match_all('/(\d+(?:[.,]\d+)?)\s*(?:m|meter|metre)\b/ui', $title, $matches)) {
            foreach ($matches[1] as $raw) {
                $meters = self::parseMeter($raw);
                if ($meters >= $minAllowed && $meters <= $maxAllowed) {
                    return true;
                }
            }
        }

        if (preg_match_all('/(\d+(?:[.,]\d+)?)\s*cm\b/ui', $title, $matches)) {
            foreach ($matches[1] as $raw) {
                $meters = self::parseMeter($raw) / 100;
                if ($meters >= $minAllowed && $meters <= $maxAllowed) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private static function kitchenTitleTerms(): array
    {
        return [
            'küche', 'kuche', 'küchen', 'einbauküche', 'küchenzeile', 'küchenmöbel', 'küchenblock',
            'kitchen', 'cucina', 'cuisine', 'kuzhina',
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function excludedTitleTerms(): array
    {
        return [
            'schlauch', 'abdeckplane', 'vogelschutz', 'verlängerung', 'verlangerung', 'kabel',
            'leiter', 'rasendünger', 'rasendunger', 'teichfolie', 'carport', 'pergola', 'klimaanlage',
            'saugschlauch', 'steckdose', 'türdichtung', 'turdichtung', 'gerätehaus', 'geratehaus',
            'baufolie', 'schutzkontakt', 'split-klima', 'dünger', 'dunger', 'schutznetz', 'markise',
            'spiralschlauch', 'zulauf', 'ablauf', 'vinylboden', 'teich-', 'netz premium',
            'fliesenlack', 'silikon', 'unterbauleuchte', 'leuchte', 'pvc ', 'bad & küche', 'bad & kuche',
        ];
    }
}
