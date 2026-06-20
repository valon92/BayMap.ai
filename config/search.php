<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Search request budget
    |--------------------------------------------------------------------------
    | Federated searches run multiple live scrapers sequentially on artisan serve.
    | Keep this above (workers × per-worker timeout) to avoid killing the dev server.
    */
    'max_execution_seconds' => (int) env('SEARCH_MAX_EXECUTION_SECONDS', 300),

    /** Hard cap on Valon workers per search (prevents LAN timeout on multi-market queries). */
    /** Max countries a user can pick in the market picker UI. */
    'max_selected_countries' => (int) env('SEARCH_MAX_SELECTED_COUNTRIES', 8),

];
