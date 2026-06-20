<?php

namespace App\Services\Marketplace\Scrapers;

use App\Services\Marketplace\Scrapers\Contracts\ScraperAdapterInterface;
use App\Support\KosovoFashionIntent;
use App\Support\KosovoToyIntent;
use App\Support\PlatformCatalogUrlBuilder;

class WooCommerceScraperAdapter implements ScraperAdapterInterface
{
    public function __construct(
        private ScraperHttpClient $http,
        private ProductGalleryEnricher $galleryEnricher,
    ) {}

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
        $all = [];
        $seen = [];
        $listingTimeout = (int) config('live_platforms.listing_timeout_seconds', 25);

        foreach ($this->urlsToScrape($platform, $parsedQuery) as $baseUrl) {
            $maxPages = $this->maxPages($parsedQuery);

            for ($page = 1; $page <= $maxPages; $page++) {
                $url = $page <= 1
                    ? $baseUrl
                    : rtrim(explode('?', $baseUrl, 2)[0], '/').'/page/'.$page.'/'.(str_contains($baseUrl, '?') ? '?'.explode('?', $baseUrl, 2)[1] : '');

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

                if (count($batch) < 8) {
                    break;
                }
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
     * @return array<int, string>
     */
    private function urlsToScrape(array $platform, array $parsedQuery): array
    {
        $base = rtrim((string) ($platform['base_url'] ?? ''), '/');
        $template = (string) ($platform['search_template'] ?? '/?s={query}&post_type=product');
        $urls = [];

        if (! empty($platform['toy_retailer']) && KosovoToyIntent::isToySearch($parsedQuery)) {
            foreach (KosovoToyIntent::searchTerms($parsedQuery) as $term) {
                $urls[] = $base.str_replace('{query}', rawurlencode($term), $template);
            }
        } else {
            $urls[] = PlatformCatalogUrlBuilder::build($platform, $parsedQuery);
        }

        if (! empty($platform['toy_retailer']) && KosovoToyIntent::isToySearch($parsedQuery)) {
            foreach ((array) ($platform['fallback_paths'] ?? []) as $path) {
                $urls[] = $base.rtrim((string) $path, '/').'/';
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     */
    private function maxPages(array $parsedQuery): int
    {
        $footwear = KosovoFashionIntent::isFootwearType((string) ($parsedQuery['product_type'] ?? ''));

        if ($footwear && (! empty($parsedQuery['size']) || ! empty($parsedQuery['brand']))) {
            return 2;
        }

        if (! empty($parsedQuery['size']) || ! empty($parsedQuery['max_price'])) {
            return 2;
        }

        return 4;
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    private function parseProducts(string $html, string $storeKey, array $platform): array
    {
        $chunks = preg_split('/(?=class="[^"]*\btype-product\b[^"]*\bpost-\d+|\bproduct type-product post-\d+)/', $html) ?: [];
        if (count($chunks) <= 1) {
            $chunks = preg_split('/(?=\bpost-\d+[^"]*type-product|\btype-product post-\d+)/', $html) ?: [];
        }
        $products = [];

        foreach ($chunks as $chunk) {
            if (! preg_match('/(?:product type-product post-(\d+)|type-product post-(\d+)|post-(\d+)[^"]*type-product)/', $chunk, $idMatch)) {
                continue;
            }

            $productId = $idMatch[1] ?: ($idMatch[2] ?: ($idMatch[3] ?? null));

            $title = null;
            $url = null;

            if (preg_match('/woocommerce-loop-product__title[^>]*>\s*<a[^>]*href="([^"]+)"[^>]*>\s*([^<]+?)\s*<\/a>/s', $chunk, $titleMatch)) {
                $title = html_entity_decode(trim($titleMatch[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $url = html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('/class="[^"]*woocommerce-loop-product__link[^"]*"[^>]*href="([^"]+)"[^>]*>\s*<h2[^>]*>\s*([^<]+?)\s*<\/h2>/s', $chunk, $titleMatch)) {
                $url = html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $title = html_entity_decode(trim($titleMatch[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('/woocommerce-loop-product__link[^>]*href="([^"]+)"[^>]*>[\s\S]{0,400}?c-product-grid__title-inner[^>]*>\s*([^<]+?)\s*</s', $chunk, $titleMatch)) {
                $url = html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $title = html_entity_decode(trim($titleMatch[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('/class="[^"]*c-product-grid__title-inner[^"]*"[^>]*>\s*([^<]+?)\s*</', $chunk, $titleMatch)) {
                $title = html_entity_decode(trim($titleMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (preg_match('/woocommerce-loop-product__link[^>]*href="([^"]+)"/', $chunk, $urlMatch)) {
                    $url = html_entity_decode($urlMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            } elseif (preg_match('/class="[^"]*product-title[^"]*"[^>]*>\s*<a[^>]*href="([^"]+)"[^>]*>\s*([^<]+?)\s*<\/a>/s', $chunk, $titleMatch)) {
                $url = html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $title = html_entity_decode(trim($titleMatch[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('/aria-label="Image link for Product:\s*([^"]+)"/', $chunk, $titleMatch)) {
                $title = html_entity_decode(trim($titleMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (preg_match('/woocommerce-LoopProduct-link[^>]*href="([^"]+)"/', $chunk, $urlMatch)) {
                    $url = html_entity_decode($urlMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }

            if ($productId === null || $productId === '' || $title === null || $title === '') {
                continue;
            }
            $image = $this->parseImage($chunk);

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

    private function parseImage(string $chunk): ?string
    {
        if (preg_match('/srcset="([^"]+)"/', $chunk, $srcsetMatch)) {
            $best = null;
            $bestWidth = 0;
            foreach (preg_split('/\s*,\s*/', html_entity_decode($srcsetMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: [] as $part) {
                if (preg_match('/^(\S+)\s+(\d+)w$/', trim($part), $m)) {
                    $width = (int) $m[2];
                    if ($width >= $bestWidth) {
                        $bestWidth = $width;
                        $best = $m[1];
                    }
                }
            }
            if ($best !== null) {
                return $best;
            }
        }

        if (preg_match('/<img[^>]+(?:data-src|data-lazy-src|src)="([^"]+)"/', $chunk, $imgMatch)) {
            return html_entity_decode($imgMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
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
