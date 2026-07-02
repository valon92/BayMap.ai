<?php

namespace App\Services\Providers;

use App\Models\Catalog\Platform;
use App\Services\Catalog\PlatformCatalogRepository;
use Illuminate\Support\Facades\Schema;

/**
 * Centralized Global Provider Registry for BuyMap.ai.
 *
 * Acts as the universal catalog of searchable platforms worldwide.
 * Workers and orchestration read normalized provider records from here.
 */
class GlobalProviderRegistry
{
    public function __construct(
        private PlatformCatalogRepository $catalog,
        private ProviderIntelligenceService $intelligence,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $records = [];
        foreach ($this->catalog->allPlatforms() as $key => $meta) {
            $records[$key] = $this->normalizeRecord($key, $meta);
        }

        return $records;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function provider(string $key): ?array
    {
        $key = strtolower(trim($key));
        $meta = $this->catalog->platform($key);
        if ($meta === null) {
            return null;
        }

        return $this->normalizeRecord($key, $meta);
    }

    /**
     * Providers for country + category, sorted by effective priority.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forCountryCategory(string $countryCode, string $category, ?string $city = null): array
    {
        $countryCode = strtoupper($countryCode);
        $category = \App\Support\CategoryCatalog::normalize($category);
        $matches = [];

        foreach ($this->all() as $key => $record) {
            if (! $this->matchesCountry($record, $countryCode)) {
                continue;
            }
            if (! $this->matchesCategory($record, $category)) {
                continue;
            }
            if (! $this->matchesCity($record, $city)) {
                continue;
            }
            $matches[] = $record;
        }

        usort($matches, fn (array $a, array $b) => ($a['effective_priority'] ?? 50) <=> ($b['effective_priority'] ?? 50));

        return $matches;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function normalizeRecord(string $key, array $meta): array
    {
        $adapter = (string) ($meta['adapter'] ?? 'generic');
        $connectorType = (string) ($meta['connector_type'] ?? $this->resolveConnectorType($adapter, $key));
        $providerType = (string) ($meta['provider_type'] ?? $this->resolveProviderType($adapter, $key));
        $country = strtoupper((string) ($meta['country'] ?? $meta['primary_country'] ?? ''));
        $categories = (array) ($meta['categories'] ?? ['marketplace']);
        $priority = (int) ($meta['priority'] ?? 50);
        $trust = (int) ($meta['trust_score'] ?? 70);

        return [
            'key' => $key,
            'provider_name' => (string) ($meta['label'] ?? $key),
            'provider_category' => $categories[0] ?? 'marketplace',
            'provider_categories' => $categories,
            'provider_country' => $country,
            'provider_region' => (string) ($meta['region'] ?? $this->regionForCountry($country)),
            'provider_type' => $providerType,
            'connector_type' => $connectorType,
            'trust_score' => $trust,
            'priority_score' => $priority,
            'effective_priority' => $this->intelligence->effectivePriority($key, $priority, $trust),
            'status' => (string) ($meta['status'] ?? 'live'),
            'search_capabilities' => (array) ($meta['search_capabilities'] ?? $this->defaultCapabilities($connectorType)),
            'adapter' => $adapter,
            'base_url' => $meta['base_url'] ?? null,
            'is_global' => (bool) ($meta['global'] ?? $meta['is_global'] ?? false),
            'cities' => (array) ($meta['cities'] ?? []),
            'intelligence' => $this->intelligence->summary($key),
        ];
    }

    public function count(): int
    {
        return count($this->catalog->allPlatforms());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function groupedByCountryCategory(): array
    {
        $groups = [];
        foreach ($this->all() as $record) {
            $country = $record['provider_country'] ?: 'WW';
            foreach ($record['provider_categories'] as $cat) {
                $groups[$country][$cat][] = $record['provider_name'];
            }
        }

        return $groups;
    }

    private function resolveConnectorType(string $adapter, string $key): string
    {
        $map = (array) config('providers.adapter_connector_map', []);

        return (string) ($map[$adapter] ?? $map[strtolower($key)] ?? 'structured_scraper');
    }

    private function resolveProviderType(string $adapter, string $key): string
    {
        $map = (array) config('providers.adapter_provider_type_map', []);
        if (isset($map[$adapter])) {
            return (string) $map[$adapter];
        }

        if (str_contains($key, 'amazon') || str_contains($key, 'zalando') || str_contains($key, 'store')) {
            return 'store';
        }

        if (in_array($adapter, ['serpapi', 'ebay', 'google_shopping'], true)) {
            return 'aggregator';
        }

        return 'marketplace';
    }

    /**
     * @return array<int, string>
     */
    private function defaultCapabilities(string $connectorType): array
    {
        return (array) (config('providers.default_capabilities')[$connectorType] ?? ['text_search']);
    }

    private function regionForCountry(string $countryCode): ?string
    {
        if ($countryCode === '' || in_array($countryCode, ['WW', 'GLOBAL', '*'], true)) {
            return null;
        }

        try {
            if (! Schema::hasTable('countries')) {
                return $this->regionFromWorldData($countryCode);
            }

            $continent = \App\Models\Catalog\Country::query()
                ->where('iso2', $countryCode)
                ->with('continent:id,code')
                ->first(['continent_id']);

            return $continent?->continent?->code ?? $this->regionFromWorldData($countryCode);
        } catch (\Throwable) {
            return $this->regionFromWorldData($countryCode);
        }
    }

    private function regionFromWorldData(string $countryCode): ?string
    {
        static $map = null;
        if ($map === null) {
            /** @var array<int, array<string, mixed>> $rows */
            $rows = require database_path('data/world_countries.php');
            $map = [];
            foreach ($rows as $row) {
                $iso2 = strtoupper((string) ($row['iso2'] ?? ''));
                if ($iso2 !== '') {
                    $map[$iso2] = (string) ($row['continent'] ?? '');
                }
            }
            $map['XK'] = 'EU';
        }

        $code = strtoupper($countryCode);

        return $map[$code] ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function matchesCountry(array $record, string $countryCode): bool
    {
        if ($record['is_global'] ?? false) {
            return true;
        }

        return strtoupper((string) ($record['provider_country'] ?? '')) === $countryCode;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function matchesCategory(array $record, string $category): bool
    {
        $cats = (array) ($record['provider_categories'] ?? []);

        return in_array($category, $cats, true)
            || ($category === 'marketplace' && in_array('marketplace', $cats, true));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function matchesCity(array $record, ?string $city): bool
    {
        $cities = (array) ($record['cities'] ?? []);
        if ($cities === [] || $city === null || $city === '') {
            return true;
        }

        return in_array(mb_strtolower($city), array_map('mb_strtolower', $cities), true);
    }
}
