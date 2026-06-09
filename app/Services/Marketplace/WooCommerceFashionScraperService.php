<?php

namespace App\Services\Marketplace;

use App\Support\KosovoFashionLiveStores;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Live WooCommerce catalog scraper — used for Driloni Sportswear and similar Kosovo stores.
 */
class WooCommerceFashionScraperService
{
    private const MAX_PAGES = 8;

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, array<string, mixed>>
     */
    public function searchDriloni(array $parsedQuery): array
    {
        $cacheKey = 'driloni:v1:'.md5(json_encode([
            KosovoFashionLiveStores::driloniSearchUrl($parsedQuery),
            $parsedQuery['brand'] ?? '',
            $parsedQuery['size'] ?? '',
            $parsedQuery['max_price'] ?? '',
        ]));

        return Cache::remember($cacheKey, 600, fn () => $this->fetchDriloni($parsedQuery));
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, array<string, mixed>>
     */
    private function fetchDriloni(array $parsedQuery): array
    {
        $baseUrl = KosovoFashionLiveStores::driloniSearchUrl($parsedQuery);
        $all = [];
        $seen = [];
        $maxPages = $this->maxPages($parsedQuery);

        for ($page = 1; $page <= $maxPages; $page++) {
            $html = $this->fetchPage($baseUrl, $page);
            if ($html === '') {
                break;
            }

            $batch = $this->parseProducts($html, 'driloni');
            if ($batch === []) {
                break;
            }

            foreach ($batch as $item) {
                $key = (string) ($item['id'] ?? '');
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $all[] = $item;
            }

            if (count($batch) < 8) {
                break;
            }
        }

        return $this->filterForIntent($all, $parsedQuery);
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     */
    private function maxPages(array $parsedQuery): int
    {
        if (! empty($parsedQuery['size']) || ! empty($parsedQuery['max_price'])) {
            return 3;
        }

        return self::MAX_PAGES;
    }

    private function fetchPage(string $baseUrl, int $page): string
    {
        if ($page <= 1) {
            $url = $baseUrl;
        } else {
            $parts = explode('?', $baseUrl, 2);
            $url = rtrim($parts[0], '/').'/page/'.$page.'/'.(isset($parts[1]) ? '?'.$parts[1] : '');
        }

        try {
            $response = Http::timeout((int) config('marketplaces.kosovo_fashion_timeout_seconds', 45))
                ->withHeaders([
                    'User-Agent' => 'BuyMap.ai ValonWorker/1.0 (+https://buymap.ai)',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'sq,en;q=0.8',
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Driloni fetch failed', ['url' => $url, 'status' => $response->status()]);

                return '';
            }

            return (string) $response->body();
        } catch (\Throwable $e) {
            Log::warning('Driloni fetch error', ['url' => $url, 'error' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseProducts(string $html, string $store): array
    {
        $chunks = preg_split('/(?=class="product type-product post-)/', $html) ?: [];
        $products = [];

        foreach ($chunks as $chunk) {
            if (! preg_match('/class="product type-product post-(\d+)/', $chunk, $idMatch)) {
                continue;
            }

            $productId = $idMatch[1];

            if (! preg_match('/woocommerce-loop-product__title[^>]*>\s*<a[^>]*href="([^"]+)"[^>]*>\s*([^<]+?)\s*<\/a>/', $chunk, $titleMatch)) {
                continue;
            }

            $title = html_entity_decode(trim($titleMatch[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $url = html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $price = $this->parsePrice($chunk);
            $image = null;

            if (preg_match('/<img[^>]+src="([^"]+)"/', $chunk, $imgMatch)) {
                $image = html_entity_decode($imgMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            $brand = $this->detectBrand($title);
            $productType = $this->detectProductType($title);

            $products[] = [
                'id' => $store.'-'.$productId,
                'store' => $store,
                'title' => $title,
                'image' => $image,
                'price' => $price,
                'currency' => 'EUR',
                'location' => 'Prishtinë, Kosovo',
                'country_code' => 'XK',
                'condition' => 'new',
                'url' => $url,
                'gender' => 'male',
                'brand' => $brand,
                'product_type' => $productType,
                'tags' => array_values(array_filter([
                    'fashion',
                    $store,
                    'kosovo',
                    'meshkuj',
                    $brand,
                    $productType,
                    'live',
                ])),
                'live' => true,
            ];
        }

        return $products;
    }

    private function parsePrice(string $chunk): float
    {
        if (preg_match('/<ins[^>]*>[\s\S]{0,400}?woocommerce-Price-currencySymbol[^>]*>.*?<\/span>\s*(\d+[.,]\d+)/', $chunk, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        if (preg_match('/woocommerce-Price-currencySymbol[^>]*>.*?<\/span>\s*(\d+[.,]\d+)/s', $chunk, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        if (preg_match('/woocommerce-Price-amount amount">\s*<bdi>\s*(\d+[.,]\d+)/', $chunk, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return 0.0;
    }

    private function detectBrand(string $title): ?string
    {
        $upper = mb_strtoupper($title);
        foreach (['PUMA', 'NIKE', 'ADIDAS', 'REEBOK', 'NEW BALANCE', 'TIMBERLAND', 'UNDER ARMOUR'] as $brand) {
            if (str_contains($upper, $brand)) {
                return strtolower(str_replace(' ', '_', $brand));
            }
        }

        return null;
    }

    private function detectProductType(string $title): string
    {
        $lower = mb_strtolower($title);

        return match (true) {
            str_contains($lower, 'sneaker') || str_contains($lower, 'atlete') => 'sneakers',
            str_contains($lower, 'jacket') || str_contains($lower, 'xhaket') => 'jacket',
            str_contains($lower, 'pant') || str_contains($lower, 'track') => 'pants',
            str_contains($lower, 'suit') || str_contains($lower, 'trenerk') => 'tracksuit',
            str_contains($lower, 'short') || str_contains($lower, 'shorce') => 'shorts',
            str_contains($lower, 'tee') || str_contains($lower, 'shirt') || str_contains($lower, 'bluz') => 'shirt',
            default => 'fashion',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $parsed
     * @return array<int, array<string, mixed>>
     */
    private function filterForIntent(array $items, array $parsed): array
    {
        $brand = mb_strtolower((string) ($parsed['brand'] ?? ''));
        if ($brand === '') {
            return $items;
        }

        return array_values(array_filter($items, function (array $item) use ($brand) {
            $title = mb_strtolower($item['title'] ?? '');
            $itemBrand = mb_strtolower((string) ($item['brand'] ?? ''));

            return str_contains($title, $brand) || $itemBrand === $brand;
        }));
    }
}
