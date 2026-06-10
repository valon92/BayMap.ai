<?php

namespace App\Services\Marketplace\Scrapers;

use App\Services\Marketplace\Scrapers\Contracts\ScraperAdapterInterface;
use App\Support\PlatformCatalogUrlBuilder;

class GenericHtmlScraperAdapter implements ScraperAdapterInterface
{
    public function __construct(
        private ScraperHttpClient $http,
        private CsCartScraperAdapter $csCart,
        private WooCommerceScraperAdapter $wooCommerce,
    ) {}

    public function adapterKey(): string
    {
        return 'generic';
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsedQuery
     */
    public function scrape(array $platform, array $parsedQuery): array
    {
        $storeKey = (string) ($platform['_key'] ?? 'generic');
        $searchUrl = PlatformCatalogUrlBuilder::build($platform, $parsedQuery);
        $html = $this->http->get($searchUrl, $platform['locale'] ?? null);

        if ($html === '') {
            return [];
        }

        if (str_contains($html, 'ut2-gl__item') && str_contains($html, 'data-ca-product-id')) {
            return $this->csCart->scrape($platform, $parsedQuery);
        }

        if (str_contains($html, 'woocommerce-loop-product__title')) {
            return $this->wooCommerce->scrape($platform, $parsedQuery);
        }

        $items = $this->parseHeuristic($html, $storeKey, $platform);

        return ProductListingNormalizer::filterForIntent($items, $parsedQuery);
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    private function parseHeuristic(string $html, string $storeKey, array $platform): array
    {
        $products = [];
        $seen = [];

        $patterns = [
            '/<a[^>]+href="([^"]+)"[^>]*class="[^"]*product[^"]*"[^>]*>[\s\S]{0,800}?<[^>]+>([^<]{4,120})<\//i',
            '/<a[^>]+class="[^"]*product[^"]*"[^>]+href="([^"]+)"[^>]*>[\s\S]{0,800}?<[^>]+>([^<]{4,120})<\//i',
            '/data-product[^>]*data-name="([^"]+)"[^>]*data-url="([^"]+)"/i',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $m) {
                if (count($m) >= 3 && str_starts_with($m[1], 'http')) {
                    $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $title = html_entity_decode(trim($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                } else {
                    $title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $url = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }

                if (strlen($title) < 4 || isset($seen[$title])) {
                    continue;
                }

                $seen[$title] = true;
                $price = 0.0;
                if (preg_match('/(\d+[.,]\d{2})\s*(?:€|EUR)/u', $html, $pm)) {
                    $price = (float) str_replace(',', '.', $pm[1]);
                }

                $products[] = ProductListingNormalizer::finalize($platform, $storeKey, [
                    'product_id' => md5($url.$title),
                    'title' => $title,
                    'url' => $url,
                    'price' => $price,
                ]);

                if (count($products) >= 32) {
                    break 2;
                }
            }
        }

        return $products;
    }
}
