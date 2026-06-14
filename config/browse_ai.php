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
    ],

];
