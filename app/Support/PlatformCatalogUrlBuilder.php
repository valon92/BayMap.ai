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
    public static function build(array $platform, array $parsed): string
    {
        return match ($platform['adapter'] ?? 'generic') {
            'cscart' => self::csCartUrl($platform, $parsed),
            'woocommerce' => self::wooCommerceUrl($platform, $parsed),
            'automotive' => self::automotiveUrl($platform, $parsed),
            default => self::genericSearchUrl($platform, $parsed),
        };
    }

    /**
     * @param  array<string, mixed>  $platform
     */
    public static function searchTerm(array $platform, array $parsed): string
    {
        if (CategoryCatalog::isElectronics($parsed['category'] ?? '')) {
            $parts = array_filter([
                $parsed['brand'] ?? null,
                $parsed['model'] ?? null,
            ]);
            if ($parts !== []) {
                return implode(' ', $parts);
            }
            $type = mb_strtolower((string) ($parsed['product_type'] ?? ''));
            if (str_contains($type, 'phone') || preg_match('/iphone|telefon/i', (string) ($parsed['raw_query'] ?? ''))) {
                return (string) ($platform['default_query'] ?? 'iphone');
            }

            return (string) ($platform['default_query'] ?? 'laptop');
        }

        if (CategoryCatalog::normalize($parsed['category'] ?? '') === 'real_estate') {
            return (string) ($parsed['search_city'] ?? $platform['default_query'] ?? 'apartment');
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
            return (string) $parsed['brand'];
        }

        $type = mb_strtolower((string) ($parsed['product_type'] ?? ''));
        if (KosovoFashionIntent::isFootwearType($type)) {
            return (string) ($platform['default_query'] ?? 'sneakers');
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
        $template = (string) ($platform['search_template'] ?? '/?s={query}&post_type=product');
        $query = rawurlencode(self::searchTerm($platform, $parsed));

        return $base.str_replace('{query}', $query, $template);
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    private static function genericSearchUrl(array $platform, array $parsed): string
    {
        $base = rtrim((string) ($platform['base_url'] ?? ''), '/');
        $template = (string) ($platform['search_template'] ?? '/?s={query}');
        $query = rawurlencode(self::searchTerm($platform, $parsed));
        $zip = rawurlencode((string) ($parsed['search_zip'] ?? $platform['default_zip'] ?? ''));

        $url = str_replace('{query}', $query, $template);
        $url = str_replace('{zip}', $zip, $url);

        return $base.$url;
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsed
     */
    private static function automotiveUrl(array $platform, array $parsed): string
    {
        $base = rtrim((string) ($platform['base_url'] ?? ''), '/');
        $template = (string) ($platform['search_template'] ?? '/lst/{make}/{model}');
        $scraper = (string) ($platform['scraper'] ?? $platform['_key'] ?? '');
        [$make, $model] = AutomotiveModelResolver::makeModelSlugs(
            (string) ($parsed['brand'] ?? 'car'),
            (string) ($parsed['model'] ?? ''),
        );
        $query = rawurlencode(self::searchTerm($platform, $parsed));
        $makeToken = str_contains($scraper, 'mobile') ? self::mobileDeMakeCode($make) : $make;

        $url = str_replace('{make}', $makeToken, $template);
        $url = str_replace('{model}', $model, $url);
        $url = str_replace('{query}', $query, $url);

        $params = self::automotiveSearchParams($parsed, (string) ($platform['scraper'] ?? $platform['_key'] ?? ''));
        if ($params !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($params);
        }

        return $base.$url;
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
}
