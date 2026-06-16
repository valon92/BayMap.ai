<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Category;
use App\Models\Catalog\Country;
use App\Models\Catalog\Platform;
use App\Support\CategoryCatalog;
use App\Support\KosovoAutomotiveIntent;
use App\Support\KosovoFashionPlatforms;
use App\Support\KosovoToyIntent;
use App\Support\KosovoToyPlatforms;
use App\Support\PlatformCatalogBridge;
use App\Support\ProductCategoryResolver;
use App\Support\SearchScopeResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Routes a parsed product intent to the exact online platforms (Valon workers) to query.
 */
class PlatformRoutingEngine
{
    private const ROUTE_CACHE = 'catalog:route:v1:';

    public function __construct(
        private PlatformCatalogRepository $catalog,
        private \App\Services\Providers\ProviderIntelligenceService $intelligence,
    ) {}

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{
     *   scope: string,
     *   country_code: string,
     *   city: ?string,
     *   category: string,
     *   keys: array<int, string>,
     *   platforms: array<int, array{key: string, label: string, country: string, source: string, score: float}>,
     *   routing_source: string
     * }
     */
    public function route(array $parsed): array
    {
        $category = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');
        $scope = SearchScopeResolver::isUniversal($parsed) ? 'universal' : 'targeted';
        $countryCode = strtoupper((string) ($parsed['search_country_code'] ?? ''));
        $city = isset($parsed['search_city']) ? (string) $parsed['search_city'] : null;

        if ($scope === 'universal') {
            $countryCode = 'WW';
        }

        $cacheKey = self::ROUTE_CACHE.md5(json_encode([
            $scope,
            $countryCode,
            $city,
            $category,
            $parsed['product_type'] ?? '',
            $parsed['brand'] ?? '',
            $parsed['raw_query'] ?? '',
        ]));

        $ttl = (int) config('catalog.routing_cache_ttl_seconds', 300);

        $result = Cache::remember($cacheKey, $ttl, function () use ($parsed, $category, $scope, $countryCode, $city) {
            if ($this->catalog->isActive()) {
                $keys = $scope === 'universal'
                    ? $this->globalKeysFromDb($category)
                    : $this->targetedKeysFromDb($countryCode, $category, $city);

                if ($keys !== []) {
                    $keys = $this->applyIntentFilters($keys, $parsed, $countryCode);
                    $keys = $this->capKeys($keys);

                    return $this->buildResult($keys, $scope, $countryCode, $city, $category, 'database');
                }
            }

            $keys = $scope === 'universal'
                ? $this->globalKeysFromConfig($category)
                : $this->legacyTargetedKeys($countryCode, $category, $city);

            $keys = $this->applyIntentFilters($keys, $parsed, $countryCode);
            $keys = $this->capKeys($keys);

            return $this->buildResult($keys, $scope, $countryCode, $city, $category, 'config');
        });

        return $result;
    }

    public static function flushCache(): void
    {
        // Route keys are hashed; full flush happens via cache tags on deploy or catalog:sync.
    }

