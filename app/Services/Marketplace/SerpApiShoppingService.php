<?php

namespace App\Services\Marketplace;

use App\Contracts\MarketplaceSearchInterface;
use App\Support\CategoryCatalog;
use App\Support\HomeFurnitureIntentParser;
use App\Support\LivePlatformRegistry;
use App\Support\SearchCountryResolver;
use App\Support\SwissFashionMarketplaces;
use App\Support\UniversalMarketplaceBridge;
/**
 * Google Shopping results via SerpAPI (aggregates many online stores).
 *
 * @see https://serpapi.com/google-shopping-api
 */
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SerpApiShoppingService implements MarketplaceSearchInterface
{
    /** @var array<string, string> */
    private const MERCHANT_PLATFORM_KEYS = [
        'hornbach' => 'hornbach_de',
        'obi.de' => 'obi_de',
        'obi' => 'obi_de',
        'home24.de' => 'home24_de',
        'home24' => 'home24_de',
        'xxxlutz de' => 'xxxlutz_de',
        'xxxlutz' => 'xxxlutz_de',
        'moebel.de' => 'home24_de',
        'küche&co' => 'xxxlutz_de',
        'kueche&co' => 'xxxlutz_de',
    ];

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
            $category = CategoryCatalog::normalize($parsedQuery['category'] ?? '');
            $configuredLimit = (int) config('serpapi.limit', 40);
            $limit = $category === 'home_furniture' ? max($configuredLimit, 120) : $configuredLimit;
            $expandImmersive = $this->shouldExpandImmersive($parsedQuery);
            $queries = $this->resolveQueries($parsedQuery, $expandedFilters);
            $merged = [];
            $seen = [];
            $immersiveProductsUsed = 0;
            $immersiveProductBudget = (int) config('serpapi.immersive_expand.max_products', 8);

            foreach ($queries as $query) {
                if (count($merged) >= $limit) {
                    break;
                }

                foreach ($this->fetchResults($query, $geo, $limit) as $item) {
                    if ($expandImmersive
                        && $immersiveProductsUsed < $immersiveProductBudget
                        && ! empty($item['serpapi_immersive_product_api'])) {
                        $immersiveProductsUsed++;
                        $storeListings = $this->fetchImmersiveStoreOffers($item, $geo['country_code']);

                        if ($storeListings !== []) {
                            foreach ($storeListings as $listing) {
                                if (count($merged) >= $limit) {
                                    break 3;
                                }

                                $key = (string) ($listing['id'] ?? md5(json_encode($listing)));
                                if (isset($seen[$key])) {
                                    continue;
                                }

                                $seen[$key] = true;
                                $merged[] = $listing;
                            }

                            continue;
                        }

                        if ($expandImmersive) {
                            continue;
                        }
                    }

                    $key = (string) ($item['product_id'] ?? md5((string) ($item['title'] ?? '')));
                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;
                    $merged[] = $this->normalize($item, $geo['country_code']);

                    if (count($merged) >= $limit) {
                        break 2;
                    }
                }
            }

            if ($expandImmersive) {
                $merged = array_values(array_filter(
                    $merged,
                    fn (array $item) => ! $this->isGoogleShoppingRedirect((string) ($item['url'] ?? '')),
                ));
            }

            return $merged;
        } catch (\Throwable $e) {
            Log::warning('SerpAPI exception', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     */
    private function shouldExpandImmersive(array $parsedQuery): bool
    {
        if (! (bool) config('serpapi.immersive_expand.enabled', true)) {
            return false;
        }

        return CategoryCatalog::normalize($parsedQuery['category'] ?? '') === 'home_furniture';
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @return array<int, string>
     */
    private function resolveQueries(array $parsedQuery, array $expandedFilters): array
    {
        if (CategoryCatalog::normalize($parsedQuery['category'] ?? '') === 'home_furniture') {
            $queries = HomeFurnitureIntentParser::serpSearchQueries($parsedQuery);
            if ($queries !== []) {
                return $queries;
            }
        }

        $query = $this->queryBuilder->build(
            $parsedQuery,
            [],
            $expandedFilters['location_suffix'] ?? null
        );

        return $query !== '' ? [$query] : [];
    }

    /**
     * @param  array{gl: string, hl: string, country_code: string}  $geo
     * @return array<int, array<string, mixed>>
     */
    private function fetchResults(string $query, array $geo, int $limit): array
    {
        $response = Http::timeout(config('serpapi.timeout', 20))
            ->get('https://serpapi.com/search', [
                'engine' => 'google_shopping',
                'q' => $query,
                'api_key' => config('serpapi.api_key'),
                'gl' => $geo['gl'],
                'hl' => $geo['hl'],
                'num' => min(40, max($limit, 20)),
            ]);

        if (! $response->successful()) {
            Log::warning('SerpAPI failed', ['status' => $response->status(), 'query' => $query]);

            return [];
        }

        $items = $response->json('shopping_results') ?? [];

        return is_array($items) ? $items : [];
    }

    /**
     * Per-merchant offers from Google Shopping compare view (Hornbach, OBI, home24, …).
     *
     * @param  array<string, mixed>  $shoppingItem
     * @return array<int, array<string, mixed>>
     */
    private function fetchImmersiveStoreOffers(array $shoppingItem, string $countryCode): array
    {
        $apiUrl = (string) ($shoppingItem['serpapi_immersive_product_api'] ?? '');
        if ($apiUrl === '') {
            return [];
        }

        $cacheKey = 'serp:immersive:v2:'.md5($apiUrl);
        $ttl = (int) config('serpapi.immersive_expand.cache_ttl_seconds', 600);

        $payload = Cache::remember($cacheKey, $ttl, function () use ($apiUrl) {
            $response = Http::timeout(config('serpapi.timeout', 20))
                ->get($this->immersiveApiUrl($apiUrl));

            if (! $response->successful()) {
                Log::warning('SerpAPI immersive product failed', ['status' => $response->status()]);

                return null;
            }

            return $response->json();
        });

        if (! is_array($payload)) {
            return [];
        }

        $product = is_array($payload['product_results'] ?? null) ? $payload['product_results'] : [];
        $stores = is_array($product['stores'] ?? null) ? $product['stores'] : [];
        if ($stores === []) {
            return [];
        }

        $images = $this->extractImages($shoppingItem);
        if ($images === []) {
            $images = $this->extractImages($product);
        }

        $baseTitle = trim((string) ($product['title'] ?? $shoppingItem['title'] ?? ''));
        $brand = trim((string) ($product['brand'] ?? ''));
        $maxStores = (int) config('serpapi.immersive_expand.max_stores_per_product', 20);
        $listings = [];

        foreach (array_slice($stores, 0, $maxStores) as $store) {
            if (! is_array($store)) {
                continue;
            }

            $listing = $this->normalizeStoreOffer($store, $countryCode, $images, $baseTitle, $brand);
            if ($listing !== null) {
                $listings[] = $listing;
            }
        }

        return $listings;
    }

    /**
     * @param  array<string, mixed>  $store
     * @param  array<int, string>  $images
     * @return array<string, mixed>|null
     */
    private function normalizeStoreOffer(
        array $store,
        string $countryCode,
        array $images,
        string $baseTitle,
        string $brand,
    ): ?array {
        $countryCode = strtoupper($countryCode);
        $merchant = trim((string) ($store['name'] ?? ''));
        $title = trim((string) ($store['title'] ?? $baseTitle));
        $url = trim((string) ($store['link'] ?? ''));

        if ($title === '' || $url === '' || $merchant === '') {
            return null;
        }

        $price = (float) ($store['extracted_price'] ?? $store['extracted_total'] ?? 0);
        if ($price <= 0) {
            return null;
        }

        $countryLabel = SearchCountryResolver::countryNameForCode($countryCode)
            ?? match ($countryCode) {
                'DE' => 'Germany',
                'CH' => 'Switzerland',
                'IT' => 'Italy',
                'FR' => 'France',
                'AT' => 'Austria',
                default => $countryCode,
            };

        $platformKey = $this->mapMerchantToPlatformKey($merchant, $url, $countryCode);
        $sourceLabel = $this->merchantSourceLabel($merchant, $platformKey);
        $details = is_array($store['details_and_offers'] ?? null) ? $store['details_and_offers'] : [];
        $specs = array_values(array_filter(array_map(
            fn ($line) => is_string($line) ? trim($line) : null,
            $details,
        )));

        return [
            'id' => 'serp-store-'.md5($merchant.'|'.$url),
            'title' => $title,
            'image' => $images[0] ?? 'https://images.unsplash.com/photo-1472851294608-062f824d2349?w=800&q=80',
            'images' => $images,
            'price' => $price,
            'currency' => UniversalMarketplaceBridge::currencyForCountry($countryCode),
            'location' => $countryLabel,
            'country_code' => $countryCode,
            'store' => $merchant,
            'brand' => $brand !== '' ? mb_strtolower($brand) : null,
            'condition' => 'new',
            'url' => $url,
            'source' => $sourceLabel,
            'source_key' => $platformKey,
            'affiliate_ready' => true,
            'sponsored' => false,
            'tags' => ['google_shopping', 'live', 'bridge', 'merchant_offer'],
            'live' => true,
            'rating' => isset($store['rating']) ? (float) $store['rating'] : null,
            'reviews' => isset($store['reviews']) ? (int) $store['reviews'] : null,
            'shipping' => isset($store['shipping']) ? (string) $store['shipping'] : null,
            'specs' => $specs,
        ];
    }

    private function mapMerchantToPlatformKey(string $merchant, string $url, string $countryCode): string
    {
        $haystack = mb_strtolower($merchant.' '.parse_url($url, PHP_URL_HOST));
        $suffix = match (strtoupper($countryCode)) {
            'CH' => '_ch',
            'DE' => '_de',
            default => '_de',
        };

        foreach (self::MERCHANT_PLATFORM_KEYS as $needle => $key) {
            if (str_contains($haystack, $needle)) {
                if (str_ends_with($key, '_de') && $suffix === '_ch') {
                    $chKey = str_replace('_de', '_ch', $key);

                    return LivePlatformRegistry::platform($chKey) !== null ? $chKey : $key;
                }

                return $key;
            }
        }

        if (str_contains($haystack, 'hornbach')) {
            return 'hornbach'.$suffix;
        }
        if (str_contains($haystack, 'obi')) {
            return 'obi'.$suffix;
        }
        if (str_contains($haystack, 'home24')) {
            return 'home24'.$suffix;
        }
        if (str_contains($haystack, 'xxxlutz')) {
            return 'xxxlutz'.$suffix;
        }

        return 'google_shopping';
    }

    private function merchantSourceLabel(string $merchant, string $platformKey): string
    {
        if ($platformKey !== 'google_shopping') {
            $label = LivePlatformRegistry::label($platformKey);
            if ($label !== '' && $label !== $platformKey) {
                return $label;
            }
        }

        return $merchant;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalize(array $item, string $countryCode = 'US'): array
    {
        $countryCode = strtoupper($countryCode);
        $price = $item['extracted_price'] ?? $item['price'] ?? 0;
        if (is_string($price)) {
            $price = (float) preg_replace('/[^\d.]/', '', $price);
        }

        $merchant = trim((string) ($item['source'] ?? ''));
        $countryLabel = SearchCountryResolver::countryNameForCode($countryCode)
            ?? match ($countryCode) {
                'DE' => 'Germany',
                'CH' => 'Switzerland',
                'IT' => 'Italy',
                'FR' => 'France',
                'AT' => 'Austria',
                default => $countryCode,
            };

        $images = $this->extractImages($item);
        $url = (string) ($item['link'] ?? $item['product_link'] ?? 'https://www.google.com/shopping');
        $platformKey = $this->isGoogleShoppingRedirect($url)
            ? 'google_shopping'
            : $this->mapMerchantToPlatformKey($merchant, $url, $countryCode);

        return [
            'id' => 'serp-'.md5(($item['product_id'] ?? $item['title'] ?? uniqid())),
            'title' => $item['title'] ?? 'Product',
            'image' => $images[0] ?? 'https://images.unsplash.com/photo-1472851294608-062f824d2349?w=800&q=80',
            'images' => $images,
            'price' => (float) $price,
            'currency' => UniversalMarketplaceBridge::currencyForCountry($countryCode),
            'location' => $countryLabel,
            'country_code' => $countryCode,
            'store' => $merchant !== '' ? $merchant : null,
            'condition' => 'new',
            'url' => $url,
            'source' => $this->merchantSourceLabel($merchant, $platformKey),
            'source_key' => $platformKey !== 'google_shopping' ? $platformKey : $this->resolveSourceKey($item, $countryCode),
            'affiliate_ready' => true,
            'sponsored' => false,
            'tags' => ['google_shopping', 'live', 'bridge'],
            'live' => true,
            'rating' => isset($item['rating']) ? (float) $item['rating'] : null,
            'reviews' => isset($item['reviews']) ? (int) $item['reviews'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<int, string>
     */
    private function extractImages(array $item): array
    {
        $images = [];

        foreach (['thumbnails', 'serpapi_thumbnails'] as $field) {
            if (! is_array($item[$field] ?? null)) {
                continue;
            }

            foreach ($item[$field] as $url) {
                if (is_string($url) && $url !== '') {
                    $images[] = $url;
                }
            }
        }

        foreach (['thumbnail', 'serpapi_thumbnail'] as $field) {
            $url = $item[$field] ?? null;
            if (is_string($url) && $url !== '') {
                $images[] = $url;
            }
        }

        return array_values(array_unique($images));
    }

    private function immersiveApiUrl(string $apiUrl): string
    {
        $key = urlencode((string) config('serpapi.api_key'));

        return $apiUrl.(str_contains($apiUrl, '?') ? '&' : '?').'api_key='.$key;
    }

    private function isGoogleShoppingRedirect(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';

        return str_contains($host, 'google.');
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
