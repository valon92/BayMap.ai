<?php

namespace App\Services\Marketplace\Scrapers\Contracts;

interface ScraperAdapterInterface
{
    public function adapterKey(): string;

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, array<string, mixed>>
     */
    public function scrape(array $platform, array $parsedQuery): array;
}
