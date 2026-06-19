<?php

namespace App\Services\Marketplace;

use App\Contracts\MarketplaceSearchInterface;
use App\Support\SwissFashionMarketplaces;
use App\Support\UniversalMarketplaceBridge;
/**
 * Google Shopping results via SerpAPI (aggregates many online stores).
 *
 * @see https://serpapi.com/google-shopping-api
 */
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SerpApiShoppingService implements MarketplaceSearchInterface
{
    public function __construct(private MarketplaceQueryBuilder $queryBuilder) {}

    public function getSourceName(): string
    {
        return 'Google Shopping';
    }

    public function isConfigured(): bool
    {
        return config('serpapi.enabled') && ! empty(config('serpapi.api_key'));
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $parsedQuery, array $expandedFilters): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $geo = UniversalMarketplaceBridge::serpGeo($parsedQuery, $expandedFilters);

            $response = Http::timeout(config('serpapi.timeout', 20))
                ->get('https://serpapi.com/search', [
                    'engine' => 'google_shopping',
                    'q' => $this->queryBuilder->build(
                        $parsedQuery,
                        [],
                        $expandedFilters['location_suffix'] ?? null
                    ),
                    'api_key' => config('serpapi.api_key'),
                    'gl' => $geo['gl'],
                    'hl' => $geo['hl'],
                    'num' => config('serpapi.limit', 12),
                ]);

            if (! $response->successful()) {
                Log::warning('SerpAPI failed', ['status' => $response->status()]);

                return [];
            }

            $items = $response->json('shopping_results') ?? [];

            return array_map(
                fn (array $item) => $this->normalize($item, $geo['country_code']),
                is_array($items) ? array_slice($items, 0, config('serpapi.limit', 12)) : []
            );
        } catch (\Throwable $e) {
            Log::warning('SerpAPI exception', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalize(array $item, string $countryCode = 'US'): array
    {
        $price = $item['extracted_price'] ?? $item['price'] ?? 0;
        if (is_string($price)) {
            $price = (float) preg_replace('/[^\d.]/', '', $price);
        }

        return [
            'id' => 'serp-'.md5(($item['product_id'] ?? $item['title'] ?? uniqid())),
            'title' => $item['title'] ?? 'Product',
            'image' => $item['thumbnail'] ?? 'https://images.unsplash.com/photo-1472851294608-062f824d2349?w=800&q=80',
            'price' => (float) $price,
            'currency' => UniversalMarketplaceBridge::currencyForCountry($countryCode),
            'location' => $item['source'] ?? 'Online',
            'country_code' => strtoupper($countryCode),
            'condition' => 'new',
            'url' => $item['link'] ?? $item['product_link'] ?? 'https://www.google.com/shopping',
            'source' => $this->resolveSourceLabel($item, $countryCode),
            'source_key' => $this->resolveSourceKey($item, $countryCode),
            'affiliate_ready' => true,
            'sponsored' => false,
            'tags' => ['google_shopping', 'live', 'bridge'],
            'live' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveSourceLabel(array $item, string $countryCode): string
    {
        $mapped = $this->mapSwissFashionSource($item, $countryCode);

        return $mapped['label'] ?? (string) ($item['source'] ?? 'Google Shopping');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveSourceKey(array $item, string $countryCode): string
    {
        $mapped = $this->mapSwissFashionSource($item, $countryCode);

        return $mapped['key'] ?? 'google_shopping';
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{key: string, label: string}|null
     */
    private function mapSwissFashionSource(array $item, string $countryCode): ?array
    {
        if (strtoupper($countryCode) !== 'CH') {
            return null;
        }

        $haystack = mb_strtolower(implode(' ', array_filter([
            (string) ($item['source'] ?? ''),
            (string) ($item['link'] ?? ''),
            (string) ($item['product_link'] ?? ''),
        ])));

        foreach (SwissFashionMarketplaces::ORDERED_KEYS as $key) {
            $label = SwissFashionMarketplaces::label($key);
            $url = SwissFashionMarketplaces::url($key) ?? '';
            $host = parse_url($url, PHP_URL_HOST) ?: '';
            $host = mb_strtolower(str_replace('www.', '', $host));
            $slug = str_replace('_ch', '', $key);

            if ($host !== '' && str_contains($haystack, $host)) {
                return ['key' => $key, 'label' => $label];
            }

            if (str_contains($haystack, str_replace('_', '', $slug))
                || str_contains($haystack, str_replace('_', '-', $slug))
                || str_contains($haystack, mb_strtolower($label))) {
                return ['key' => $key, 'label' => $label];
            }
        }

        return null;
    }
}
