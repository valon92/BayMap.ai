<?php

namespace App\Services\Marketplace\Scrapers;

use App\Support\ListingEnricher;

/**
 * Fetches multi-image galleries from product detail pages (JSON-LD, CS-Cart, WooCommerce).
 */
class ProductGalleryEnricher
{
    public function __construct(private ScraperHttpClient $http) {}

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    public function enrichFromDetailPages(array $products, ?string $locale = null, ?int $limit = null): array
    {
        if (! config('live_platforms.gallery_enrich_enabled', true)) {
            return $products;
        }

        $limit ??= (int) config('live_platforms.gallery_enrich_max_products', 10);
        $budgetSeconds = (int) config('live_platforms.gallery_enrich_time_budget_seconds', 25);
        $deadline = microtime(true) + max(5, $budgetSeconds);
        $enriched = 0;

        foreach ($products as &$product) {
            if ($enriched >= $limit || microtime(true) >= $deadline) {
                break;
            }

            $existing = is_array($product['images'] ?? null) ? count($product['images']) : 0;
            if ($existing > 1) {
                continue;
            }

            $url = trim((string) ($product['url'] ?? ''));
            if ($url === '' || $url === '#') {
                continue;
            }

            $images = $this->imagesFromDetailPage($url, $locale);
            if (count($images) <= 1) {
                continue;
            }

            $product['images'] = $images;
            $product['image'] = $images[0];
            $enriched++;
        }
        unset($product);

        return $products;
    }

    /**
     * @return array<int, string>
     */
    private function imagesFromDetailPage(string $url, ?string $locale): array
    {
        $timeout = (int) config('live_platforms.gallery_enrich_timeout_seconds', 12);
        $html = $this->http->get($url, $locale, null, null, $timeout);
        if ($html === '') {
            return [];
        }

        $images = $this->imagesFromJsonLdBlocks($html);
        if (count($images) > 1) {
            return $this->filterProductImages($images);
        }

        if (preg_match_all('/cm-previewer[^>]*href="([^"]+\/detailed\/[^"]+)"/i', $html, $matches)) {
            $images = array_merge($images, $matches[1]);
        }

        if (preg_match_all('/data-large_image="([^"]+)"/i', $html, $matches)) {
            $images = array_merge($images, $matches[1]);
        }

        if (preg_match_all('/woocommerce-product-gallery[^>]*data-thumb="([^"]+)"/i', $html, $matches)) {
            $images = array_merge($images, $matches[1]);
        }

        return $this->filterProductImages($images);
    }

    /**
     * @return array<int, string>
     */
    private function imagesFromJsonLdBlocks(string $html): array
    {
        if (! preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/si', $html, $blocks)) {
            return [];
        }

        $images = [];

        foreach ($blocks[1] as $json) {
            $data = json_decode(html_entity_decode(trim($json), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            if (! is_array($data)) {
                continue;
            }

            foreach ($this->productNodesFromJsonLd($data) as $node) {
                $images = array_merge($images, ListingEnricher::imagesFromJsonLd($node['image'] ?? null));
            }
        }

        return $images;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function productNodesFromJsonLd(array $data): array
    {
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            $nodes = [];
            foreach ($data['@graph'] as $node) {
                if (is_array($node) && $this->isProductNode($node)) {
                    $nodes[] = $node;
                }
            }

            return $nodes;
        }

        if ($this->isProductNode($data)) {
            return [$data];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function isProductNode(array $node): bool
    {
        $type = $node['@type'] ?? '';

        if (is_array($type)) {
            return in_array('Product', $type, true);
        }

        return (string) $type === 'Product';
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    private function filterProductImages(array $urls): array
    {
        $clean = [];

        foreach ($urls as $url) {
            $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($url === '' || ! str_starts_with($url, 'http')) {
                continue;
            }

            $lower = mb_strtolower($url);
            if (str_contains($lower, 'facebook.com/tr')
                || str_contains($lower, '/logos/')
                || str_contains($lower, 'menu-with-icon')
                || str_contains($lower, 'placeholder')
                || str_contains($lower, 'unsplash.com')) {
                continue;
            }

            if (! preg_match('/\.(jpe?g|png|webp|gif)(\?|$)/i', $url) && ! str_contains($lower, '/detailed/') && ! str_contains($lower, '/uploads/')) {
                continue;
            }

            $clean[] = ListingEnricher::collectImages(['image' => $url])[0] ?? $url;
        }

        return array_values(array_unique($clean));
    }
}
