<?php

namespace App\Services\Marketplace\Scrapers;

use App\Services\Marketplace\BrowseAiScrapeService;
use App\Services\Marketplace\Scrapers\Contracts\ScraperAdapterInterface;
use App\Support\AutomotiveColorResolver;
use App\Support\AutomotiveDisplayNormalizer;
use App\Support\AutomotiveEngineResolver;
use App\Support\AutomotiveModelResolver;
use App\Support\AutoScout24ListingParser;
use App\Support\GermanAutomotiveIntent;
use App\Support\PlatformCatalogUrlBuilder;

class AutomotiveHtmlScraperAdapter implements ScraperAdapterInterface
{
    private const MAX_LISTINGS = 20;

    private const MAX_SCAN_ROWS = 180;

    public function __construct(
        private ScraperHttpClient $http,
        private BrowseAiScrapeService $browseAi,
        private ProductGalleryEnricher $galleryEnricher,
    ) {}

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
        $maxListings = (int) config('live_platforms.automotive_max_listings_per_platform', 100);
        $maxPages = (int) config('live_platforms.automotive_max_pages', 5);
        $multiPage = GermanAutomotiveIntent::isGermanSearch($parsedQuery)
            && GermanAutomotiveIntent::supportsMultiPageScraping($storeKey);

        if ($this->browseAi->shouldUse($storeKey)) {
            $searchUrl = PlatformCatalogUrlBuilder::build($platform, $parsedQuery);
            $structured = $this->browseAi->scrapeListings($storeKey, $searchUrl, $platform, $parsedQuery);
            if ($structured !== []) {
                return ProductListingNormalizer::filterForIntent($structured, $parsedQuery);
            }
        }

        $raw = [];
        $platformTotal = null;
        $pagesToFetch = $multiPage ? max(1, $maxPages) : 1;

        for ($page = 1; $page <= $pagesToFetch && count($raw) < $maxListings; $page++) {
            $searchUrl = PlatformCatalogUrlBuilder::build($platform, $parsedQuery, $page);
            $html = $this->http->get($searchUrl, $platform['locale'] ?? 'de-DE', null, $storeKey);

            if ($html === '') {
                break;
            }

            [$pageItems, $reportedTotal] = $this->parseAutomotiveHtml($html, $scraper, $storeKey, $platform, $parsedQuery);
            if ($platformTotal === null && $reportedTotal !== null && $reportedTotal > 0) {
                $platformTotal = $reportedTotal;
            }

            if ($pageItems === []) {
                break;
            }

            $raw = array_merge($raw, $pageItems);

            if (! $multiPage || count($pageItems) < 12) {
                break;
            }
        }

        $raw = ProductListingNormalizer::filterForIntent($raw, $parsedQuery);
        $raw = array_slice($raw, 0, $maxListings);

        if ($raw === []
            && GermanAutomotiveIntent::isGermanSearch($parsedQuery)
            && GermanAutomotiveIntent::shouldUseCatalogFallback($storeKey)) {
            $raw = GermanAutomotiveIntent::catalogFallback($storeKey, $parsedQuery);
        }

        $raw = ProductListingNormalizer::filterForIntent($raw, $parsedQuery);
        $raw = array_slice($raw, 0, $maxListings);

        if ($platformTotal !== null && $raw !== []) {
            $raw[0]['_marketplace_result_total'] = $platformTotal;
        }

        if ($this->shouldEnrichAutomotiveGallery($scraper) && $raw !== []) {
            $limit = min(
                count($raw),
                (int) config('live_platforms.automotive_gallery_enrich_max', 20),
            );
            $raw = $this->galleryEnricher->enrichFromDetailPages(
                $raw,
                isset($platform['locale']) ? (string) $platform['locale'] : null,
                $limit,
                (int) config('live_platforms.automotive_gallery_enrich_budget_seconds', 30),
            );
        }

