<?php

return [

    'enabled' => (bool) env('CHANNEL3_ENABLED', true),

    'api_key' => env('CHANNEL3_API_KEY'),

    'base_url' => env('CHANNEL3_BASE_URL', 'https://api.trychannel3.com'),

    'limit' => min(30, max(1, (int) env('CHANNEL3_LIMIT', 20))),

    'timeout' => (int) env('CHANNEL3_TIMEOUT', 25),

    'cache_ttl_seconds' => (int) env('CHANNEL3_CACHE_TTL', 300),

];
