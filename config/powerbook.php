<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Powerbook.ai Configuration
    |--------------------------------------------------------------------------
    */

    'name' => 'Powerbook.ai',
    'tagline' => 'Describe it. Powerbook finds it.',
    'tagline_sq' => 'Trego çfarë kërkon — Powerbook e gjen.',

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
