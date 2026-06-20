<?php

namespace App\Services\Marketplace\Scrapers;

use App\Support\AutomotiveColorResolver;
use App\Support\AutomotiveDisplayNormalizer;
use App\Support\AutomotiveEngineResolver;
use App\Support\AutomotiveModelResolver;
use App\Support\AutoScout24ListingParser;
use App\Support\BookIntentParser;
use App\Support\CategoryCatalog;
use App\Support\ElectronicsIntentParser;
use App\Support\FashionIntentParser;
use App\Support\HomeFurnitureIntentParser;
use App\Support\KosovoToyIntent;
use App\Support\ListingEnricher;

class ProductListingNormalizer
{
    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    public static function detectBrand(string $title): ?string
    {
        $upper = mb_strtoupper($title);
        foreach (['APPLE', 'SAMSUNG', 'DELL', 'HP', 'LENOVO', 'ASUS', 'ACER', 'MICROSOFT', 'SONY', 'LG', 'MSI'] as $brand) {
            if (str_contains($upper, $brand)) {
                return strtolower($brand);
            }
        }

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
    public static function detectProductType(string $title, ?string $category = null): string
    {
        $category = CategoryCatalog::normalize($category ?? '');

        if ($category === 'home_furniture') {
            return HomeFurnitureIntentParser::productTypeFromTitle($title) ?? 'furniture';
        }

        $lower = mb_strtolower($title);

        if (CategoryCatalog::isElectronics($category ?? '')) {
            return match (true) {
                str_contains($lower, 'macbook') || str_contains($lower, 'notebook') || str_contains($lower, 'laptop') => 'laptop',
                str_contains($lower, 'iphone') || str_contains($lower, 'galaxy') || str_contains($lower, 'smartphone') || str_contains($lower, 'phone') => 'phone',
                str_contains($lower, 'ipad') => 'tablet',
                str_contains($lower, 'airpods') || str_contains($lower, 'headphone') || str_contains($lower, 'earbuds') => 'headphones',
                str_contains($lower, 'monitor') || str_contains($lower, 'display') => 'monitor',
                str_contains($lower, 'smartwatch') || str_contains($lower, 'watch') => 'smartwatch',
                default => 'electronics',
            };
        }

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
        $parsedQuery = is_array($platform['_parsed_query'] ?? null) ? $platform['_parsed_query'] : [];
        $category = ! empty($item['category'])
            ? CategoryCatalog::normalize((string) $item['category'])
            : CategoryCatalog::categoryFromPlatform($platform, $parsedQuery);

        $titleHints = CategoryCatalog::isElectronics($category)
            ? ElectronicsIntentParser::attributesFromTitle($title)
            : [];

        if ($category === 'marketplace' && ($titleHints['product_type'] ?? null) !== null) {
            $category = 'electronics_tech';
        }

        $brand = $item['brand'] ?? $titleHints['brand'] ?? self::detectBrand($title);

        $listing = [
            'id' => $storeKey.'-'.($item['product_id'] ?? md5($title)),
            'store' => $storeKey,
            'title' => $title,
            'image' => $item['image'] ?? null,
            'images' => is_array($item['images'] ?? null) ? $item['images'] : [],
            'price' => (float) ($item['price'] ?? 0),
            'currency' => (string) ($platform['currency'] ?? 'EUR'),
            'location' => (string) ($item['location'] ?? $platform['location'] ?? AutomotiveDisplayNormalizer::platformCountryLabel($platform)),
            'country_code' => (string) ($platform['country'] ?? ''),
            'condition' => (string) ($item['condition'] ?? 'new'),
            'url' => $item['url'] ?? ($platform['base_url'] ?? '#'),
            'gender' => $item['gender'] ?? null,
            'brand' => $brand,
            'model' => $item['model'] ?? $titleHints['model'] ?? null,
            'color' => $item['color'] ?? null,
            'storage' => $item['storage'] ?? $titleHints['storage'] ?? null,
            'ram' => $item['ram'] ?? $titleHints['ram'] ?? null,
            'chip' => $item['chip'] ?? $titleHints['chip'] ?? null,
            'display_size' => $item['display_size'] ?? $titleHints['display_size'] ?? null,
            'year' => $item['year'] ?? $titleHints['year'] ?? null,
            'product_type' => $item['product_type'] ?? $titleHints['product_type'] ?? self::detectProductType($title, $category),
            'sizes' => $item['sizes'] ?? [],
            'category' => $category,
            'tags' => array_values(array_filter([
                $category,
                $storeKey,
                strtolower((string) ($platform['country'] ?? '')),
                $brand,
                'live',
            ])),
            'live' => true,
        ];

        return ListingEnricher::enrich($listing, $category);
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
        $countryCode = strtoupper((string) ($platform['country'] ?? 'DE'));
        $countryLabel = AutomotiveDisplayNormalizer::platformCountryLabel($platform);
        $item = AutomotiveDisplayNormalizer::normalizeListingFields($item, $countryLabel);
        $images = [];
        if (! empty($item['images']) && is_array($item['images'])) {
            $images = array_values(array_filter($item['images'], fn ($u) => is_string($u) && $u !== ''));
        }
        if ($images === [] && ! empty($item['image'])) {
            $images = [(string) $item['image']];
        }

        $listing = [
            'id' => $storeKey.'-'.($item['product_id'] ?? md5($title)),
            'store' => $storeKey,
            'title' => $title,
            'image' => $images[0] ?? ($item['image'] ?? null),
            'images' => $images,
            'price' => (float) ($item['price'] ?? 0),
            'currency' => (string) ($platform['currency'] ?? 'EUR'),
            'location' => (string) ($item['location'] ?? $countryLabel),
            'country_code' => $countryCode,
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
            'seller_type' => $item['seller_type'] ?? null,
            'power_hp' => $item['power_hp'] ?? null,
            'power_kw' => $item['power_kw'] ?? null,
            'electric_range_km' => $item['electric_range_km'] ?? null,
            'body_type' => $item['body_type'] ?? null,
            'first_registration' => $item['first_registration'] ?? null,
            'consumption' => $item['consumption'] ?? null,
            'product_type' => 'car',
            'category' => 'automotive',
            'sizes' => [],
            'tags' => array_values(array_filter([
                'automotive',
                $storeKey,
                strtolower($countryCode),
                $brand,
                'live',
            ])),
            'live' => true,
        ];

        if (is_array($item['specs'] ?? null) && $item['specs'] !== []) {
            $listing['specs'] = $item['specs'];
        } else {
            $specPayload = array_filter([
                'year' => $listing['year'],
                'mileage' => $listing['mileage'],
                'fuel' => $listing['fuel'],
                'transmission' => $listing['transmission'],
                'power_hp' => $listing['power_hp'],
                'power_kw' => $listing['power_kw'],
                'electric_range_km' => $listing['electric_range_km'],
                'body_type' => $listing['body_type'],
                'seller_type' => $listing['seller_type'],
                'first_registration' => $listing['first_registration'],
                'consumption' => $listing['consumption'],
            ], fn ($v) => $v !== null && $v !== '');

            if ($specPayload !== []) {
                $listing['specs'] = AutoScout24ListingParser::buildSpecChips($specPayload);
            }
        }

        return ListingEnricher::enrich($listing, 'automotive');
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function finalizeRealEstate(array $platform, string $storeKey, array $item): array
    {
        $title = (string) ($item['title'] ?? '');

        $listing = [
            'id' => $storeKey.'-'.($item['product_id'] ?? md5($title)),
            'store' => $storeKey,
            'title' => $title,
            'image' => $item['image'] ?? null,
            'images' => is_array($item['images'] ?? null) ? $item['images'] : [],
            'price' => (float) ($item['price'] ?? 0),
            'currency' => (string) ($item['currency'] ?? $platform['currency'] ?? 'CHF'),
            'location' => (string) ($item['location'] ?? $platform['location'] ?? ''),
            'country_code' => (string) ($item['country_code'] ?? $platform['country'] ?? ''),
            'url' => $item['url'] ?? ($platform['base_url'] ?? '#'),
            'category' => 'real_estate',
            'property_type' => $item['property_type'] ?? null,
            'listing_type' => $item['listing_type'] ?? null,
            'rooms' => $item['rooms'] ?? $item['bedrooms'] ?? null,
            'area_sqm' => $item['area_sqm'] ?? $item['living_space'] ?? null,
            'condition' => (string) ($item['condition'] ?? 'used'),
            'live' => (bool) ($item['live'] ?? true),
            'tags' => ['real_estate', $storeKey, 'live'],
        ];

        return ListingEnricher::enrich($listing, 'real_estate');
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
        if ($model !== '' && CategoryCatalog::isAutomotive($parsed['category'] ?? '')) {
            $model = mb_strtolower(AutomotiveModelResolver::normalizeModelForBrand(
                (string) ($parsed['brand'] ?? ''),
                (string) ($parsed['model'] ?? ''),
            ));
        }
        $size = trim((string) ($parsed['size'] ?? ''));
        $wantedColor = trim((string) ($parsed['color'] ?? ''));
        $wantedEngine = isset($parsed['engine_liters']) ? (float) $parsed['engine_liters'] : null;

        $isAutomotive = CategoryCatalog::isAutomotive($parsed['category'] ?? '');
        $isToy = KosovoToyIntent::isToySearch($parsed);
        $isElectronics = CategoryCatalog::isElectronics($parsed['category'] ?? '') && ! $isToy;
        $isFashion = in_array(CategoryCatalog::normalize($parsed['category'] ?? ''), ['fashion', 'sports_outdoor'], true);
        $isHomeFurniture = CategoryCatalog::normalize($parsed['category'] ?? '') === 'home_furniture';
        $productType = FashionIntentParser::normalizeType((string) ($parsed['product_type'] ?? ''));
        $maxPrice = isset($parsed['max_price']) ? (float) $parsed['max_price'] : null;

        return array_values(array_filter($items, function (array $item) use ($brand, $model, $size, $wantedColor, $wantedEngine, $parsed, $isAutomotive, $isElectronics, $isFashion, $isHomeFurniture, $isToy, $productType, $maxPrice) {
            if ($isHomeFurniture
                && ! HomeFurnitureIntentParser::matchesListing((string) ($item['title'] ?? ''), $parsed)) {
                return false;
            }

            if ($isToy
                && ! KosovoToyIntent::titleMatchesIntent((string) ($item['title'] ?? ''), $parsed)) {
                return false;
            }

            if ($maxPrice !== null && $maxPrice > 0) {
                $price = (float) ($item['price'] ?? $item['price_eur'] ?? 0);
                if ($price <= 0 && ! $isAutomotive) {
                    return false;
                }
                if ($price > 0 && $price > $maxPrice) {
                    return false;
                }
            }

            if ($productType !== '') {
                $typeMatch = match (true) {
                    $isToy => true,
                    $isAutomotive => true,
                    $isElectronics => ElectronicsIntentParser::productMatchesType($item, $productType),
                    $isFashion => FashionIntentParser::productMatchesType($item, $productType),
                    CategoryCatalog::isBookSearch($parsed) => BookIntentParser::productMatchesType($item, $productType),
                    default => true,
                };
                if (! $typeMatch) {
                    return false;
                }
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
                    $store = strtolower((string) ($item['store'] ?? ''));
                    if (AutomotiveModelResolver::shouldTrustPlatformScope($store, $parsed)) {
                        // AutoScout24 / Kleinanzeigen URLs are already scoped to make/model.
                    } else {
                    $allowUnknownYear = str_contains($store, 'merrjep')
                        || str_contains($store, 'veturaneshitje')
                        || str_contains($store, 'autolina')
                        || str_contains($store, 'autogrid')
                        || str_contains($store, 'autoscout24');
                    $matchParsed = array_merge($parsed, ['year' => $item['year'] ?? null]);
                    if ($allowUnknownYear) {
                        unset($matchParsed['year_min'], $matchParsed['year_max']);
                    }
                    if (! AutomotiveModelResolver::matchesListing(
                        (string) ($item['title'] ?? ''),
                        isset($item['model']) ? (string) $item['model'] : null,
                        $model,
                        $matchParsed,
                        $allowUnknownYear,
                    )) {
                        return false;
                    }
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

            if (! empty($parsed['gender']) && $isFashion) {
                if (! FashionIntentParser::matchesGender($item, (string) $parsed['gender'])) {
                    return false;
                }
            }

            if ($size !== '' && ! empty($item['sizes']) && is_array($item['sizes'])) {
                if (! in_array($size, array_map('strval', $item['sizes']), true)) {
                    return false;
                }
            }

            if ($wantedColor !== '' && $isAutomotive) {
                $store = strtolower((string) ($item['store'] ?? ''));
                $allowUnknown = str_contains($store, 'kleinanzeigen')
                    || str_contains($store, 'merrjep')
                    || str_contains($store, 'veturaneshitje')
                    || $wantedColor === 'multicolor';
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
