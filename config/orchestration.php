<?php

/**
 * Global AI Worker Orchestration — BuyMap.ai discovery layer configuration.
 *
 * BuyMap does not sell products. It orchestrates ephemeral workers that query
 * external providers and return normalized, ranked matches.
 */
return [

    'platform_name' => 'BuyMap.ai',

    'mission' => 'AI discovery and matching layer between users and external providers',

    /*
    |--------------------------------------------------------------------------
    | Dynamic worker generation (stateless, per search session)
    |--------------------------------------------------------------------------
    */
    'max_workers' => (int) env('ORCHESTRATION_MAX_WORKERS', env('VALON_MAX_WORKERS', 24)),

    'worker_timeout_seconds' => (int) env('ORCHESTRATION_WORKER_TIMEOUT', env('VALON_WORKER_TIMEOUT', 15)),

    'max_concurrency' => (int) env('ORCHESTRATION_MAX_CONCURRENCY', 8),

    /** Fork workers in CLI when pcntl is available (Horizon/queue workers). */
    'enable_fork' => (bool) env('ORCHESTRATION_ENABLE_FORK', false),

    /** Dispatch workers to Laravel queue when not sync (requires Redis + queue worker). */
    'use_queue' => (bool) env('ORCHESTRATION_USE_QUEUE', false),

    'queue_connection' => env('ORCHESTRATION_QUEUE', env('QUEUE_CONNECTION', 'sync')),

    /*
    |--------------------------------------------------------------------------
    | AI matching engine weights (must sum to 1.0)
    | 40% specification | 25% semantic | 15% location | 10% price | 10% trust
    |--------------------------------------------------------------------------
    */
    'ranking_weights' => [
        'specification_match' => 0.40,
        'semantic_similarity' => 0.25,
        'location_relevance' => 0.15,
        'price_relevance' => 0.10,
        'provider_trust' => 0.10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Location expansion tiers when results are insufficient
    |--------------------------------------------------------------------------
    */
    'location_expansion' => [
        'min_results' => (int) env('ORCHESTRATION_MIN_RESULTS', 3),
        'tiers' => ['city', 'country', 'region', 'global'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Connector types supported by the federated search layer
    |--------------------------------------------------------------------------
    */
    'connector_types' => [
        'generic',
        'woocommerce',
        'cscart',
        'automotive',
        'api',
        'serp',
    ],

];
