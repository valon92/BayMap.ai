<?php

namespace App\Support;

/**
 * Universal listing enrichment — multi-image galleries and spec chips for every category.
 */
class ListingEnricher
{
    /**
     * @param  array<string, mixed>  $item
     * @return array<int, string>
     */
    public static function collectImages(array $item): array
    {
        $urls = [];

        foreach (['images', 'gallery', 'photos', 'pictures'] as $key) {
            $chunk = $item[$key] ?? null;
            if (! is_array($chunk)) {
                continue;
            }
            foreach ($chunk as $url) {
                if (is_string($url) && trim($url) !== '') {
                    $urls[] = self::upgradeImageUrl(trim($url));
                } elseif (is_array($url) && ! empty($url['url'])) {
                    $urls[] = self::upgradeImageUrl((string) $url['url']);
                }
            }
        }

        if (! empty($item['image']) && is_string($item['image'])) {
            array_unshift($urls, self::upgradeImageUrl(trim($item['image'])));
        }

        if (! empty($item['image_url']) && is_string($item['image_url'])) {
            $urls[] = self::upgradeImageUrl(trim($item['image_url']));
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function enrich(array $item, ?string $category = null): array
    {
        $category = CategoryCatalog::normalize($category ?? (string) ($item['category'] ?? 'marketplace'));
        $images = self::collectImages($item);

        if ($images !== []) {
            $item['images'] = $images;
            $item['image'] = $images[0];
        }

        if (empty($item['category'])) {
            $item['category'] = $category;
        }

        if (empty($item['specs']) || ! is_array($item['specs'])) {
            $item['specs'] = self::buildSpecChips($item, $category);
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<int, array{label: string, value: string}>
     */
    public static function buildSpecChips(array $item, ?string $category = null): array
    {
        $category = CategoryCatalog::normalize($category ?? (string) ($item['category'] ?? 'marketplace'));
        $chips = [];

        if (CategoryCatalog::isAutomotive($category)) {
            return AutoScout24ListingParser::buildSpecChips(array_filter([
                'year' => $item['year'] ?? null,
                'mileage' => $item['mileage'] ?? null,
                'fuel' => $item['fuel'] ?? null,
                'transmission' => $item['transmission'] ?? null,
                'power_hp' => $item['power_hp'] ?? null,
                'power_kw' => $item['power_kw'] ?? null,
                'electric_range_km' => $item['electric_range_km'] ?? null,
                'body_type' => $item['body_type'] ?? null,
                'seller_type' => $item['seller_type'] ?? null,
                'first_registration' => $item['first_registration'] ?? null,
                'consumption' => $item['consumption'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''));
        }

        if ($category === 'real_estate') {
            self::addChip($chips, 'rooms', $item['rooms'] ?? $item['bedrooms'] ?? null, fn ($v) => $v.' '.($v == 1 ? 'room' : 'rooms'));
            self::addChip($chips, 'area', $item['area_sqm'] ?? $item['living_space'] ?? null, fn ($v) => is_numeric($v) ? number_format((float) $v, 0, ',', '.').' m²' : (string) $v);
            self::addChip($chips, 'type', $item['property_type'] ?? null);
            self::addChip($chips, 'listing', $item['listing_type'] ?? null);
            self::addChip($chips, 'condition', $item['condition'] ?? null);

            return $chips;
        }

        if (CategoryCatalog::isBooks($category)) {
            self::addChip($chips, 'format', $item['format'] ?? null);
            self::addChip($chips, 'genre', $item['genre'] ?? null);
            self::addChip($chips, 'author', $item['author'] ?? null);
            self::addChip($chips, 'language', $item['language'] ?? null);
            self::addChip($chips, 'condition', $item['condition'] ?? null);

            return $chips;
        }

        if (CategoryCatalog::isElectronics($category)) {
            self::addChip($chips, 'storage', $item['storage'] ?? null);
            self::addChip($chips, 'ram', $item['ram'] ?? null);
            self::addChip($chips, 'display', $item['display_size'] ?? null);
            self::addChip($chips, 'chip', $item['chip'] ?? null);
            self::addChip($chips, 'year', $item['year'] ?? null);
            self::addChip($chips, 'brand', $item['brand'] ?? null);
            self::addChip($chips, 'model', $item['model'] ?? null);
            self::addChip($chips, 'type', $item['product_type'] ?? null);
            self::addChip($chips, 'condition', $item['condition'] ?? null);

            return $chips;
        }

        if (in_array($category, ['fashion', 'sports_outdoor', 'luxury_collectibles'], true)) {
            self::addChip($chips, 'brand', $item['brand'] ?? null);
            self::addChip($chips, 'size', self::formatSizes($item['sizes'] ?? null));
            self::addChip($chips, 'gender', $item['gender'] ?? null);
            self::addChip($chips, 'color', $item['color'] ?? null);
            self::addChip($chips, 'type', $item['product_type'] ?? null);

            return $chips;
        }

        self::addChip($chips, 'brand', $item['brand'] ?? null);
        self::addChip($chips, 'type', $item['product_type'] ?? null);
        self::addChip($chips, 'condition', $item['condition'] ?? null);
        self::addChip($chips, 'location', $item['location'] ?? null);

        return $chips;
    }

    /**
     * @param  array<int, array{label: string, value: string}>  $chips
     */
    private static function addChip(array &$chips, string $label, mixed $value, ?callable $formatter = null): void
    {
        if ($value === null || $value === '' || $value === []) {
            return;
        }

        if (is_array($value)) {
            $value = implode(', ', array_map('strval', $value));
        }

        $formatted = $formatter ? $formatter($value) : (string) $value;
        if ($formatted === '') {
            return;
        }

        $chips[] = ['label' => $label, 'value' => $formatted];
    }

    private static function formatSizes(mixed $sizes): ?string
    {
        if (! is_array($sizes) || $sizes === []) {
            return is_string($sizes) && $sizes !== '' ? $sizes : null;
        }

        $list = array_slice(array_map('strval', $sizes), 0, 4);
        $suffix = count($sizes) > 4 ? '…' : '';

        return implode(', ', $list).$suffix;
    }

    /**
     * @return array<int, string>
     */
    public static function imagesFromJsonLd(mixed $image): array
    {
        if (is_string($image) && trim($image) !== '') {
            return [trim($image)];
        }

        if (! is_array($image)) {
            return [];
        }

        $urls = [];
        foreach ($image as $entry) {
            if (is_string($entry) && trim($entry) !== '') {
                $urls[] = trim($entry);
            } elseif (is_array($entry) && ! empty($entry['contentUrl']) && is_string($entry['contentUrl'])) {
                $urls[] = trim($entry['contentUrl']);
            } elseif (is_array($entry) && ! empty($entry['url']) && is_string($entry['url'])) {
                $urls[] = trim($entry['url']);
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    private static function upgradeImageUrl(string $url): string
    {
        if (str_contains($url, 'autoscout24.net') || str_contains($url, 'pictures.autoscout24')) {
            $upgraded = preg_replace('#/\d+x\d+\.(webp|jpg|jpeg)(?:\?.*)?$#i', '/720x540.webp', $url);

            return is_string($upgraded) ? $upgraded : $url;
        }

        if (preg_match('#/thumb(?:nail)?/|/small/|/xs/|w=\d{2,3}([^\d]|$)#i', $url)) {
            return preg_replace(['#/thumb(?:nail)?/#i', '#/small/#i', '#w=\d+#i'], ['/large/', '/large/', 'w=800'], $url) ?? $url;
        }

        return $url;
    }
}
