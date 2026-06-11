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
            str_contains($scraper, 'autolina') => $this->parseAutolina($html, $storeKey, $platform, $parsedQuery),
            str_contains($scraper, 'autogrid') => $this->parseAutogrid($html, $storeKey, $platform, $parsedQuery),
            str_contains($scraper, 'swiss_html') => $this->parseSwissAutomotive($html, $storeKey, $platform, $parsedQuery),
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
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, array<string, mixed>>
     */
    private function parseAutolina(string $html, string $storeKey, array $platform, array $parsedQuery = []): array
    {
        if ($html === '' || $this->isBlockedHtml($html)) {
            return [];
        }

        if (! preg_match_all('/<app-car-row\b.*?<\/app-car-row>/is', $html, $rows)) {
            return [];
        }

        $baseUrl = rtrim((string) ($platform['base_url'] ?? 'https://www.autolina.ch'), '/');
        $items = [];
        $seen = [];

        foreach ($rows[0] as $row) {
            if (count($items) >= self::MAX_LISTINGS) {
                break;
            }

            if (! preg_match('/href="(\/auto\/[^"]+)"/i', $row, $hrefMatch)) {
                continue;
            }

            $path = html_entity_decode($hrefMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;

            preg_match_all('/title="([^"]+)"/i', $row, $titleMatches);
            $brand = trim((string) ($titleMatches[1][0] ?? ''));
            $variant = trim((string) ($titleMatches[1][1] ?? ''));
            $title = trim($brand.($variant !== '' ? ' '.$variant : ''));
            if ($title === '') {
                if (preg_match('/\/auto\/([^\/]+)\//', $path, $slugMatch)) {
                    $title = str_replace('-', ' ', $slugMatch[1]);
                } else {
                    continue;
                }
            }

            $price = $this->extractAutolinaPrice($row);

            if ($price < 1000) {
                continue;
            }

            $year = null;
            if (preg_match_all('/>(\d{4})</', $row, $yearMatches)) {
                foreach ($yearMatches[1] as $candidate) {
                    $candidateYear = (int) $candidate;
                    if ($candidateYear >= 1990 && $candidateYear <= (int) date('Y') + 1) {
                        $year = $candidateYear;
                        break;
                    }
                }
            }
            if ($year === null) {
                $year = $this->yearFromTitle($title);
            }

            $locationLabel = (string) ($platform['location'] ?? 'Switzerland');
            if (preg_match('/translate="no"[^>]*>(\d{4}\s+[^<]+)</i', $row, $locMatch)) {
                $loc = trim(html_entity_decode($locMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($loc !== '' && ! preg_match('/^[A-Z]{2,}$/u', $loc)) {
                    $locationLabel = $loc.', Switzerland';
                }
            }

            $image = null;
            if (preg_match('/src="(https:\/\/[^"]+autolina[^"]+)"/i', $row, $imgMatch)) {
                $image = html_entity_decode($imgMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            $url = str_starts_with($path, 'http') ? $path : $baseUrl.$path;
            $productId = '';
            if (preg_match('/\/(\d+)$/', $path, $idMatch)) {
                $productId = $idMatch[1];
            }

            $items[] = ProductListingNormalizer::finalizeAutomotive($platform, $storeKey, [
                'product_id' => $productId !== '' ? $productId : md5($title.$url),
                'title' => $title,
                'price' => $price,
                'image' => $image,
                'url' => $url,
                'location' => $locationLabel,
                'brand' => $this->brandFromTitle($title) ?? mb_strtolower($brand),
                'model' => $this->modelFromTitle($title) ?? mb_strtolower($variant),
                'year' => $year,
                'condition' => 'used',
                'color' => AutomotiveColorResolver::extractFromText($title),
                'engine_liters' => AutomotiveEngineResolver::extractFromTitle(
                    $title,
                    isset($parsedQuery['fuel']) ? (string) $parsedQuery['fuel'] : null,
                ),
            ]);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, array<string, mixed>>
     */
    private function parseAutogrid(string $html, string $storeKey, array $platform, array $parsedQuery = []): array
    {
        if ($html === '' || $this->isBlockedHtml($html)) {
            return [];
        }

        if (! preg_match_all('/<article class="ag-listing-card\b.*?<\/article>/is', $html, $articles)) {
            return [];
        }

        $baseUrl = rtrim((string) ($platform['base_url'] ?? 'https://www.autogrid.ch'), '/');
        $items = [];
        $seen = [];

        foreach ($articles[0] as $article) {
            if (count($items) >= self::MAX_LISTINGS) {
                break;
            }

            if (! preg_match('/ag-listing-card-title[\s\S]*?<a[^>]+href="([^"]+)"[^>]*>([^<]+)</is', $article, $titleMatch)) {
                continue;
            }

            $url = html_entity_decode(trim($titleMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;

            $title = trim(html_entity_decode($titleMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($title === '') {
                continue;
            }

            $price = 0.0;
            if (preg_match('/>\s*(\d{1,3}(?:&#039;\d{3})*)\s*<\/div>\s*<div[^>]*>\s*CHF/is', $article, $priceMatch)) {
                $price = $this->parseSwissPrice(html_entity_decode($priceMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            } elseif (preg_match('/text-\[#d1b371\][^>]*>(\d{1,3}(?:&#039;\d{3})*)</', $article, $priceMatch)) {
                $price = $this->parseSwissPrice(html_entity_decode($priceMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            if ($price < 1000) {
                continue;
            }

            $year = null;
            if (preg_match('/title="Jahr"[\s\S]*?ag-listing-card-spec-chip-value[^>]*>(\d{4})</is', $article, $yearMatch)) {
                $year = (int) $yearMatch[1];
            }
            if ($year === null) {
                $year = $this->yearFromTitle($title);
            }

            $mileage = null;
            if (preg_match('/title="Kilometerstand"[\s\S]*?ag-listing-card-spec-chip-value[^>]*>([^<]+)</is', $article, $kmMatch)) {
                $kmDigits = preg_replace('/[^\d]/', '', html_entity_decode($kmMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '';
                $mileage = $kmDigits !== '' ? (int) $kmDigits : null;
            }

            $locationLabel = (string) ($platform['location'] ?? 'Switzerland');
            if (preg_match('/ag-listing-card-footer[\s\S]*?<span>(\d{4}\s+[^<]+)<\/span>/is', $article, $locMatch)) {
                $loc = trim(html_entity_decode($locMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($loc !== '') {
                    $locationLabel = $loc.', Switzerland';
                }
            }

            $image = null;
            if (preg_match('/class="ag-listing-card-image"[^>]+src="([^"]+)"/i', $article, $imgMatch)) {
                $image = html_entity_decode($imgMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            if (! str_starts_with($url, 'http')) {
                $url = $baseUrl.$url;
            }

            $items[] = ProductListingNormalizer::finalizeAutomotive($platform, $storeKey, [
                'product_id' => md5($url),
                'title' => $title,
                'price' => $price,
                'image' => $image,
                'url' => $url,
                'location' => $locationLabel,
                'brand' => $this->brandFromTitle($title),
                'model' => $this->modelFromTitle($title),
                'year' => $year,
                'mileage' => $mileage,
                'condition' => 'used',
                'color' => AutomotiveColorResolver::extractFromText($title),
                'engine_liters' => AutomotiveEngineResolver::extractFromTitle(
                    $title,
                    isset($parsedQuery['fuel']) ? (string) $parsedQuery['fuel'] : null,
                ),
            ]);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, array<string, mixed>>
     */
    private function parseSwissAutomotive(string $html, string $storeKey, array $platform, array $parsedQuery = []): array
    {
        foreach ([
            fn () => $this->parseAutolina($html, $storeKey, $platform, $parsedQuery),
            fn () => $this->parseAutogrid($html, $storeKey, $platform, $parsedQuery),
            fn () => $this->parseGenericAutomotive($html, $storeKey, $platform, $parsedQuery),
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
        if (preg_match('/<app-car-row\b/i', $html) && preg_match('/href="\/auto\//i', $html)) {
            return false;
        }

        if (preg_match('/<article class="ag-listing-card\b/i', $html)) {
            return false;
        }

        $lower = mb_strtolower($html);

        if (preg_match('/<title>[^<]*(captcha|attention required|access denied|just a moment)[^<]*<\/title>/i', $html)) {
            return true;
        }

        return str_contains($lower, 'access denied')
            || str_contains($lower, 'cf-challenge')
            || str_contains($lower, 'bot detection')
            || str_contains($lower, 'vercel security checkpoint')
            || (str_contains($lower, 'captcha') && ! str_contains($lower, 'recaptcha'));
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

    private function parseSwissPrice(string $raw): float
    {
        $normalized = str_replace(["'", '’', ' '], '', $raw);
        $digits = preg_replace('/[^\d]/', '', $normalized) ?? '';

        return $digits !== '' ? (float) $digits : 0.0;
    }

    private function extractAutolinaPrice(string $row): float
    {
        if (preg_match('/CHF<\/span>\s*<span[^>]*>([^<]+)</i', $row, $priceMatch)) {
            return $this->parseSwissPrice($priceMatch[1]);
        }

        if (preg_match('/CHF(.*?)class="middle-row/s', $row, $section)) {
            $priceText = html_entity_decode(strip_tags($section[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $price = $this->parseSwissPrice($priceText);
            if ($price >= 1000) {
                return $price;
            }
        }

        if (preg_match('/translate="no">(\d{1,3})(?:<span[^>]*>\'<\/span>)?(\d{3})/s', $row, $priceMatch)) {
            return (float) ($priceMatch[1].$priceMatch[2]);
        }

        return 0.0;
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
        if (preg_match('/\bx\s*([1-7])\b/i', $title, $match)) {
            return 'x'.$match[1];
        }
        if (preg_match('/\bq\s*([2-8])\b/i', $title, $match)) {
            return 'q'.$match[1];
        }
        if (preg_match('/\ba\s*([1-8])\b/i', $title, $match)) {
            return 'a'.$match[1];
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
