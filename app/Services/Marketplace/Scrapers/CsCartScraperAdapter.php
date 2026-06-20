<?php

namespace App\Services\Marketplace\Scrapers;

use App\Services\Marketplace\Scrapers\Contracts\ScraperAdapterInterface;
use App\Support\KosovoFashionIntent;
use App\Support\PlatformCatalogUrlBuilder;

class CsCartScraperAdapter implements ScraperAdapterInterface
{
    public function __construct(
        private ScraperHttpClient $http,
        private ProductGalleryEnricher $galleryEnricher,
    ) {}

    public function adapterKey(): string
    {
        return 'cscart';
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsedQuery
     */
    public function scrape(array $platform, array $parsedQuery): array
    {
        $storeKey = (string) ($platform['_key'] ?? 'cscart');

        $catalogUrl = PlatformCatalogUrlBuilder::build($platform, $parsedQuery);
        $itemsPerPage = (int) ($platform['items_per_page'] ?? 32);
        $maxPages = $this->maxPages($platform, $parsedQuery);
        $all = [];
        $seen = [];

        $listingTimeout = (int) config('live_platforms.listing_timeout_seconds', 25);

        for ($page = 1; $page <= $maxPages; $page++) {
            $separator = str_contains($catalogUrl, '?') ? '&' : '?';
            $url = $catalogUrl.$separator.'items_per_page='.$itemsPerPage;
            if ($page > 1) {
                $url .= '&page='.$page;
            }

            $html = $this->http->get($url, $platform['locale'] ?? null, null, null, $listingTimeout);
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

            if (count($batch) < 20) {
                break;
            }
        }

        return $this->galleryEnricher->enrichFromDetailPages(
            ProductListingNormalizer::filterForIntent($all, $parsedQuery),
            isset($platform['locale']) ? (string) $platform['locale'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsedQuery
     */
    private function maxPages(array $platform, array $parsedQuery): int
    {
        $brand = mb_strtolower((string) ($parsedQuery['brand'] ?? ''));
        $hashes = (array) ($platform['brand_hashes'] ?? []);
        $hasBrandHash = $brand !== '' && isset($hashes[$brand]);
        $footwear = KosovoFashionIntent::isFootwearType((string) ($parsedQuery['product_type'] ?? ''));

        if ($hasBrandHash || ($footwear && ! empty($parsedQuery['size']))) {
            return 1;
        }

        if (! empty($parsedQuery['size']) || ! empty($parsedQuery['max_price'])) {
            return 2;
        }

        return 2;
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    private function parseProducts(string $html, string $storeKey, array $platform): array
    {
        $chunks = preg_split('/(?=<div class="ut2-gl__item)/', $html) ?: [];
        $products = [];
        $baseHost = parse_url((string) ($platform['base_url'] ?? ''), PHP_URL_HOST) ?: '';

        foreach ($chunks as $chunk) {
            if (! preg_match('/data-ca-product-id="(\d+)"/', $chunk, $idMatch)) {
                continue;
            }

            $productId = $idMatch[1];
            $title = null;
            $url = null;

            if (preg_match('/class="product-title"[^>]*title="([^"]+)"/', $chunk, $m)) {
                $title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('/class="product-title"[^>]*>\s*<a[^>]*>\s*<span>([^<]+)/s', $chunk, $m)) {
                $title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            if (preg_match('/href="(https?:\/\/[^"]+)"[^>]*class="product-title"/', $chunk, $m)) {
                $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('/class="product-title"[^>]*href="([^"]+)"/', $chunk, $m)) {
                $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            if ($title === null || $title === '') {
                continue;
            }

            $price = $this->parsePrice($chunk, $productId);
            $image = null;
            if (preg_match('/<img[^>]+(?:src|data-src)="([^"]+)"/', $chunk, $m)) {
                $image = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            $products[] = ProductListingNormalizer::finalize($platform, $storeKey, [
                'product_id' => $productId,
                'title' => $title,
                'url' => $url ?: ($platform['base_url'] ?? '#'),
                'image' => $image,
                'price' => $price,
                'sizes' => $this->parseSizes($chunk),
            ]);
        }

        return $products;
    }

    private function parsePrice(string $chunk, string $productId): float
    {
        if (preg_match('/id="sec_discounted_price_'.$productId.'"[^>]*>(\d+)<sup[^>]*>(\d+)/', $chunk, $m)) {
            return round(((int) $m[1]) + ((int) $m[2]) / 100, 2);
        }

        if (preg_match('/id="line_discounted_price_'.$productId.'"[^>]*>.*?(\d+)<sup[^>]*>(\d+)/s', $chunk, $m)) {
            return round(((int) $m[1]) + ((int) $m[2]) / 100, 2);
        }

        return 0.0;
    }

    /**
     * @return array<int, string>
     */
    private function parseSizes(string $chunk): array
    {
        if (! preg_match('/ut2-lv__features-description">(?:Madhesia|Größe|Size)<\/div>(.*?)<\/div>\s*<\/div>/s', $chunk, $block)) {
            return [];
        }

        preg_match_all('/<(?:span|a|label)[^>]*>\s*([^<]{1,12})\s*<\/(?:span|a|label)>/', $block[1], $matches);

        $sizes = [];
        foreach ($matches[1] ?? [] as $raw) {
            $size = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($size === '' || preg_match('/^(\+|\-|madh|ngjy|size)/iu', $size)) {
                continue;
            }
            if (preg_match('/^\d+(\.\d+)?$|^(\d+\s*\/\s*\d+)|^[XSML]{1,3}$|^\d{2,3}$/iu', $size)) {
                $sizes[] = $size;
            }
        }

        return array_values(array_unique($sizes));
    }
}
