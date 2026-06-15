<?php

/**
 * Category-based agent pools for intelligent federated search.
 * Agents map to provider source keys — only a small relevant subset activates per query.
 */
return [

    'min_agents' => (int) env('AGENT_POOL_MIN', 3),
    'max_agents' => (int) env('AGENT_POOL_MAX', 6),
    'min_results_before_expand' => (int) env('AGENT_MIN_RESULTS_EXPAND', 3),
    'per_agent_timeout_seconds' => (int) env('AGENT_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Platform trust scores (0–100) used in weighted ranking
    |--------------------------------------------------------------------------
    */
        'trust_scores' => [
        'web_services_bridge' => 94,
        'ebay' => 92,
        'google_shopping' => 88,
        'amazon' => 85,
        'mobile.de' => 90,
        'autoscout24' => 90,
        'autoscout24_de' => 90,
        'mobile_de' => 90,
        'facebook_marketplace' => 72,
        'etsy' => 78,
        'driloni' => 82,
        'melodiapx' => 88,
        'default' => 70,
    ],

    /*
    |--------------------------------------------------------------------------
    | Category pools — agent_id => provider binding + geo affinity
    |--------------------------------------------------------------------------
    */
    'pools' => [

        'fashion' => [
            'agents' => [
                ['id' => 'KosovoFashionPlatformAgent', 'sources' => ['melodiapx', 'albi_online', 'driloni', 'butiku_regina', 'vedude_fashion', 'arjana_shop', 'ssprint_fashion', 'am_fashion', 'waikiki_kosovo', 'minimax_fashion'], 'countries' => ['XK'], 'trust' => 90, 'speed' => 95],
                ['id' => 'KosovoFashionLiveAgent', 'sources' => ['melodiapx', 'driloni'], 'countries' => ['XK'], 'trust' => 92, 'speed' => 98],
                ['id' => 'LocalMarketplaceAgent', 'sources' => ['melodiapx', 'driloni'], 'countries' => ['XK'], 'trust' => 88, 'speed' => 95],
                ['id' => 'BalkanScraperAgent', 'sources' => ['merrjep', 'dyqani', 'pazar3', 'gjirafa50', 'tregu'], 'countries' => ['XK', 'AL', 'MK'], 'trust' => 75, 'speed' => 88],
                ['id' => 'ZalandoAgent', 'sources' => ['google_shopping'], 'countries' => ['DE', 'AT', 'CH', 'NL'], 'trust' => 86, 'speed' => 80],
                ['id' => 'ASOSAgent', 'sources' => ['ebay'], 'countries' => ['GB', 'DE', 'US'], 'trust' => 84, 'speed' => 78],
                ['id' => 'AboutYouAgent', 'sources' => ['amazon', 'etsy'], 'countries' => ['DE', 'AT'], 'trust' => 80, 'speed' => 75],
                ['id' => 'EUAggregatorAgent', 'sources' => ['ebay', 'google_shopping'], 'countries' => ['*'], 'trust' => 90, 'speed' => 70],
            ],
        ],

        'sports_outdoor' => [
            'extends' => 'fashion',
        ],

        'automotive' => [
            'agents' => [
                ['id' => 'MobileDeAgent', 'sources' => ['mobile.de', 'mobile_de'], 'countries' => ['DE'], 'trust' => 92, 'speed' => 85],
                ['id' => 'AutoScout24Agent', 'sources' => ['autoscout24', 'autoscout24_de', 'autoscout24_nl', 'autoscout24_ch'], 'countries' => ['DE', 'CH', 'NL', 'AT'], 'trust' => 91, 'speed' => 83],
                ['id' => 'CarGurusAgent', 'sources' => ['google_shopping', 'ebay'], 'countries' => ['US', 'DE', 'GB'], 'trust' => 80, 'speed' => 72],
                ['id' => 'SwissCarAgent', 'sources' => ['autoscout24_ch', 'autolina', 'autogrid_ch', 'car_trade24', 'carlando', 'carindex', 'amag', 'troovo', 'motoauto_ch', 'tutti', 'ricardo'], 'countries' => ['CH'], 'trust' => 88, 'speed' => 80],
                ['id' => 'DutchCarAgent', 'sources' => ['marktplaats', 'autoscout24_nl', 'gaspedaal'], 'countries' => ['NL'], 'trust' => 87, 'speed' => 78],
                ['id' => 'GermanCarAgent', 'sources' => ['kleinanzeigen', 'heycar_de', 'autoplenum'], 'countries' => ['DE'], 'trust' => 85, 'speed' => 76],
                ['id' => 'LocalAutoAgent', 'sources' => ['facebook_marketplace', 'merrjep'], 'countries' => ['XK', 'AL'], 'trust' => 72, 'speed' => 90],
            ],
        ],

        'real_estate' => [
            'agents' => [
                ['id' => 'ZillowAgent', 'sources' => ['google_shopping', 'facebook_marketplace'], 'countries' => ['US'], 'trust' => 88, 'speed' => 75],
                ['id' => 'HomegateAgent', 'sources' => ['google_shopping'], 'countries' => ['CH'], 'trust' => 86, 'speed' => 78],
                ['id' => 'ImmoScout24Agent', 'sources' => ['google_shopping', 'facebook_marketplace'], 'countries' => ['DE'], 'trust' => 90, 'speed' => 80],
                ['id' => 'LocalPropertyAgent', 'sources' => ['facebook_marketplace', 'merrjep', 'pazar3'], 'countries' => ['XK', 'AL'], 'trust' => 74, 'speed' => 88],
            ],
        ],

        'electronics_tech' => [
            'agents' => [
                ['id' => 'SwissElectronicsAgent', 'sources' => ['digitec_ch', 'galaxus_ch', 'manor_ch', 'apple_ch', 'interdiscount_ch', 'mediamarkt_ch'], 'countries' => ['CH'], 'trust' => 90, 'speed' => 88],
                ['id' => 'GermanElectronicsAgent', 'sources' => ['mediamarkt', 'saturn', 'cyberport', 'notebooksbilliger', 'euronics', 'computeruniverse', 'apple_de'], 'countries' => ['DE'], 'trust' => 90, 'speed' => 92],
                ['id' => 'GermanTechRetailAgent', 'sources' => ['conrad', 'expert_de', 'voelkner', 'pearl', 'jacob', 'alternate'], 'countries' => ['DE'], 'trust' => 84, 'speed' => 88],
                ['id' => 'AmazonAgent', 'sources' => ['amazon'], 'countries' => ['*'], 'trust' => 88, 'speed' => 82],
                ['id' => 'EbayTechAgent', 'sources' => ['ebay'], 'countries' => ['*'], 'trust' => 90, 'speed' => 80],
                ['id' => 'GoogleShoppingAgent', 'sources' => ['google_shopping'], 'countries' => ['*'], 'trust' => 87, 'speed' => 78],
                ['id' => 'LocalTechAgent', 'sources' => ['gjirafa50', 'tregu', 'pcstore', 'neptun'], 'countries' => ['XK'], 'trust' => 80, 'speed' => 92],
            ],
        ],

        'online_education' => [
            'agents' => [
                ['id' => 'AmazonBooksAgent', 'sources' => ['amazon'], 'countries' => ['*'], 'trust' => 90, 'speed' => 85],
                ['id' => 'EbayBooksAgent', 'sources' => ['ebay'], 'countries' => ['*'], 'trust' => 88, 'speed' => 82],
                ['id' => 'GoogleBooksAgent', 'sources' => ['google_shopping'], 'countries' => ['*'], 'trust' => 86, 'speed' => 80],
                ['id' => 'BookDepositoryAgent', 'sources' => ['book_depository'], 'countries' => ['*'], 'trust' => 84, 'speed' => 78],
                ['id' => 'WaterstonesAgent', 'sources' => ['waterstones'], 'countries' => ['GB', 'DE', 'US'], 'trust' => 82, 'speed' => 76],
                ['id' => 'KosovoBookAgent', 'sources' => ['dukagjini', 'libraria_albas', 'merrjep'], 'countries' => ['XK', 'AL'], 'trust' => 80, 'speed' => 88],
            ],
        ],

        'travel' => [
            'agents' => [
                ['id' => 'GoogleFlightsAgent', 'sources' => ['google_flights'], 'countries' => ['*'], 'trust' => 92, 'speed' => 78],
            ],
        ],

        'ai_software' => [
            'agents' => [
                ['id' => 'WebServicesBridgeAgent', 'sources' => ['web_services_bridge'], 'countries' => ['*'], 'trust' => 94, 'speed' => 98],
            ],
        ],

        'default' => [
            'agents' => [
                ['id' => 'EUAggregatorAgent', 'sources' => ['ebay', 'google_shopping'], 'countries' => ['*'], 'trust' => 90, 'speed' => 75],
                ['id' => 'AmazonAgent', 'sources' => ['amazon'], 'countries' => ['*'], 'trust' => 85, 'speed' => 78],
                ['id' => 'LocalMarketplaceAgent', 'sources' => ['facebook_marketplace', 'merrjep'], 'countries' => ['XK', 'AL'], 'trust' => 72, 'speed' => 90],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ranking weights (must sum to 1.0)
    |--------------------------------------------------------------------------
    */
    'ranking_weights' => [
        'specification_match' => 0.40,
        'semantic_similarity' => 0.25,
        'location_relevance' => 0.15,
        'price_relevance' => 0.10,
        'provider_trust' => 0.10,
    ],

];
