<?php

namespace App\Services\Marketplace;

use App\Contracts\MarketplaceSearchInterface;
use Illuminate\Support\Facades\File;

/**
 * Simulates marketplace APIs using static JSON datasets.
 * Replace with real HTTP clients implementing MarketplaceSearchInterface.
 */
class MockMarketplaceService implements MarketplaceSearchInterface
{
    private string $source;

    public function __construct(string $source = 'ebay')
    {
        $this->source = $source;
    }

    public function getSourceName(): string
    {
        return $this->source;
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $parsedQuery, array $expandedFilters): array
    {
        $category = $parsedQuery['category'] ?? 'marketplace';
        $dataset = $this->loadDataset($category);
        $marketplaces = $expandedFilters['marketplaces'] ?? [];

        if (! empty($marketplaces) && ! in_array($this->mapSourceToKey(), $marketplaces, true)) {
            $sourceKey = $this->mapSourceToKey();
            $allowed = false;
            foreach ($marketplaces as $mp) {
                if (str_contains($sourceKey, str_replace('.', '_', $mp)) || str_contains($mp, $this->source)) {
                    $allowed = true;
                    break;
                }
            }
            if (! $allowed && count($marketplaces) > 2) {
                return [];
            }
        }

        return array_map(function (array $item) {
            $item['source'] = $this->displaySourceName();
            $item['source_key'] = $this->source;
            $item['affiliate_ready'] = true;
            $item['sponsored'] = (bool) ($item['sponsored'] ?? false);

            return $item;
        }, $dataset);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadDataset(string $category): array
    {
        $path = storage_path("data/products/{$category}.json");
        if (! File::exists($path)) {
            $path = storage_path('data/products/marketplace.json');
        }

        $data = json_decode(File::get($path), true);

        return is_array($data) ? $data : [];
    }

    private function mapSourceToKey(): string
    {
        return str_replace('.', '_', $this->source);
    }

    private function displaySourceName(): string
    {
        return match ($this->source) {
            'mobile.de' => 'mobile.de',
            'autoscout24' => 'AutoScout24',
            'ebay' => 'eBay',
            'etsy' => 'Etsy',
            'amazon' => 'Amazon',
            'google_shopping' => 'Google Shopping',
            'facebook_marketplace' => 'Facebook Marketplace',
            default => ucfirst($this->source),
        };
    }
}
