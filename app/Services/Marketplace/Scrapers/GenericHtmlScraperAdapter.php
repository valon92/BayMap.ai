<?php

namespace App\Services\Marketplace\Scrapers;

use App\Services\Marketplace\Scrapers\Contracts\ScraperAdapterInterface;
use App\Support\PlatformCatalogUrlBuilder;

class GenericHtmlScraperAdapter implements ScraperAdapterInterface
{
    private const MAX_LISTINGS = 32;

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

        $items = $this->parseStructuredCatalog($html, $storeKey, $platform);
        if ($items === []) {
            $items = $this->parseHeuristic($html, $storeKey, $platform);
        }

        return ProductListingNormalizer::filterForIntent($items, $parsedQuery);
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    private function parseStructuredCatalog(string $html, string $storeKey, array $platform): array
    {
        $scraper = (string) ($platform['scraper'] ?? '');

        foreach ([
            fn () => $this->parseManorNextData($html, $storeKey, $platform),
            fn () => $this->parseAlternateProducts($html, $storeKey, $platform),
            fn () => $this->parseJsonLdProducts($html, $storeKey, $platform),
            fn () => str_contains($scraper, 'apple') ? [] : $this->parseAppleSearchData($html, $storeKey, $platform),
        ] as $parser) {
            $items = $parser();
            if ($items !== []) {
                return $items;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    private function parseManorNextData(string $html, string $storeKey, array $platform): array
    {
        if (! preg_match('/<script id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $match)) {
            return [];
        }

        $data = json_decode($match[1], true);
        if (! is_array($data)) {
            return [];
        }

        $nodes = [];
        $this->collectManorProducts($data, $nodes);

        $items = [];
        $seen = [];

        foreach ($nodes as $node) {
            if (count($items) >= self::MAX_LISTINGS) {
                break;
            }

            $title = trim((string) ($node['name'] ?? ''));
            if ($title === '' || isset($seen[$title])) {
                continue;
            }

            $price = (float) ($node['priceValue']['amount'] ?? 0);
            if ($price <= 0) {
                continue;
            }

            $url = (string) ($node['link'] ?? '');
            if ($url === '') {
                continue;
            }

            $seen[$title] = true;
            $image = (string) ($node['imageUrls']['desktop'] ?? $node['imageUrls']['mobile'] ?? '');

            $items[] = ProductListingNormalizer::finalize($platform, $storeKey, [
                'product_id' => (string) ($node['code'] ?? md5($title.$url)),
                'title' => $title,
                'url' => $url,
                'price' => $price,
                'image' => $image !== '' ? $image : null,
                'brand' => mb_strtolower((string) ($node['brandName'] ?? '')),
                'location' => (string) ($platform['location'] ?? 'Switzerland'),
            ]);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, array<string, mixed>>  $found
     */
    private function collectManorProducts(array $node, array &$found, int $depth = 0): void
    {
        if ($depth > 18 || count($found) >= self::MAX_LISTINGS * 2) {
            return;
        }

        if (isset($node['code'], $node['name'], $node['priceValue']) && is_array($node['priceValue'])) {
            $found[] = $node;
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $this->collectManorProducts($value, $found, $depth + 1);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    private function parseJsonLdProducts(string $html, string $storeKey, array $platform): array
    {
        if (! preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $blocks)) {
            return [];
        }

        $baseUrl = rtrim((string) ($platform['base_url'] ?? ''), '/');
        $items = [];
        $seen = [];

        foreach ($blocks[1] as $raw) {
            $json = json_decode(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            if (! is_array($json)) {
                continue;
            }

            $nodes = isset($json['@graph']) && is_array($json['@graph']) ? $json['@graph'] : [$json];
            foreach ($nodes as $node) {
                if (! is_array($node) || count($items) >= self::MAX_LISTINGS) {
                    break;
                }

                $type = (string) ($node['@type'] ?? '');
                if ($type === 'ItemList' && isset($node['itemListElement']) && is_array($node['itemListElement'])) {
                    foreach ($node['itemListElement'] as $element) {
                        if (count($items) >= self::MAX_LISTINGS) {
                            break 2;
                        }

                        $product = is_array($element['item'] ?? null) ? $element['item'] : null;
                        if ($product !== null) {
                            $this->appendJsonLdProduct($product, $items, $seen, $platform, $storeKey, $baseUrl);
                        }
                    }

                    continue;
                }

                if (! in_array($type, ['Product', 'ProductGroup'], true)) {
                    continue;
                }

                $this->appendJsonLdProduct($node, $items, $seen, $platform, $storeKey, $baseUrl);
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, true>  $seen
     * @param  array<string, mixed>  $platform
     */
    private function appendJsonLdProduct(array $node, array &$items, array &$seen, array $platform, string $storeKey, string $baseUrl): void
    {
        if (count($items) >= self::MAX_LISTINGS) {
            return;
        }

        $title = trim((string) ($node['name'] ?? ''));
        if ($title === '' || isset($seen[$title])) {
            return;
        }

        $price = $this->priceFromJsonLdNode($node);
        if ($price <= 0) {
            return;
        }

        $url = (string) ($node['url'] ?? '#');
        if (! str_starts_with($url, 'http')) {
            $url = $baseUrl.$url;
        }

        $image = $node['image'] ?? null;
        if (is_array($image)) {
            $image = $image[0] ?? null;
        }

        $seen[$title] = true;
        $brand = str_contains((string) ($platform['scraper'] ?? ''), 'apple') ? 'apple' : null;
        $items[] = ProductListingNormalizer::finalize($platform, $storeKey, [
            'product_id' => md5($title.$url),
            'title' => $title,
            'url' => $url,
            'price' => $price,
            'image' => is_string($image) ? $image : null,
            'brand' => $brand,
            'location' => (string) ($platform['location'] ?? 'Switzerland'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    private function parseAlternateProducts(string $html, string $storeKey, array $platform): array
    {
        $scraper = (string) ($platform['scraper'] ?? $storeKey);
        if (! str_contains($scraper, 'alternate') && $storeKey !== 'alternate') {
            return [];
        }

        if (! preg_match_all(
            '/product-name font-weight-bold"><span>Apple<\/span>\s*([^<]+)<\/div>[\s\S]{0,4000}?<span class="price ">€\s*([0-9.,]+)[\s\S]{0,2000}?href="(https:\/\/www\.alternate\.de\/[^"]+\/html\/product\/\d+)"/i',
            $html,
            $matches,
            PREG_SET_ORDER,
        )) {
            return [];
        }

        $items = [];
        $seen = [];

        foreach ($matches as $match) {
            if (count($items) >= self::MAX_LISTINGS) {
                break;
            }

            $title = trim(html_entity_decode('Apple '.$match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($title === '' || isset($seen[$title])) {
                continue;
            }

            $price = $this->parseGermanPrice($match[2]);
            if ($price <= 0) {
                continue;
            }

            $seen[$title] = true;
            $items[] = ProductListingNormalizer::finalize($platform, $storeKey, [
                'product_id' => md5($title.$match[3]),
                'title' => $title,
                'url' => html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'price' => $price,
                'brand' => 'apple',
                'location' => (string) ($platform['location'] ?? 'Germany'),
            ]);
        }

        return $items;
    }

    private function parseGermanPrice(string $raw): float
    {
        $raw = trim(str_replace(['€', ' '], '', $raw));
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);

        return (float) $raw;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function priceFromJsonLdNode(array $node): float
    {
        $offers = $node['offers'] ?? null;
        if (is_array($offers)) {
            if (isset($offers[0]) && is_array($offers[0])) {
                $first = $offers[0];
                if (($first['@type'] ?? '') === 'AggregateOffer') {
                    return (float) ($first['lowPrice'] ?? $first['highPrice'] ?? 0);
                }

                if (isset($first['price'])) {
                    return (float) $first['price'];
                }
            }

            if (($offers['@type'] ?? '') === 'AggregateOffer') {
                return (float) ($offers['lowPrice'] ?? $offers['highPrice'] ?? 0);
            }

            if (isset($offers['price'])) {
                return (float) $offers['price'];
            }
        }

        return (float) ($node['price'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    private function parseAppleSearchData(string $html, string $storeKey, array $platform): array
    {
        if (! preg_match('/searchResults\.searchData\s*=\s*(\{.*?\});/s', $html, $match)) {
            return [];
        }

        $data = json_decode($match[1], true);
        if (! is_array($data)) {
            return [];
        }

        $baseUrl = rtrim((string) ($platform['base_url'] ?? 'https://www.apple.com'), '/');
        $items = [];
        $seen = [];

        $tileGroups = $data['results']['explore'] ?? [];
        foreach ($tileGroups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $tiles = $group['tiles']['items'] ?? $group['items'] ?? [];
            if (! is_array($tiles)) {
                continue;
            }

            foreach ($tiles as $tile) {
                if (count($items) >= self::MAX_LISTINGS) {
                    break 2;
                }

                $value = is_array($tile['value'] ?? null) ? $tile['value'] : $tile;
                if (! is_array($value)) {
                    continue;
                }

                $title = trim((string) ($value['title'] ?? $value['name'] ?? ''));
                if ($title === '' || isset($seen[$title])) {
                    continue;
                }

                $path = (string) ($value['link']['url'] ?? $value['link'] ?? '');
                $url = str_starts_with($path, 'http') ? $path : $baseUrl.'/ch-de/shop'.(str_starts_with($path, '/') ? '' : '/').ltrim($path, '/');
                if ($url === $baseUrl.'/ch-de/shop') {
                    $url = $baseUrl.'/ch-de/shop/buy-mac/macbook-air';
                }

                $seen[$title] = true;
                $items[] = ProductListingNormalizer::finalize($platform, $storeKey, [
                    'product_id' => md5($title.$url),
                    'title' => $title,
                    'url' => $url,
                    'price' => 0.0,
                    'image' => is_string($value['imageURL'] ?? null) ? $value['imageURL'] : null,
                    'brand' => 'apple',
                    'location' => (string) ($platform['location'] ?? 'Switzerland'),
                ]);
            }
        }

        return $items;
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
                if (preg_match('/(\d+[.,\']\d{2,3})\s*(?:CHF|€|EUR)/u', $html, $pm)) {
                    $price = (float) str_replace(["'", ','], ['', '.'], $pm[1]);
                }

                $products[] = ProductListingNormalizer::finalize($platform, $storeKey, [
                    'product_id' => md5($url.$title),
                    'title' => $title,
                    'url' => $url,
                    'price' => $price,
                    'location' => (string) ($platform['location'] ?? 'Switzerland'),
                ]);

                if (count($products) >= self::MAX_LISTINGS) {
                    break 2;
                }
            }
        }

        return $products;
    }
}
