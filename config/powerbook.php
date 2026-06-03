<?php

return [

    /*
    |--------------------------------------------------------------------------
    | BuyMap.ai Configuration
    |--------------------------------------------------------------------------
    */

    'name' => 'BuyMap.ai',
    'tagline' => 'Describe it. BuyMap finds it.',
    'tagline_sq' => 'Trego çfarë kërkon — BuyMap e gjen.',

    'geolocation' => [
        'providers' => ['ip-api.com', 'ipapi.co'],
        'timeout' => 3,
    ],

    'marketplaces' => [
        'mock' => true,
        'sources' => [
            'mobile.de',
            'autoscout24',
            'ebay',
            'etsy',
            'amazon',
            'google_shopping',
            'facebook_marketplace',
        ],
    ],

    'monetization' => [
        'affiliate_enabled' => false,
        'sponsored_slots' => true,
    ],

];
