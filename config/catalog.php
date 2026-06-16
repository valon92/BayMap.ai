<?php

return [

    'enabled' => (bool) env('CATALOG_DB_ENABLED', true),

    'cache_ttl_seconds' => (int) env('CATALOG_CACHE_TTL', 3600),

    'routing_cache_ttl_seconds' => (int) env('CATALOG_ROUTING_CACHE_TTL', 300),

    'fallback_to_config' => (bool) env('CATALOG_FALLBACK_CONFIG', true),

    /*
    |--------------------------------------------------------------------------
    | Curation — platforms are analyzed before entering live worker routing
    |--------------------------------------------------------------------------
    */
    'curation' => [
        'require_verification' => (bool) env('CATALOG_REQUIRE_VERIFICATION', true),
        'target_platforms_per_country' => (int) env('CATALOG_TARGET_PLATFORMS', 50),
        'auto_analyze_on_register' => (bool) env('CATALOG_AUTO_ANALYZE', false),
    ],

    'continents' => [
        ['code' => 'AF', 'name' => 'Africa', 'sort_order' => 1],
        ['code' => 'AN', 'name' => 'Antarctica', 'sort_order' => 2],
        ['code' => 'AS', 'name' => 'Asia', 'sort_order' => 3],
        ['code' => 'EU', 'name' => 'Europe', 'sort_order' => 4],
        ['code' => 'NA', 'name' => 'North America', 'sort_order' => 5],
        ['code' => 'OC', 'name' => 'Oceania', 'sort_order' => 6],
        ['code' => 'SA', 'name' => 'South America', 'sort_order' => 7],
    ],

];
