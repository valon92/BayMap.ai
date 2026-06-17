<?php

namespace App\Support;

/**
 * Builds catalog/search URLs for live platform scrapers from config + parsed intent.
 */
class PlatformCatalogUrlBuilder
{
    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    public static function build(array $platform, array $parsed, int $page = 1): string
    {
        return match ($platform['adapter'] ?? 'generic') {
            'cscart' => self::csCartUrl($platform, $parsed),
            'woocommerce' => self::wooCommerceUrl($platform, $parsed),
            'automotive' => self::automotiveUrl($platform, $parsed, $page),
            'real_estate' => self::realEstateUrl($platform, $parsed),
            default => self::genericSearchUrl($platform, $parsed),
        };
    }

    /**
     * @param  array<string, mixed>  $platform
     */
    public static function searchTerm(array $platform, array $parsed): string
    {
        if (CategoryCatalog::normalize($parsed['category'] ?? '') === 'gaming_entertainment'
            && (($platform['toy_retailer'] ?? false) || KosovoToyIntent::isToySearch($parsed))) {
            return self::toySearchTerm($platform, $parsed);
        }

        if (CategoryCatalog::isElectronics($parsed['category'] ?? '')) {
            $brand = trim((string) ($parsed['brand'] ?? ''));
            $model = trim((string) ($parsed['model'] ?? ''));
            $raw = trim((string) ($parsed['raw_query'] ?? $parsed['search_query'] ?? ''));

            if ($model !== '') {
                if (preg_match('/\b(macbook|iphone|ipad|airpods)\b/i', $model)) {
                    return $model;
                }

                return $brand !== '' ? trim($brand.' '.$model) : $model;
            }

            if ($raw !== '' && preg_match('/\b(macbook\s*(?:air|pro)?|iphone\s*[\d\w\s]*|ipad\s*[\w\s]*|airpods(?:\s*(?:pro|max|3|2))?)\b/ui', $raw, $m)) {
                return trim($m[0]);
            }

            if ($brand !== '') {
                return $brand;
            }

            $type = mb_strtolower((string) ($parsed['product_type'] ?? ''));
            if (str_contains($type, 'phone') || preg_match('/iphone|telefon/i', $raw)) {
                return (string) ($platform['default_query'] ?? 'iphone');
            }

            return (string) ($platform['default_query'] ?? 'laptop');
        }

        if (CategoryCatalog::normalize($parsed['category'] ?? '') === 'real_estate') {
            $city = trim((string) ($parsed['search_city'] ?? ''));
            if ($city !== '') {
                return $city;
            }

            $raw = mb_strtolower((string) ($parsed['raw_query'] ?? ''));
            foreach (['zürich', 'zurich', 'bern', 'basel', 'geneva', 'genève', 'genf', 'lausanne', 'luzern', 'winterthur'] as $place) {
                if (str_contains($raw, $place)) {
                    return $place === 'zürich' ? 'zurich' : $place;
                }
            }

            return (string) ($platform['default_query'] ?? 'schweiz');
        }

        if (CategoryCatalog::normalize($parsed['category'] ?? '') === 'sports_outdoor') {
            $type = mb_strtolower((string) ($parsed['product_type'] ?? ''));
            if ($type === 'scooter' || preg_match('/\b(skuter|scooter)\b/ui', (string) ($parsed['raw_query'] ?? ''))) {
                return (string) ($platform['default_query'] ?? 'scooter');
            }
        }

        if (CategoryCatalog::isAutomotive($parsed['category'] ?? '')) {
            $parts = array_filter([
                $parsed['brand'] ?? null,
                $parsed['model'] ?? null,
            ]);
            if ($parts !== []) {
                return implode(' ', $parts);
            }

            return (string) ($platform['default_query'] ?? 'car');
        }

        if (! empty($parsed['brand'])) {
            $brand = trim((string) $parsed['brand']);
            $type = mb_strtolower((string) ($parsed['product_type'] ?? ''));
            $raw = mb_strtolower(trim((string) ($parsed['raw_query'] ?? $parsed['search_query'] ?? '')));

            if (KosovoFashionIntent::isAccessoryType($type) || KosovoFashionIntent::queryMentionsAccessory($raw)) {
                $accessory = match (true) {
                    str_contains($type, 'cap') || str_contains($type, 'hat') || str_contains($raw, 'cap') || str_contains($raw, 'hat') || str_contains($raw, 'kapel') => self::localizedCapTerm($platform, $parsed),
                    default => trim($type !== '' ? $type : 'accessory'),
                };

                return $brand !== '' ? trim($brand.' '.$accessory) : $accessory;
            }

            if (in_array(CategoryCatalog::normalize($parsed['category'] ?? ''), ['fashion', 'sports_outdoor'], true)
                && KosovoFashionIntent::isFootwearType($type)) {
                return $brand;
            }

            return $brand;
        }

        $type = mb_strtolower((string) ($parsed['product_type'] ?? ''));
        if (KosovoFashionIntent::isFootwearType($type)) {
            return (string) ($platform['default_query'] ?? 'sneakers');
        }

        if (KosovoFashionIntent::isAccessoryType($type)) {
            return self::localizedCapTerm($platform, $parsed);
        }

        $raw = trim((string) ($parsed['search_query'] ?? $parsed['raw_query'] ?? ''));
        $raw = preg_replace('/\b(?:size|numer|nr|madh[eë]sia|größe|groesse)\s*\d+(?:\.\d+)?\b/ui', '', $raw) ?? $raw;
        $raw = preg_replace('/\b(?:deri|max|up to|bis)\s*(?:ne|në|to)?\s*\d+\s*(?:euro|eur|€)\b/ui', '', $raw) ?? $raw;
        $raw = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);

