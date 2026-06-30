<?php

return [

    'enabled' => env('WALMART_ENABLED', true),

    'client_id' => env('WALMART_CLIENT_ID'),

    'client_secret' => env('WALMART_CLIENT_SECRET'),

    /** client_credentials | refresh_token | authorization_code */
    'grant_type' => env('WALMART_GRANT_TYPE', 'client_credentials'),

    'refresh_token' => env('WALMART_REFRESH_TOKEN'),

    /** One-time authorization code exchange (Solution Provider OAuth). */
    'auth_code' => env('WALMART_AUTH_CODE'),

    'redirect_uri' => env('WALMART_REDIRECT_URI'),

    /** Optional channel type from Walmart Developer onboarding. */
    'consumer_channel_type' => env('WALMART_CONSUMER_CHANNEL_TYPE'),

    'base_url' => env('WALMART_BASE_URL', 'https://marketplace.walmartapis.com'),

    'market' => env('WALMART_MARKET', 'US'),

    'svc_name' => env('WALMART_SVC_NAME', 'Walmart Marketplace'),

    'limit' => (int) env('WALMART_SEARCH_LIMIT', 20),

    'timeout' => (int) env('WALMART_TIMEOUT', 20),

    'cache_ttl_seconds' => (int) env('WALMART_CACHE_TTL', 300),

];
