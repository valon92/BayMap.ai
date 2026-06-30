<?php

namespace App\Services\Marketplace;

use App\Support\CategoryCatalog;
use App\Support\FashionFilterCatalog;
use App\Support\FashionIntentParser;
use App\Support\IndustrialB2BIntentParser;
use App\Support\SearchCountryResolver;
use App\Support\UniversalMarketplaceBridge;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Channel3 universal product catalog — semantic search across retailers.
 *
 * @see https://docs.trychannel3.com/api-reference/v1/search
 */
class Channel3SearchService
{
    public function __construct(private MarketplaceQueryBuilder $queryBuilder) {}

    public function isConfigured(): bool
    {
        return (bool) config('channel3.enabled') && ! empty(config('channel3.api_key'));
    }

    public function getSourceName(): string
    {
        return 'Channel3';
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

        $category = CategoryCatalog::normalize($parsedQuery['category'] ?? '');
        if (in_array($category, ['travel', 'automotive', 'automotive_parts', 'real_estate'], true)) {
            return [];
        }

        $query = $this->buildQuery($parsedQuery, $expandedFilters);
        if ($query === '') {
            return [];
        }

        $countryCode = strtoupper((string) ($parsedQuery['search_country_code'] ?? ''));
        $channel3Country = $this->mapCountry($countryCode);
        $currency = UniversalMarketplaceBridge::currencyForCountry($countryCode !== '' ? $countryCode : 'US');

        $config = array_filter([
            'country' => $channel3Country,
            'currency' => $this->mapCurrency($currency),
        ]);

        $filters = $this->buildFilters($parsedQuery);

        $body = [
            'query' => $query,
            'limit' => (int) config('channel3.limit', 20),
        ];

        if ($config !== []) {
            $body['config'] = $config;
        }

        if ($filters !== []) {
            $body['filters'] = $filters;
        }

        $cacheKey = 'channel3:search:v2:'.md5(json_encode($body));
        $ttl = (int) config('channel3.cache_ttl_seconds', 300);

        try {
            $payload = Cache::get($cacheKey);
            if (! is_array($payload)) {
                $response = Http::timeout((int) config('channel3.timeout', 25))
                    ->withHeaders([
                        'x-api-key' => (string) config('channel3.api_key'),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])
                    ->post(rtrim((string) config('channel3.base_url'), '/').'/v1/search', $body);

                if (! $response->successful()) {
                    Log::warning('Channel3 search failed', [
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
            Log::warning('Channel3 exception', ['error' => $e->getMessage()]);

            return [];
        }

        if (! is_array($payload)) {
            return [];
        }

        $listings = $this->listingsFromPayload($payload, $countryCode);

        if ($listings === [] && empty($body['config']['keyword_search_only'])) {
            $retryBody = $body;
            $retryBody['config'] = array_merge($body['config'] ?? [], ['keyword_search_only' => true]);
            $retryKey = 'channel3:search:v2:retry:'.md5(json_encode($retryBody));

            try {
                $retryPayload = Cache::get($retryKey);
                if (! is_array($retryPayload)) {
                    $response = Http::timeout((int) config('channel3.timeout', 25))
                        ->withHeaders([
                            'x-api-key' => (string) config('channel3.api_key'),
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ])
                        ->post(rtrim((string) config('channel3.base_url'), '/').'/v1/search', $retryBody);

                    if ($response->successful()) {
                        $retryPayload = $response->json();
                        if (is_array($retryPayload)) {
                            Cache::put($retryKey, $retryPayload, $ttl);
                        }
                    }
                }

                if (is_array($retryPayload)) {
                    $listings = $this->listingsFromPayload($retryPayload, $countryCode);
                }
            } catch (\Throwable $e) {
                Log::warning('Channel3 keyword retry failed', ['error' => $e->getMessage()]);
            }
        }

        return $listings;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function listingsFromPayload(array $payload, string $countryCode): array
    {
        $products = $payload['products'] ?? [];
        if (! is_array($products)) {
            return [];
        }

        $listings = [];
        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            foreach ($this->normalizeProduct($product, $countryCode) as $listing) {
                $listings[] = $listing;
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
            $expandedFilters['location_suffix'] ?? null,
        ));

        $query = $this->dedupeQueryWords($query);

        if (in_array(CategoryCatalog::normalize($parsedQuery['category'] ?? ''), ['fashion', 'sports_outdoor'], true)) {
            $query = $this->enrichFashionSearchQuery($query, $parsedQuery);
        }

        if (CategoryCatalog::normalize($parsedQuery['category'] ?? '') === 'industrial_b2b') {
            $term = IndustrialB2BIntentParser::searchTerm(
                $parsedQuery,
                (string) ($parsedQuery['raw_query'] ?? ''),
            );
            if ($term !== '') {
                return $term;
            }
        }

        return $query;
    }

    private function dedupeQueryWords(string $query): string
    {
        $seen = [];
        $out = [];

        foreach (preg_split('/\s+/u', trim($query)) ?: [] as $word) {
            $word = trim($word);
            if ($word === '') {
                continue;
            }

            $key = mb_strtolower($word);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[] = $word;
        }

        return implode(' ', $out);
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     */
    private function enrichFashionSearchQuery(string $query, array $parsedQuery): string
    {
        $marketplaceQuery = FashionIntentParser::marketplaceQuery($parsedQuery);
        if ($marketplaceQuery !== '') {
            return $marketplaceQuery;
        }

        $terms = preg_split('/\s+/u', trim($query)) ?: [];
        $type = FashionIntentParser::normalizeType((string) ($parsedQuery['product_type'] ?? ''));
        if ($type === '' && count($terms) === 1) {
            $type = FashionIntentParser::normalizeType((string) $terms[0]);
        }

        $boost = [];
        $gender = mb_strtolower((string) ($parsedQuery['gender'] ?? ''));
        if (in_array($gender, ['women', 'female', 'femra'], true)
            && ! preg_match('/\b(women|woman|female|femra|ladies)\b/ui', $query)) {
            $boost[] = 'women';
        } elseif (in_array($gender, ['men', 'male', 'meshkuj'], true)
            && ! preg_match('/\b(men|man|male|meshkuj)\b/ui', $query)) {
            $boost[] = 'men';
        }

        if ($type !== '') {
            foreach ($this->fashionQueryBoostTerms($type) as $needle) {
                if (! str_contains(mb_strtolower($query), mb_strtolower($needle))) {
                    $boost[] = $needle;
                }
            }
        }

        if ($boost === []) {
            return $query;
        }

        return trim($query.' '.implode(' ', array_unique($boost)));
    }

    /**
     * @return array<int, string>
     */
    private function fashionQueryBoostTerms(string $type): array
    {
        return match ($type) {
            'blouse' => ['dress shirt', 'button down'],
            'shirt', 't_shirt', 'polo_shirt' => ['top'],
            'dress' => ['gown'],
            'sneakers', 'running_shoes' => ['shoes'],
            'boots', 'sandals', 'loafers' => ['shoes'],
            'jeans', 'pants', 'chinos' => ['trousers'],
            'jacket', 'coat', 'blazer' => ['outerwear'],
            'handbag', 'backpack', 'tote_bag' => ['bag'],
            default => [str_replace('_', ' ', $type)],
        };
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @return array<string, mixed>
     */
    private function buildFilters(array $parsedQuery): array
    {
        $filters = [];

        $gender = mb_strtolower((string) ($parsedQuery['gender'] ?? ''));
        if (in_array($gender, ['women', 'female', 'femra'], true)) {
            $filters['gender'] = 'female';
        } elseif (in_array($gender, ['men', 'male', 'meshkuj'], true)) {
            $filters['gender'] = 'male';
        }

        $maxPrice = $parsedQuery['max_price'] ?? null;
        if (is_numeric($maxPrice) && (float) $maxPrice > 0) {
            $filters['price'] = ['max_price' => (float) $maxPrice];
        }

        return $filters;
    }

    private function mapCountry(string $countryCode): ?string
    {
        $countryCode = strtoupper($countryCode);
        $direct = ['US', 'GB', 'EU', 'AU', 'CA', 'IE', 'DE', 'AT', 'FR', 'BE', 'IT', 'ES', 'NL', 'SE', 'FI', 'PT', 'CZ', 'GR', 'RO'];
        if (in_array($countryCode, $direct, true)) {
            return $countryCode;
        }

        if ($countryCode === 'UK') {
            return 'GB';
        }

        $euRegion = [
            'XK', 'AL', 'MK', 'RS', 'BA', 'ME', 'HR', 'SI', 'SK', 'HU', 'BG', 'PL', 'LU', 'LT', 'LV', 'EE', 'CH', 'NO', 'DK',
        ];
        if (in_array($countryCode, $euRegion, true)) {
            return 'EU';
        }

        return null;
    }

    private function mapCurrency(string $currency): ?string
    {
        $currency = strtoupper($currency);
        $supported = ['USD', 'CAD', 'AUD', 'GBP', 'EUR', 'SEK', 'CZK', 'RON'];

        return in_array($currency, $supported, true) ? $currency : 'EUR';
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<int, array<string, mixed>>
     */
    private function normalizeProduct(array $product, string $countryCode): array
    {
        $title = trim((string) ($product['title'] ?? ''));
        if ($title === '') {
            return [];
        }

        $productId = (string) ($product['id'] ?? md5($title));
        $brand = '';
        if (! empty($product['brands'][0]['name'])) {
            $brand = (string) $product['brands'][0]['name'];
        }

        $image = $this->pickImage($product);
        $categorySlug = is_array($product['category'] ?? null)
            ? (string) (($product['category']['slug'] ?? '') ?: ($product['category']['title'] ?? ''))
            : '';

        $countryLabel = SearchCountryResolver::countryNameForCode($countryCode) ?? $countryCode;
        $gender = match ((string) ($product['gender'] ?? '')) {
            'female' => 'women',
            'male' => 'men',
            'unisex' => 'unisex',
            default => null,
        };

        $offers = is_array($product['offers'] ?? null) ? $product['offers'] : [];
        if ($offers === []) {
            return [];
        }

        usort($offers, function (array $a, array $b): int {
            $priceA = (float) (($a['price']['price'] ?? PHP_FLOAT_MAX));
            $priceB = (float) (($b['price']['price'] ?? PHP_FLOAT_MAX));
            $stockA = ($a['availability'] ?? '') === 'InStock' ? 0 : 1;
            $stockB = ($b['availability'] ?? '') === 'InStock' ? 0 : 1;
            if ($stockA !== $stockB) {
                return $stockA <=> $stockB;
            }

            return $priceA <=> $priceB;
        });

        foreach ($offers as $offer) {
            if (! is_array($offer)) {
                continue;
            }

            $url = (string) ($offer['url'] ?? '');
            if ($url === '') {
                continue;
            }

            $price = (float) ($offer['price']['price'] ?? 0);
            $currency = (string) ($offer['price']['currency'] ?? 'USD');
            $domain = (string) ($offer['domain'] ?? parse_url($url, PHP_URL_HOST) ?? '');

            return [[
                'id' => 'c3-'.$productId.'-'.md5($url),
                'title' => $title,
                'description' => $product['description'] ?? null,
                'image' => $image,
                'images' => $image !== '' ? [$image] : [],
                'price' => $price,
                'currency' => $currency,
                'location' => $countryLabel,
                'country_code' => $countryCode,
                'store' => $domain,
                'brand' => $brand !== '' ? $brand : null,
                'gender' => $gender,
                'product_type' => $categorySlug !== '' ? FashionFilterCatalog::slugify($categorySlug) : null,
                'condition' => 'new',
                'url' => $url,
                'source' => $domain !== '' ? $domain : 'Channel3',
                'source_key' => 'channel3',
                'affiliate_ready' => true,
                'sponsored' => false,
                'tags' => ['channel3', 'bridge'],
                'live' => false,
                'channel3_product_id' => $productId,
            ]];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function pickImage(array $product): string
    {
        $images = is_array($product['images'] ?? null) ? $product['images'] : [];
        $cleaned = null;
        $main = null;
        $fallback = null;

        foreach ($images as $image) {
            if (! is_array($image) || empty($image['url'])) {
                continue;
            }
            $url = (string) $image['url'];
            if (! empty($image['is_cleaned_image'])) {
                $cleaned = $url;
            }
            if (! empty($image['is_main_image'])) {
                $main = $url;
            }
            $fallback ??= $url;
        }

        return $cleaned ?? $main ?? $fallback ?? 'https://images.unsplash.com/photo-1472851294608-062f824d2349?w=800&q=80';
    }
}
