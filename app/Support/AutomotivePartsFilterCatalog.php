<?php

namespace App\Support;

/**
 * Auto parts filter options for the results UI — top-level types, grouped components, curated brands.
 */
class AutomotivePartsFilterCatalog
{
    /** @var array<int, string> */
    public const TOP_TYPES = ['engine', 'auto_part', 'machinery', 'tire', 'accessory'];

    /** @var array<string, array<int, string>> */
    private const COMPONENTS_BY_TYPE = [
        'engine' => [
            'engine', 'engine_block', 'cylinder_head', 'piston', 'piston_ring', 'connecting_rod',
            'crankshaft', 'camshaft', 'valve', 'valve_spring', 'timing_chain', 'drive_belt',
            'turbo', 'intercooler', 'intake_manifold', 'exhaust_manifold', 'oil_pump', 'water_pump',
            'oil_filter', 'air_filter', 'fuel_filter', 'fuel_pump', 'ignition_coil', 'spark_plug',
            'injector', 'alternator', 'starter', 'ecu', 'engine_mount', 'engine_cover',
        ],
        'auto_part' => [
            'brake_disc', 'brake_pad', 'clutch', 'radiator', 'radiator_fan', 'thermostat',
            'coolant_reservoir', 'steering_wheel', 'steering_column', 'control_arm', 'shock_absorber',
            'coil_spring', 'wheel_hub', 'drive_shaft', 'gearbox', 'catalytic_converter', 'dpf_filter',
            'exhaust_manifold', 'battery', 'maf_sensor', 'map_sensor', 'lambda_sensor', 'filter',
        ],
        'machinery' => ['machinery'],
        'tire' => ['tire'],
        'accessory' => ['accessory', 'dashcam', 'backup_camera', 'parking_sensor', 'radio', 'infotainment'],
    ];

