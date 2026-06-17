<?php

namespace App\Services\Marketplace\Scrapers;

use App\Services\Marketplace\Scrapers\Contracts\ScraperAdapterInterface;
use App\Support\ListingEnricher;
use App\Support\PlatformCatalogUrlBuilder;
use App\Support\SwissRealEstateIntent;

class RealEstateHtmlScraperAdapter implements ScraperAdapterInterface
{
    public function __construct(
        private ScraperHttpClient $http,
        private \App\Services\Marketplace\BrowseAiScrapeService $browseAi,
    ) {}

    public function adapterKey(): string
    {
        return 'real_estate';
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsedQuery
     */
    public function scrape(array $platform, array $parsedQuery): array
    {
        $storeKey = (string) ($platform['_key'] ?? 'real_estate');
        $searchUrl = PlatformCatalogUrlBuilder::build($platform, $parsedQuery);
        $referer = rtrim((string) ($platform['base_url'] ?? ''), '/').'/';
        $html = $this->http->get($searchUrl, $platform['locale'] ?? null, $referer, $storeKey);

        $items = $html !== '' ? $this->parseListings($html, $storeKey, $platform) : [];

        if ($items === [] && $this->browseAi->shouldUse($storeKey)) {
            $items = $this->browseAi->scrapeListings($storeKey, $searchUrl, $platform, $parsedQuery);
        }

        if ($items === [] && SwissRealEstateIntent::isSwissSearch($parsedQuery)) {
            $items = SwissRealEstateIntent::catalogFallback($storeKey, $parsedQuery);
        }

        return ProductListingNormalizer::filterForIntent($items, $parsedQuery);
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    private function parseListings(string $html, string $storeKey, array $platform): array
    {
        $fromJsonLd = $this->parseJsonLd($html, $storeKey, $platform);
        if ($fromJsonLd !== []) {
            return $fromJsonLd;
        }

        return $this->parseHeuristicCards($html, $storeKey, $platform);
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    private function parseJsonLd(string $html, string $storeKey, array $platform): array
    {
        if (! preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            return [];
        }

        $items = [];
        $base = rtrim((string) ($platform['base_url'] ?? ''), '/');

        foreach ($matches[1] as $json) {
            $data = json_decode(html_entity_decode($json), true);
            if (! is_array($data)) {
                continue;
            }

            $nodes = isset($data['@graph']) ? $data['@graph'] : [$data];
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }

                $type = (string) ($node['@type'] ?? '');
                if (! str_contains($type, 'Apartment') && ! str_contains($type, 'House') && ! str_contains($type, 'Residence')) {
                    continue;
                }

                $title = (string) ($node['name'] ?? '');
                if ($title === '') {
                    continue;
                }

                $price = (float) ($node['offers']['price'] ?? $node['price'] ?? 0);
                $url = (string) ($node['url'] ?? '#');
                if ($url !== '#' && ! str_starts_with($url, 'http')) {
                    $url = $base.'/'.ltrim($url, '/');
                }

                $images = ListingEnricher::imagesFromJsonLd($node['image'] ?? null);
                $rooms = $node['numberOfRooms'] ?? null;
                $area = null;
                if (isset($node['floorSize']) && is_array($node['floorSize'])) {
                    $area = $node['floorSize']['value'] ?? null;
                }

                $items[] = ProductListingNormalizer::finalizeRealEstate($platform, $storeKey, [
                    'title' => $title,
                    'price' => $price,
                    'currency' => (string) ($node['offers']['priceCurrency'] ?? $platform['currency'] ?? 'CHF'),
                    'url' => $url,
                    'image' => $images[0] ?? null,
                    'images' => $images,
                    'location' => (string) ($platform['location'] ?? 'Switzerland'),
                    'country_code' => strtoupper((string) ($platform['country'] ?? 'CH')),
                    'property_type' => str_contains($type, 'House') ? 'house' : 'apartment',
                    'rooms' => is_numeric($rooms) ? (int) $rooms : null,
                    'area_sqm' => is_numeric($area) ? (float) $area : null,
                    'live' => true,
                ]);
            }
        }

        return array_slice($items, 0, 24);
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    private function parseHeuristicCards(string $html, string $storeKey, array $platform): array
    {
        $items = [];
        $base = rtrim((string) ($platform['base_url'] ?? ''), '/');

        if (preg_match_all('/<article[^>]*>.*?<\/article>/is', $html, $articles) && count($articles[0]) > 0) {
            foreach (array_slice($articles[0], 0, 24) as $block) {
                $item = $this->parseCardBlock($block, $storeKey, $platform, $base);
                if ($item !== null) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array<string, mixed>|null
     */
    private function parseCardBlock(string $block, string $storeKey, array $platform, string $base): ?array
    {
        if (! preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $block, $titleMatch)) {
            return null;
        }

        $title = trim(strip_tags($titleMatch[1]));
        if ($title === '' || mb_strlen($title) < 8) {
            return null;
        }

        $price = 0.0;
        if (preg_match('/(?:CHF|Fr\.?|€|EUR)\s*([\d\'\s]+(?:\.\d{2})?)/u', $block, $priceMatch)) {
            $price = (float) str_replace(['\'', ' ', ','], ['', '', '.'], $priceMatch[1]);
        }

        $url = '#';
        if (preg_match('/href=["\']([^"\']+)["\']/i', $block, $hrefMatch)) {
            $url = $hrefMatch[1];
            if (! str_starts_with($url, 'http')) {
                $url = $base.'/'.ltrim($url, '/');
            }
        }

        return ProductListingNormalizer::finalizeRealEstate($platform, $storeKey, [
            'title' => $title,
            'price' => $price,
            'currency' => (string) ($platform['currency'] ?? 'CHF'),
            'url' => $url,
            'location' => (string) ($platform['location'] ?? 'Switzerland'),
            'country_code' => strtoupper((string) ($platform['country'] ?? 'CH')),
            'property_type' => 'apartment',
            'live' => true,
        ]);
    }
}
