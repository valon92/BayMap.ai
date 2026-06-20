<?php

namespace App\Support;

/**
 * Parses AutoScout24 listing JSON (search + detail) into BuyMap automotive fields.
 */
class AutoScout24ListingParser
{
    /**
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    public static function upgradeImageUrls(array $urls): array
    {
        $out = [];

        foreach ($urls as $url) {
            if (! is_string($url) || trim($url) === '') {
                continue;
            }

            $highRes = preg_replace('#/\d+x\d+\.(webp|jpg|jpeg)(?:\?.*)?$#i', '/720x540.webp', trim($url));
            $out[] = is_string($highRes) ? $highRes : trim($url);
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  array<string, mixed>  $listing
     * @return array<int, string>
     */
    public static function collectImages(array $listing): array
    {
        $urls = [];

        foreach (['images', 'ocsImagesA'] as $key) {
            $chunk = $listing[$key] ?? [];
            if (! is_array($chunk)) {
                continue;
            }
            foreach ($chunk as $url) {
                if (is_string($url) && $url !== '') {
                    $urls[] = $url;
                }
            }
        }

        return self::upgradeImageUrls($urls);
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     * @return array<string, mixed>
     */
    public static function parseVehicleDetails(array $details): array
    {
        $out = [];

        foreach ($details as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = mb_strtolower((string) ($row['ariaLabel'] ?? $row['iconName'] ?? ''));
            $data = trim((string) ($row['data'] ?? ''));
            if ($data === '' || $data === '-') {
                continue;
            }

            if (str_contains($label, 'leistung') || str_contains($label, 'power') || str_contains($label, 'speedometer') || str_contains($label, 'potenza')) {
                if (preg_match('/(\d+)\s*kW\s*\((\d+)\s*PS\)/u', $data, $match)) {
                    $out['power_kw'] = (int) $match[1];
                    $out['power_hp'] = (int) $match[2];
                } elseif (preg_match('/(\d+)\s*PS/u', $data, $match)) {
                    $out['power_hp'] = (int) $match[1];
                } elseif (preg_match('/(\d+)\s*kW/u', $data, $match)) {
                    $out['power_kw'] = (int) $match[1];
                }
            } elseif (str_contains($label, 'kilometer') || str_contains($label, 'mileage') || str_contains($label, 'chilometr')) {
                $out['mileage_label'] = $data;
                if (preg_match('/([\d\.]+)/', $data, $match)) {
                    $out['mileage'] = (int) str_replace('.', '', $match[1]);
                }
            } elseif (str_contains($label, 'erstzulassung') || str_contains($label, 'registration') || str_contains($label, 'immatricolazione') || str_contains($label, 'immatriculation')) {
                $out['first_registration'] = $data;
                if (preg_match('/(\d{4})/', $data, $match)) {
                    $out['year'] = (int) $match[1];
                }
            } elseif (str_contains($label, 'verbrauch') || str_contains($label, 'consumption') || str_contains($label, 'consommation') || str_contains($label, 'consumo')) {
                $out['consumption'] = $data;
            } elseif (str_contains($label, 'kraftstoff') || str_contains($label, 'fuel') || str_contains($label, 'carburante') || str_contains($label, 'carburant')) {
                $out['fuel'] = $data;
            } elseif (str_contains($label, 'getriebe') || str_contains($label, 'transmission') || str_contains($label, 'cambio') || str_contains($label, 'boîte') || str_contains($label, 'boite')) {
                $out['transmission'] = $data;
            } elseif (str_contains($label, 'reichweite') || str_contains($label, 'range') || str_contains($label, 'autonomia')) {
                if (preg_match('/(\d+)/', $data, $match)) {
                    $out['electric_range_km'] = (int) $match[1];
                }
            }
        }

        return $out;
    }

    public static function normalizeSellerType(?string $sellerType): ?string
    {
        if ($sellerType === null || $sellerType === '') {
            return null;
        }

        $lower = mb_strtolower($sellerType);

        return match (true) {
            str_contains($lower, 'dealer') || str_contains($lower, 'händler') || str_contains($lower, 'concession') || str_contains($lower, 'profession') => 'dealer',
            str_contains($lower, 'private') || str_contains($lower, 'privat') || str_contains($lower, 'particul') => 'private',
            default => $sellerType,
        };
    }

    /**
     * @param  array<string, mixed>  $specs
     * @return array<int, array{label: string, value: string}>
     */
    public static function buildSpecChips(array $specs): array
    {
        $chips = [];
        $map = [
            'year' => 'year',
            'mileage' => 'mileage',
            'fuel' => 'fuel',
            'transmission' => 'transmission',
            'power_hp' => 'power',
            'electric_range_km' => 'range',
            'body_type' => 'body',
            'seller_type' => 'seller',
            'first_registration' => 'registration',
            'consumption' => 'consumption',
        ];

        foreach ($map as $key => $type) {
            if (empty($specs[$key])) {
                continue;
            }

            $value = $specs[$key];
            if ($key === 'mileage' && is_numeric($value)) {
                $value = number_format((int) $value, 0, ',', '.').' km';
            } elseif ($key === 'power_hp') {
                $value = $value.' PS';
                if (! empty($specs['power_kw'])) {
                    $value = $specs['power_kw'].' kW ('.$value.')';
                }
            } elseif ($key === 'electric_range_km') {
                $value = $value.' km';
            } elseif ($key === 'seller_type') {
                $value = $value === 'dealer' ? 'Dealer' : ($value === 'private' ? 'Private' : (string) $value);
            } else {
                $value = (string) $value;
            }

            $chips[] = ['label' => $type, 'value' => $value];
        }

        return $chips;
    }
}
