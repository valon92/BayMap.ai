<?php

namespace App\Services\Marketplace;

use App\Contracts\MarketplaceSearchInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Real product search via eBay Browse API.
 * @see https://developer.ebay.com/api-docs/buy/browse/resources/item_summary/methods/search
 */
use App\Support\UniversalMarketplaceBridge;

class EbayBrowseService implements MarketplaceSearchInterface
{
    public function __construct(
        private EbayOAuthService $oauth,
        private MarketplaceQueryBuilder $queryBuilder,
    ) {}

    public function getSourceName(): string
    {
        return 'eBay';
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

        try {
            $token = $this->oauth->getAccessToken();
            $base = config('ebay.sandbox')
                ? 'https://api.sandbox.ebay.com'
                : 'https://api.ebay.com';
            $countryCode = UniversalMarketplaceBridge::resolveCountryCode($parsedQuery, $expandedFilters);
            $marketplaceId = UniversalMarketplaceBridge::ebayMarketplaceId($countryCode);

            $response = Http::withToken($token)
                ->timeout(config('ebay.timeout', 15))
                ->withHeaders([
                    'X-EBAY-C-MARKETPLACE-ID' => $marketplaceId,
                    'Accept' => 'application/json',
                ])
                ->get("{$base}/buy/browse/v1/item_summary/search", [
                    'q' => $this->queryBuilder->build(
                        $parsedQuery,
                        [],
                        $expandedFilters['location_suffix'] ?? null
                    ),
                    'limit' => config('ebay.limit', 12),
                ]);

            if (! $response->successful()) {
                Log::warning('eBay Browse search failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return [];
            }

            $summaries = $response->json('itemSummaries') ?? [];

            return array_map(
                fn (array $item) => $this->normalizeItem($item),
                is_array($summaries) ? $summaries : []
            );
        } catch (\Throwable $e) {
            Log::warning('eBay search exception', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item): array
    {
        $price = $item['price'] ?? [];
        $image = $item['image'] ?? [];
        $location = $item['itemLocation'] ?? $item['seller'] ?? [];

        $locationStr = '';
        if (is_array($location)) {
            $locationStr = trim(
                ($location['city'] ?? '').', '.($location['country'] ?? $location['stateOrProvince'] ?? '')
            );
        }

        $tags = array_filter(array_map('mb_strtolower', array_filter([
            $item['condition'] ?? null,
        ])));

        return [
            'id' => 'ebay-'.($item['itemId'] ?? uniqid()),
            'title' => $item['title'] ?? 'eBay listing',
            'image' => $image['imageUrl'] ?? 'https://images.unsplash.com/photo-1472851294608-062f824d2349?w=800&q=80',
            'price' => isset($price['value']) ? (float) $price['value'] : 0,
            'currency' => $price['currency'] ?? 'EUR',
            'location' => $locationStr ?: 'eBay',
            'condition' => strtolower($item['condition'] ?? 'used'),
            'url' => $item['itemWebUrl'] ?? 'https://www.ebay.com',
            'source' => 'eBay',
            'source_key' => 'ebay',
            'affiliate_ready' => true,
            'sponsored' => false,
            'tags' => array_merge($tags, ['live']),
            'live' => true,
        ];
    }
}
