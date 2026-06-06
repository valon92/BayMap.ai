<?php

/**
 * Federated search provider registry.
 * Add new connectors here — no database required.
 */
return [

    'timeout_seconds' => (int) env('MARKETPLACE_TIMEOUT', 15),

    'live_result_cap' => 16,

    'skip_mock_when_live_at_least' => 8,

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
        'amazon' => [
            'adapter' => 'mock',
            'label' => 'Amazon',
            'mode' => 'demo',
            'priority' => 30,
            'categories' => ['electronics_tech', 'gaming_entertainment', 'home_appliances', 'grocery', 'beauty', 'marketplace'],
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
        'driloni' => [
            'adapter' => 'mock',
            'label' => 'Driloni Sportswear',
            'mode' => 'demo',
            'priority' => 5,
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