        return $raw;
    }

    private function shouldEnrichAutomotiveGallery(string $scraper): bool
    {
        return str_contains($scraper, 'merrjep')
            || str_contains($scraper, 'veturaneshitje');
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array{0: array<int, array<string, mixed>>, 1: int|null}
     */
    private function parseAutomotiveHtml(
        string $html,
        string $scraper,
        string $storeKey,
        array $platform,
        array $parsedQuery,
    ): array {
        if (str_contains($scraper, 'autoscout')) {
            return $this->parseAutoScout24WithTotal($html, $storeKey, $platform);
        }

        if (str_contains($scraper, 'kleinanzeigen')) {
            return [
                $this->parseKleinanzeigen($html, $storeKey, $platform, $parsedQuery),
                $this->extractKleinanzeigenTotal($html),
            ];
        }

        $items = match (true) {
            str_contains($scraper, 'autouncle') => $this->parseAutoUncle($html, $storeKey, $platform),
            str_contains($scraper, 'autolina') => $this->parseAutolina($html, $storeKey, $platform, $parsedQuery),
            str_contains($scraper, 'autogrid') => $this->parseAutogrid($html, $storeKey, $platform, $parsedQuery),
            str_contains($scraper, 'swiss_html') => $this->parseSwissAutomotive($html, $storeKey, $platform, $parsedQuery),
            str_contains($scraper, 'mobile') => $this->parseMobileDe($html, $storeKey, $platform, $parsedQuery),
            str_contains($scraper, 'heycar') => $this->parseHeycar($html, $storeKey, $platform, $parsedQuery),
            str_contains($scraper, 'merrjep') => $this->parseMerrjepAuto($html, $storeKey, $platform, $parsedQuery),
            str_contains($scraper, 'veturaneshitje') => $this->parseVeturaneshitje($html, $storeKey, $platform, $parsedQuery),
            str_contains($scraper, 'carvago') => $this->parseCarvago($html, $storeKey, $platform, $parsedQuery),
            default => $this->parseGenericAutomotive($html, $storeKey, $platform, $parsedQuery),
        };

        return [$items, null];
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array{0: array<int, array<string, mixed>>, 1: int|null}
     */
    private function parseAutoScout24WithTotal(string $html, string $storeKey, array $platform): array
    {
        if (! preg_match('/<script id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $match)) {
            return [[], null];
        }

        $data = json_decode($match[1], true);
        $pageProps = $data['props']['pageProps'] ?? [];
        $total = isset($pageProps['numberOfResults']) ? (int) $pageProps['numberOfResults'] : null;

        return [$this->parseAutoScout24($html, $storeKey, $platform), $total];
    }

    private function extractKleinanzeigenTotal(string $html): ?int
    {
        if (preg_match('/(\d{1,3}(?:\.\d{3})*)\s*Ergebnisse/u', $html, $match)) {
            return (int) str_replace('.', '', $match[1]);
        }

        return null;
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
        $countryLabel = AutomotiveDisplayNormalizer::platformCountryLabel($platform);
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
            $locationLabel = $city !== ''
                ? $city.', '.$countryLabel
                : $countryLabel;

            $path = (string) ($listing['url'] ?? '');
            $url = str_starts_with($path, 'http') ? $path : $baseUrl.$path;
            $color = AutomotiveColorResolver::extractFromAutoScoutUrl($path);

            $images = AutoScout24ListingParser::collectImages($listing);
            $image = $images[0] ?? null;

            $detailSpecs = AutoScout24ListingParser::parseVehicleDetails((array) ($listing['vehicleDetails'] ?? []));
            $year = $detailSpecs['year'] ?? $this->yearFromVehicleDetails((array) ($listing['vehicleDetails'] ?? []));
            $mileage = $detailSpecs['mileage'] ?? $this->mileageFromVehicle($vehicle, (array) ($listing['vehicleDetails'] ?? []));
            $fuel = AutomotiveDisplayNormalizer::normalizeFuelDisplay(
                $detailSpecs['fuel'] ?? (string) ($vehicle['fuel'] ?? ''),
            ) ?? '';
            $transmission = AutomotiveDisplayNormalizer::normalizeTransmissionDisplay(
                $detailSpecs['transmission'] ?? (string) ($vehicle['transmission'] ?? ''),
            ) ?? '';
            $sellerType = AutoScout24ListingParser::normalizeSellerType((string) (($listing['seller']['type'] ?? '') ?: ''));
            $powerHp = $detailSpecs['power_hp'] ?? null;
            $powerKw = $detailSpecs['power_kw'] ?? null;
            $electricRange = $detailSpecs['electric_range_km'] ?? null;
            $firstRegistration = $detailSpecs['first_registration'] ?? null;
            $consumption = $detailSpecs['consumption'] ?? null;
            $bodyType = (string) ($vehicle['bodyType'] ?? $vehicle['body'] ?? '');

            $engineLiters = AutomotiveEngineResolver::litersFromCcm(
                isset($vehicle['engineDisplacementInCCM']) ? (string) $vehicle['engineDisplacementInCCM'] : null,
            ) ?? AutomotiveEngineResolver::extractFromTitle($title, $fuel);

            $specPayload = array_filter([
                'year' => $year,
                'mileage' => $mileage,
                'fuel' => $fuel,
                'transmission' => $transmission,
                'power_hp' => $powerHp,
                'power_kw' => $powerKw,
                'electric_range_km' => $electricRange,
                'body_type' => $bodyType !== '' ? $bodyType : null,
                'seller_type' => $sellerType,
                'first_registration' => $firstRegistration,
                'consumption' => $consumption,
            ], fn ($v) => $v !== null && $v !== '');

            $items[] = ProductListingNormalizer::finalizeAutomotive($platform, $storeKey, [
                'product_id' => (string) ($listing['id'] ?? md5($title.$url)),
                'title' => $title,
                'price' => $price,
                'image' => $image,
                'images' => $images,
                'url' => $url,
                'location' => $locationLabel,
                'brand' => mb_strtolower($make),
                'model' => mb_strtolower($model),
                'year' => $year,
                'mileage' => $mileage,
                'fuel' => $fuel,
                'transmission' => $transmission,
                'condition' => (($vehicle['offerType'] ?? 'U') === 'N') ? 'new' : 'used',
                'color' => $color,
                'engine_liters' => $engineLiters,
                'seller_type' => $sellerType,
                'power_hp' => $powerHp,
                'power_kw' => $powerKw,
                'electric_range_km' => $electricRange,
                'body_type' => $bodyType !== '' ? $bodyType : null,
                'first_registration' => $firstRegistration,
                'consumption' => $consumption,
                'specs' => AutoScout24ListingParser::buildSpecChips($specPayload),
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
        $yearFiltered = $this->hasYearFilter($parsedQuery);
        $maxScan = $yearFiltered ? self::MAX_SCAN_ROWS : count($rows[0]);

        foreach (array_slice($rows[0], 0, $maxScan) as $row) {
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

            $locationLabel = AutomotiveDisplayNormalizer::platformCountryLabel($platform);
            if (preg_match('/class="[^"]*\bregion-or-title\b[^"]*"[^>]*>([\s\S]*?)<\/div>\s*<div[^>]*tabindex="0"/i', $row, $regionMatch)) {
                $loc = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($regionMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if ($loc !== '' && preg_match('/\d{4}\s/u', $loc)) {
                    $locationLabel = $loc.', '.$locationLabel;
                }
            } elseif (preg_match('/translate="no"[^>]*>(\d{4}\s+[^<]+)</i', $row, $locMatch)) {
                $loc = trim(html_entity_decode($locMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($loc !== '' && ! preg_match('/^[A-Z]{2,}$/u', $loc)) {
                    $locationLabel = $loc.', '.$locationLabel;
                }
            }

            $image = null;
            if (preg_match('/src="(https:\/\/[^"]+autolina[^"]+)"/i', $row, $imgMatch)) {
                $image = html_entity_decode(urldecode($imgMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            $url = str_starts_with($path, 'http') ? $path : $baseUrl.$path;
            $productId = '';
            if (preg_match('/\/(\d+)$/', $path, $idMatch)) {
                $productId = $idMatch[1];
            }

            $vehicleData = $this->extractAutolinaVehicleData($row);
            $images = $this->buildAutolinaGallery($row, $image, $productId);
            if ($images === [] && $image !== null) {
                $images = [$image];
            }

            $mileage = $vehicleData['mileage'] ?? null;
            $fuel = AutomotiveDisplayNormalizer::normalizeFuelDisplay($vehicleData['fuel'] ?? null);
            $transmission = AutomotiveDisplayNormalizer::normalizeTransmissionDisplay($vehicleData['transmission'] ?? null);
            $powerHp = $vehicleData['power_hp'] ?? null;
            $powerKw = $vehicleData['power_kw'] ?? null;

            $model = $this->modelFromTitle($title) ?? mb_strtolower($variant);
            if (! AutomotiveModelResolver::matchesListing(
                $title,
                $model,
                (string) ($parsedQuery['model'] ?? ''),
                array_merge($parsedQuery, ['year' => $year]),
            )) {
                continue;
            }

            $specPayload = array_filter([
                'year' => $year,
                'mileage' => $mileage,
                'fuel' => $fuel,
                'transmission' => $transmission,
                'power_hp' => $powerHp,
                'power_kw' => $powerKw,
            ], fn ($v) => $v !== null && $v !== '');

            $items[] = ProductListingNormalizer::finalizeAutomotive($platform, $storeKey, [
                'product_id' => $productId !== '' ? $productId : md5($title.$url),
                'title' => $title,
                'price' => $price,
                'image' => $images[0] ?? $image,
                'images' => $images,
                'url' => $url,
                'location' => $locationLabel,
                'brand' => $this->brandFromTitle($title) ?? mb_strtolower($brand),
                'model' => $this->modelFromTitle($title) ?? mb_strtolower($variant),
                'year' => $year,
                'mileage' => $mileage,
                'fuel' => $fuel,
                'transmission' => $transmission,
                'power_hp' => $powerHp,
                'power_kw' => $powerKw,
                'condition' => 'used',
                'color' => AutomotiveColorResolver::extractFromText($title),
                'engine_liters' => AutomotiveEngineResolver::extractFromTitle(
                    $title,
                    isset($parsedQuery['fuel']) ? (string) $parsedQuery['fuel'] : null,
                ),
                'specs' => $specPayload !== [] ? AutoScout24ListingParser::buildSpecChips($specPayload) : [],
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
    private function parseMobileDe(string $html, string $storeKey, array $platform, array $parsedQuery = []): array
    {
        if ($html === '' || $this->isBlockedHtml($html)) {
            return [];
        }

        if (preg_match('/<script id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $match)) {
            $data = json_decode($match[1], true);
            $listings = $data['props']['pageProps']['searchResults']['items']
                ?? $data['props']['pageProps']['listings']
                ?? [];
            if (is_array($listings) && $listings !== []) {
                return $this->mapMobileDeJsonListings($listings, $storeKey, $platform);
            }
        }

        $baseUrl = rtrim((string) ($platform['base_url'] ?? 'https://www.mobile.de'), '/');
        $items = [];
        $seen = [];

        if (! preg_match_all('/href="(\/fahrzeuge\/details\.html\?id=\d+[^"]*)"[^>]*>[\s\S]{0,4000}?<\/a>/is', $html, $blocks, PREG_SET_ORDER)) {
            return $this->parseGenericAutomotive($html, $storeKey, $platform, $parsedQuery);
        }

        foreach ($blocks as $block) {
            if (count($items) >= self::MAX_LISTINGS) {
                break;
            }

            $path = html_entity_decode($block[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;

            $chunk = $block[0];
            $title = '';
            if (preg_match('/title="([^"]+)"/i', $chunk, $titleMatch)) {
                $title = trim(html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            } elseif (preg_match('/>([^<]{8,120})</', $chunk, $titleMatch)) {
                $title = trim(html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            if ($title === '' || $this->isKleinanzeigenPartsListing($title)) {
                continue;
            }

            $price = 0.0;
            if (preg_match('/(\d{1,3}(?:\.\d{3})+)\s*€/', $chunk, $priceMatch)) {
                $price = $this->parseGermanPrice($priceMatch[1].' €');
            }

            if ($price < 800) {
                continue;
            }

            $image = null;
            if (preg_match('/src="(https:\/\/[^"]+mobile\.de[^"]+)"/i', $chunk, $imgMatch)) {
                $image = html_entity_decode($imgMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            $url = str_starts_with($path, 'http') ? $path : $baseUrl.$path;

            $items[] = ProductListingNormalizer::finalizeAutomotive($platform, $storeKey, [
                'product_id' => md5($path),
                'title' => $title,
                'price' => $price,
                'image' => $image,
                'url' => $url,
                'location' => (string) ($platform['location'] ?? 'Germany'),
                'brand' => $this->brandFromTitle($title),
                'model' => $this->modelFromTitle($title),
                'year' => $this->yearFromTitle($title),
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
     * @param  array<int, array<string, mixed>>  $listings
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    private function mapMobileDeJsonListings(array $listings, string $storeKey, array $platform): array
    {
        $baseUrl = rtrim((string) ($platform['base_url'] ?? 'https://www.mobile.de'), '/');
        $items = [];

        foreach (array_slice($listings, 0, self::MAX_LISTINGS) as $listing) {
            if (! is_array($listing)) {
                continue;
            }

            $make = (string) ($listing['make'] ?? $listing['brand'] ?? '');
            $model = (string) ($listing['model'] ?? '');
            $variant = trim((string) ($listing['title'] ?? $listing['modelDescription'] ?? ''));
            $title = $variant !== '' ? $variant : trim($make.' '.$model);
            if ($title === '') {
                continue;
            }

            $price = (float) ($listing['price'] ?? $listing['priceGross'] ?? 0);
            if ($price <= 0 && isset($listing['priceFormatted'])) {
                $price = $this->parseGermanPrice((string) $listing['priceFormatted']);
            }
            if ($price < 800) {
                continue;
            }

            $path = (string) ($listing['url'] ?? $listing['relativeUrl'] ?? '');
            $url = str_starts_with($path, 'http') ? $path : $baseUrl.$path;
            $image = is_string($listing['image'] ?? null)
                ? $listing['image']
                : (is_string(($listing['images'][0] ?? null)) ? $listing['images'][0] : null);

            $items[] = ProductListingNormalizer::finalizeAutomotive($platform, $storeKey, [
                'product_id' => (string) ($listing['id'] ?? md5($title.$url)),
                'title' => $title,
                'price' => $price,
                'image' => $image,
                'url' => $url,
                'location' => (string) ($listing['location'] ?? $platform['location'] ?? 'Germany'),
                'brand' => $this->brandFromTitle($title) ?? mb_strtolower($make),
                'model' => $this->modelFromTitle($title) ?? mb_strtolower($model),
                'year' => isset($listing['year']) ? (int) $listing['year'] : $this->yearFromTitle($title),
                'mileage' => isset($listing['mileage']) ? (int) $listing['mileage'] : null,
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
    private function parseHeycar(string $html, string $storeKey, array $platform, array $parsedQuery = []): array
    {
        if ($html === '' || $this->isBlockedHtml($html)) {
            return [];
        }

        $baseUrl = rtrim((string) ($platform['base_url'] ?? 'https://www.heycar.de'), '/');
        $items = [];
        $seen = [];

        if (! preg_match_all('/<a[^>]+href="(\/de\/autos\/[^"]+)"[^>]*>[\s\S]{0,6000}?<\/a>/is', $html, $blocks, PREG_SET_ORDER)) {
            return $this->parseGenericAutomotive($html, $storeKey, $platform, $parsedQuery);
        }

        foreach ($blocks as $block) {
            if (count($items) >= self::MAX_LISTINGS) {
                break;
            }

            $path = html_entity_decode($block[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (isset($seen[$path]) || ! preg_match('/\/de\/autos\/[^\/]+\/[^\/]+/i', $path)) {
                continue;
            }
            $seen[$path] = true;

            $chunk = $block[0];
            $title = '';
            if (preg_match('/aria-label="([^"]+)"/i', $chunk, $titleMatch)) {
                $title = trim(html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            } elseif (preg_match('/<h[23][^>]*>([^<]+)</i', $chunk, $titleMatch)) {
                $title = trim(html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            if ($title === '') {
                continue;
            }

            $price = 0.0;
            if (preg_match('/(\d{1,3}(?:\.\d{3})+)\s*€/', $chunk, $priceMatch)) {
                $price = $this->parseGermanPrice($priceMatch[1].' €');
            }

            if ($price < 800) {
                continue;
            }

            $image = null;
            if (preg_match('/src="(https:\/\/[^"]+)"/i', $chunk, $imgMatch)) {
                $image = html_entity_decode($imgMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            $url = str_starts_with($path, 'http') ? $path : $baseUrl.$path;

            $items[] = ProductListingNormalizer::finalizeAutomotive($platform, $storeKey, [
                'product_id' => md5($path),
                'title' => $title,
                'price' => $price,
                'image' => $image,
                'url' => $url,
                'location' => (string) ($platform['location'] ?? 'Germany'),
                'brand' => $this->brandFromTitle($title),
                'model' => $this->modelFromTitle($title),
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

        if (preg_match('/row-listing/i', $html) && str_contains($html, 'merrjep.com')) {
            return false;
        }

        if (preg_match('/li class="row"/i', $html) && str_contains($html, 'veturaneshitje.com')) {
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
     * @return array<string, mixed>
     */
    private function extractAutolinaVehicleData(string $row): array
    {
        $out = [];

        if (! preg_match('/class="[^"]*\bvehicle-data\b[^"]*"[^>]*>([\s\S]*?)(?=tabindex="0"[^>]*class="[^"]*\bfavorite\b)/i', $row, $dataMatch)) {
            return $out;
        }

        preg_match_all('/<div[^>]*ng-star-inserted[^>]*>([\s\S]*?)<\/div>/i', (string) $dataMatch[1], $cells);

        foreach ($cells[1] ?? [] as $cell) {
            $value = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($value === '' || $value === '-') {
                continue;
            }

            if (preg_match('/^\d{4}$/', $value)) {
                continue;
            }

            if (preg_match("/(\d[\d\s'’.]*)\s*km\b/iu", $value, $match)) {
                $digits = preg_replace('/[^\d]/', '', $match[1]) ?? '';
                if ($digits !== '') {
                    $out['mileage'] = (int) $digits;
                }

                continue;
            }

            if (preg_match('/(\d+)\s*kW\s*\((\d+)\s*PS\)/u', $value, $match)) {
                $out['power_kw'] = (int) $match[1];
                $out['power_hp'] = (int) $match[2];

                continue;
            }

            if (preg_match('/(\d+)\s*PS\b/iu', $value, $match)) {
                $out['power_hp'] = (int) $match[1];

                continue;
            }

            if (preg_match('/^(Mild-Hybrid\s+Diesel\/Elektro|Elektro\/Benzin|Elettrica\/Benzina|Diesel\/Elektro|Diesel|Benzin|Elektro|Hybrid|Elettrica|Elettrico|Elettrica\/Diesel)$/iu', $value)) {
                $out['fuel'] = $value;

                continue;
            }

            if (preg_match('/^(Automatik|Manuell|Manuale|Automatico|Semiautomatico|Schaltgetriebe)$/iu', $value)) {
                $out['transmission'] = $value;
            }
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function buildAutolinaGallery(string $row, ?string $firstImage, string $productId): array
    {
        if ($firstImage === null || $firstImage === '') {
            return [];
        }

        $firstImage = html_entity_decode(urldecode($firstImage), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $photoCount = 1;
        if (preg_match('/class="[^"]*\bcircles\b[^"]*"[^>]*>([\s\S]*?)<\/div>\s*<\/div>\s*<div[^>]*make-logo/i', $row, $circlesSection)) {
            $photoCount = max(1, substr_count((string) $circlesSection[1], 'ng-star-inserted'));
        }

        if (preg_match('#(https://api\.autolina\.ch/auto-bild/[^"\s]+/(\d+))_1\.jpg#i', $firstImage, $match)) {
            $base = $match[1];
            $images = [];
            $limit = min(max($photoCount, 1), 12);

            for ($i = 1; $i <= $limit; $i++) {
                $images[] = $base.'_'.$i.'.jpg';
            }

            return $images;
        }

        if ($productId !== '' && preg_match('#(https://api\.autolina\.ch/auto-bild/[^"\s]+/)#i', $firstImage, $match)) {
            $images = [];
            $limit = min(max($photoCount, 1), 12);

            for ($i = 1; $i <= $limit; $i++) {
                $images[] = $match[1].$productId.'_'.$i.'.jpg';
            }

            return $images;
        }

        return [$firstImage];
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

    /**
     * MerrJep Auto — Kosovo dealer & private car listings.
     *
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, array<string, mixed>>
     */
    private function parseMerrjepAuto(string $html, string $storeKey, array $platform, array $parsedQuery): array
    {
        $baseUrl = rtrim((string) ($platform['base_url'] ?? 'https://www.merrjep.com'), '/');
        $chunks = preg_split('/(?=<div class="new row row-listing)/', $html) ?: [];
        $items = [];
        $seen = [];

        foreach ($chunks as $chunk) {
            if (count($items) >= self::MAX_LISTINGS) {
                break;
            }

            if (! preg_match('/data-product-id="(\d+)"/', $chunk, $idMatch)) {
                continue;
            }

            $productId = $idMatch[1];
            if (isset($seen[$productId])) {
                continue;
            }

            $title = null;
            $url = null;
            if (preg_match('/<h2>\s*<a[^>]+href="([^"]+)"[^>]*>([^<]+)<\/a>/s', $chunk, $titleMatch)) {
                $url = html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $title = html_entity_decode(trim($titleMatch[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('/title="([^"]+)"[^>]*href="(\/shpallja\/[^"]+)"/', $chunk, $titleMatch)) {
                $title = html_entity_decode(trim($titleMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $url = html_entity_decode($titleMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            if ($title === null || $title === '') {
                continue;
            }

            if ($this->isMerrjepNoiseListing($title)) {
                continue;
            }

            if (! str_starts_with($url ?? '', 'http')) {
                $url = $baseUrl.($url ?? '');
            }

            $image = null;
            if (preg_match('/data-src="(https:\/\/media\.merrjep\.com[^"]+)"/', $chunk, $imgMatch)) {
                $image = html_entity_decode($imgMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            $price = 0.0;
            if (preg_match('/format-money-int" value="(\d+)"/', $chunk, $priceMatch)) {
                $price = (float) $priceMatch[1];
            }

            $location = (string) ($platform['location'] ?? 'Kosovo');
            if (preg_match('/href="[^"]*\/(prishtine|ferizaj|peje|gjakove|prizren|mitrovice|gjilan|lipjan)[^"]*"/i', $chunk, $locMatch)) {
                $location = ucfirst($locMatch[1]).', Kosovo';
            }

            $seen[$productId] = true;
            $items[] = ProductListingNormalizer::finalizeAutomotive($platform, $storeKey, [
                'product_id' => $productId,
                'title' => $title,
                'price' => $price,
                'image' => $image,
                'url' => $url,
                'location' => $location,
                'brand' => $this->brandFromTitle($title),
                'model' => $this->modelFromTitle($title),
                'year' => $this->yearFromTitle($title),
                'mileage' => $this->mileageFromTitle($title),
                'color' => AutomotiveColorResolver::extractFromText($title),
                'condition' => 'used',
            ]);
        }

        return $items;
    }

    /**
     * Veturaneshitje.com — Kosovo autosallon listings.
     *
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, array<string, mixed>>
     */
    private function parseVeturaneshitje(string $html, string $storeKey, array $platform, array $parsedQuery): array
    {
        $baseUrl = rtrim((string) ($platform['base_url'] ?? 'https://www.veturaneshitje.com'), '/');
        $chunks = preg_split('/(?=<li class="row")/', $html) ?: [];
        $items = [];
        $seen = [];

        foreach ($chunks as $chunk) {
            if (count($items) >= self::MAX_LISTINGS) {
                break;
            }

            if (! preg_match('/href="(\/vetura\/\d+\/([^"]+))"/', $chunk, $urlMatch)) {
                continue;
            }

            $path = html_entity_decode($urlMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $slug = html_entity_decode($urlMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (isset($seen[$path]) || str_contains($slug, 'sipas-markes')) {
                continue;
            }

            $title = $this->titleFromVeturaneshitjeSlug($slug);

            if ($this->isVeturaneshitjeNoiseListing($title, $slug)) {
                continue;
            }

            $price = 0.0;
            if (preg_match('/<div class="lead text-orange price">.*?<strong>\s*([\d.,]+)\s*EUR\s*<\/strong>/is', $chunk, $priceMatch)) {
                $price = (float) str_replace(',', '', trim($priceMatch[1]));
            }

            $year = null;
            if (preg_match('/time\.png"[^>]*>\s*(\d{4})/', $chunk, $yearMatch)) {
                $year = (int) $yearMatch[1];
            }
            $year ??= $this->yearFromTitle($title);

            $location = (string) ($platform['location'] ?? 'Kosovo');
            if (preg_match('/glyphicon-map-marker[^>]*><\/i>\s*([^<]+)/', $chunk, $locMatch)) {
                $location = trim(html_entity_decode($locMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')).', Kosovo';
            }

            $image = null;
            if (preg_match('/<img src="(\/CarImages\/[^"]+)"/', $chunk, $imgMatch)) {
                $image = $baseUrl.html_entity_decode($imgMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            $seen[$path] = true;
            $items[] = ProductListingNormalizer::finalizeAutomotive($platform, $storeKey, [
                'product_id' => md5($path),
                'title' => $title,
                'price' => $price,
                'image' => $image,
                'url' => $baseUrl.$path,
                'location' => $location,
                'brand' => $this->brandFromTitle($title),
                'model' => $this->modelFromTitle($title),
                'year' => $year,
                'mileage' => $this->mileageFromTitle($title),
                'color' => AutomotiveColorResolver::extractFromText($title),
                'condition' => 'used',
            ]);
        }

        return $items;
    }

    /**
     * Carvago.com — embedded JSON listings on SSR search pages.
     *
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, array<string, mixed>>
     */
    private function parseCarvago(string $html, string $storeKey, array $platform, array $parsedQuery): array
    {
        $baseUrl = rtrim((string) ($platform['base_url'] ?? 'https://carvago.com'), '/');

        $blocks = preg_split('/(?="id":"\d+")/', $html) ?: [];
        $items = [];
        $seen = [];

        foreach ($blocks as $block) {
            if (count($items) >= self::MAX_LISTINGS) {
                break;
            }

            if (! preg_match('/^"id":"(\d+)".*?"slug":"([^"]+)".*?"title":"([^"]+)".*?"registration_date":"(\d{4})-\d{2}-\d{2}"/s', $block, $match)) {
                continue;
            }

            $id = $match[1];
            if (isset($seen[$id])) {
                continue;
            }

            $price = 0.0;
            if (preg_match('/"price_data":\{"price":(\d+)/', $block, $priceMatch)) {
                $price = (float) $priceMatch[1];
            }

            $mileage = null;
            if (preg_match('/"mileage":(\d+)/', $block, $mileageMatch)) {
                $mileage = (int) $mileageMatch[1];
            }

            $location = 'Europe';
            if (preg_match('/"location_city":"([^"]*)".*?"location_country":\{[^}]*"name":"([^"]+)"/s', $block, $locMatch)) {
                $city = html_entity_decode($locMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $country = html_entity_decode($locMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $location = trim($city) !== '' ? trim($city.', '.$country) : $country;
            }

            $slug = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $title = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $year = (int) $match[4];

            $seen[$id] = true;
            $items[] = ProductListingNormalizer::finalizeAutomotive($platform, $storeKey, [
                'product_id' => $id,
                'title' => $title,
                'price' => $price,
                'url' => $baseUrl.'/cars/'.$slug,
                'location' => $location,
                'brand' => $this->brandFromTitle($title),
                'model' => $this->modelFromTitle($title),
                'year' => $year,
                'mileage' => $mileage,
                'color' => AutomotiveColorResolver::extractFromText($title),
                'condition' => 'used',
            ]);
        }

        return $items;
    }

    private function titleFromVeturaneshitjeSlug(string $slug): string
    {
        $slug = preg_replace('/^id-\d+-[^-]+-/', '', $slug) ?? $slug;

        return ucwords(str_replace('-', ' ', $slug));
    }

    private function isMerrjepNoiseListing(string $title): bool
    {
        $lower = mb_strtolower($title);
        $noise = [
            'pjes per', 'per pjes', 'shitet per pjes', 'kemi pjes', 'rent a car', 'renta car',
            'blejm te gjitha', 'blejme te gjitha', 'auto:tregu', 'tregu/blejm', 'blejm vetura',
            '24/7 kesh', 'invalid shitet', 'shes pjes', 'pjes per audi', 'motorri defekt',
            'aksidentua', 'pa dogane aksident',
        ];

        foreach ($noise as $hint) {
            if (str_contains($lower, $hint)) {
                return true;
            }
        }

        return false;
    }

    private function isVeturaneshitjeNoiseListing(string $title, string $slug): bool
    {
        $lower = mb_strtolower($title.' '.$slug);
        $noise = [
            'abortion', 'cytotec', 'counterfeit', 'fake money', 'buy-counterfeit',
            'whatsapp', 'telegram', 'pills', 'drug', 'casino', 'escort',
        ];

        foreach ($noise as $hint) {
            if (str_contains($lower, $hint)) {
                return true;
            }
        }

        return false;
    }

    private function mileageFromTitle(string $title): ?int
    {
        if (preg_match('/\bkm[:\s]*([\d.x]+)/ui', $title, $m)) {
            $raw = str_replace(['.', 'x', 'X', ' '], '', $m[1]);
            if (preg_match('/^(\d+)/', $raw, $num)) {
                $km = (int) $num[1];

                return $km < 1000 ? $km * 1000 : $km;
            }
        }

        if (preg_match('/\b(\d{2,3})[,.\s]*(?:000|xxx)\s*km/ui', $title, $m)) {
            return (int) $m[1] * 1000;
        }

        if (preg_match('/\b(\d{5,6})\s*km/ui', $title, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     */
    private function hasYearFilter(array $parsedQuery): bool
    {
        return ! empty($parsedQuery['year_min'])
            || ! empty($parsedQuery['year_max'])
            || ! empty($parsedQuery['year']);
    }
}