    /** @var array<int, string> */
    private const BRANDS = [
        'volkswagen', 'audi', 'bmw', 'mercedes', 'opel', 'ford', 'toyota', 'honda',
        'renault', 'peugeot', 'citroen', 'fiat', 'skoda', 'seat', 'porsche', 'volvo',
        'nissan', 'mazda', 'hyundai', 'kia', 'mini', 'smart', 'dacia', 'jeep',
    ];

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    public static function topTypes(array $parsed = []): array
    {
        return self::TOP_TYPES;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    public static function componentOptions(array $parsed): array
    {
        $type = self::normalizeTopType((string) ($parsed['product_type'] ?? ''));
        $component = trim((string) ($parsed['item'] ?? ''));

        $options = self::COMPONENTS_BY_TYPE[$type] ?? self::COMPONENTS_BY_TYPE['auto_part'];

        if ($component !== '' && ! in_array($component, $options, true)) {
            array_unshift($options, $component);
        }

        return array_values(array_unique($options));
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    public static function brandOptions(array $parsed): array
    {
        $parsedBrand = self::slugifyBrand((string) ($parsed['brand'] ?? ''));
        $brands = self::BRANDS;

        if ($parsedBrand !== '' && ! in_array($parsedBrand, $brands, true)) {
            array_unshift($brands, $parsedBrand);
        }

        return array_values(array_unique($brands));
    }

    public static function normalizeTopType(string $type): string
    {
        $type = strtolower(trim(str_replace(' ', '_', $type)));

        if (in_array($type, self::TOP_TYPES, true)) {
            return $type;
        }

        if (in_array($type, ['motore', 'motor', 'engines'], true)) {
            return 'engine';
        }

        if (in_array($type, ['part', 'parts', 'spare_part', 'autopjese', 'autopjesë'], true)) {
            return 'auto_part';
        }

        $component = AutomotivePartsIntentParser::extractComponent(['item' => $type], $type);
        if ($component === 'engine' || in_array($component, self::COMPONENTS_BY_TYPE['engine'], true)) {
            return 'engine';
        }

        return 'auto_part';
    }

    public static function slugifyBrand(string $brand): string
    {
        $brand = mb_strtolower(trim($brand));

        return match ($brand) {
            'vw', 'volkswagen ag' => 'volkswagen',
            'mercedes', 'mercedes benz', 'mercedes-benz' => 'mercedes',
            'citroën' => 'citroen',
            default => str_replace([' ', '-'], '_', $brand),
        };
    }

    public static function brandLabel(string $slug): string
    {
        return match (self::slugifyBrand($slug)) {
            'volkswagen' => 'Volkswagen',
            'mercedes' => 'Mercedes-Benz',
            'bmw' => 'BMW',
            'citroen' => 'Citroën',
            default => ucwords(str_replace('_', ' ', self::slugifyBrand($slug))),
        };
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function syncParsedFromFilters(array $parsed, array $filters): array
    {
        if (! empty($filters['product_type'])) {
            $parsed['product_type'] = self::normalizeTopType((string) $filters['product_type']);
        }

        if (! empty($filters['item'])) {
            $parsed['item'] = trim((string) $filters['item']);
        }

        if (! empty($filters['brand'])) {
            $parsed['brand'] = self::brandLabel((string) $filters['brand']);
        }

        if (! empty($filters['model'])) {
            $parsed['model'] = trim((string) $filters['model']);
        }

        if (! empty($filters['condition'])) {
            $parsed['condition'] = (string) $filters['condition'];
        }

        if (isset($filters['price_max']) && $filters['price_max'] !== '') {
            $parsed['max_price'] = (float) $filters['price_max'];
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $filters
     */
    public static function rebuildSearchQuery(array $parsed, array $filters): array
    {
        $parsed = self::syncParsedFromFilters($parsed, $filters);
        $raw = (string) ($parsed['raw_query'] ?? '');
        $term = AutomotivePartsIntentParser::searchTerm($parsed, $raw);
        if ($term !== '') {
            $parsed['search_query'] = $term;
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function hasSelections(array $filters): bool
    {
        foreach (['product_type', 'item', 'brand', 'model', 'condition', 'price_max'] as $key) {
            if (! empty($filters[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $aiParsed
     */
    public static function changedSearchIntent(array $filters, array $aiParsed): bool
    {
        foreach (['product_type', 'item', 'brand', 'model'] as $key) {
            if (empty($filters[$key])) {
                continue;
            }

            $filterVal = $key === 'brand'
                ? self::slugifyBrand((string) $filters[$key])
                : ($key === 'product_type'
                    ? self::normalizeTopType((string) $filters[$key])
                    : mb_strtolower(trim((string) $filters[$key])));

            $aiVal = $key === 'brand'
                ? self::slugifyBrand((string) ($aiParsed[$key] ?? ''))
                : ($key === 'product_type'
                    ? self::normalizeTopType((string) ($aiParsed[$key] ?? ''))
                    : mb_strtolower(trim((string) ($aiParsed[$key] ?? ''))));

            if ($filterVal !== '' && $filterVal !== $aiVal) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function activeDescription(array $parsed, bool $sq = true): string
    {
        $parts = [];

        $item = trim((string) ($parsed['item'] ?? ''));
        $type = self::normalizeTopType((string) ($parsed['product_type'] ?? ''));
        if ($item !== '') {
            $parts[] = str_replace('_', ' ', $item);
        } elseif ($type !== '') {
            $parts[] = $type === 'engine' ? ($sq ? 'motor' : 'engine') : str_replace('_', ' ', $type);
        }

        if (! empty($parsed['brand'])) {
            $parts[] = self::brandLabel((string) $parsed['brand']);
        }

        if (! empty($parsed['model'])) {
            $parts[] = (string) $parsed['model'];
        }

        if ($parts === []) {
            return (string) ($parsed['description'] ?? ($parsed['raw_query'] ?? ''));
        }

        $subject = implode(' ', $parts);

        return $sq
            ? 'Kërkoni '.$subject.'.'
            : 'Search for '.$subject.'.';
    }
}
