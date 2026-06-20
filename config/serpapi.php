<?php

return [

    'enabled' => env('SERPAPI_ENABLED', true),

    'api_key' => env('SERPAPI_KEY'),

    'limit' => (int) env('SERPAPI_LIMIT', 40),

    'timeout' => (int) env('SERPAPI_TIMEOUT', 20),

    /**
     * Expand Google Shopping products into per-merchant offers (Hornbach, OBI, home24, …)
     * via SerpAPI google_immersive_product — same data as Google Shopping compare view.
     */
    'immersive_expand' => [
        'enabled' => (bool) env('SERPAPI_IMMERSIVE_EXPAND', true),
        'max_products' => (int) env('SERPAPI_IMMERSIVE_MAX_PRODUCTS', 16),
        'max_stores_per_product' => (int) env('SERPAPI_IMMERSIVE_MAX_STORES', 20),
        'cache_ttl_seconds' => (int) env('SERPAPI_IMMERSIVE_CACHE_TTL', 600),
    ],

    'gl' => env('SERPAPI_GL', 'de'),

    'hl' => env('SERPAPI_HL', 'en'),

];