    /**
     * @return array<int, string>
     */
    private function targetedKeysFromDb(string $countryCode, string $category, ?string $city): array
    {
        if ($countryCode === '') {
            return [];
        }

        $country = Country::query()->where('iso2', $countryCode)->first(['id']);
        if ($country === null) {
            return [];
        }

        $categoryId = Category::query()->where('slug', $category)->value('id');

        $query = Platform::query()
            ->routable()
            ->where(function ($builder) use ($country, $countryCode) {
                $builder->where('primary_country', $countryCode)
                    ->orWhere('is_global', true)
                    ->orWhereHas('countries', function ($q) use ($country) {
                        $q->where('countries.id', $country->id)->where('country_platform.enabled', true);
                    });
            });

        if ($categoryId !== null) {
            $query->where(function ($builder) use ($categoryId, $category) {
                $builder->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId));
                if ($category === 'marketplace') {
                    $builder->orWhereDoesntHave('categories');
                }
            });
        }

        $platforms = $query
            ->with(['cities:platform_id,city', 'categories:slug'])
            ->get();

        if ($city !== null && $city !== '') {
            $platforms = $platforms->filter(function (Platform $platform) use ($city) {
                $cities = $platform->cities->pluck('city')->all();
                if ($cities === []) {
                    return true;
                }

                return in_array(mb_strtolower($city), array_map('mb_strtolower', $cities), true);
            });
        }

        return $platforms
            ->sortBy(fn (Platform $p) => $this->scorePlatform($p, $category))
            ->pluck('slug')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function globalKeysFromDb(string $category): array
    {
        $categoryId = Category::query()->where('slug', $category)->value('id');

        $query = Platform::query()->routable()->where('is_global', true);

        if ($categoryId !== null) {
            $query->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId));
        }

        return $query->orderBy('priority')->pluck('slug')->all();
    }

    /**
     * @return array<int, string>
     */
    private function globalKeysFromConfig(string $category): array
    {
        $keys = [];
        foreach ($this->catalog->allPlatforms() as $key => $meta) {
            if ($this->matchesCategory($meta, $category) && $this->isGlobalPlatform($meta)) {
                $keys[] = $key;
            }
        }

        usort($keys, fn ($a, $b) => $this->configPriority($a) <=> $this->configPriority($b));

        return $keys;
    }

    /**
     * @return array<int, string>
     */
    private function legacyTargetedKeys(string $countryCode, string $category, ?string $city): array
    {
        if ($countryCode === '') {
            return [];
        }

        $keys = [];
        foreach ($this->catalog->allPlatforms() as $key => $meta) {
            if (strtoupper((string) ($meta['country'] ?? '')) !== $countryCode) {
                continue;
            }
            if (! $this->matchesCategory($meta, $category)) {
                continue;
            }
            if (! $this->matchesCity($meta, $city)) {
                continue;
            }
            $keys[] = $key;
        }

        $keys = array_values(array_unique(array_merge($keys, PlatformCatalogBridge::keysFor($countryCode, $category))));
        usort($keys, fn ($a, $b) => $this->configPriority($a) <=> $this->configPriority($b));

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function matchesCity(array $meta, ?string $city): bool
    {
        $cities = (array) ($meta['cities'] ?? []);
        if ($cities === [] || $city === null || $city === '') {
            return true;
        }

        $cityLower = mb_strtolower($city);
        foreach ($cities as $allowed) {
            if (mb_strtolower((string) $allowed) === $cityLower) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    private function applyIntentFilters(array $keys, array $parsed, string $countryCode): array
    {
        if (KosovoToyIntent::isToySearch($parsed) && $countryCode === 'XK') {
            $allowed = array_flip(KosovoToyPlatforms::keys());

            return array_values(array_filter($keys, fn (string $key) => isset($allowed[$key])));
        }

        if (CategoryCatalog::isAutomotive($parsed['category'] ?? '') && $countryCode === 'XK') {
            $allowed = array_flip(KosovoAutomotiveIntent::livePlatformKeys());

            return array_values(array_filter($keys, fn (string $key) => isset($allowed[$key])));
        }

        if (ProductCategoryResolver::isFashionPlatformRelevant($parsed)) {
            $toyKeys = array_flip(KosovoToyPlatforms::keys());

            return array_values(array_filter($keys, fn (string $key) => ! isset($toyKeys[$key])));
        }

        $fashionKeys = array_flip(KosovoFashionPlatforms::keys());
        $toyKeys = array_flip(KosovoToyPlatforms::keys());

        return array_values(array_filter(
            $keys,
            fn (string $key) => ! isset($fashionKeys[$key]) && ! isset($toyKeys[$key]),
        ));
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    private function capKeys(array $keys): array
    {
        $cap = (int) config('live_platforms.max_workers_cap', 24);

        return count($keys) > $cap ? array_slice($keys, 0, $cap) : $keys;
    }

    /**
     * @param  array<int, string>  $keys
     * @return array{
     *   scope: string,
     *   country_code: string,
     *   city: ?string,
     *   category: string,
     *   keys: array<int, string>,
     *   platforms: array<int, array{key: string, label: string, country: string, source: string, score: float}>,
     *   routing_source: string
     * }
     */
    private function buildResult(
        array $keys,
        string $scope,
        string $countryCode,
        ?string $city,
        string $category,
        string $routingSource,
    ): array {
        $platforms = [];
        $registry = $this->catalog->allPlatforms();

        foreach ($keys as $key) {
            $meta = $registry[$key] ?? null;
            $platforms[] = [
                'key' => $key,
                'label' => (string) ($meta['label'] ?? $key),
                'country' => (string) ($meta['country'] ?? $meta['primary_country'] ?? $countryCode),
                'source' => $routingSource === 'database' ? 'catalog_db' : 'live',
                'score' => 100 - (int) ($meta['priority'] ?? 50),
            ];
        }

        return [
            'scope' => $scope,
            'country_code' => $countryCode,
            'city' => $city,
            'category' => $category,
            'keys' => $keys,
            'platforms' => $platforms,
            'routing_source' => $routingSource,
        ];
    }

    private function scorePlatform(Platform $platform, string $category): int
    {
        $base = (int) $platform->priority;
        $trust = (int) ($platform->trust_score ?? 70);

        $score = $this->intelligence->effectivePriority($platform->slug, $base, $trust);
        $categorySlugs = $platform->categories->pluck('slug')->all();
        if (in_array($category, $categorySlugs, true)) {
            $score -= 10;
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function matchesCategory(array $meta, string $category): bool
    {
        $cats = (array) ($meta['categories'] ?? []);

        return in_array($category, $cats, true)
            || ($category === 'marketplace' && in_array('marketplace', $cats, true));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function isGlobalPlatform(array $meta): bool
    {
        return in_array(strtoupper((string) ($meta['country'] ?? '')), ['WW', 'GLOBAL', '*'], true)
            || ! empty($meta['global']);
    }

    private function configPriority(string $key): int
    {
        return (int) ($this->catalog->allPlatforms()[$key]['priority'] ?? 50);
    }
}
