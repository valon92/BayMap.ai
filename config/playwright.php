<?php

return [

    'enabled' => (bool) env('PLAYWRIGHT_ENABLED', false),

    /** Path to node binary (use `which node` on the server). */
    'node_binary' => env('PLAYWRIGHT_NODE', env('NODE_BINARY', 'node')),

    /** CLI script that returns page HTML as JSON. */
    'script' => env('PLAYWRIGHT_SCRIPT', base_path('scripts/playwright-fetch.mjs')),

    'timeout_seconds' => (int) env('PLAYWRIGHT_TIMEOUT', 35),

    'headless' => (bool) env('PLAYWRIGHT_HEADLESS', true),

    /**
     * Optional CSS selector to wait for after navigation (per-platform overrides in live_platforms).
     * Example: .s-result-item for Amazon, .listing-item for Autodoc.
     */
    'default_wait_selector' => env('PLAYWRIGHT_WAIT_SELECTOR', ''),

    /**
     * Playwright waitUntil: load | domcontentloaded | networkidle | commit
     */
    'wait_until' => env('PLAYWRIGHT_WAIT_UNTIL', 'domcontentloaded'),

    /**
     * Platform keys / prefixes that should fetch via Playwright first (anti-bot stores).
     * HTTP is still tried when Playwright returns empty.
     */
    'prefer_key_patterns' => array_filter(array_map('trim', explode(',', env(
        'PLAYWRIGHT_PREFER_PATTERNS',
        'amazon_,mister_auto,oscaro,kfzteile24,mobile_de,heycar_de,autoscout24,ebay',
    )))),

];
