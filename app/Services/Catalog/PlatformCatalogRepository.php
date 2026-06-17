<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Category;
use App\Models\Catalog\Continent;
use App\Models\Catalog\Country;
use App\Models\Catalog\Platform;
use App\Support\CategoryCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * DB-backed global catalog — continents, countries, categories, platforms.
 * Falls back to config/live_platforms.php when DB is empty or disabled.
 */
class PlatformCatalogRepository
{
    private const PLATFORMS_CACHE = 'catalog:platforms:v1';

    private const CATEGORIES_CACHE = 'catalog:categories:v1';

    private const COUNTRIES_CACHE = 'catalog:countries:v1';

    public function isActive(): bool
    {
        if (! config('catalog.enabled', true)) {
            return false;
        }

        if (! Schema::hasTable('platforms')) {
            return false;
        }

        return Platform::query()->where('enabled', true)->whereIn('status', [
            Platform::STATUS_LIVE,
            Platform::STATUS_VERIFIED,
        ])->exists();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allPlatforms(): array
    {
        if (! $this->isActive()) {
            return config('catalog.fallback_to_config', true)
                ? CatalogSyncService::allConfigPlatforms()
                : [];
        }

        $ttl = (int) config('catalog.cache_ttl_seconds', 3600);

        return Cache::remember(self::PLATFORMS_CACHE, $ttl, function () {
            $platforms = Platform::query()
                ->routable()
                ->with(['categories:id,slug', 'cities:id,platform_id,city'])
                ->orderBy('priority')
                ->get();

            $map = [];
            foreach ($platforms as $platform) {
                $map[$platform->slug] = $platform->toRegistryArray();
            }

            if ($map === [] && config('catalog.fallback_to_config', true)) {
                return CatalogSyncService::allConfigPlatforms();
            }

            return $map;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function platform(string $slug): ?array
    {
        $slug = strtolower(trim($slug));

        return $this->allPlatforms()[$slug] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function categorySlugs(): array
    {
        if (! Schema::hasTable('categories') || Category::query()->count() === 0) {
            return CategoryCatalog::ALL;
        }

        $ttl = (int) config('catalog.cache_ttl_seconds', 3600);

        return Cache::remember(self::CATEGORIES_CACHE, $ttl, function () {
            return Category::query()
                ->where('enabled', true)
                ->orderBy('sort_order')
                ->pluck('slug')
                ->all();
        });
    }

    /**
     * @return array<int, array{iso2: string, name: string, continent_code: string}>
     */
    public function countries(): array
    {
        if (! Schema::hasTable('countries') || Country::query()->count() === 0) {
            return [];
        }

        $ttl = (int) config('catalog.cache_ttl_seconds', 3600);

        return Cache::remember(self::COUNTRIES_CACHE, $ttl, function () {
            return Country::query()
                ->where('enabled', true)
                ->with('continent:id,code')
                ->orderBy('name')
                ->get(['id', 'iso2', 'name', 'continent_id'])
                ->map(fn (Country $country) => [
                    'iso2' => $country->iso2,
                    'name' => $country->name,
                    'continent_code' => (string) ($country->continent?->code ?? ''),
                ])
                ->all();
        });
    }

    /**
     * Resolve ISO2 from alias stored in DB.
     */
    public function resolveCountryCode(string $needle): ?string
    {
        if (! Schema::hasTable('country_aliases')) {
            return null;
        }

        $needle = mb_strtolower(trim($needle));
        if ($needle === '') {
            return null;
        }

        $country = Country::query()
            ->where('enabled', true)
            ->where(function ($query) use ($needle) {
                $query->whereRaw('LOWER(iso2) = ?', [$needle])
                    ->orWhereRaw('LOWER(name) = ?', [$needle])
                    ->orWhereHas('aliases', fn ($q) => $q->whereRaw('LOWER(alias) = ?', [$needle]));
            })
            ->first(['iso2']);

        return $country?->iso2;
    }

    public function flushCache(): void
    {
        Cache::forget(self::PLATFORMS_CACHE);
        Cache::forget(self::CATEGORIES_CACHE);
        Cache::forget(self::COUNTRIES_CACHE);
        PlatformRoutingEngine::flushCache();
    }
}
