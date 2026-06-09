<?php

namespace App\Services\Marketplace;

use App\Support\MelodiaPxCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Live scraper for Melodia Px (CS-Cart) — fetches real product listings from catalog pages.
 */
class MelodiaPxScraperService
{
    private const ITEMS_PER_PAGE = 32;

    private const MAX_PAGES = 9;

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, array<string, mixed>>
     */
    public function search(array $parsedQuery): array
    {
        $cacheKey = 'melodiapx:v1:'.md5(json_encode([
            MelodiaPxCatalog::catalogUrl($parsedQuery),
            $parsedQuery['brand'] ?? '',
            $parsedQuery['size'] ?? '',
            $parsedQuery['max_price'] ?? '',
            $parsedQuery['gender'] ?? '',
        ]));

        return Cache::remember($cacheKey, 600, fn () => $this->fetchAll($parsedQuery));
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, array<string, mixed>>
     */
    private function fetchAll(array $parsedQuery): array
    {
        $catalogUrl = MelodiaPxCatalog::catalogUrl($parsedQuery);
        $all = [];
        $seen = [];
        $maxPages = $this->maxPages($parsedQuery);

        for ($page = 1; $page <= $maxPages; $page++) {
            $html = $this->fetchPage($catalogUrl, $page);
            if ($html === '') {
                break;
            }

            $batch = $this->parseProducts($html);
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

        return $this->filterForIntent($all, $parsedQuery);
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     */
    private function maxPages(array $parsedQuery): int
    {
        $brand = (string) ($parsedQuery['brand'] ?? '');
        $hasBrandHash = $brand !== '' && MelodiaPxCatalog::hasBrandHash($brand);

        if ($hasBrandHash) {
            return 5;
        }

        if (! empty($parsedQuery['size']) || ! empty($parsedQuery['max_price'])) {
            return 3;
        }

        if ($brand !== '') {
            return 5;
        }

        return self::MAX_PAGES;
    }

    private function fetchPage(string $catalogUrl, int $page): string
    {
        $separator = str_contains($catalogUrl, '?') ? '&' : '?';
        $url = $catalogUrl.$separator.'items_per_page='.self::ITEMS_PER_PAGE;
        if ($page > 1) {
            $url .= '&page='.$page;
        }

        try {
            $response = Http::timeout((int) config('marketplaces.melodiapx_timeout_seconds', 90))
                ->withHeaders([
                    'User-Agent' => 'BuyMap.ai ValonWorker/1.0 (+https://buymap.ai)',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'sq,en;q=0.8',
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Melodia Px fetch failed', ['url' => $url, 'status' => $response->status()]);

                return '';
            }

            return (string) $response->body();
        } catch (\Throwable $e) {
            Log::warning('Melodia Px fetch error', ['url' => $url, 'error' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseProducts(string $html): array
    {
        $chunks = preg_split('/(?=<div class="ut2-gl__item)/', $html) ?: [];
        $products = [];

        foreach ($chunks as $chunk) {
            if (! str_contains($chunk, 'data-ca-product-id="')) {
                continue;
            }

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

            if (preg_match('/href="(https:\/\/www\.melodiapx\.com\/[^"]+)"[^>]*class="product-title"/', $chunk, $m)) {
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

            $sizes = $this->parseSizes($chunk);
            $brand = $this->detectBrand($title);
            $productType = $this->detectProductType($title);

            $products[] = [
                'id' => 'melodiapx-'.$productId,
                'store' => 'melodiapx',
                'title' => $title,
                'image' => $image,
                'price' => $price,
                'currency' => 'EUR',
                'location' => 'Prishtinë, Kosovo',
                'country_code' => 'XK',
                'condition' => 'new',
                'url' => $url ?: MelodiaPxCatalog::BASE_URL,
                'gender' => 'male',
                'brand' => $brand,
                'product_type' => $productType,
                'sizes' => $sizes,
                'tags' => array_values(array_filter([
                    'fashion',
                    'melodiapx',
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
        if (! preg_match('/ut2-lv__features-description">Madhesia<\/div>(.*?)<\/div>\s*<\/div>/s', $chunk, $block)) {
            return [];
        }

        preg_match_all('/<(?:span|a|label)[^>]*>\s*([^<]{1,12})\s*<\/(?:span|a|label)>/', $block[1], $matches);

        $sizes = [];
        foreach ($matches[1] ?? [] as $raw) {
            $size = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($size === '' || preg_match('/^(\+|\-|madh|ngjy)/iu', $size)) {
                continue;
            }
            if (preg_match('/^\d+(\.\d+)?$|^(\d+\s*\/\s*\d+)|^[XSML]{1,3}$|^\d{2,3}$/iu', $size)) {
                $sizes[] = $size;
            }
        }

        return array_values(array_unique($sizes));
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
            str_contains($lower, 'atlete') => 'sneakers',
            str_contains($lower, 'papuç') || str_contains($lower, 'papuq') => 'shoes',
            str_contains($lower, 'shorce') => 'shorts',
            str_contains($lower, 'bluz') => 'shirt',
            str_contains($lower, 'trenerka') => 'tracksuit',
            str_contains($lower, 'xhaket') => 'jacket',
            str_contains($lower, 'pantallon') => 'pants',
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
        $size = trim((string) ($parsed['size'] ?? ''));

        return array_values(array_filter($items, function (array $item) use ($brand, $size) {
            if ($brand !== '') {
                $title = mb_strtolower($item['title'] ?? '');
                $itemBrand = mb_strtolower((string) ($item['brand'] ?? ''));
                if (! str_contains($title, $brand) && $itemBrand !== $brand) {
                    return false;
                }
            }

            if ($size !== '' && ! empty($item['sizes']) && is_array($item['sizes'])) {
                $normalized = array_map('strval', $item['sizes']);
                if (! in_array($size, $normalized, true)) {
                    return false;
                }
            }

            return true;
        }));
    }
}
