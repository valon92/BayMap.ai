<?php

namespace App\Services\Valon;

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

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function normalize(array $item, string $workerId, string $platform, string $sourceLabel): array
    {
        $images = [];
        if (! empty($item['image'])) {
            $images[] = $item['image'];
        }
        if (! empty($item['images']) && is_array($item['images'])) {
            $images = array_merge($images, $item['images']);
        }
        $images = array_values(array_unique(array_filter($images)));

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
}