        if ($raw !== '') {
            return $raw;
        }

        return (string) ($platform['default_query'] ?? 'fashion');
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    private static function csCartUrl(array $platform, array $parsed): string
    {
        $base = rtrim((string) ($platform['base_url'] ?? ''), '/');
        $paths = (array) ($platform['paths'] ?? []);
        $productType = mb_strtolower((string) ($parsed['product_type'] ?? ''));
        $query = mb_strtolower((string) ($parsed['raw_query'] ?? $parsed['search_query'] ?? ''));

        $path = match (true) {
            KosovoFashionIntent::isFootwearType($productType) || KosovoFashionIntent::queryMentionsFootwear($query)
                => $paths['footwear'] ?? $paths['default'] ?? '/',
            in_array(mb_strtolower((string) ($parsed['gender'] ?? '')), ['female', 'women', 'femra'], true)
                => $paths['women'] ?? $paths['default'] ?? '/',
            in_array(mb_strtolower((string) ($parsed['gender'] ?? '')), ['male', 'men', 'meshkuj'], true)
                => $paths['men'] ?? $paths['default'] ?? '/',
            default => $paths['default'] ?? '/',
        };

        $brand = mb_strtolower((string) ($parsed['brand'] ?? ''));
        $hashes = (array) ($platform['brand_hashes'] ?? []);
        $hash = $hashes[$brand] ?? null;

        $url = $base.$path;
        if ($hash !== null) {
            $url .= '?features_hash='.$hash;
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    private static function wooCommerceUrl(array $platform, array $parsed): string
    {
        $base = rtrim((string) ($platform['base_url'] ?? ''), '/');
        $accessoryPath = self::wooCommerceAccessoryPath($platform, $parsed);

        if ($accessoryPath !== null) {
            $query = rawurlencode(self::wooCommerceAccessoryQuery($platform, $parsed));
            $suffix = (string) ($platform['category_search_template'] ?? '?s={query}&post_type=product');

            return $base.rtrim($accessoryPath, '/').'/'.ltrim(str_replace('{query}', $query, $suffix), '/');
        }

        $template = (string) ($platform['search_template'] ?? '/?s={query}&post_type=product');
        $query = rawurlencode(self::searchTerm($platform, $parsed));

        return $base.str_replace('{query}', $query, $template);
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    private static function wooCommerceAccessoryPath(array $platform, array $parsed): ?string
    {
        $paths = (array) ($platform['paths'] ?? []);
        $type = mb_strtolower((string) ($parsed['product_type'] ?? ''));
        $raw = mb_strtolower(trim((string) ($parsed['raw_query'] ?? $parsed['search_query'] ?? '')));

        if (! KosovoFashionIntent::isAccessoryType($type) && ! KosovoFashionIntent::queryMentionsAccessory($raw)) {
            return null;
        }

        return isset($paths['accessories']) ? (string) $paths['accessories'] : null;
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    private static function wooCommerceAccessoryQuery(array $platform, array $parsed): string
    {
        if (! empty($parsed['brand'])) {
            return trim((string) $parsed['brand']);
        }

        return self::localizedCapTerm($platform, $parsed);
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    private static function genericSearchUrl(array $platform, array $parsed): string
    {
        $scraper = (string) ($platform['scraper'] ?? $platform['_key'] ?? '');

        if (str_contains($scraper, 'apple')) {
            return self::appleStoreUrl($platform, $parsed);
        }

        $base = rtrim((string) ($platform['base_url'] ?? ''), '/');
        $template = (string) ($platform['search_template'] ?? '/?s={query}');
        $query = rawurlencode(self::searchTerm($platform, $parsed));
        $slug = rawurlencode(self::appleSearchSlug(self::searchTerm($platform, $parsed)));
        $zip = rawurlencode((string) ($parsed['search_zip'] ?? $platform['default_zip'] ?? ''));

        $url = str_replace('{query}', $query, $template);
        $url = str_replace('{slug}', $slug, $url);
        $url = str_replace('{zip}', $zip, $url);

        return $base.$url;
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    private static function appleStoreUrl(array $platform, array $parsed): string
    {
        $base = rtrim((string) ($platform['base_url'] ?? 'https://www.apple.com'), '/');
        $shopPrefix = trim((string) ($platform['shop_prefix'] ?? 'ch-de'), '/');
        $query = mb_strtolower(self::searchTerm($platform, $parsed));

        if (str_contains($query, 'macbook')) {
            if (str_contains($query, 'pro')) {
                return $base.'/'.$shopPrefix.'/shop/buy-mac/macbook-pro';
            }

            return $base.'/'.$shopPrefix.'/shop/buy-mac/macbook-air';
        }

        if (str_contains($query, 'iphone')) {
            return $base.'/'.$shopPrefix.'/shop/buy-iphone';
        }

        if (str_contains($query, 'ipad')) {
            return $base.'/'.$shopPrefix.'/shop/buy-ipad';
        }

        if (str_contains($query, 'watch')) {
            return $base.'/'.$shopPrefix.'/shop/buy-watch';
        }

        $slug = self::appleSearchSlug(self::searchTerm($platform, $parsed));

        return $base.'/'.$shopPrefix.'/shop/search/'.$slug;
    }

    private static function appleSearchSlug(string $query): string
    {
        $parts = preg_split('/\s+/', trim($query)) ?: [];
        $slugParts = array_map(fn (string $part) => ucfirst(mb_strtolower($part)), $parts);

        return implode('-', $slugParts);
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    private static function realEstateUrl(array $platform, array $parsed): string
    {
        $key = strtolower((string) ($platform['_key'] ?? ''));
        $listing = SwissRealEstateIntent::listingType($parsed);
        $segment = $listing === 'sale' ? 'kaufen' : 'mieten';
        $query = rawurlencode(self::searchTerm($platform, $parsed));
        $slug = str_replace('%20', '-', $query);
        $base = rtrim((string) ($platform['base_url'] ?? ''), '/');

        if (str_contains($key, 'homegate')) {
            return $base.'/'.$segment.'/wohnung/ort-'.$slug.'/trefferliste';
        }

        if (str_contains($key, 'immoscout24')) {
            return $base.'/'.$segment.'/wohnung';
        }

        if (str_contains($key, 'newhome')) {
            return $base.'/'.$segment.'/suche?location='.$query;
        }

        if (str_contains($key, 'comparis')) {
            $type = $listing === 'sale' ? 'wohnung-kaufen' : 'wohnung-mieten';

            return $base.'/immobilien/marktplatz/'.$type.'?q='.$query;
        }

        $template = (string) ($platform['search_template'] ?? '/'.$segment.'?q={query}');
        $template = str_replace('mieten', $segment, $template);

        return $base.str_replace('{query}', $query, $template);
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    private static function automotiveUrl(array $platform, array $parsed, int $page = 1): string
    {
        $scraper = (string) ($platform['scraper'] ?? $platform['_key'] ?? '');

        if (str_contains($scraper, 'autolina')) {
            return self::autolinaUrl($platform, $parsed);
        }

        if (str_contains($scraper, 'merrjep')) {
            return self::merrjepAutoUrl($platform, $parsed);
        }

        if (str_contains($scraper, 'veturaneshitje')) {
            return self::veturaneshitjeUrl($platform, $parsed);
        }

        if (str_contains($scraper, 'autogrid')) {
            return self::autogridUrl($platform, $parsed);
        }

        if (str_contains($scraper, 'swiss_html')) {
            return self::swissCatalogUrl($platform, $parsed);
        }

        $base = rtrim((string) ($platform['base_url'] ?? ''), '/');
        $template = (string) ($platform['search_template'] ?? '/lst/{make}/{model}');
        [$make, $model] = AutomotiveModelResolver::makeModelSlugs(
            (string) ($parsed['brand'] ?? 'car'),
            (string) ($parsed['model'] ?? ''),
        );
        $query = rawurlencode(self::searchTerm($platform, $parsed));
        $makeToken = str_contains($scraper, 'mobile') ? self::mobileDeMakeCode($make) : $make;

        $url = str_replace('{make}', $makeToken, $template);
        $url = str_replace('{model}', $model, $url);
        $url = str_replace('{query}', $query, $url);

        $params = self::automotiveSearchParams($parsed, $scraper);
        if ($page > 1) {
            if (str_contains($scraper, 'kleinanzeigen')) {
                $params['page'] = $page;
            } elseif (str_contains($scraper, 'autoscout')) {
                $params['page'] = $page;
            }
        }

        if ($params !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($params);
        }

        return $base.$url;
    }

    /**
     * Autolina uses /{make}/{model} paths (e.g. /audi/q7) with SSR listing cards.
     *
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    private static function autolinaUrl(array $platform, array $parsed): string
    {
        $base = rtrim((string) ($platform['base_url'] ?? 'https://www.autolina.ch'), '/');
        [$make, $model] = AutomotiveModelResolver::makeModelSlugs(
            (string) ($parsed['brand'] ?? ''),
            (string) ($parsed['model'] ?? ''),
        );
        $make = self::autolinaMakeSlug($make);

        if ($make === '' || $make === 'car' || $make === 'all') {
            return $base.'/de/fahrzeuge';
        }

        if ($model === '' || $model === 'all') {
            return $base.'/'.$make;
        }

        return $base.'/'.$make.'/'.$model;
    }

    private static function autolinaMakeSlug(string $make): string
    {
        return match (mb_strtolower(trim($make))) {
            'volkswagen', 'vw' => 'vw',
            'mercedes', 'mercedes-benz', 'mercedes benz' => 'mercedes-benz',
            default => str_replace(' ', '-', mb_strtolower(trim($make))),
        };
    }

    /**
     * Autogrid uses /inserate/marke/{make}-occasionen or /inserate?q=bmw+x5.
     *
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    private static function autogridUrl(array $platform, array $parsed): string
    {
        $base = rtrim((string) ($platform['base_url'] ?? 'https://www.autogrid.ch'), '/');
        [$make, $model] = AutomotiveModelResolver::makeModelSlugs(
            (string) ($parsed['brand'] ?? ''),
            (string) ($parsed['model'] ?? ''),
        );
        $make = self::autolinaMakeSlug($make);

        if ($make !== '' && $make !== 'car' && $make !== 'all') {
            if ($model !== '' && $model !== 'all') {
                $query = rawurlencode(trim($make.' '.$model));

                return $base.'/inserate?q='.$query;
            }

            return $base.'/inserate/marke/'.$make.'-occasionen';
        }

        return $base.'/inserate';
    }

    /**
     * Generic Swiss catalog URL from platform search_template.
     *
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    private static function swissCatalogUrl(array $platform, array $parsed): string
    {
        $base = rtrim((string) ($platform['base_url'] ?? ''), '/');
        $template = (string) ($platform['search_template'] ?? '/?q={query}');
        [$make, $model] = AutomotiveModelResolver::makeModelSlugs(
            (string) ($parsed['brand'] ?? ''),
            (string) ($parsed['model'] ?? ''),
        );
        $make = self::autolinaMakeSlug($make);
        $query = self::searchTerm($platform, $parsed);
        $slugQuery = str_replace(' ', '-', mb_strtolower(trim($make.($model !== '' && $model !== 'all' ? '-'.$model : ''))));

        $url = str_replace('{make}', $make, $template);
        $url = str_replace('{model}', $model === 'all' ? '' : $model, $url);
        $url = str_replace('{query}', rawurlencode($query), $url);
        $url = str_replace('{slug}', $slugQuery, $url);

        return $base.$url;
    }

    /**
     * MerrJep Auto — dealer listings by make/model path.
     *
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    private static function merrjepAutoUrl(array $platform, array $parsed): string
    {
        $base = rtrim((string) ($platform['base_url'] ?? ''), '/');
        $make = mb_strtolower(trim((string) ($parsed['brand'] ?? '')));
        $model = mb_strtolower(trim((string) ($parsed['model'] ?? '')));
        $model = str_replace(' ', '-', $model);

        if ($make !== '' && $model !== '') {
            return $base.'/shpallje/makina/vetura/'.$make.'/'.$model.'?Private=False';
        }

        if ($make !== '') {
            return $base.'/shpallje/makina/vetura/'.$make.'?Private=False';
        }

        return $base.'/shpallje/makina/vetura?Private=False';
    }

    /**
     * Veturaneshitje.com — Kosovo autosallon aggregator.
     *
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    private static function veturaneshitjeUrl(array $platform, array $parsed): string
    {
        $base = rtrim((string) ($platform['base_url'] ?? ''), '/');
        $makeIds = (array) ($platform['make_ids'] ?? []);
        $brand = mb_strtolower(str_replace([' ', '-'], '', (string) ($parsed['brand'] ?? '')));
        $makeId = $makeIds[$brand] ?? null;

        if ($makeId !== null) {
            return $base.'/vetura?make='.$makeId;
        }

        return $base.'/vetura';
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, string|int>
     */
    private static function automotiveSearchParams(array $parsed, string $scraper): array
    {
        if (str_contains($scraper, 'kleinanzeigen')) {
            return self::kleinanzeigenSearchParams($parsed);
        }

        if (! str_contains($scraper, 'autoscout')) {
            return [];
        }

        $params = [];

        if (! empty($parsed['year_min'])) {
            $params['fregfrom'] = (int) $parsed['year_min'];
        }
        if (! empty($parsed['year_max'])) {
            $params['fregto'] = (int) $parsed['year_max'];
        }
        if (! empty($parsed['max_price'])) {
            $maxPrice = (int) $parsed['max_price'];
            if (str_contains($scraper, '_ch') || strtoupper((string) ($parsed['search_country_code'] ?? '')) === 'CH') {
                $maxPrice = (int) round($maxPrice * 0.95);
            }
            $params['priceto'] = $maxPrice;
        }

        $fuel = AutomotiveIntentParser::normalizeFuel((string) ($parsed['fuel'] ?? ''));
        $fuelCode = match ($fuel) {
            'diesel' => 'D',
            'petrol' => 'B',
            'electric' => 'E',
            'hybrid' => '2',
            default => null,
        };
        if ($fuelCode !== null) {
            $params['fuel'] = $fuelCode;
        }

        $color = (string) ($parsed['color'] ?? '');
        if ($color !== '' && $color !== 'multicolor') {
            $bodyColor = AutomotiveColorResolver::autoScoutBodyColorCode($color);
            if ($bodyColor !== null) {
                $params['bodyColor'] = $bodyColor;
            }
        } elseif ($color === 'multicolor' && ! empty($parsed['colors']) && is_array($parsed['colors'])) {
            // AutoScout accepts one body color — skip URL filter; post-filter handles multicolor.
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, string>
     */
    private static function kleinanzeigenSearchParams(array $parsed): array
    {
        $keywords = [];
        $model = (string) ($parsed['model'] ?? '');
        $base = AutomotiveModelResolver::baseModelName($model);
        if ($base !== '') {
            $keywords[] = $base;
        }
        $generation = AutomotiveModelResolver::generationFromModel($model);
        if ($generation !== null) {
            $keywords[] = (string) $generation;
        }

        $fuel = mb_strtolower((string) ($parsed['fuel'] ?? ''));
        if (in_array($fuel, ['diesel', 'dizel', 'tdi', 'disel'], true)) {
            $keywords[] = 'tdi';
        }

        if (! empty($parsed['engine_liters'])) {
            $keywords[] = AutomotiveEngineResolver::keywordForSearch((float) $parsed['engine_liters']);
        }

        if (! empty($parsed['color'])) {
            $colorKeyword = AutomotiveColorResolver::germanSearchKeyword((string) $parsed['color']);
            if ($colorKeyword !== null) {
                $keywords[] = $colorKeyword;
            }
        }

        if ($keywords === []) {
            return [];
        }

        return ['keywords' => implode(' ', $keywords)];
    }

    private static function mobileDeMakeCode(string $make): string
    {
        return match (mb_strtolower(str_replace('-', '', $make))) {
            'volkswagen' => '25200',
            'audi' => '19000',
            'bmw' => '3500',
            'mercedesbenz', 'mercedes' => '17200',
            'ford' => '9000',
            'opel' => '5400',
            'skoda' => '22900',
            'seat' => '21900',
            default => '11000',
        };
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    private static function toySearchTerm(array $platform, array $parsed): string
    {
        $type = mb_strtolower((string) ($parsed['product_type'] ?? ''));
        $raw = trim((string) ($parsed['search_query'] ?? $parsed['raw_query'] ?? ''));

        if ($type === 'toy_car' || ProductCategoryResolver::isChildrenToyVehicleQuery($raw)) {
            return 'makina femije';
        }

        if ($type === 'piano' || preg_match('/\bpiano\b/ui', $raw)) {
            return 'piano';
        }
        $raw = preg_replace('/\b(?:e\s+)?vogel\b/ui', '', $raw) ?? $raw;
        $raw = preg_replace('/\b(?:per|për)\s+femij(?:e|ë)?\b/ui', '', $raw) ?? $raw;
        $raw = preg_replace('/\b(?:kerkoj|kërkoj|dua|nje|një|loder|lodër)\b/ui', '', $raw) ?? $raw;
        $raw = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);

        if ($raw !== '' && mb_strlen($raw) >= 3) {
            return $raw;
        }

        return (string) ($platform['default_query'] ?? 'lodra');
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    private static function localizedCapTerm(array $platform, array $parsed): string
    {
        return 'cap';
    }
}
