<?php

namespace App\Services\Marketplace;

use App\Support\CategoryCatalog;
use App\Support\FashionIntentParser;
use App\Support\UniversalMarketplaceBridge;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Walmart Marketplace catalog keyword search.
 *
 * @see https://developer.walmart.com/us-marketplace/docs/item-search-for-the-walmart-catalog
 */
class WalmartCatalogService
{
    public function __construct(
        private WalmartOAuthService $oauth,
        private MarketplaceQueryBuilder $queryBuilder,
    ) {}

    public function getSourceName(): string
    {
        return 'Walmart';
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $parsedQuery, array $expandedFilters): array
    {
        if (! $this->oauth->isConfigured()) {
            return [];
        }

        $countryCode = strtoupper(UniversalMarketplaceBridge::resolveCountryCode($parsedQuery, $expandedFilters));
        if ($countryCode !== '' && $countryCode !== 'US') {
            return [];
        }

        $category = CategoryCatalog::normalize($parsedQuery['category'] ?? '');
        if (in_array($category, ['travel', 'automotive', 'automotive_parts', 'real_estate'], true)) {
            return [];
        }

        $query = $this->buildQuery($parsedQuery, $expandedFilters);
        if ($query === '') {
            return [];
        }

        $limit = (int) config('walmart.limit', 20);
        $cacheKey = 'walmart:catalog:v1:'.md5($query.':'.$limit);
        $ttl = (int) config('walmart.cache_ttl_seconds', 300);

        try {
            $payload = Cache::get($cacheKey);
            if (! is_array($payload)) {
                $base = rtrim((string) config('walmart.base_url'), '/');
                $response = Http::timeout((int) config('walmart.timeout', 20))
                    ->withHeaders($this->oauth->requestHeaders())
                    ->get("{$base}/v3/items/walmart/search", [
                        'query' => $query,
                    ]);

                if (! $response->successful()) {
                    Log::warning('Walmart catalog search failed', [
                        'status' => $response->status(),
                        'query' => $query,
                        'body' => mb_substr((string) $response->body(), 0, 400),
                    ]);

                    return [];
                }

                $payload = $response->json();
                if (is_array($payload)) {
                    Cache::put($cacheKey, $payload, $ttl);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Walmart catalog search exception', ['error' => $e->getMessage()]);

            return [];
        }

        if (! is_array($payload)) {
            return [];
        }

        $items = $payload['items'] ?? [];
        if (! is_array($items)) {
            return [];
        }

        $listings = [];
        foreach (array_slice($items, 0, $limit) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized = $this->normalizeItem($item);
            if ($normalized !== null) {
                $listings[] = $normalized;
            }
        }

        return $listings;
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     */
    private function buildQuery(array $parsedQuery, array $expandedFilters): string
    {
        $query = trim($this->queryBuilder->build(
            $parsedQuery,
            [],
            null,
        ));

        if (in_array(CategoryCatalog::normalize($parsedQuery['category'] ?? ''), ['fashion', 'sports_outdoor'], true)) {
            $marketplaceQuery = FashionIntentParser::marketplaceQuery($parsedQuery);
            if ($marketplaceQuery !== '') {
                return $marketplaceQuery;
            }
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function normalizeItem(array $item): ?array
    {
        $itemId = trim((string) ($item['itemId'] ?? ''));
        if ($itemId === '') {
            return null;
        }

        $title = $this->stripMarkup((string) ($item['title'] ?? 'Walmart product'));
        $price = $item['price'] ?? [];
        $amount = is_array($price) ? ($price['amount'] ?? null) : null;
        $currency = is_array($price) ? ($price['currency'] ?? 'USD') : 'USD';

        $imageUrl = '';
        $images = $item['images'] ?? [];
        if (is_array($images) && isset($images[0]['url'])) {
            $imageUrl = (string) $images[0]['url'];
        }

        $condition = strtolower(trim((string) ($item['condition'] ?? 'new')));
        if ($condition === '') {
            $condition = 'new';
        }

        $tags = array_filter([
            $condition,
            ! empty($item['isMarketPlaceItem']) ? 'marketplace' : null,
            $item['brand'] ?? null,
        ]);

        return [
            'id' => 'walmart-'.$itemId,
            'title' => $title,
            'image' => $imageUrl !== ''
                ? $imageUrl
                : 'https://images.unsplash.com/photo-1472851294608-062f824d2349?w=800&q=80',
            'price' => is_numeric($amount) ? (float) $amount : 0,
            'currency' => is_string($currency) && $currency !== '' ? $currency : 'USD',
            'location' => 'United States',
            'country_code' => 'US',
            'condition' => $condition,
            'url' => 'https://www.walmart.com/ip/'.$itemId,
            'source' => 'Walmart',
            'source_key' => 'walmart_us',
            'affiliate_ready' => false,
            'sponsored' => false,
            'tags' => array_merge(array_map('mb_strtolower', array_filter($tags)), ['live']),
            'live' => true,
        ];
    }

    private function stripMarkup(string $text): string
    {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
