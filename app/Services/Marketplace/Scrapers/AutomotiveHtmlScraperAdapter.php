<?php

namespace App\Services\Marketplace\Scrapers;

use App\Services\Marketplace\Scrapers\Contracts\ScraperAdapterInterface;
use App\Support\AutomotiveColorResolver;
use App\Support\AutomotiveEngineResolver;
use App\Support\AutomotiveModelResolver;
use App\Support\PlatformCatalogUrlBuilder;

class AutomotiveHtmlScraperAdapter implements ScraperAdapterInterface
{
    private const MAX_LISTINGS = 20;

    public function __construct(private ScraperHttpClient $http) {}

    public function adapterKey(): string
    {
        return 'automotive';
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsedQuery
     */
    public function scrape(array $platform, array $parsedQuery): array
    {
        $storeKey = (string) ($platform['_key'] ?? 'automotive');
        $scraper = (string) ($platform['scraper'] ?? $storeKey);
        $searchUrl = PlatformCatalogUrlBuilder::build($platform, $parsedQuery);
        $html = $this->http->get($searchUrl, $platform['locale'] ?? 'de-DE');

        if ($html === '') {
            return [];
        }

        $raw = match (true) {
            str_contains($scraper, 'autoscout') => $this->parseAutoScout24($html, $storeKey, $platform),
            str_contains($scraper, 'kleinanzeigen') => $this->parseKleinanzeigen($html, $storeKey, $platform, $parsedQuery),
            str_contains($scraper, 'autouncle') => $this->parseAutoUncle($html, $storeKey, $platform),
            default => $this->parseGenericAutomotive($html, $storeKey, $platform, $parsedQuery),
        };

        return ProductListingNormalizer::filterForIntent($raw, $parsedQuery);
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    private function parseAutoScout24(string $html, string $storeKey, array $platform): array
    {
        if (! preg_match('/<script id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $match)) {
            return [];
        }

        $data = json_decode($match[1], true);
        $listings = $data['props']['pageProps']['listings'] ?? [];
        if (! is_array($listings)) {
            return [];
        }

        $baseUrl = rtrim((string) ($platform['base_url'] ?? 'https://www.autoscout24.de'), '/');
        $items = [];

        foreach (array_slice($listings, 0, self::MAX_LISTINGS) as $listing) {
            if (! is_array($listing)) {
                continue;
            }

            $vehicle = (array) ($listing['vehicle'] ?? []);
            $make = (string) ($vehicle['make'] ?? '');
            $model = (string) ($vehicle['model'] ?? '');
            $variant = trim((string) ($vehicle['modelVersionInput'] ?? ''));
            $title = trim($make.' '.$model.($variant !== '' ? ' '.$variant : ''));

            $priceFormatted = (string) (($listing['price']['priceFormatted'] ?? '') ?: '');
            $price = $this->parseGermanPrice($priceFormatted);

            $location = (array) ($listing['location'] ?? []);
            $city = (string) ($location['city'] ?? '');
            $countryLabel = (string) ($platform['location'] ?? 'Germany');
            $locationLabel = $city !== ''
                ? $city.', '.$countryLabel
                : $countryLabel;

            $path = (string) ($listing['url'] ?? '');
            $url = str_starts_with($path, 'http') ? $path : $baseUrl.$path;
            $color = AutomotiveColorResolver::extractFromAutoScoutUrl($path);

            $images = (array) ($listing['images'] ?? []);
            $image = is_string($images[0] ?? null) ? $images[0] : null;

            $year = $this->yearFromVehicleDetails((array) ($listing['vehicleDetails'] ?? []));
            $mileage = $this->mileageFromVehicle($vehicle, (array) ($listing['vehicleDetails'] ?? []));
            $engineLiters = AutomotiveEngineResolver::litersFromCcm(
                isset($vehicle['engineDisplacementInCCM']) ? (string) $vehicle['engineDisplacementInCCM'] : null,
            ) ?? AutomotiveEngineResolver::extractFromTitle($title, (string) ($vehicle['fuel'] ?? ''));

            $items[] = ProductListingNormalizer::finalizeAutomotive($platform, $storeKey, [
                'product_id' => (string) ($listing['id'] ?? md5($title.$url)),
                'title' => $title,
                'price' => $price,
                'image' => $image,
                'url' => $url,
                'location' => $locationLabel,
                'brand' => mb_strtolower($make),
                'model' => mb_strtolower($model),
                'year' => $year,
                'mileage' => $mileage,
                'fuel' => (string) ($vehicle['fuel'] ?? ''),
                'transmission' => (string) ($vehicle['transmission'] ?? ''),
                'condition' => (($vehicle['offerType'] ?? 'U') === 'N') ? 'new' : 'used',
                'color' => $color,
                'engine_liters' => $engineLiters,
            ]);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, array<string, mixed>>
     */
    private function parseKleinanzeigen(string $html, string $storeKey, array $platform, array $parsedQuery = []): array
    {
        if (! preg_match_all('/<article[^>]*data-adid="(\d+)"[^>]*>(.*?)<\/article>/is', $html, $articles, PREG_SET_ORDER)) {
            return [];
        }

        $baseUrl = rtrim((string) ($platform['base_url'] ?? 'https://www.kleinanzeigen.de'), '/');
        $items = [];

        foreach ($articles as $article) {
            if (count($items) >= self::MAX_LISTINGS) {
                break;
            }

            $adId = (string) ($article[1] ?? '');
            $body = (string) ($article[2] ?? '');

            if (! preg_match('/<h2[^>]*>.*?<a[^>]*href="([^"]+)"[^>]*>([^<]+)</s', $body, $titleMatch)) {
                continue;
            }

            $title = html_entity_decode(trim($titleMatch[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $path = html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($this->isKleinanzeigenPartsListing($title) || ! $this->isKleinanzeigenVehicleListing($title, $parsedQuery)) {
                continue;
            }

            $price = 0.0;
            if (preg_match('/<p class="aditem-main--middle--price[^"]*"[^>]*>\s*([^<]+)/', $body, $priceMatch)) {
                $price = $this->parseGermanPrice($priceMatch[1]);
            } elseif (preg_match('/(\d{1,3}(?:\.\d{3})*)\s*€/', $body, $priceMatch)) {
                $price = $this->parseGermanPrice($priceMatch[1].' €');
            }

            if ($price <= 0 || $price < 8000) {
                continue;
            }

            $image = $this->extractKleinanzeigenImage($body);

            $locationLabel = (string) ($platform['location'] ?? 'Germany');
            if (preg_match('/<div class="aditem-main--top--left"[^>]*>.*?<\/div>\s*<div[^>]*>\s*([^<]+)/s', $body, $locMatch)) {
                $loc = trim(html_entity_decode($locMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($loc !== '') {
                    $locationLabel = str_contains(mb_strtolower($loc), 'germany') ? $loc : $loc.', Germany';
                }
            }

            $url = str_starts_with($path, 'http') ? $path : $baseUrl.$path;
            $year = $this->yearFromTitle($title);
            $color = AutomotiveColorResolver::extractFromText($title)
                ?? AutomotiveColorResolver::extractFromKleinanzeigenBody($body);
            $engineLiters = AutomotiveEngineResolver::extractFromTitle(
                $title,
                isset($parsedQuery['fuel']) ? (string) $parsedQuery['fuel'] : null,
            );

            $items[] = ProductListingNormalizer::finalizeAutomotive($platform, $storeKey, [
                'product_id' => $adId !== '' ? $adId : md5($title.$url),
                'title' => $title,
                'price' => $price,
                'image' => $image,
                'url' => $url,
                'location' => $locationLabel,
                'brand' => $this->brandFromTitle($title),
                'model' => $this->modelFromTitle($title),
                'year' => $year,
                'condition' => 'used',
                'color' => $color,
                'engine_liters' => $engineLiters,
            ]);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    private function parseAutoUncle(string $html, string $storeKey, array $platform): array
    {
        $pattern = '/<a[^>]+href="(\/de\/[^"]+)"[^>]*>[\s\S]{0,8000}?<img[^>]+alt="([^"]*Audi Q5[^"]*)"[^>]+src="([^"]+)"/i';
        if (! preg_match_all($pattern, $html, $blocks, PREG_SET_ORDER)) {
            return [];
        }

        $baseUrl = rtrim((string) ($platform['base_url'] ?? 'https://www.autouncle.de'), '/');
        $items = [];
        $seen = [];

        foreach ($blocks as $block) {
            if (count($items) >= self::MAX_LISTINGS) {
                break;
            }

            $path = html_entity_decode($block[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $title = html_entity_decode(trim($block[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $image = html_entity_decode($block[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $chunk = $block[0];

            if (isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;

            $title = preg_replace('/^Gebraucht\s+/i', '', $title) ?? $title;
            $price = 0.0;
            if (preg_match('/(\d{1,3}(?:\.\d{3})+)\s*€/', $chunk, $priceMatch)) {
                $price = $this->parseGermanPrice($priceMatch[1].' €');
            }

            if ($price < 8000) {
                continue;
            }

            $locationLabel = (string) ($platform['location'] ?? 'Germany');
            if (preg_match('/\b(\d{5})\s+([A-Za-zäöüÄÖÜß][A-Za-zäöüÄÖÜß\-\s]{2,40})\b/u', $chunk, $locMatch)) {
                $locationLabel = trim($locMatch[2]).', Germany';
            }

            $url = str_starts_with($path, 'http') ? $path : $baseUrl.$path;

            $items[] = ProductListingNormalizer::finalizeAutomotive($platform, $storeKey, [
                'product_id' => md5($path),
                'title' => $title,
                'price' => $price,
                'image' => $image,
                'url' => $url,
                'location' => $locationLabel,
                'brand' => 'audi',
                'model' => 'q5',
                'year' => $this->yearFromTitle($title),
                'condition' => 'used',
            ]);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, array<string, mixed>>
     */
    private function parseGenericAutomotive(string $html, string $storeKey, array $platform, array $parsedQuery = []): array
    {
        if ($html === '' || $this->isBlockedHtml($html)) {
            return [];
        }

        $items = [];
        if (! preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $blocks, PREG_SET_ORDER)) {
            return [];
        }

        $baseUrl = rtrim((string) ($platform['base_url'] ?? ''), '/');

        foreach ($blocks as $block) {
            $json = json_decode(html_entity_decode($block[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            if (! is_array($json)) {
                continue;
            }

            $nodes = isset($json['@graph']) && is_array($json['@graph']) ? $json['@graph'] : [$json];
            foreach ($nodes as $node) {
                if (! is_array($node) || count($items) >= self::MAX_LISTINGS) {
                    break;
                }

                $type = (string) ($node['@type'] ?? '');
                if (! in_array($type, ['Car', 'Vehicle', 'Product', 'Offer'], true)) {
                    continue;
                }

                $title = trim((string) ($node['name'] ?? $node['title'] ?? ''));
                if ($title === '' || $this->isKleinanzeigenPartsListing($title)) {
                    continue;
                }

                $price = 0.0;
                if (isset($node['offers']) && is_array($node['offers'])) {
                    $price = (float) ($node['offers']['price'] ?? $node['offers']['lowPrice'] ?? 0);
                } else {
                    $price = (float) ($node['price'] ?? 0);
                }

                if ($price < 800) {
                    continue;
                }

                $url = (string) ($node['url'] ?? '#');
                if (! str_starts_with($url, 'http')) {
                    $url = $baseUrl.$url;
                }

                $items[] = ProductListingNormalizer::finalizeAutomotive($platform, $storeKey, [
                    'product_id' => md5($title.$url),
                    'title' => $title,
                    'price' => $price,
                    'image' => is_string($node['image'] ?? null) ? $node['image'] : null,
                    'url' => $url,
                    'location' => (string) ($platform['location'] ?? 'Germany'),
                    'brand' => $this->brandFromTitle($title),
                    'model' => $this->modelFromTitle($title),
                    'year' => $this->yearFromTitle($title),
                    'condition' => 'used',
                    'engine_liters' => AutomotiveEngineResolver::extractFromTitle(
                        $title,
                        isset($parsedQuery['fuel']) ? (string) $parsedQuery['fuel'] : null,
                    ),
                ]);
            }
        }

        return $items;
    }

    private function isBlockedHtml(string $html): bool
    {
        $lower = mb_strtolower($html);

        return str_contains($lower, 'access denied')
            || str_contains($lower, 'captcha')
            || str_contains($lower, 'cf-challenge')
            || str_contains($lower, 'bot detection');
    }

    private function extractKleinanzeigenImage(string $body): ?string
    {
        if (preg_match('/<img[^>]+src="([^"]+img\.kleinanzeigen\.de[^"]+)"/i', $body, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (preg_match('/<img[^>]+data-src="([^"]+img\.kleinanzeigen\.de[^"]+)"/i', $body, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }

    private function parseGermanPrice(string $raw): float
    {
        $digits = preg_replace('/[^\d]/', '', $raw) ?? '';

        return $digits !== '' ? (float) $digits : 0.0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     */
    private function yearFromVehicleDetails(array $details): ?int
    {
        foreach ($details as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['iconName'] ?? '') !== 'calendar') {
                continue;
            }
            $data = (string) ($row['data'] ?? '');
            if (preg_match('/(\d{4})/', $data, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $vehicle
     * @param  array<int, array<string, mixed>>  $details
     */
    private function mileageFromVehicle(array $vehicle, array $details): ?int
    {
        $fromVehicle = (string) ($vehicle['mileageInKm'] ?? '');
        if (preg_match('/([\d.]+)/', $fromVehicle, $m)) {
            return (int) str_replace('.', '', $m[1]);
        }

        foreach ($details as $row) {
            if (! is_array($row) || ($row['iconName'] ?? '') !== 'mileage_odometer') {
                continue;
            }
            if (preg_match('/([\d.]+)/', (string) ($row['data'] ?? ''), $m)) {
                return (int) str_replace('.', '', $m[1]);
            }
        }

        return null;
    }

    private function yearFromTitle(string $title): ?int
    {
        if (preg_match('/\b(19|20)\d{2}\b/', $title, $m)) {
            return (int) $m[0];
        }

        return null;
    }

    private function brandFromTitle(string $title): ?string
    {
        $known = ['audi', 'bmw', 'mercedes', 'volkswagen', 'vw', 'porsche', 'opel', 'ford', 'toyota'];
        $lower = mb_strtolower($title);
        foreach ($known as $brand) {
            if (preg_match('/\b'.preg_quote($brand, '/').'\b/ui', $lower)) {
                return $brand === 'vw' ? 'volkswagen' : $brand;
            }
        }

        return null;
    }

    private function modelFromTitle(string $title): ?string
    {
        if (preg_match('/\bq\s*5\b/i', $title)) {
            return 'q5';
        }
        if (preg_match('/\bgolf\b/i', $title)) {
            return 'golf';
        }

        return null;
    }

    private function isKleinanzeigenPartsListing(string $title): bool
    {
        $lower = mb_strtolower($title);
        $partsHints = [
            'lenkrad', 'carplay', 'android auto', 'öldruck', 'oeldruck', 'anhänger',
            'felgen', 'reifen', 'navigations', 'radio', 'sitzbezug', 'dachbox',
            'ankauf', 'motorschaden', 'getriebeschaden', 'defekt', 'ersatzteil',
            'teile', 'schrott', 'export', 'verkaufe alle', 'motorhaube', 'kotflügel',
            'kotfluegel', 'schloßträger', 'schlosstraeger', 'stoßstange', 'stossstange',
            'getriebe ulk', 'getriebeulk', 'front motorhaube', 'felgen', 'alufelgen',
            'japan racing', 'reifen', 'winterreifen', 'sommerreifen',
        ];

        if (preg_match('/^getriebe\b/i', $lower)) {
            return true;
        }

        foreach ($partsHints as $hint) {
            if (str_contains($lower, $hint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     */
    private function isKleinanzeigenVehicleListing(string $title, array $parsedQuery = []): bool
    {
        $lower = mb_strtolower($title);
        if (preg_match('/\b(lenkrad|motorhaube|getriebe|kotflügel|ersatzteil)\b/i', $lower)) {
            return false;
        }

        $model = trim((string) ($parsedQuery['model'] ?? ''));
        if ($model !== '') {
            if (! AutomotiveModelResolver::matchesListing($title, null, $model, $parsedQuery)) {
                return false;
            }
        }

        return (bool) preg_match(
            '/^(vw|volkswagen|audi|bmw|mercedes|opel|ford|skoda|seat|golf)\b/i',
            trim($title),
        );
    }
}
