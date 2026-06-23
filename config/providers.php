<?php

/**
 * BuyMap.ai Global Provider Registry — connector taxonomy and discovery defaults.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Provider types (what the platform is)
    |--------------------------------------------------------------------------
    */
    'provider_types' => [
        'marketplace' => 'General marketplace / classifieds',
        'store' => 'Online store / retailer',
        'agency' => 'Agency or broker (real estate, travel, services)',
        'aggregator' => 'Multi-source search aggregator',
        'platform' => 'Platform API or feed partner',
    ],

    /*
    |--------------------------------------------------------------------------
    | Connector types (how workers collect data — connector-agnostic to workers)
    |--------------------------------------------------------------------------
    */
    'connector_types' => [
        'official_api' => 'Official partner or merchant API',
        'merchant_feed' => 'Product/inventory feed (CSV, XML, JSON)',
        'search_feed' => 'Search index or sponsored product feed',
        'structured_scraper' => 'HTML scraper with normalized listing parser',
        'search_aggregator' => 'Third-party search API (SerpAPI, DataForSEO, etc.)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Map scraper/API adapter keys → connector_type
    |--------------------------------------------------------------------------
    */
    'adapter_connector_map' => [
        'ebay' => 'official_api',
        'serpapi' => 'search_aggregator',
        'google_shopping' => 'search_aggregator',
        'channel3' => 'search_aggregator',
        'google_flights' => 'search_aggregator',
        'web_services_bridge' => 'search_aggregator',
        'mock' => 'structured_scraper',
        'generic' => 'structured_scraper',
        'woocommerce' => 'structured_scraper',
        'cscart' => 'structured_scraper',
        'automotive' => 'structured_scraper',
        'real_estate' => 'structured_scraper',
        'gy_digital' => 'structured_scraper',
        'api' => 'official_api',
    ],

    /*
    |--------------------------------------------------------------------------
    | Map adapter / slug hints → provider_type
    |--------------------------------------------------------------------------
    */
    'adapter_provider_type_map' => [
        'ebay' => 'aggregator',
        'serpapi' => 'aggregator',
        'google_shopping' => 'aggregator',
        'channel3' => 'aggregator',
        'google_flights' => 'aggregator',
        'web_services_bridge' => 'aggregator',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default search capabilities by connector type
    |--------------------------------------------------------------------------
    */
    'default_capabilities' => [
        'official_api' => ['text_search', 'category_filter', 'price_filter', 'structured_results'],
        'merchant_feed' => ['category_browse', 'price_filter', 'structured_results'],
        'search_feed' => ['text_search', 'price_filter'],
        'structured_scraper' => ['text_search', 'category_browse', 'location_filter'],
        'search_aggregator' => ['text_search', 'semantic_search', 'price_filter', 'global_reach'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Geographic expansion — city → country → neighbors → region → global
    |--------------------------------------------------------------------------
    */
    'expansion' => [
        'tiers' => ['city', 'country', 'neighbors', 'region', 'global'],
        'neighbor_map' => [
            'XK' => ['AL', 'MK', 'RS', 'ME'],
            'AL' => ['XK', 'MK', 'IT', 'GR'],
            'MK' => ['XK', 'AL', 'RS', 'BG'],
            'DE' => ['AT', 'CH', 'FR', 'NL', 'PL', 'IT'],
            'CH' => ['DE', 'FR', 'IT', 'AT'],
            'AT' => ['DE', 'CH', 'IT', 'HU'],
            'NL' => ['DE', 'BE', 'FR', 'GB'],
            'FR' => ['DE', 'BE', 'CH', 'IT', 'ES', 'GB'],
            'IT' => ['FR', 'CH', 'AT', 'DE', 'AL'],
            'GB' => ['IE', 'FR', 'DE', 'NL'],
            'US' => ['CA', 'MX'],
            'IN' => ['PK', 'BD', 'NP', 'AE'],
        ],
        'region_map' => [
            'EU' => ['DE', 'CH', 'FR', 'IT', 'AT', 'NL', 'GB', 'ES', 'PL', 'BE', 'XK', 'AL'],
            'NA' => ['US', 'CA', 'MX'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider intelligence — rolling window for adaptive routing
    |--------------------------------------------------------------------------
    */
    'intelligence' => [
        'enabled' => (bool) env('PROVIDER_INTELLIGENCE_ENABLED', true),
        'metrics_window_hours' => (int) env('PROVIDER_METRICS_WINDOW', 168),
        'min_samples_for_adjustment' => 5,
        'weights' => [
            'success_rate' => 0.35,
            'latency' => 0.25,
            'result_count' => 0.20,
            'trust_score' => 0.20,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dynamic worker naming by category (BuyMap Valon Workers)
    |--------------------------------------------------------------------------
    */
    'worker_prefix_by_category' => [
        'fashion' => 'FashionWorker',
        'automotive' => 'VehicleWorker',
        'real_estate' => 'PropertyWorker',
        'electronics' => 'TechWorker',
        'online_education' => 'BookWorker',
        'travel' => 'TravelWorker',
        'web_services' => 'ServiceWorker',
        'marketplace' => 'ValonWorker',
    ],

];
