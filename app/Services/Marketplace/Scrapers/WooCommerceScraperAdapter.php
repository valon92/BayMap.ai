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
        if (preg_match('/woocommerce-Price-amount amount">\s*<bdi>\s*(\d+[.,]\d+)/', $chunk, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        if (preg_match('/<ins[^>]*>[\s\S]{0,400}?woocommerce-Price-currencySymbol[^>]*>.*?<\/span>\s*(\d+[.,]\d+)/', $chunk, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return 0.0;
    }
}
