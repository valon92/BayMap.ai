<?php

/**
 * Valon AI — distributed multi-agent orchestrator configuration.
 */
return [

    'orchestrator_name' => 'Valon AI',

    'worker_prefix' => 'ValonWorker',

    'max_workers' => (int) env('VALON_MAX_WORKERS', 10),

    'worker_timeout_seconds' => (int) env('VALON_WORKER_TIMEOUT', 15),

    'melodiapx_timeout_seconds' => (int) env('VALON_MELODIA_PX_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Role labels shown in UI / worker reports
    |--------------------------------------------------------------------------
    */
    'role_labels' => [
        'LivePlatformAgent' => 'Live platform catalog search',
        'KosovoFashionPlatformAgent' => 'Kosovo fashion platform search',
        'KosovoFashionLiveAgent' => 'Kosovo fashion live catalog search',
        'LocalMarketplaceAgent' => 'Local marketplace search',
        'BalkanScraperAgent' => 'Balkan marketplace search',
        'ZalandoAgent' => 'Zalando search',
        'ASOSAgent' => 'ASOS search',
        'AboutYouAgent' => 'About You / fashion search',
        'EUAggregatorAgent' => 'EU aggregator search',
        'MobileDeAgent' => 'mobile.de vehicle search',
        'AutoScout24Agent' => 'AutoScout24 search',
        'CarGurusAgent' => 'Car marketplace search',
        'SwissCarAgent' => 'Swiss car marketplace search',
        'DutchCarAgent' => 'Dutch car marketplace search',
        'GermanCarAgent' => 'German car marketplace search',
        'GermanElectronicsAgent' => 'German electronics retailer search',
        'GermanTechRetailAgent' => 'German tech retail search',
        'LocalAutoAgent' => 'Local vehicle listings',
        'ZillowAgent' => 'Zillow property search',
        'HomegateAgent' => 'Homegate property search',
        'ImmoScout24Agent' => 'ImmoScout24 search',
        'LocalPropertyAgent' => 'Local property search',
        'AmazonAgent' => 'Amazon search',
        'EbayTechAgent' => 'eBay search',
        'GoogleShoppingAgent' => 'Google Shopping search',
        'LocalTechAgent' => 'Local tech marketplace search',
        'FallbackAgent' => 'Platform search',
    ],

];
