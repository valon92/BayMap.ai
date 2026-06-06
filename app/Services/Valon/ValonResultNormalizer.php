<?php

namespace App\Services\Valon;

/**
 * Normalizes raw platform listings into Valon worker output schema.
 */
class ValonResultNormalizer
{
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

        return [
            'id' => $item['id'] ?? null,
            'title' => (string) ($item['title'] ?? ''),
            'price' => (float) ($item['price'] ?? $item['price_eur'] ?? 0),
            'currency' => (string) ($item['currency'] ?? 'EUR'),
            'location' => (string) ($item['location'] ?? ''),
            'images' => $images,
            'image' => $images[0] ?? null,
            'source' => $sourceLabel ?: (string) ($item['source'] ?? $platform),
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
            '_provider_latency_ms' => $item['_provider_latency_ms'] ?? null,
        ];
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
