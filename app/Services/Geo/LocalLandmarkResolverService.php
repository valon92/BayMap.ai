<?php

namespace App\Services\Geo;

use App\Support\SearchLocale;

/**
 * Resolves local landmarks (e.g. "te gjykata" in Ferizaj) into nearby streets for property search.
 */
class LocalLandmarkResolverService
{
    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $geo
     * @return array<string, mixed>
     */
    public function enrich(array $parsed, string $rawQuery, array $geo, ?string $locale = 'en'): array
    {
        if ($this->shouldSkipLocalLandmarks($parsed, $rawQuery)) {
            return $parsed;
        }

        $normalizedQuery = $this->normalizeText($rawQuery);
        $category = $parsed['category'] ?? 'marketplace';

        if (! $this->isPropertySearch($category, $normalizedQuery)) {
            return $parsed;
        }

        $cityKey = $this->detectCityKey($normalizedQuery, $geo, $parsed);
        if (! $cityKey) {
            return $parsed;
        }

        $landmark = $this->resolveLandmark($cityKey, $normalizedQuery);
        if ($landmark === null) {
            return $this->applyPropertyDefaults($parsed, $cityKey, $geo, $locale);
        }

        $isSq = SearchLocale::isAlbanian($locale);
        $parsed['category'] = 'real_estate';
        $parsed['city'] = $parsed['city'] ?? ucfirst($cityKey);
        $parsed['landmark'] = $landmark['key'];
        $parsed['landmark_label'] = $isSq ? $landmark['label_sq'] : $landmark['label_en'];
        $parsed['nearby_streets'] = $landmark['streets'];
        $parsed['neighborhoods'] = $landmark['neighborhoods'] ?? [];

        if (empty($parsed['property_type'])) {
            $parsed['property_type'] = $this->detectPropertyType($normalizedQuery);
        }

        $parsed['min_sqm'] = $parsed['min_sqm'] ?? $this->detectMinSqm($normalizedQuery);
        $parsed['search_query'] = $this->buildPropertySearchQuery($parsed, $cityKey, $landmark);
        $parsed['area_summary'] = $this->buildAreaSummary($parsed, $isSq);

        $keywords = array_merge(
            $parsed['keywords'] ?? [],
            [$parsed['city'], $parsed['landmark'], ...$landmark['streets']]
        );
        $parsed['keywords'] = array_values(array_unique(array_filter($keywords)));

        if (empty($parsed['description'])) {
            $parsed['description'] = $parsed['area_summary'];
        }

        return $parsed;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function locationContext(array $parsed): ?array
    {
        if (empty($parsed['landmark_label']) && empty($parsed['nearby_streets'])) {
            return null;
        }

        return [
            'city' => $parsed['city'] ?? null,
            'landmark' => $parsed['landmark_label'] ?? null,
            'streets' => $parsed['nearby_streets'] ?? [],
            'summary' => $parsed['area_summary'] ?? null,
        ];
    }

    private function isPropertySearch(string $category, string $query): bool
    {
        if ($category === 'real_estate') {
            return true;
        }

        $markers = [
            'banes', 'banese', 'banesa', 'apartament', 'apartment', 'shtëpi', 'shtepi',
            'patundsh', 'real estate', 'rent', 'qira', 'blerje banes', '120m', 'm2', 'm²',
            'gjykata', 'lagj', 'rruga', 'rr.',
        ];

        foreach ($markers as $marker) {
            if (str_contains($query, $this->normalizeText($marker))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function shouldSkipLocalLandmarks(array $parsed, string $rawQuery): bool
    {
        $targetCode = strtoupper((string) ($parsed['search_country_code'] ?? ''));
        if (! empty($parsed['search_target']) && $targetCode !== '' && $targetCode !== 'XK') {
            return true;
        }

        $normalized = $this->normalizeText($rawQuery);
        $foreignMarkers = [
            'london', 'londer', 'londra', 'paris', 'berlin', 'amsterdam', 'washington',
            'new york', 'zurich', 'bern', 'geneva', 'manchester', 'birmingham',
            'dubai', 'miami', 'romë', 'rome', 'milano', 'milan',
        ];

        foreach ($foreignMarkers as $marker) {
            if (str_contains($normalized, $this->normalizeText($marker))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function detectCityKey(string $query, array $geo, array $parsed = []): ?string
    {
        if (str_contains($query, 'ferizaj') || str_contains($query, 'ferizaji')) {
            return 'ferizaj';
        }

        if (str_contains($query, 'prishtin') || str_contains($query, 'pristina')) {
            return 'prishtina';
        }

        $countryCode = strtoupper((string) ($parsed['search_country_code'] ?? $geo['country_code'] ?? ''));
        if ($countryCode !== '' && $countryCode !== 'XK') {
            return null;
        }

        $city = $this->normalizeText($geo['city'] ?? '');
        if (str_contains($city, 'ferizaj')) {
            return 'ferizaj';
        }

        return null;
    }

    /**
     * @return array{key: string, label_sq: string, label_en: string, streets: array<int, string>, neighborhoods: array<int, string>}|null
     */
    private function resolveLandmark(string $cityKey, string $query): ?array
    {
        $cityLandmarks = config("local_landmarks.{$cityKey}", []);
        if (! is_array($cityLandmarks)) {
            return null;
        }

        foreach ($cityLandmarks as $key => $data) {
            foreach ($data['aliases'] ?? [] as $alias) {
                if (str_contains($query, $this->normalizeText($alias))) {
                    return [
                        'key' => $key,
                        'label_sq' => $data['label_sq'] ?? $key,
                        'label_en' => $data['label_en'] ?? $key,
                        'streets' => $data['streets'] ?? [],
                        'neighborhoods' => $data['neighborhoods'] ?? [],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $geo
     * @return array<string, mixed>
     */
    private function applyPropertyDefaults(array $parsed, string $cityKey, array $geo, ?string $locale): array
    {
        $parsed['category'] = 'real_estate';
        $parsed['city'] = $parsed['city'] ?? ucfirst($cityKey);
        $parsed['min_sqm'] = $parsed['min_sqm'] ?? $this->detectMinSqm($this->normalizeText($parsed['raw_query'] ?? ''));
        $parsed['property_type'] = $parsed['property_type'] ?? 'apartment';

        return $parsed;
    }

    private function detectPropertyType(string $query): string
    {
        if (str_contains($query, 'banes') || str_contains($query, 'apartament') || str_contains($query, 'apartment')) {
            return 'apartment';
        }
        if (str_contains($query, 'shtepi') || str_contains($query, 'shtëpi') || str_contains($query, 'house')) {
            return 'house';
        }

        return 'apartment';
    }

    private function detectMinSqm(string $query): ?int
    {
        if (preg_match('/(\d{2,4})\s*(m2|m²|sqm|metra|meter)/i', $query, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/(\d{2,4})\s*m\b/i', $query, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * @param  array{streets: array<int, string>}  $landmark
     */
    private function buildPropertySearchQuery(array $parsed, string $cityKey, array $landmark): string
    {
        $parts = array_filter([
            $parsed['property_type'] ?? 'apartment',
            'banes',
            $parsed['min_sqm'] ? ($parsed['min_sqm'].'m2') : null,
            ucfirst($cityKey),
            $parsed['landmark_label'] ?? null,
            implode(' ', array_slice($landmark['streets'], 0, 4)),
            $parsed['listing_type'] ?? null,
        ]);

        return trim(implode(' ', $parts));
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function buildAreaSummary(array $parsed, bool $isSq): string
    {
        $streets = $parsed['nearby_streets'] ?? [];
        $streetList = implode(', ', array_map(
            fn ($s) => ($isSq ? 'Rr. ' : '').$s,
            array_slice($streets, 0, 5)
        ));

        if ($isSq) {
            $sqm = ! empty($parsed['min_sqm']) ? " (~{$parsed['min_sqm']} m²)" : '';

            return sprintf(
                'Kërkim banesash%s afër %s. Rrugët e zonës: %s.',
                $sqm,
                $parsed['landmark_label'] ?? 'qendrës',
                $streetList
            );
        }

        $sqm = ! empty($parsed['min_sqm']) ? " (~{$parsed['min_sqm']} sqm)" : '';

        return sprintf(
            'Apartments%s near %s. Area streets: %s.',
            $sqm,
            $parsed['landmark_label'] ?? 'city center',
            $streetList
        );
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower($text);
        $replacements = ['ë' => 'e', 'ç' => 'c', 'â' => 'a', 'î' => 'i', 'û' => 'u'];
        $text = strtr($text, $replacements);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }
}
