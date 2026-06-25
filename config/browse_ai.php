<?php

return [

    'enabled' => (bool) env('BROWSE_AI_ENABLED', false),

    'api_key' => env('BROWSE_AI_API_KEY'),

    'base_url' => env('BROWSE_AI_BASE_URL', 'https://api.browse.ai/v2'),

    'timeout' => (int) env('BROWSE_AI_TIMEOUT', 60),

    'max_wait_seconds' => (int) env('BROWSE_AI_MAX_WAIT', 55),

    'poll_interval_ms' => (int) env('BROWSE_AI_POLL_MS', 2000),

    /**
     * Trained Browse AI robots for anti-bot platforms.
     * Create robots at https://dashboard.browse.ai/ and paste robot IDs here.
     *
     * Robot input should accept a search URL (default param: originUrl).
     * Capture either a listings list (Title, Price, URL, Image) or full page HTML.
     */
    'platforms' => [
        'mobile_de' => [
            'robot_id' => env('BROWSE_AI_ROBOT_MOBILE_DE'),
            'url_input' => env('BROWSE_AI_MOBILE_DE_URL_INPUT', 'originUrl'),
            'list_name' => env('BROWSE_AI_MOBILE_DE_LIST', 'Listings'),
            'html_fields' => ['Page HTML', 'page_html'],
            'fields' => [
                'title' => 'Title',
                'price' => 'Price',
                'url' => 'URL',
                'image' => 'Image',
                'location' => 'Location',
            ],
        ],
        'heycar_de' => [
            'robot_id' => env('BROWSE_AI_ROBOT_HEYCAR_DE'),
            'url_input' => env('BROWSE_AI_HEYCAR_DE_URL_INPUT', 'originUrl'),
            'list_name' => env('BROWSE_AI_HEYCAR_DE_LIST', 'Listings'),
            'html_fields' => ['Page HTML', 'page_html'],
            'fields' => [
                'title' => 'Title',
                'price' => 'Price',
                'url' => 'URL',
                'image' => 'Image',
                'location' => 'Location',
            ],
        ],
        'homegate_ch' => [
            'robot_id' => env('BROWSE_AI_ROBOT_HOMEGATE_CH'),
            'url_input' => env('BROWSE_AI_HOMEGATE_CH_URL_INPUT', 'originUrl'),
            'list_name' => env('BROWSE_AI_HOMEGATE_CH_LIST', 'Listings'),
            'category' => 'real_estate',
            'html_fields' => ['Page HTML', 'page_html'],
            'fields' => [
                'title' => 'Title',
                'price' => 'Price',
                'url' => 'URL',
                'image' => 'Image',
                'location' => 'Location',
            ],
        ],
        'immoscout24_ch' => [
            'robot_id' => env('BROWSE_AI_ROBOT_IMMOSCOUT24_CH'),
            'url_input' => env('BROWSE_AI_IMMOSCOUT24_CH_URL_INPUT', 'originUrl'),
            'list_name' => env('BROWSE_AI_IMMOSCOUT24_CH_LIST', 'Listings'),
            'category' => 'real_estate',
            'html_fields' => ['Page HTML', 'page_html'],
            'fields' => [
                'title' => 'Title',
                'price' => 'Price',
                'url' => 'URL',
                'image' => 'Image',
                'location' => 'Location',
            ],
        ],
        'autoscout24_ch' => [
            'robot_id' => env('BROWSE_AI_ROBOT_AUTOSCOUT24_CH'),
            'url_input' => env('BROWSE_AI_AUTOSCOUT24_CH_URL_INPUT', 'originUrl'),
            'list_name' => env('BROWSE_AI_AUTOSCOUT24_CH_LIST', 'Listings'),
            'category' => 'automotive',
            'html_fields' => ['Page HTML', 'page_html'],
            'fields' => [
                'title' => 'Title',
                'price' => 'Price',
                'url' => 'URL',
                'image' => 'Image',
                'location' => 'Location',
            ],
        ],
        'newhome_ch' => [
            'robot_id' => env('BROWSE_AI_ROBOT_NEWHOME_CH'),
            'url_input' => env('BROWSE_AI_NEWHOME_CH_URL_INPUT', 'originUrl'),
            'list_name' => env('BROWSE_AI_NEWHOME_CH_LIST', 'Listings'),
            'category' => 'real_estate',
            'html_fields' => ['Page HTML', 'page_html'],
            'fields' => [
                'title' => 'Title',
                'price' => 'Price',
                'url' => 'URL',
                'image' => 'Image',
                'location' => 'Location',
            ],
        ],
        'comparis_immobilien' => [
            'robot_id' => env('BROWSE_AI_ROBOT_COMPARIS_IMMO'),
            'url_input' => env('BROWSE_AI_COMPARIS_IMMO_URL_INPUT', 'originUrl'),
            'list_name' => env('BROWSE_AI_COMPARIS_IMMO_LIST', 'Listings'),
            'category' => 'real_estate',
            'html_fields' => ['Page HTML', 'page_html'],
            'fields' => [
                'title' => 'Title',
                'price' => 'Price',
                'url' => 'URL',
                'image' => 'Image',
                'location' => 'Location',
            ],
        ],
        // DE automotive parts — one robot per site; pass search URL via originUrl
        'autodoc_de' => [
            'robot_id' => env('BROWSE_AI_ROBOT_AUTODOC_DE', env('BROWSE_AI_ROBOT_AUTODOC')),
            'url_input' => env('BROWSE_AI_AUTODOC_URL_INPUT', 'originUrl'),
            'list_name' => env('BROWSE_AI_AUTODOC_LIST', 'Listings'),
            'category' => 'automotive_parts',
            'html_fields' => ['Page HTML', 'page_html'],
            'fields' => [
                'title' => 'Title',
                'price' => 'Price',
                'url' => 'URL',
                'image' => 'Image',
                'location' => 'Location',
            ],
        ],
        'kfzteile24_de' => [
            'robot_id' => env('BROWSE_AI_ROBOT_KFZTEILE24_DE'),
            'url_input' => env('BROWSE_AI_KFZTEILE24_URL_INPUT', 'originUrl'),
            'list_name' => env('BROWSE_AI_KFZTEILE24_LIST', 'Listings'),
            'category' => 'automotive_parts',
            'html_fields' => ['Page HTML', 'page_html'],
            'fields' => [
                'title' => 'Title',
                'price' => 'Price',
                'url' => 'URL',
                'image' => 'Image',
                'location' => 'Location',
            ],
        ],
        'amazon_automotive_de' => [
            'robot_id' => env('BROWSE_AI_ROBOT_AMAZON_DE', env('BROWSE_AI_ROBOT_AMAZON_AUTOMOTIVE')),
            'url_input' => env('BROWSE_AI_AMAZON_DE_URL_INPUT', 'originUrl'),
            'list_name' => env('BROWSE_AI_AMAZON_DE_LIST', 'Listings'),
            'category' => 'automotive_parts',
            'html_fields' => ['Page HTML', 'page_html'],
            'fields' => [
                'title' => 'Title',
                'price' => 'Price',
                'url' => 'URL',
                'image' => 'Image',
                'location' => 'Location',
            ],
        ],
        'mister_auto_de' => [
            'robot_id' => env('BROWSE_AI_ROBOT_MISTER_AUTO_DE'),
            'url_input' => env('BROWSE_AI_MISTER_AUTO_URL_INPUT', 'originUrl'),
            'list_name' => env('BROWSE_AI_MISTER_AUTO_LIST', 'Listings'),
            'category' => 'automotive_parts',
            'html_fields' => ['Page HTML', 'page_html'],
            'fields' => [
                'title' => 'Title',
                'price' => 'Price',
                'url' => 'URL',
                'image' => 'Image',
                'location' => 'Location',
            ],
        ],
        'oscaro_de' => [
            'robot_id' => env('BROWSE_AI_ROBOT_OSCARO_DE'),
            'url_input' => env('BROWSE_AI_OSCARO_URL_INPUT', 'originUrl'),
            'list_name' => env('BROWSE_AI_OSCARO_LIST', 'Listings'),
            'category' => 'automotive_parts',
            'html_fields' => ['Page HTML', 'page_html'],
            'fields' => [
                'title' => 'Title',
                'price' => 'Price',
                'url' => 'URL',
                'image' => 'Image',
                'location' => 'Location',
            ],
        ],
    ],

];
