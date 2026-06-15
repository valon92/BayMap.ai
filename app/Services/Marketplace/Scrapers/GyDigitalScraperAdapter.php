<?php

namespace App\Services\Marketplace\Scrapers;

use App\Services\Marketplace\Scrapers\Contracts\ScraperAdapterInterface;
use App\Support\KosovoToyIntent;
use App\Support\PlatformCatalogUrlBuilder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GY Digital storefronts (e.g. Jumbo Kosovo) — JSON autocomplete search API.
 */
class GyDigitalScraperAdapter implements ScraperAdapterInterface
{
    private const MAX_LISTINGS = 32;

    public function adapterKey(): string
    {
        return 'gy_digital';
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsedQuery
     */
    public function scrape(array $platform, array $parsedQuery): array
    {
        $storeKey = (string) ($platform['_key'] ?? 'gy_digital');
        $all = [];
        $seen = [];

        foreach ($this->searchQueries($platform, $parsedQuery) as $query) {
            foreach ($this->fetchProducts($platform, $query) as $product) {
                $code = (string) ($product['code'] ?? md5((string) ($product['title'] ?? '')));
                if ($code === '' || isset($seen[$code])) {
                    continue;
                }
                $seen[$code] = true;

                $item = $this->mapProduct($platform, $storeKey, $product, $parsedQuery);
                if ($item !== null) {
                    $all[] = $item;
                }

                if (count($all) >= self::MAX_LISTINGS) {
                    break 2;
                }
            }
        }

        return ProductListingNormalizer::filterForIntent($all, $parsedQuery);
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, string>
     */
    private function searchQueries(array $platform, array $parsedQuery): array
    {
        if (! empty($platform['toy_retailer']) && KosovoToyIntent::isToySearch($parsedQuery)) {
            return KosovoToyIntent::searchTerms($parsedQuery);
        }

        return [PlatformCatalogUrlBuilder::searchTerm($platform, $parsedQuery)];
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    private function fetchProducts(array $platform, string $query): array
    {
        $baseUrl = rtrim((string) ($platform['base_url'] ?? ''), '/');
        $locale = (string) ($platform['locale'] ?? 'sq');
        $template = (string) ($platform['search_api_template'] ?? '/{locale}/products/autocomplete.json?query={query}');
        $url = $baseUrl.str_replace(
            ['{locale}', '{query}'],
            [$locale, rawurlencode($query)],
            $template,
        );

        $payload = $this->fetchJson($url, $baseUrl.'/'.$locale);
        if ($payload === null || ! isset($payload['products']) || ! is_array($payload['products'])) {
            return [];
        }

        return $payload['products'];
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsedQuery
     * @return array<string, mixed>|null
     */
    private function mapProduct(array $platform, string $storeKey, array $product, array $parsedQuery): ?array
    {
        $title = trim((string) ($product['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $baseUrl = rtrim((string) ($platform['base_url'] ?? ''), '/');
        $path = (string) ($product['path'] ?? '');
        $productUrl = $path !== ''
            ? (str_starts_with($path, 'http') ? $path : $baseUrl.$path)
            : $baseUrl;

        $priceRaw = (string) ($product['discounted_price'] ?? $product['price'] ?? '');
        $productType = mb_strtolower((string) ($parsedQuery['product_type'] ?? 'toy'));

        return ProductListingNormalizer::finalize($platform, $storeKey, [
            'product_id' => (string) ($product['code'] ?? md5($title)),
            'title' => $title,
            'url' => $productUrl,
            'image' => $this->normalizeImageUrl($product['photo'] ?? null),
            'price' => $this->parsePrice($priceRaw),
            'product_type' => $productType,
            'category' => 'gaming_entertainment',
        ]);
    }

    private function normalizeImageUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        }

        if (str_contains($url, 's3.gy.digital') && str_contains($url, 'tiny_')) {
            $url = preg_replace('/\/tiny_/', '/', $url) ?? $url;
        }

        return $url;
    }

    private function fetchJson(string $url, string $referer): ?array
    {
        try {
            $response = Http::timeout((int) config('live_platforms.timeout_seconds', 60))
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'Accept' => 'application/json',
                    'Accept-Language' => 'sq-AL,sq;q=0.9,en;q=0.8',
                    'Referer' => $referer,
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('GY Digital fetch failed', ['url' => $url, 'status' => $response->status()]);

                return null;
            }

            $data = $response->json();

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::warning('GY Digital fetch error', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function parsePrice(string $raw): float
    {
        $clean = preg_replace('/[^\d,.]/', '', $raw) ?? '';
        if ($clean === '') {
            return 0.0;
        }

        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace('.', '', $clean);
        }

        return (float) str_replace(',', '.', $clean);
    }
}
