<?php

namespace App\Services\Marketplace\Scrapers;

use App\Support\AutomotiveColorResolver;
use App\Support\AutomotiveEngineResolver;
use App\Support\AutomotiveModelResolver;
use App\Support\CategoryCatalog;
use App\Support\ElectronicsIntentParser;

class ProductListingNormalizer
{
    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    public static function detectBrand(string $title): ?string
    {
        $upper = mb_strtoupper($title);
        foreach (['PUMA', 'NIKE', 'ADIDAS', 'REEBOK', 'NEW BALANCE', 'TIMBERLAND', 'UNDER ARMOUR', 'JORDAN', 'CONVERSE'] as $brand) {
            if (str_contains($upper, $brand)) {
                return strtolower(str_replace(' ', '_', $brand));
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $platform
     */
    public static function detectProductType(string $title): string
    {
        $lower = mb_strtolower($title);

        return match (true) {
            str_contains($lower, 'sneaker') || str_contains($lower, 'atlete') || str_contains($lower, 'trainer') => 'sneakers',
            str_contains($lower, 'shoe') || str_contains($lower, 'këpuc') || str_contains($lower, 'kepuce') => 'shoes',
            str_contains($lower, 'jacket') || str_contains($lower, 'xhaket') => 'jacket',
            str_contains($lower, 'pant') || str_contains($lower, 'track') => 'pants',
            str_contains($lower, 'short') || str_contains($lower, 'shorce') => 'shorts',
            str_contains($lower, 'dress') || str_contains($lower, 'fustan') => 'dress',
            str_contains($lower, 'bag') || str_contains($lower, 'qant') || str_contains($lower, 'çant') => 'bag',
            str_contains($lower, 'tee') || str_contains($lower, 'shirt') || str_contains($lower, 'bluz') => 'shirt',
            default => 'fashion',
        };
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function finalize(array $platform, string $storeKey, array $item): array
    {
        $title = (string) ($item['title'] ?? '');
        $brand = $item['brand'] ?? self::detectBrand($title);

        return [
            'id' => $storeKey.'-'.($item['product_id'] ?? md5($title)),
            'store' => $storeKey,
            'title' => $title,
            'image' => $item['image'] ?? null,
            'price' => (float) ($item['price'] ?? 0),
            'currency' => (string) ($platform['currency'] ?? 'EUR'),
            'location' => (string) ($item['location'] ?? $platform['location'] ?? ''),
            'country_code' => (string) ($platform['country'] ?? ''),
            'condition' => 'new',
            'url' => $item['url'] ?? ($platform['base_url'] ?? '#'),
            'gender' => $item['gender'] ?? null,
            'brand' => $brand,
            'product_type' => $item['product_type'] ?? self::detectProductType($title),
            'sizes' => $item['sizes'] ?? [],
            'tags' => array_values(array_filter([
                'fashion',
                $storeKey,
                strtolower((string) ($platform['country'] ?? '')),
                $brand,
                'live',
            ])),
            'live' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function finalizeAutomotive(array $platform, string $storeKey, array $item): array
    {
        $title = (string) ($item['title'] ?? '');
        $brand = $item['brand'] ?? null;

        return [
            'id' => $storeKey.'-'.($item['product_id'] ?? md5($title)),
            'store' => $storeKey,
            'title' => $title,
            'image' => $item['image'] ?? null,
            'price' => (float) ($item['price'] ?? 0),
            'currency' => (string) ($platform['currency'] ?? 'EUR'),
            'location' => (string) ($item['location'] ?? $platform['location'] ?? 'Germany'),
            'country_code' => (string) ($platform['country'] ?? 'DE'),
            'condition' => (string) ($item['condition'] ?? 'used'),
            'url' => $item['url'] ?? ($platform['base_url'] ?? '#'),
            'brand' => $brand,
            'model' => $item['model'] ?? null,
            'year' => $item['year'] ?? null,
            'mileage' => $item['mileage'] ?? null,
            'fuel' => $item['fuel'] ?? null,
            'transmission' => $item['transmission'] ?? null,
            'color' => $item['color'] ?? null,
            'engine_liters' => $item['engine_liters'] ?? null,
            'product_type' => 'car',
            'category' => 'automotive',
            'sizes' => [],
            'tags' => array_values(array_filter([
                'automotive',
                $storeKey,
                'de',
                $brand,
                'live',
            ])),
            'live' => true,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $parsed
     * @return array<int, array<string, mixed>>
     */
    public static function filterForIntent(array $items, array $parsed): array
    {
        $brand = mb_strtolower((string) ($parsed['brand'] ?? ''));
        $model = mb_strtolower((string) ($parsed['model'] ?? ''));
        $size = trim((string) ($parsed['size'] ?? ''));
        $wantedColor = trim((string) ($parsed['color'] ?? ''));
        $wantedEngine = isset($parsed['engine_liters']) ? (float) $parsed['engine_liters'] : null;

        $isAutomotive = CategoryCatalog::isAutomotive($parsed['category'] ?? '');
        $productType = mb_strtolower((string) ($parsed['product_type'] ?? ''));

        return array_values(array_filter($items, function (array $item) use ($brand, $model, $size, $wantedColor, $wantedEngine, $parsed, $isAutomotive, $productType) {
            if (! $isAutomotive && $productType !== '' && ! ElectronicsIntentParser::productMatchesType($item, $productType)) {
                return false;
            }
            if ($brand !== '') {
                $title = mb_strtolower($item['title'] ?? '');
                $itemBrand = mb_strtolower((string) ($item['brand'] ?? ''));
                $brandAliases = [$brand];
                if ($brand === 'volkswagen') {
                    $brandAliases[] = 'vw';
                }
                if ($brand === 'apple') {
                    $brandAliases = array_merge($brandAliases, ['macbook', 'iphone', 'ipad', 'airpods', 'imac', 'mac mini', 'mac studio']);
                }
                $brandMatch = false;
                foreach ($brandAliases as $alias) {
                    if ($itemBrand === $alias
                        || str_contains($itemBrand, $alias)
                        || preg_match('/\b'.preg_quote($alias, '/').'\b/u', $title) === 1) {
                        $brandMatch = true;
                        break;
                    }
                }
                if (! $brandMatch) {
                    return false;
                }
            }

            if ($model !== '') {
                if ($isAutomotive) {
                    if (! AutomotiveModelResolver::matchesListing(
                        (string) ($item['title'] ?? ''),
                        isset($item['model']) ? (string) $item['model'] : null,
                        $model,
                        array_merge($parsed, ['year' => $item['year'] ?? null]),
                    )) {
                        return false;
                    }
                } else {
                    $title = mb_strtolower($item['title'] ?? '');
                    $itemModel = mb_strtolower((string) ($item['model'] ?? ''));
                    $modelPattern = str_replace(' ', '\s*', preg_quote($model, '/'));
                    $modelMatch = $itemModel === $model
                        || str_contains($itemModel, $model)
                        || preg_match('/\b'.$modelPattern.'\b/u', $title) === 1;
                    if (! $modelMatch) {
                        return false;
                    }
                }
            }

            if ($size !== '' && ! empty($item['sizes']) && is_array($item['sizes'])) {
                if (! in_array($size, array_map('strval', $item['sizes']), true)) {
                    return false;
                }
            }

            if ($wantedColor !== '' && $isAutomotive) {
                $store = strtolower((string) ($item['store'] ?? ''));
                $allowUnknown = str_contains($store, 'kleinanzeigen') || $wantedColor === 'multicolor';
                $colorMatch = false;
                if ($wantedColor === 'multicolor' && ! empty($parsed['colors']) && is_array($parsed['colors'])) {
                    foreach ($parsed['colors'] as $tone) {
                        if (AutomotiveColorResolver::matchesWanted(
                            isset($item['color']) ? (string) $item['color'] : null,
                            (string) $tone,
                            (string) ($item['title'] ?? ''),
                            $allowUnknown,
                        )) {
                            $colorMatch = true;
                            break;
                        }
                    }
                } else {
                    $colorMatch = AutomotiveColorResolver::matchesWanted(
                        isset($item['color']) ? (string) $item['color'] : null,
                        $wantedColor,
                        (string) ($item['title'] ?? ''),
                        $allowUnknown,
                    );
                }
                if (! $colorMatch) {
                    return false;
                }
            }

            if ($wantedEngine !== null && $wantedEngine > 0 && $isAutomotive) {
                $productEngine = isset($item['engine_liters']) ? (float) $item['engine_liters'] : null;
                if (! AutomotiveEngineResolver::matchesWanted(
                    $productEngine,
                    $wantedEngine,
                    (string) ($item['title'] ?? ''),
                    isset($item['fuel']) ? (string) $item['fuel'] : (isset($parsed['fuel']) ? (string) $parsed['fuel'] : null),
                )) {
                    return false;
                }
            }

            return true;
        }));
    }
}
