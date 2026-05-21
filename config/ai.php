<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI provider for parsing & vision
    |--------------------------------------------------------------------------
    | auto   — Gemini if configured, else OpenAI, else rule-based
    | gemini — Google Gemini API only
    | openai — OpenAI only
    */

    'provider' => env('AI_PROVIDER', 'auto'),

];
