<?php

namespace App\Services\Marketplace\Scrapers;

use App\Services\Marketplace\Scrapers\Contracts\ScraperAdapterInterface;
use App\Support\KosovoFashionIntent;
use App\Support\PlatformCatalogUrlBuilder;

class WooCommerceScraperAdapter implements ScraperAdapterInterface
{
    public function __construct(private ScraperHttpClient $http) {}

    public function adapterKey(): string
    {
        return 'woocommerce';
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsedQuery
     */
    public function scrape(array $platform, array $parsedQuery): array
    {
        $storeKey = (string) ($platform['_key'] ?? 'woocommerce');
        $baseUrl = PlatformCatalogUrlBuilder::build($platform, $parsedQuery);
        $maxPages = $this->maxPages($parsedQuery);
        $all = [];
        $seen = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            $url = $page <= 1
                ? $baseUrl
                : rtrim(explode('?', $baseUrl, 2)[0], '/').'/page/'.$page.'/'.(str_contains($baseUrl, '?') ? '?'.explode('?', $baseUrl, 2)[1] : '');

            $html = $this->http->get($url, $platform['locale'] ?? null);
            if ($html === '') {
                break;
            }

            $batch = $this->parseProducts($html, $storeKey, $platform);
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

        return ProductListingNormalizer::filterForIntent($all, $parsedQuery);
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     */
    private function maxPages(array $parsedQuery): int
    {
        $footwear = KosovoFashionIntent::isFootwearType((string) ($parsedQuery['product_type'] ?? ''));

        if ($footwear && (! empty($parsedQuery['size']) || ! empty($parsedQuery['brand']))) {
            return 5;
        }

        if (! empty($parsedQuery['size']) || ! empty($parsedQuery['max_price'])) {
            return 3;
        }

        return 8;
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    private function parseProducts(string $html, string $storeKey, array $platform): array
    {
        $chunks = preg_split('/(?=class="[^"]*type-product[^"]*post-|class="product type-product post-)/', $html) ?: [];
        if (count($chunks) <= 1) {
            $chunks = preg_split('/(?=post-\d+[^"]*type-product|type-product post-\d+)/', $html) ?: [];
        }
        $products = [];

        foreach ($chunks as $chunk) {
            if (! preg_match('/(?:product type-product post-|type-product post-|post-(\d+)[^"]*type-product)/', $chunk, $idMatch)) {
                continue;
            }

            $productId = $idMatch[1] ?? null;
            if ($productId === null && preg_match('/post-(\d+)/', $chunk, $idFallback)) {
                $productId = $idFallback[1];
            }
            if ($productId === null) {
                continue;
            }

            $title = null;
            $url = null;

            if (preg_match('/woocommerce-loop-product__title[^>]*>\s*<a[^>]*href="([^"]+)"[^>]*>\s*([^<]+?)\s*<\/a>/s', $chunk, $titleMatch)) {
                $title = html_entity_decode(trim($titleMatch[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $url = html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('/class="[^"]*woocommerce-loop-product__link[^"]*"[^>]*href="([^"]+)"[^>]*>\s*<h2[^>]*>\s*([^<]+?)\s*<\/h2>/s', $chunk, $titleMatch)) {
                $url = html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $title = html_entity_decode(trim($titleMatch[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            if ($title === null || $title === '') {
                continue;
            }
            $image = null;

            if (preg_match('/<img[^>]+src="([^"]+)"/', $chunk, $imgMatch)) {
                $image = html_entity_decode($imgMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            $products[] = ProductListingNormalizer::finalize($platform, $storeKey, [
                'product_id' => $productId,
                'title' => $title,
                'url' => $url,
                'image' => $image,
                'price' => $this->parsePrice($chunk),
            ]);
        }

        return $products;
    }

    private function parsePrice(string $chunk): float
    {
        if (preg_match('/woocommerce-Price-amount amount[^>]*>\s*<bdi>([\s\S]{0,160}?)<\/bdi>/', $chunk, $m)) {
            if (preg_match('/(\d[\d.\']*(?:,\d{2})?)/', html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $num)) {
                return $this->normalizeEuropeanPrice($num[1]);
            }
        }

        if (preg_match('/woocommerce-Price-amount amount">\s*<bdi>\s*(\d+[.,]\d+)/', $chunk, $m)) {
            return $this->normalizeEuropeanPrice($m[1]);
        }

        if (preg_match('/<ins[^>]*>[\s\S]{0,400}?woocommerce-Price-currencySymbol[^>]*>.*?<\/span>\s*(\d+[.,]\d+)/', $chunk, $m)) {
            return $this->normalizeEuropeanPrice($m[1]);
        }

        if (preg_match('/class="price"[^>]*>[\s\S]{0,300}?(\d+[.,]\d{2})/', $chunk, $m)) {
            return $this->normalizeEuropeanPrice($m[1]);
        }

        return 0.0;
    }

    private function normalizeEuropeanPrice(string $raw): float
    {
        $raw = trim(str_replace(["'", ' '], '', $raw));
        if ($raw === '') {
            return 0.0;
        }

        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
        }

        return (float) str_replace(',', '.', $raw);
    }
}
