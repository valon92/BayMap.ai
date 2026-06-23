<?php

/**
 * Global fashion platforms — US/GB live store search URLs.
 *
 * @return array<string, array<string, mixed>>
 */
return (static function (): array {
    $search = static fn (string $template): array => ['search_template' => $template];

    return [
        'zara_us' => [
            'adapter' => 'generic',
            'label' => 'Zara USA',
            'country' => 'US',
            'categories' => ['fashion', 'sports_outdoor'],
            'base_url' => 'https://www.zara.com',
            'location' => 'United States',
            'currency' => 'USD',
            'priority' => 4,
            'locale' => 'en-US',
            ...$search('/us/en/search?searchTerm={query}'),
        ],
        'hm_us' => [
            'adapter' => 'generic',
            'label' => 'H&M USA',
            'country' => 'US',
            'categories' => ['fashion', 'sports_outdoor'],
            'base_url' => 'https://www2.hm.com',
            'location' => 'United States',
            'currency' => 'USD',
            'priority' => 5,
            'locale' => 'en-US',
            ...$search('/en_us/search-results.html?q={query}'),
        ],
        'gap_us' => [
            'adapter' => 'generic',
            'label' => 'Gap',
            'country' => 'US',
            'categories' => ['fashion', 'sports_outdoor'],
            'base_url' => 'https://www.gap.com',
            'location' => 'United States',
            'currency' => 'USD',
            'priority' => 7,
            'locale' => 'en-US',
            ...$search('/browse/search.do?searchText={query}'),
        ],
        'old_navy_us' => [
            'adapter' => 'generic',
            'label' => 'Old Navy',
            'country' => 'US',
            'categories' => ['fashion', 'sports_outdoor'],
            'base_url' => 'https://oldnavy.gap.com',
            'location' => 'United States',
            'currency' => 'USD',
            'priority' => 8,
            'locale' => 'en-US',
            ...$search('/browse/search.do?searchText={query}'),
        ],
        'asos_us' => [
            'adapter' => 'generic',
            'label' => 'ASOS US',
            'country' => 'US',
            'categories' => ['fashion', 'sports_outdoor'],
            'base_url' => 'https://www.asos.com',
            'location' => 'United States',
            'currency' => 'USD',
            'priority' => 9,
            'locale' => 'en-US',
            ...$search('/us/search/?q={query}'),
        ],
        'fashion_nova_us' => [
            'adapter' => 'generic',
            'label' => 'Fashion Nova',
            'country' => 'US',
            'categories' => ['fashion', 'sports_outdoor'],
            'base_url' => 'https://www.fashionnova.com',
            'location' => 'United States',
            'currency' => 'USD',
            'priority' => 10,
            'locale' => 'en-US',
            ...$search('/search?q={query}'),
        ],
    ];
})();
