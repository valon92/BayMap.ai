<?php

namespace App\Services\Valon;

use App\Support\ListingEnricher;

/**
 * Normalizes raw platform listings into Valon worker output schema.
 */
class ValonResultNormalizer
{
    /** @var array<int, string> */
    private const TRAVEL_PASSTHROUGH = [
        'category', 'product_type', 'travel_mode', 'subtitle', 'departure_time', 'arrival_time',
        'departure_airport', 'arrival_airport', 'departure_date', 'return_date', 'origin_city',
        'destination_city', 'origin_country_code', 'destination_country_code', 'duration_minutes',
        'duration_label', 'stops', 'stops_label', 'travel_class', 'airline', 'flight_number',
        'carbon_kg', 'price_on_request', 'legs', 'travel_type', 'travelers',
    ];

    /** @var array<int, string> */
    private const WEB_SERVICES_PASSTHROUGH = [
        'category', 'product_type', 'web_service_type', 'subtitle', 'domain_name', 'provider_rank',
        'price_label', 'billing_period', 'brand_color', 'brand_bg', 'logo_url', 'price_on_request',
    ];

    /** @var array<int, string> */
    private const PRODUCT_PASSTHROUGH = [
        'category', 'product_type', 'model', 'fuel', 'transmission', 'color', 'engine_liters',
        'seller_type', 'power_hp', 'power_kw', 'electric_range_km', 'body_type',
        'first_registration', 'consumption', 'specs', 'gender', 'sizes', 'storage', 'ram',
        'property_type', 'listing_type', 'rooms', 'area_sqm', 'author', 'format', 'genre', 'language',
        'chip', 'display_size',
    ];

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function normalize(array $item, string $workerId, string $platform, string $sourceLabel): array
    {
        $images = is_array($item['images'] ?? null)
            ? array_values(array_unique(array_filter(
                $item['images'],
                fn ($url) => is_string($url) && $url !== '' && ! $this->isPlaceholderImage($url),
            )))
            : [];

        if ($images === [] && ! empty($item['image']) && is_string($item['image']) && ! $this->isPlaceholderImage($item['image'])) {
            $images = [(string) $item['image']];
        }

        $source = (string) ($item['source'] ?? '');
        if ($source === '') {
            $source = $sourceLabel ?: $platform;
        }

        $normalized = [
            'id' => $item['id'] ?? null,
            'title' => (string) ($item['title'] ?? ''),
            'price' => (float) ($item['price'] ?? $item['price_eur'] ?? 0),
            'currency' => (string) ($item['currency'] ?? 'EUR'),
            'location' => (string) ($item['location'] ?? ''),
            'images' => $images,
            'image' => $images[0] ?? null,
            'source' => $source,
            'source_key' => (string) ($item['source_key'] ?? $platform),
            'url' => (string) ($item['url'] ?? '#'),
            'match_score' => (int) ($item['match_score'] ?? 0),
            'availability' => $this->availability($item),
            'condition' => $item['condition'] ?? 'used',
            'live' => (bool) ($item['live'] ?? false),
            'valon_worker_id' => $workerId,
            'valon_platform' => $platform,
            'tags' => $item['tags'] ?? [],
            'year' => $item['year'] ?? null,
            'mileage' => $item['mileage'] ?? null,
            'brand' => $item['brand'] ?? null,
            'store' => $item['store'] ?? null,
            'fingerprint' => $item['fingerprint'] ?? null,
            'sponsored' => (bool) ($item['sponsored'] ?? false),
            'country_code' => $item['country_code'] ?? null,
            '_provider_latency_ms' => $item['_provider_latency_ms'] ?? null,
        ];

        foreach (self::TRAVEL_PASSTHROUGH as $field) {
            if (! array_key_exists($field, $item)) {
                continue;
            }
            $value = $item[$field];
            if ($value !== null && $value !== '' && $value !== []) {
                $normalized[$field] = $value;
            }
        }

        foreach (self::WEB_SERVICES_PASSTHROUGH as $field) {
            if (! array_key_exists($field, $item)) {
                continue;
            }
            $value = $item[$field];
            if ($value !== null && $value !== '' && $value !== []) {
                $normalized[$field] = $value;
            }
        }

        foreach (self::PRODUCT_PASSTHROUGH as $field) {
            if (! array_key_exists($field, $item)) {
                continue;
            }
            $value = $item[$field];
            if ($value !== null && $value !== '' && $value !== []) {
                $normalized[$field] = $value;
            }
        }

        if (! empty($item['_marketplace_result_total'])) {
            $normalized['_marketplace_result_total'] = (int) $item['_marketplace_result_total'];
        }

        $enriched = ListingEnricher::enrich(array_merge($item, $normalized));
        if (! empty($enriched['images'])) {
            $normalized['images'] = $enriched['images'];
            $normalized['image'] = $enriched['image'] ?? $normalized['image'];
        }
        if (! empty($enriched['specs'])) {
            $normalized['specs'] = $enriched['specs'];
        }
        if (! empty($enriched['category']) && empty($normalized['category'])) {
            $normalized['category'] = $enriched['category'];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function normalizeMany(array $items, string $workerId, string $platform, string $sourceLabel): array
    {
        return array_map(
            fn (array $item) => $this->normalize($item, $workerId, $platform, $sourceLabel),
            $items
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function availability(array $item): string
    {
        if (! empty($item['live'])) {
            return 'live';
        }
        if (! empty($item['url']) && $item['url'] !== '#') {
            return 'available';
        }

        return 'unknown';
    }

    private function isPlaceholderImage(string $url): bool
    {
        return str_contains($url, 'images.unsplash.com/photo-1618843479313')
            || str_contains($url, 'images.unsplash.com/photo-1472851294608');
    }
}
