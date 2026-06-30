<?php

/**
 * Federated search provider registry.
 * Add new connectors here — no database required.
 */
return [

    'timeout_seconds' => (int) env('MARKETPLACE_TIMEOUT', 15),

    'melodiapx_timeout_seconds' => (int) env('MELODIA_PX_TIMEOUT', 90),

    'kosovo_fashion_timeout_seconds' => (int) env('KOSOVO_FASHION_TIMEOUT', 45),

    'live_result_cap' => 16,

    'skip_mock_when_live_at_least' => 8,

    /** When live connectors return nothing, query demo datasets so discovery examples still work. */
    'demo_fallback_when_empty' => (bool) env('MARKETPLACE_DEMO_FALLBACK', true),

    /** Max demo providers to query per fallback (stops early once target_results reached). */
    'demo_fallback_max_providers' => (int) env('MARKETPLACE_DEMO_FALLBACK_MAX_PROVIDERS', 8),

    'demo_fallback_target_results' => (int) env('MARKETPLACE_DEMO_FALLBACK_TARGET_RESULTS', 8),

    /*
    |--------------------------------------------------------------------------
    | Intelligent agent expansion (see config/agent_pools.php)
    |--------------------------------------------------------------------------
    */
    'agent_expansion_enabled' => (bool) env('AGENT_EXPANSION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Provider connectors (adapter key => config)
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'ebay' => [
            'adapter' => 'ebay',
            'label' => 'eBay',
            'mode' => 'live',
            'priority' => 10,
            'categories' => ['*'],
        ],
        'google_shopping' => [
            'adapter' => 'serpapi',
            'label' => 'Google Shopping',
            'mode' => 'live',
            'priority' => 20,
            'categories' => ['*'],
        ],
        'channel3' => [
            'adapter' => 'channel3',
            'label' => 'Channel3',
            'mode' => 'live',
            'priority' => 12,
            'categories' => ['fashion', 'sports_outdoor', 'electronics_tech', 'home_appliances', 'home_furniture', 'beauty', 'grocery', 'marketplace', 'luxury_collectibles', 'industrial_b2b'],
        ],
        'walmart_us' => [
            'adapter' => 'walmart',
            'label' => 'Walmart',
            'mode' => 'live',
            'priority' => 5,
            'categories' => ['electronics_tech', 'home_appliances', 'fashion', 'sports_outdoor', 'marketplace', 'grocery', 'beauty'],
            'countries' => ['US'],
        ],
        'amazon' => [
            'adapter' => 'mock',
            'label' => 'Amazon',
            'mode' => 'demo',
            'priority' => 30,
            'categories' => ['electronics_tech', 'gaming_entertainment', 'home_appliances', 'grocery', 'beauty', 'online_education', 'marketplace'],
        ],
        'mobile.de' => [
            'adapter' => 'mock',
            'label' => 'mobile.de',
            'mode' => 'demo',
            'priority' => 40,
            'categories' => ['automotive'],
        ],
        'autoscout24' => [
            'adapter' => 'mock',
            'label' => 'AutoScout24',
            'mode' => 'demo',
            'priority' => 50,
            'categories' => ['automotive'],
        ],
        'etsy' => [
            'adapter' => 'mock',
            'label' => 'Etsy',
            'mode' => 'demo',
            'priority' => 60,
            'categories' => ['fashion', 'sports_outdoor', 'luxury_collectibles', 'marketplace'],
        ],
        'facebook_marketplace' => [
            'adapter' => 'mock',
            'label' => 'Facebook Marketplace',
            'mode' => 'demo',
            'priority' => 70,
            'categories' => ['fashion', 'sports_outdoor', 'automotive', 'marketplace'],
        ],
        'melodiapx' => [
            'adapter' => 'melodiapx',
            'label' => 'Melodia Px',
            'mode' => 'live',
            'priority' => 3,
            'categories' => ['fashion', 'sports_outdoor', 'marketplace'],
            'countries' => ['XK'],
        ],
        'driloni' => [
            'adapter' => 'driloni',
            'label' => 'Driloni Sportswear',
            'mode' => 'live',
            'priority' => 4,
            'categories' => ['fashion', 'sports_outdoor', 'marketplace'],
            'countries' => ['XK'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Swiss automotive federated sources (connector stubs)
    |--------------------------------------------------------------------------
    */
    'swiss_automotive' => [
        'autoscout24_ch', 'tutti', 'ricardo', 'anibis', 'car4you', 'carzone',
    ],

];
