<?php

namespace App\Support;

/**
 * Auto spare parts intent — component registry, localized search terms, listing filters.
 *
 * Every identified component gets: query patterns, SerpAPI terms, title match regex, junk exclusions.
 */
class AutomotivePartsIntentParser
{
    /** @var array<string, array<string, mixed>> */
    private const COMPONENTS = [
        'brake_disc' => [
            'query_patterns' => [
                '/\bdisqet?\s+e?\s*frenav/i',
                '/\bdisqe\s+fren/i',
                '/\bdisq\s+fren/i',
                '/\bdisk\s+fren/i',
                '/\bbrake\s*disc/i',
                '/\bbrake\s*rotor/i',
                '/\bbremsscheib/i',
                '/\bdisque\s+de\s+frein/i',
                '/\bdisco\s+freno/i',
                '/\btarcza\s+hamulc/i',
            ],
            'title_regex' => '/\b(bremsscheib(?:e|en)?|brems\s*scheib(?:e|en)?|brake\s*disc(?:s)?|brake\s*rotor(?:s)?|disque\s+de\s+frein|disco\s+freno|tarcza\s+hamulc)\b/u',
            'search' => [
                'DE' => 'Bremsscheibe', 'AT' => 'Bremsscheibe', 'CH' => 'Bremsscheibe',
                'FR' => 'disque de frein', 'IT' => 'disco freno', 'ES' => 'disco freno',
                'PL' => 'tarcza hamulcowa', 'default' => 'brake disc',
            ],
            'serp_extra' => ['Bremsscheiben', 'brake disc', 'brake rotor'],
        ],
        'brake_pad' => [
            'query_patterns' => [
                '/\bplaket?\s+e?\s*frenav/i',
                '/\bplaka\s+fren/i',
                '/\bbrake\s*pad/i',
                '/\bbremsbel(?:ag|äge)/i',
                '/\bplaquette/i',
            ],
            'title_regex' => '/\b(bremsbel(?:ag|äge)|brake\s*pad(?:s)?|plaquette(?:s)?\s+de\s+frein|pastilla(?:s)?\s+de\s+freno)\b/u',
            'search' => [
                'DE' => 'Bremsbeläge', 'AT' => 'Bremsbeläge', 'CH' => 'Bremsbeläge',
                'FR' => 'plaquettes de frein', 'IT' => 'pastiglie freno', 'default' => 'brake pads',
            ],
            'serp_extra' => ['Bremsbelag', 'brake pads'],
        ],
        'turbo' => [
            'query_patterns' => [
                '/\bturbin/i',
                '/\bturbolader/i',
                '/\bturbocharger/i',
                '/\bturbocompresseur/i',
            ],
            'title_regex' => '/\b(turbo(?:charger|lader|s)?|turbolader|turbina|turbine|turbocompresseur|wastegate)\b/u',
            'search' => [
                'DE' => 'Turbolader', 'AT' => 'Turbolader', 'CH' => 'Turbolader',
                'FR' => 'turbocompresseur', 'default' => 'turbo',
            ],
            'serp_extra' => ['turbocharger', 'Turbolader'],
        ],
        'engine' => [
            'query_patterns' => [
                '/\bmotor(?:i|ë|in|et)?\s+(?:per|për|for)\b/ui',
                '/\bmotor\s+(?:per|për|for)\b/ui',
                '/\b(?:gebraucht(?:s)?motor|komplettmotor)\b/ui',
            ],
            'title_regex' => '/\b(gebrauchtmotor|komplett(?:er\s+)?motor|komplettmot|tauschmotor|rumpfmotor|motor(?:block|satz|ger(?:ä|a)t)?|benzinmotor|dieselmotor|ottomotor|complete\s+engine|used\s+engine)\b/ui',
            'search' => [
                'DE' => 'Komplettmotor', 'AT' => 'Komplettmotor', 'CH' => 'Komplettmotor',
                'FR' => 'moteur', 'IT' => 'motore', 'ES' => 'motor', 'PT' => 'motor',
                'NL' => 'motor', 'PL' => 'silnik', 'default' => 'engine',
            ],
            'serp_extra' => ['Gebrauchtmotor', 'Komplettmotor', 'Tauschmotor', 'used engine'],
        ],
        'filter' => [
            'query_patterns' => [
                '/\bfilter\s+per\s+vetur/i',
                '/\b(?:oil|air|fuel|cabin)\s+filter/i',
                '/\b(?:öl|luft|kraftstoff)filter/i',
                '/\bfilt(?:er|ër|ri)\b/i',
            ],
            'title_regex' => '/\b(?:ölfilter|luftfilter|kraftstofffilter|oil\s+filter|air\s+filter|fuel\s+filter|cabin\s+filter|pollen\s+filter|filtre(?:\s+(?:à|a)\s+(?:huile|air))?|filtro)\b/u',
            'search' => [
                'DE' => 'Ölfilter', 'AT' => 'Ölfilter', 'CH' => 'Ölfilter',
                'FR' => 'filtre à huile', 'IT' => 'filtro olio', 'default' => 'oil filter',
            ],
            'serp_extra' => ['Luftfilter', 'air filter'],
        ],
        'battery' => [
            'query_patterns' => ['/\\bbateri\\b/i', '/\\bbattery\\b/i', '/\\bakkumulator/i', '/\\bautobatterie/i'],
            'title_regex' => '/\\b(batter(?:y|ie|ia)|akkumulator|autobatterie|bateri)\\b/u',
            'search' => ['DE' => 'Autobatterie', 'default' => 'car battery'],
            'serp_extra' => ['Starterbatterie'],
        ],
        'clutch' => [
            'query_patterns' => ['/\\bclutch\\b/i', '/\\bkupplung/i', '/\\bembrayage/i', '/\\bfrizion/i'],
            'title_regex' => '/\\b(clutch|kupplung(?:ssatz|satz)?|embrayage|frizione)\\b/u',
            'search' => ['DE' => 'Kupplungssatz', 'default' => 'clutch kit'],
            'serp_extra' => [],
        ],
        'radiator' => [
            'query_patterns' => ['/\\bradiator\\b/i', '/\\bkühler/i', '/\\bradiateur/i'],
            'title_regex' => '/\\b(radiator|kühler|radiateur|radiatore)\\b/u',
            'search' => ['DE' => 'Kühler', 'default' => 'radiator'],
            'serp_extra' => [],
        ],
        'alternator' => [
            'query_patterns' => ['/\\balternator/i', '/\\blichtmaschine/i', '/\\balternateur/i'],
            'title_regex' => '/\\b(alternator|lichtmaschine|alternateur|alternatore)\\b/u',
            'search' => ['DE' => 'Lichtmaschine', 'default' => 'alternator'],
            'serp_extra' => [],
        ],
        'spark_plug' => [
            'query_patterns' => ['/\\bspark\\s*plug/i', '/\\bzündkerze/i', '/\\bbougies/i'],
            'title_regex' => '/\\b(spark\\s*plug|zündkerze|bougie(?:s)?)\\b/u',
            'search' => ['DE' => 'Zündkerze', 'default' => 'spark plugs'],
            'serp_extra' => [],
        ],
        'water_pump' => [
            'query_patterns' => ['/\\bwater\\s*pump/i', '/\\bwasserpumpe/i', '/\\bpompa\\s+ujit/i'],
            'title_regex' => '/\\b(water\\s*pump|wasserpumpe|pompe\\s+à\\s+eau|pompa\\s+acqua)\\b/u',
            'search' => ['DE' => 'Wasserpumpe', 'default' => 'water pump'],
            'serp_extra' => [],
        ],
        'shock_absorber' => [
            'query_patterns' => [
                '/\\bshock\\s*absorber/i',
                '/\\bstoßdämpfer/i',
                '/\\bstossdaempfer/i',
                '/\\bamortiz(?:ator(?:ët|et|i)?|ues(?:it|ët|et)?|im)?\\b/i',
                '/\\bamortizer(?:ët|et|i)?\\b/i',
            ],
            'title_regex' => '/\\b(shock\\s*absorber|stoßdämpfer|stossdaempfer|amortisseur|amortizator)\\b/u',
            'search' => ['DE' => 'Stoßdämpfer', 'default' => 'shock absorber'],
            'serp_extra' => [],
        ],
        'exhaust' => [
            'query_patterns' => ['/\\bexhaust/i', '/\\bauspuff/i', '/\\bschalldämpfer/i', '/\\bmuffler/i'],
            'title_regex' => '/\\b(exhaust|auspuff|schalldämpfer|muffler|pot\\s+d[\'’]échappement)\\b/u',
            'search' => ['DE' => 'Auspuff', 'default' => 'exhaust'],
            'serp_extra' => [],
        ],
        'headlight' => [
            'query_patterns' => ['/\\bheadlight/i', '/\\bscheinwerfer/i', '/\\bfar(?:a|ë)/i'],
            'title_regex' => '/\\b(headlight|scheinwerfer|phare|faro|far(?:a|ë))\\b/u',
            'search' => ['DE' => 'Scheinwerfer', 'default' => 'headlight'],
            'serp_extra' => [],
        ],
        'dashcam' => [
            'query_patterns' => [
                '/\bkamera\s+(?:per|për|for)\s+(?:vetur|makina|auto|car)/ui',
                '/\b(?:dash\s*cam|dashcam|drive\s*recorder|auto\s*dvr)\b/ui',
                '/\b(?:perpara|përpara|front|vorne).*(?:permas|përmas|pasme|hinten|rear|back)/ui',
                '/\b(?:permas|përmas|pasme|hinten|rear).*(?:perpara|përpara|front|vorne)/ui',
                '/\brückfahrkamera\b/ui',
                '/\bfront\s*(?:and|&|\+|\/?)\s*rear\s*(?:cam|camera|kamera)?/ui',
                '/\bkamera\s+(?:perpara|përpara|front).*(?:pasme|permas|përmas|rear)/ui',
            ],
            'title_regex' => '/\b(dash\s*cam|dashcam|rückfahrkamera|backup\s*camera|front\s*rear|vorne\s*hinten|dual\s*channel|drive\s*recorder|auto\s*kamera|car\s*camera|kamera\s*set)\b/ui',
            'search' => [
                'DE' => 'Dashcam Front Rückfahrkamera',
                'AT' => 'Dashcam Front Rückfahrkamera',
                'CH' => 'Dashcam Front Rückfahrkamera',
                'default' => 'dash cam front rear',
            ],
            'serp_extra' => ['Dashcam', 'Rückfahrkamera Set', 'dual dash cam'],
        ],
        'injector' => [
            'query_patterns' => ['/\\binjector/i', '/\\beinspritz/i', '/\\biniettore/i'],
            'title_regex' => '/\\b(injector|einspritz(?:düse|duse)|iniettore)\\b/u',
            'search' => ['DE' => 'Einspritzdüse', 'default' => 'fuel injector'],
            'serp_extra' => [],
        ],
        'timing_belt' => [
            'query_patterns' => ['/\\btiming\\s*belt/i', '/\\bzahnriemen/i', '/\\bcourroie/i'],
            'title_regex' => '/\\b(timing\\s*belt|zahnriemen|courroie\\s+de\\s+distribution)\\b/u',
            'search' => ['DE' => 'Zahnriemen', 'default' => 'timing belt'],
            'serp_extra' => [],
        ],
        'windshield' => [
            'query_patterns' => ['/\\bwindshield/i', '/\\bwindscreen/i', '/\\bfrontscheibe/i', '/\\bxham/i'],
            'title_regex' => '/\\b(windshield|windscreen|frontscheibe|pare[- ]brise)\\b/u',
            'search' => ['DE' => 'Frontscheibe', 'default' => 'windshield'],
            'serp_extra' => [],
        ],
        'wiper' => [
            'query_patterns' => ['/\\bwiper/i', '/\\bscheibenwischer/i', '/\\bfshij/i'],
            'title_regex' => '/\\b(wiper|scheibenwischer|balai\\s+d[\'’]essuie)\\b/u',
            'search' => ['DE' => 'Scheibenwischer', 'default' => 'wiper blades'],
            'serp_extra' => [],
        ],
        'bumper' => [
            'query_patterns' => ['/\\bbumper/i', '/\\bstoßstange/i', '/\\bparaurti/i'],
            'title_regex' => '/\\b(bumper|stoßstange|stossstange|pare[- ]chocs|paraurti)\\b/u',
            'search' => ['DE' => 'Stoßstange', 'default' => 'bumper'],
            'serp_extra' => [],
        ],
        'steering_wheel' => [
            'query_patterns' => [
                '/\\btimon/i',
                '/\\bvolan/i',
                '/\\bsteering\\s*wheel/i',
                '/\\blenkrad/i',
                '/\\bvolante\\b/i',
                '/\\bvolant\\s+(?:de\\s+direction|direction)/i',
                '/\\bruota\\s+volante/i',
            ],
            'title_regex' => '/\\b(timon|volan|steering\\s*wheel|lenkrad|volante|ruota\\s+(?:del\\s+)?volante|volant\\s+(?:de\\s+direction)?)\\b/u',
            'search' => [
                'DE' => 'Lenkrad', 'AT' => 'Lenkrad', 'CH' => 'Lenkrad',
                'IT' => 'volante', 'FR' => 'volant direction', 'ES' => 'volante',
                'default' => 'steering wheel',
            ],
            'serp_extra' => ['Lenkrad', 'steering wheel', 'volante', 'Mercedes Lenkrad', 'Lenkrad Mercedes'],
        ],
        'mirror' => [
            'query_patterns' => ['/\b(?:pasqyr|mirror|spiegel|retrovisor|rétroviseur)\b/i'],
            'title_regex' => '/\b(?:side\s+mirror|wing\s+mirror|außenspiegel|aussenspiegel|retrovisor|rétroviseur|pasqyr(?:a|ë)?)\b/u',
            'search' => ['DE' => 'Außenspiegel', 'IT' => 'retrovisore', 'default' => 'side mirror'],
            'serp_extra' => ['Außenspiegel', 'side mirror'],
        ],
        'seat' => [
            'query_patterns' => ['/\b(?:sed(?:a|ë|e)|seat|sitz|siege)\b/i'],
            'title_regex' => '/\b(?:car\s+seat|autositz|sitz(?:bank|bezug)?|sed(?:a|ë|e)?)\b/u',
            'search' => ['DE' => 'Autositz', 'default' => 'car seat'],
            'serp_extra' => [],
        ],
        'door' => [
            'query_patterns' => ['/\b(?:der(?:a|ë|e)|door|tür|port(?:a|e|iere))\b/i'],
            'title_regex' => '/\b(?:car\s+door|autotür|port(?:a|e|iere)|der(?:a|ë|e))\b/u',
            'search' => ['DE' => 'Autotür', 'default' => 'car door'],
            'serp_extra' => [],
        ],
        'starter' => [
            'query_patterns' => ['/\b(?:starter|anlasser|demarreur|avviamento)\b/i'],
            'title_regex' => '/\b(?:starter(?:motor)?|anlasser|demarreur|motorino\s+avviamento)\b/u',
            'search' => ['DE' => 'Anlasser', 'default' => 'starter motor'],
            'serp_extra' => [],
        ],
        'suspension' => [
            'query_patterns' => ['/\b(?:suspension|federung|trapez|querlenker\s*arm)\b/i'],
            'title_regex' => '/\b(?:suspension|federbein|querlenker|control\s+arm)\b/u',
            'search' => ['DE' => 'Federbein', 'default' => 'suspension arm'],
            'serp_extra' => [],
        ],
        'air_spring' => [
            'query_patterns' => [
                '/\bluftfeder/i',
                '/\bluftbalg/i',
                '/\bair\s*spring/i',
                '/\bpneumat/i',
                '/\bluftfederung/i',
            ],
            'title_regex' => '/\b(luftfeder(?:ung)?|luftbalg|air\s*spring|pneumat(?:ische)?|federbein\s+vor(?:ne)?|federbein\s+hinten)\b/ui',
            'search' => [
                'DE' => 'Luftfeder', 'AT' => 'Luftfeder', 'CH' => 'Luftfeder',
                'default' => 'air spring',
            ],
            'serp_extra' => ['Luftfederung', 'Luftbalg', 'air spring'],
        ],
        'machinery' => [
            'query_patterns' => ['/\\bmachinery/i', '/\\bmakineri/i', '/\\btractor/i', '/\\bexcavator/i', '/\\bforklift/i'],
            'title_regex' => '/\\b(machinery|tractor|excavator|forklift|bulldozer|bagger|stapler)\\b/u',
            'search' => ['default' => 'machinery parts'],
            'serp_extra' => [],
        ],
    ];

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public static function merge(array $parsed, string $rawQuery): array
    {
        if (! self::isPartsSearch($parsed, $rawQuery)) {
            return $parsed;
        }

        if (self::isVehiclePurchaseQuery($parsed, $rawQuery)) {
            return $parsed;
        }

        $parsed['category'] = 'automotive_parts';
        $parsed['product_type'] = self::productType($rawQuery);
        $component = self::extractComponent($parsed, $rawQuery);
        if ($component !== '') {
            $parsed['item'] = $component;
        }

        [$inferredBrand, $inferredModel] = self::inferBrandModel($parsed, $rawQuery);
        if ($inferredBrand !== '' && empty($parsed['brand'])) {
            $parsed['brand'] = $inferredBrand;
        }
        if ($inferredModel !== '' && empty($parsed['model'])) {
            $parsed['model'] = $inferredModel;
        }

        unset($parsed['year_min'], $parsed['year_max'], $parsed['mileage'], $parsed['min_sqm']);

        $term = self::searchTerm($parsed, $rawQuery);
        if ($term !== '') {
            $parsed['search_query'] = $term;
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function isPartsSearch(array $parsed, string $rawQuery = ''): bool
    {
        $rawQuery = $rawQuery !== '' ? $rawQuery : (string) ($parsed['raw_query'] ?? '');
        if (IndustrialB2BIntentParser::isIndustrialQuery($parsed, $rawQuery)) {
            return false;
        }

        if (self::isCarAccessoryQuery($rawQuery)) {
            return true;
        }

        if (CategoryCatalog::normalize($parsed['category'] ?? '') === 'automotive_parts') {
            return true;
        }

        $rawQuery = $rawQuery !== '' ? $rawQuery : (string) ($parsed['raw_query'] ?? '');
        $lower = mb_strtolower($rawQuery);

        foreach (self::partsKeywords() as $keyword) {
            if (preg_match('/\b'.preg_quote($keyword, '/').'\b/u', $lower)) {
                return true;
            }
        }

        if (self::extractComponent($parsed, $rawQuery) !== '') {
            return true;
        }

        return false;
    }

    public static function isCarAccessoryQuery(string $rawQuery): bool
    {
        $lower = mb_strtolower(trim($rawQuery));

        if (preg_match('/\b(?:kamera|dash\s*cam|dashcam|dvr|drive\s*recorder)\b/ui', $lower)
            && preg_match('/\b(?:vetur|makina|auto|car|automjet)\b/ui', $lower)) {
            return true;
        }

        if (preg_match('/\b(?:kamera|dash\s*cam|dashcam)\b/ui', $lower)
            && preg_match('/\b(?:perpara|përpara|front|vorne).*(?:permas|përmas|pasme|hinten|rear)/ui', $lower)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function isVehiclePurchaseQuery(array $parsed, string $rawQuery): bool
    {
        if (AutomotiveIntentParser::isCarQuery($rawQuery)
            && (isset($parsed['year_min']) || isset($parsed['year_max']) || isset($parsed['mileage']))) {
            return true;
        }

        $lower = mb_strtolower($rawQuery);

        return (bool) preg_match(
            '/\b(vetur[aëe]?|makina|car)\b.*\b(viti|year|km|kilomet|mileage|diesel|benzin|automatic|manual)\b/u',
            $lower
        );
    }

    public static function productType(string $rawQuery): string
    {
        $component = self::extractComponent([], $rawQuery);

        if ($component === 'machinery') {
            return 'machinery';
        }

        if (in_array($component, ['engine', 'engine_block', 'cylinder_head'], true)) {
            return 'engine';
        }

        if (in_array($component, ['dashcam', 'backup_camera', 'parking_sensor', 'radio', 'infotainment', 'speaker'], true)) {
            return 'accessory';
        }

        return 'auto_part';
    }

    /**
     * Live scrapers often return 1–2 irrelevant listings; supplement with Google Shopping when matches are thin.
     *
     * @param  array<int, array<string, mixed>>  $results
     * @param  array<string, mixed>  $parsed
     */
    public static function needsShoppingSupplement(array $results, array $parsed): bool
    {
        if (! CategoryCatalog::isAutomotiveParts($parsed['category'] ?? '')) {
            return $results === [];
        }

        $component = self::extractComponent($parsed, (string) ($parsed['raw_query'] ?? ''));
        if ($component === '') {
            return $results === [];
        }

        $matching = 0;
        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (self::matchesListing((string) ($row['title'] ?? ''), $parsed)) {
                $matching++;
            }
        }

        return $matching < 3;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function searchTerm(array $parsed, string $rawQuery): string
    {
        [$inferredBrand, $inferredModel] = self::inferBrandModel($parsed, $rawQuery);
        $brand = trim((string) ($parsed['brand'] ?? '')) ?: $inferredBrand;
        $model = trim((string) ($parsed['model'] ?? '')) ?: $inferredModel;
        $component = self::extractComponent($parsed, $rawQuery);
        $country = strtoupper((string) ($parsed['search_country_code'] ?? ''));
        $localizedComponent = self::localizedComponentSearchTerm($country, $component);
        $brandTerm = self::formatBrandForSearch($brand, $country);
        $modelTerm = self::formatModelForSearch($model, $country);

        if ($brandTerm !== '' && $modelTerm !== '' && $localizedComponent !== '') {
            return trim($brandTerm.' '.$modelTerm.' '.$localizedComponent);
        }

        if ($brandTerm !== '' && $modelTerm !== '') {
            return trim($brandTerm.' '.$modelTerm.($localizedComponent !== '' ? ' '.$localizedComponent : ''));
        }

        $parts = array_filter([$brandTerm, $modelTerm, $localizedComponent]);

        return $parts !== [] ? trim(implode(' ', $parts)) : self::genericPartsTerm($country);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    public static function serpSearchQueries(array $parsed): array
    {
        $raw = (string) ($parsed['raw_query'] ?? '');
        $base = self::searchTerm($parsed, $raw);
        $component = self::extractComponent($parsed, $raw);
        [$inferredBrand, $inferredModel] = self::inferBrandModel($parsed, $raw);
        $brand = trim((string) ($parsed['brand'] ?? '')) ?: $inferredBrand;
        $model = trim((string) ($parsed['model'] ?? '')) ?: $inferredModel;
        $country = strtoupper((string) ($parsed['search_country_code'] ?? ''));
        $localized = self::localizedComponentSearchTerm($country, $component);
        $brandTerm = self::formatBrandForSearch($brand, $country);
        $modelTerm = self::formatModelForSearch($model, $country);
        $def = self::componentDef($component);

        $queries = [$base];

        if ($brandTerm !== '' && $modelTerm !== '' && $localized !== '') {
            $queries[] = trim($brandTerm.' '.$modelTerm.' '.$localized);
        }
        if ($modelTerm !== '' && $localized !== '') {
            $queries[] = trim($modelTerm.' '.$localized);
        }

        foreach ((array) ($def['serp_extra'] ?? []) as $extra) {
            if ($brandTerm !== '' && $modelTerm !== '') {
                $queries[] = trim($brandTerm.' '.$modelTerm.' '.$extra);
            }
            if ($modelTerm !== '') {
                $queries[] = trim($modelTerm.' '.$extra);
            }
        }

        if ($component === 'engine' && in_array($country, ['DE', 'AT', 'CH'], true)) {
            if ($brandTerm !== '' && $modelTerm !== '') {
                $queries[] = trim($brandTerm.' '.$modelTerm.' Gebrauchtmotor');
                $queries[] = trim($brandTerm.' '.$modelTerm.' Komplettmotor');
            }
        }

        return array_slice(array_values(array_unique(array_filter($queries))), 0, 4);
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function extractComponent(array $parsed, string $rawQuery): string
    {
        $item = trim((string) ($parsed['item'] ?? ''));
        if ($item !== '') {
            $normalized = self::normalizeComponentAlias($item);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        $fromLexicon = AutomotivePartsAlbanianLexicon::componentFromQuery($rawQuery);
        if ($fromLexicon !== '') {
            return $fromLexicon;
        }

        $haystack = mb_strtolower($rawQuery);

        foreach (self::allComponents() as $key => $def) {
            foreach ((array) ($def['query_patterns'] ?? []) as $pattern) {
                if (preg_match($pattern, $haystack)) {
                    return $key;
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function matchesListing(string $title, array $parsed): bool
    {
        if (! CategoryCatalog::isAutomotiveParts($parsed['category'] ?? '')) {
            return true;
        }

        $lower = mb_strtolower($title);
        $component = self::extractComponent($parsed, (string) ($parsed['raw_query'] ?? ''));

        foreach (self::universalExcludedTerms() as $term) {
            if (str_contains($lower, $term)) {
                return false;
            }
        }

        if ($component === '') {
            return self::looksLikeGenericPart($lower);
        }

        foreach (self::componentExcludedTerms($component) as $term) {
            if (str_contains($lower, $term)) {
                return false;
            }
        }

        $def = self::componentDef($component);
        $regex = (string) ($def['title_regex'] ?? '');

        if ($regex !== '' && preg_match($regex, $lower) === 1) {
            return true;
        }

        if ($component === 'engine') {
            if (preg_match('/\b(motor[oö]l|engine\s*oil|ölfilter)\b/ui', $lower)) {
                return false;
            }

            if (preg_match('/\b(gebrauchtmotor|komplettmotor|tauschmotor|rumpfmotor|motorblock|complete\s+engine|used\s+engine)\b/ui', $lower) === 1) {
                return true;
            }

            return preg_match('/\b(motor|engine|moteur|motore|silnik)\b/u', $lower) === 1
                && preg_match('/\b(motor[oö]l|engine\s*oil)\b/ui', $lower) !== 1;
        }

        return false;
    }

    private static function normalizeComponentAlias(string $term): string
    {
        $lower = mb_strtolower(trim($term));

        $fromLexicon = AutomotivePartsAlbanianLexicon::componentFromQuery($term);
        if ($fromLexicon !== '') {
            return $fromLexicon;
        }

        foreach (self::allComponents() as $key => $def) {
            if ($lower === $key || str_replace('_', ' ', $lower) === str_replace('_', ' ', $key)) {
                return $key;
            }
            foreach ((array) ($def['query_patterns'] ?? []) as $pattern) {
                if (preg_match($pattern, $lower)) {
                    return $key;
                }
            }
        }

        return match ($lower) {
            'brake', 'fren', 'frena', 'frenave', 'brakes' => 'brake_pad',
            'disc', 'disq', 'disqe', 'disqet' => 'brake_disc',
            'turbo', 'turbina', 'turbolader', 'turbocharger' => 'turbo',
            'filter', 'filtër', 'filteri' => 'filter',
            'timon', 'volan', 'lenkrad', 'volante', 'steering wheel', 'steering_wheel' => 'steering_wheel',
            default => array_key_exists($lower, self::allComponents()) ? $lower : '',
        };
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{0: string, 1: string}
     */
    private static function inferBrandModel(array $parsed, string $rawQuery): array
    {
        $brand = trim((string) ($parsed['brand'] ?? ''));
        $model = trim((string) ($parsed['model'] ?? ''));
        $auto = AutomotiveIntentParser::fromQuery($rawQuery);

        if ($brand === '' && ! empty($auto['brand'])) {
            $brand = (string) $auto['brand'];
        }
        if ($model === '' && ! empty($auto['model'])) {
            $model = (string) $auto['model'];
        }

        if ($brand === '' && $model !== '' && preg_match('/\bgolf\b/i', $model)) {
            $brand = 'Volkswagen';
        }

        if ($brand !== '' && $model !== '') {
            $model = AutomotiveModelResolver::normalizeModelForBrand($brand, $model);
        }

        return [$brand, $model];
    }

    private static function localizedComponentSearchTerm(string $countryCode, string $component): string
    {
        if ($component === '') {
            return '';
        }

        $def = self::componentDef($component);
        $search = (array) ($def['search'] ?? []);

        return AutomotivePartsLocale::resolve($countryCode, $search) ?: $component;
    }

    private static function formatBrandForSearch(string $brand, string $countryCode): string
    {
        $brand = trim($brand);
        if ($brand === '') {
            return '';
        }

        if (in_array($countryCode, ['DE', 'AT', 'CH'], true)
            && strcasecmp($brand, 'Volkswagen') === 0) {
            return 'VW';
        }

        return $brand;
    }

    private static function formatModelForSearch(string $model, string $countryCode): string
    {
        $model = trim($model);
        if ($model === '') {
            return '';
        }

        if (preg_match('/\bgolf\s*(7|vii)\b/i', $model)) {
            return 'Golf 7';
        }

        return ucwords($model);
    }

    /**
     * @return array<string, mixed>
     */
    private static function componentDef(string $component): array
    {
        return self::allComponents()[$component] ?? [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function allComponents(): array
    {
        static $merged = null;

        if ($merged === null) {
            $merged = array_merge(AutomotivePartsComponentRegistry::definitions(), self::COMPONENTS);
            if (isset($merged['engine'])) {
                $merged['engine']['search'] = AutomotivePartsLocale::searchMap('Komplettmotor', 'engine', [
                    'FR' => 'moteur',
                    'IT' => 'motore',
                    'ES' => 'motor',
                    'PT' => 'motor',
                    'NL' => 'motor',
                    'PL' => 'silnik',
                ]);
            }
        }

        return $merged;
    }

    private static function genericPartsTerm(string $countryCode): string
    {
        return AutomotivePartsLocale::genericPartsTerm($countryCode);
    }

    private static function looksLikeGenericPart(string $lowerTitle): bool
    {
        if (preg_match('/\b(?:teil(?:e)?|ersatzteil|autoteil|ricambi|repuesto|onderdeel|spare\s+part|oem|aftermarket)\b/u', $lowerTitle)) {
            return true;
        }

        foreach (array_keys(self::allComponents()) as $key) {
            $def = self::allComponents()[$key];
            $regex = (string) ($def['title_regex'] ?? '');
            if ($regex !== '' && preg_match($regex, $lowerTitle) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Junk that appears in almost every brand+model Google Shopping query.
     *
     * @return array<int, string>
     */
    private static function universalExcludedTerms(): array
    {
        return [
            'modellauto', 'modell ', 'model auto', 'diecast', 'die cast', 'spielzeug',
            'minichamps', 'herpa', 'ixo ', 'welly', 'norev', 'motormax', 'bburago',
            'paragon', 'gt spirit', 'speed city', 'maßstab', 'massstab', '1:18', '1:24',
            '1:36', '1:43', '1:87', 'miniature', 'sammlermodell', 'sammlung',
            '2kidstoys', 'online toy', 'zweikinder',
            'chiptuning', 'racechip', 'dte systems', 'turboost', 'gp-tuning', 'gp tuning',
            'leistungssteigerung', 'stage-x', 'stufe 1', 'performance chip',
            'ac schnitzer', 'renegade design', 'widebody', 'body kit', 'bodykit',
            'aero kit', 'aero bodykit', 'kitt tuning', 'spoilerlippe', 'spoiler lip',
            'diffusor', 'diffuser', 'stoßstange', 'stossstange', 'frontstoßstange',
            'front bumper', 'stoßstangenlippe', 'tieferlegung', 'tieferlegungsfedern',
            'gewindefahrwerk', 'coilover', 'fahrwerk', 'led-paket', 'led paket',
            'prospekt', 'preisliste', 'brochure',
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function componentExcludedTerms(string $component): array
    {
        $tuning = ['chiptuning', 'racechip', 'leistungssteigerung', 'turboost', 'gp-tuning'];

        return match ($component) {
            'brake_disc', 'brake_pad' => array_merge($tuning, [
                'modellauto', 'stoßstange', 'spoiler', 'body kit', 'tieferlegung',
            ]),
            'turbo' => [
                'luftfilter', 'air filter', 'ölfilter', 'oil filter', 'grill', 'spoiler',
            ],
            'filter' => ['grill', 'spoiler', 'stoßstange', 'modellauto'],
            'steering_wheel' => [
                'paraurti', 'pare-chocs', 'stoßstange', 'stossstange', 'bumper',
                'auspuff', 'sportauspuff', 'schalldämpfer', 'exhaust', 'muffler',
            ],
            'engine', 'engine_block' => [
                'tachometer', 'drehzahlmesser', 'drehzahl', 'speedometer', 'kilometerteller',
                'instrumentencluster', 'instrument cluster', 'kombiinstrument', 'cockpit',
                'wischermotor', 'scheibenwischer', 'anlasser', 'startermotor', 'fensterheber',
                'motoröl', 'motorol', 'engine oil', 'öl ', ' oil ',
            ],
            'dashcam', 'backup_camera' => [
                'bmw ', 'audi ', 'mercedes', 'volkswagen', 'opel ', 'ford ', 'toyota ',
                '520d', '530d', 'q5', 'q7', 'a6', 'a4', 'golf', 'tiguan', 'passat',
                'gebrauchtwagen', 'fahrzeug', 'pkw', 'limousine', 'coupe', ' km', 'kilometer',
                'efficientdynamics', 'm sport', 'tdi quattro', 'tfsi quattro',
            ],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private static function partsKeywords(): array
    {
        return array_values(array_unique(array_merge(
            AutomotivePartsAlbanianLexicon::partsKeywords(),
            [
                'disqet', 'disqe', 'disq', 'frenave', 'plaket', 'plaka',
                'timon', 'timoni', 'timonë', 'timone', 'volan', 'volani',
                'luftfeder', 'luftbalg', 'luftfederung', 'pneumat',
            ],
        )));
    }
}
