<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Gemini API (https://ai.google.dev/gemini-api/docs/api-key)
    |--------------------------------------------------------------------------
    | Set GEMINI_API_KEY or GOOGLE_API_KEY in .env (server-side only).
    */

    'api_key' => env('GEMINI_API_KEY', env('GOOGLE_API_KEY')),

    'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),

    'vision_model' => env('GEMINI_VISION_MODEL', 'gemini-2.0-flash'),

    'enabled' => env('GEMINI_ENABLED', true),

    'timeout' => (int) env('GEMINI_TIMEOUT', 25),

    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

];
