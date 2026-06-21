<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Category;
use App\Models\Catalog\Country;
use App\Models\Catalog\Platform;
use App\Models\Catalog\PlatformCity;
use Illuminate\Support\Arr;

/**
 * Imports platforms from config/live_platforms.php + database/data/global_platforms_a_z.php.
 */
class CatalogSyncService
{
    private const PLATFORM_FIELDS = [
        'label', 'adapter', 'scraper', 'base_url', 'priority',
    ];

    public function __construct(private PlatformCatalogRepository $catalog) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function allConfigPlatforms(): array
    {
        $live = (array) config('live_platforms.platforms', []);
        $globalPath = database_path('data/global_platforms_a_z.php');
        $global = is_file($globalPath) ? (array) require $globalPath : [];
        $partsPath = database_path('data/global_automotive_parts_platforms.php');
        $parts = is_file($partsPath) ? (array) require $partsPath : [];

        return array_merge($live, $global, $parts);
    }

    /**
     * @return array{platforms: int, created: int, updated: int}
     */
    public function syncPlatformsFromConfig(): array
    {
        $configPlatforms = self::allConfigPlatforms();
        $created = 0;
        $updated = 0;
        $curator = app(PlatformCuratorService::class);

        foreach ($configPlatforms as $slug => $meta) {
            if (! is_array($meta)) {
                continue;
            }

            $country = strtoupper((string) ($meta['country'] ?? ''));
            $isGlobal = in_array($country, ['WW', 'GLOBAL', '*'], true) || ! empty($meta['global']);

            $settings = Arr::except($meta, array_merge(self::PLATFORM_FIELDS, [
                'country', 'categories', 'cities', 'global', 'toy_retailer', 'automotive_live',
            ]));

            if (! empty($meta['toy_retailer'])) {
                $settings['toy_retailer'] = true;
            }
            if (! empty($meta['automotive_live'])) {
                $settings['automotive_live'] = true;
            }

            $adapter = (string) ($meta['adapter'] ?? 'generic');
            $connectorMap = (array) config('providers.adapter_connector_map', []);
            $providerMap = (array) config('providers.adapter_provider_type_map', []);
            $connectorType = (string) ($meta['connector_type'] ?? $connectorMap[$adapter] ?? 'structured_scraper');
            $providerType = (string) ($meta['provider_type'] ?? $providerMap[$adapter] ?? 'marketplace');
            $capabilities = (array) ($meta['search_capabilities'] ?? config('providers.default_capabilities.'.$connectorType, ['text_search']));

            $platform = Platform::query()->updateOrCreate(
                ['slug' => strtolower((string) $slug)],
                [
                    'label' => (string) ($meta['label'] ?? $slug),
                    'base_url' => $meta['base_url'] ?? null,
                    'adapter' => $adapter,
                    'scraper' => $meta['scraper'] ?? null,
                    'provider_type' => $providerType,
                    'connector_type' => $connectorType,
                    'primary_country' => $isGlobal ? 'WW' : ($country !== '' ? $country : null),
                    'region' => $isGlobal ? null : $this->regionForCountry($country),
                    'is_global' => $isGlobal,
                    'priority' => (int) ($meta['priority'] ?? 50),
                    'trust_score' => (int) config('agent_pools.trust_scores.'.strtolower((string) $slug), 70),
                    'speed_score' => 80,
                    'search_capabilities' => $capabilities,
                    'settings' => $settings !== [] ? $settings : null,
                    'enabled' => true,
                ],
            );

            $platform->wasRecentlyCreated ? $created++ : $updated++;

            $this->syncCategories($platform, (array) ($meta['categories'] ?? ['marketplace']));
            $this->syncCountry($platform, $country, $isGlobal);
            $this->syncCities($platform, (array) ($meta['cities'] ?? []));

            $curator->markConfigPlatformLive($platform);
        }

        $this->catalog->flushCache();

        return [
            'platforms' => count($configPlatforms),
            'created' => $created,
            'updated' => $updated,
        ];
    }

    private function syncCategories(Platform $platform, array $slugs): void
    {
        $ids = Category::query()
            ->whereIn('slug', array_map(fn ($s) => \App\Support\CategoryCatalog::normalize((string) $s), $slugs))
            ->pluck('id', 'slug');

        $sync = [];
        foreach ($ids as $slug => $id) {
            $sync[$id] = ['priority' => null];
        }

        $platform->categories()->sync($sync);
    }

    private function syncCountry(Platform $platform, string $countryCode, bool $isGlobal): void
    {
        if ($isGlobal || $countryCode === '') {
            return;
        }

        $country = Country::query()->where('iso2', $countryCode)->first();
        if ($country === null) {
            return;
        }

        $platform->countries()->syncWithoutDetaching([
            $country->id => ['priority' => $platform->priority, 'enabled' => true],
        ]);
    }

    /**
     * @param  array<int, string>  $cities
     */
    private function syncCities(Platform $platform, array $cities): void
    {
        PlatformCity::query()->where('platform_id', $platform->id)->delete();

        foreach ($cities as $city) {
            $city = trim((string) $city);
            if ($city === '') {
                continue;
            }

            PlatformCity::query()->create([
                'platform_id' => $platform->id,
                'city' => $city,
            ]);
        }
    }

    private function regionForCountry(string $countryCode): ?string
    {
        if ($countryCode === '') {
            return null;
        }

        $country = Country::query()
            ->where('iso2', strtoupper($countryCode))
            ->with('continent:id,code')
            ->first(['continent_id']);

        return $country?->continent?->code;
    }
}
