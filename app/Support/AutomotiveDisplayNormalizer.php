<?php

namespace App\Support;

/**
 * Normalizes automotive listing labels for consistent card display across countries.
 */
class AutomotiveDisplayNormalizer
{
    /**
     * @param  array<string, mixed>  $platform
     */
    public static function platformCountryLabel(array $platform): string
    {
        $location = trim((string) ($platform['location'] ?? ''));
        if ($location !== '') {
            return $location;
        }

        $code = strtoupper(trim((string) ($platform['country'] ?? '')));

        return SearchCountryResolver::countryNameForCode($code)
            ?? match ($code) {
                'DE' => 'Germany',
                'CH' => 'Switzerland',
                'IT' => 'Italy',
                'FR' => 'France',
                'AT' => 'Austria',
                'NL' => 'Netherlands',
                default => 'Germany',
            };
    }

    public static function normalizeFuelDisplay(?string $fuel): ?string
    {
        if ($fuel === null || trim($fuel) === '') {
            return null;
        }

        $lower = mb_strtolower(trim($fuel));

        return match (true) {
            str_contains($lower, 'elektro') && (str_contains($lower, 'benzin') || str_contains($lower, 'essence') || str_contains($lower, 'benzina') || str_contains($lower, 'petrol')) => 'Elektro/Benzin',
            str_contains($lower, 'mild-hybrid') && str_contains($lower, 'diesel') => 'Diesel',
            str_contains($lower, 'elettrica') && str_contains($lower, 'diesel') => 'Elektro/Diesel',
            str_contains($lower, 'hybrid') || str_contains($lower, 'ibrid') => 'Hybrid',
            in_array($lower, ['benzin', 'benzina', 'essence', 'petrol', 'gasoline', 'tfsi', 'tsi'], true) => 'Benzin',
            in_array($lower, ['diesel', 'dizel'], true) => 'Diesel',
            in_array($lower, ['elektro', 'electric', 'elettrica', 'elettrico', 'ev'], true) => 'Elektro',
            default => trim($fuel),
        };
    }

    public static function normalizeTransmissionDisplay(?string $transmission): ?string
    {
        if ($transmission === null || trim($transmission) === '') {
            return null;
        }

        $lower = mb_strtolower(trim($transmission));

        return match (true) {
            str_contains($lower, 'automatik') || str_contains($lower, 'automatic') || str_contains($lower, 'automatico') || str_contains($lower, 'automatique') || str_contains($lower, 'semiautomatic') => 'Automatik',
            str_contains($lower, 'manuell') || str_contains($lower, 'manual') || str_contains($lower, 'manuale') || str_contains($lower, 'manuelle') || str_contains($lower, 'schalt') => 'Schaltgetriebe',
            default => trim($transmission),
        };
    }

    public static function fixLocationCountrySuffix(string $location, string $countryLabel): string
    {
        $location = trim($location);
        $countryLabel = trim($countryLabel);

        if ($location === '') {
            return $countryLabel;
        }

        if ($countryLabel === '') {
            return $location;
        }

        $knownCountries = [
            'Germany', 'Switzerland', 'Italy', 'France', 'Austria', 'Netherlands', 'Belgium', 'Spain',
        ];

        foreach ($knownCountries as $name) {
            if (strcasecmp($name, $countryLabel) === 0) {
                continue;
            }

            if (preg_match('/,\s*'.preg_quote($name, '/').'$/iu', $location)) {
                return preg_replace('/,\s*'.preg_quote($name, '/').'$/iu', ', '.$countryLabel, $location);
            }
        }

        if (! str_contains(mb_strtolower($location), mb_strtolower($countryLabel))) {
            if (preg_match('/^\d{4}\s/u', $location) || ! str_contains($location, ',')) {
                return $location.', '.$countryLabel;
            }
        }

        return $location;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function normalizeListingFields(array $item, string $countryLabel): array
    {
        if (! empty($item['fuel'])) {
            $item['fuel'] = self::normalizeFuelDisplay((string) $item['fuel']);
        }

        if (! empty($item['transmission'])) {
            $item['transmission'] = self::normalizeTransmissionDisplay((string) $item['transmission']);
        }

        if (! empty($item['location'])) {
            $item['location'] = self::fixLocationCountrySuffix((string) $item['location'], $countryLabel);
        }

        if (is_array($item['specs'] ?? null) && $item['specs'] !== []) {
            $item['specs'] = self::normalizeSpecChips($item['specs']);
        }

        return $item;
    }

    /**
     * @param  array<int, array<string, mixed>|string>  $chips
     * @return array<int, array{label: string, value: string}>
     */
    public static function normalizeSpecChips(array $chips): array
    {
        $out = [];

        foreach ($chips as $chip) {
            if (is_string($chip)) {
                $out[] = ['label' => '', 'value' => $chip];

                continue;
            }

            if (! is_array($chip)) {
                continue;
            }

            $label = (string) ($chip['label'] ?? '');
            $value = (string) ($chip['value'] ?? '');

            if ($label === 'fuel' || $label === 'kraftstoff') {
                $value = self::normalizeFuelDisplay($value) ?? $value;
            } elseif ($label === 'transmission' || $label === 'getriebe') {
                $value = self::normalizeTransmissionDisplay($value) ?? $value;
            }

            $out[] = ['label' => $label, 'value' => $value];
        }

        return $out;
    }
}
