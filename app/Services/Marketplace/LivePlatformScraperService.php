<?php

namespace App\Services\Marketplace;

use App\Services\Marketplace\Scrapers\ScraperAdapterResolver;
use App\Support\LivePlatformRegistry;
use Illuminate\Support\Facades\Cache;

/**
 * Universal live scraper — one entry point for every platform in config/live_platforms.php.
 */
class LivePlatformScraperService
{
    public function __construct(private ScraperAdapterResolver $adapters) {}

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, array<string, mixed>>
     */
    public function search(string $platformKey, array $parsedQuery): array
    {
        $platform = LivePlatformRegistry::platform($platformKey);
        if ($platform === null) {
            return [];
        }

        $cacheKey = 'live:'.$platformKey.':v20:'.md5(json_encode([
            $parsedQuery['brand'] ?? '',
            $parsedQuery['model'] ?? '',
            $parsedQuery['year_min'] ?? '',
            $parsedQuery['year_max'] ?? '',
            $parsedQuery['fuel'] ?? '',
            $parsedQuery['color'] ?? '',
            $parsedQuery['engine_liters'] ?? '',
            $parsedQuery['size'] ?? '',
            $parsedQuery['max_price'] ?? '',
            $parsedQuery['product_type'] ?? '',
            $parsedQuery['category'] ?? '',
            $parsedQuery['raw_query'] ?? '',
            $parsedQuery['search_query'] ?? '',
        ]));

        $ttl = (int) config('live_platforms.cache_ttl_seconds', 600);

        return Cache::remember($cacheKey, $ttl, function () use ($platformKey, $platform, $parsedQuery) {
            $platform['_key'] = $platformKey;
            $adapter = $this->adapters->for((string) ($platform['adapter'] ?? 'generic'));

            return $adapter->scrape($platform, $parsedQuery);
        });
    }
}
