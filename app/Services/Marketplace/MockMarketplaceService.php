<?php

namespace App\Services\Marketplace;

use App\Contracts\MarketplaceSearchInterface;
use App\Support\CategoryCatalog;
use App\Support\DutchCarMarketplaces;
use App\Support\KosovoMarketplaces;
use App\Support\SwissCarMarketplaces;
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
        $category = CategoryCatalog::normalize($parsedQuery['category'] ?? 'marketplace');
        $dataset = $this->loadDataset($category);
        $marketplaces = $expandedFilters['marketplaces'] ?? [];

        if (! empty($marketplaces)
            && ! SwissCarMarketplaces::isTarget($this->source, $marketplaces)
            && ! DutchCarMarketplaces::isTarget($this->source, $marketplaces)
            && ! KosovoMarketplaces::isTarget($this->source, $marketplaces)) {
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

        $dataset = $this->filterForSource($dataset);
        $dataset = $this->filterForIntent($dataset, $parsedQuery);

        return array_map(function (array $item) {
            $item['source'] = $this->displaySourceName();
            $item['source_key'] = $this->source;
            $item['url'] = $this->listingUrl($item['url'] ?? null);
            $item['affiliate_ready'] = true;
            $item['sponsored'] = (bool) ($item['sponsored'] ?? false);
            $item['live'] = $this->source === 'driloni';

            return $item;
        }, $dataset);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadDataset(string $category): array
    {
        $key = CategoryCatalog::datasetKey($category);
        $path = storage_path("data/products/{$key}.json");
        if (! File::exists($path)) {
            $path = storage_path('data/products/marketplace.json');
        }

        $data = json_decode(File::get($path), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function filterForSource(array $items): array
    {
        if (KosovoMarketplaces::isKosovoPlatform($this->source)) {
            return array_values(array_filter($items, function (array $item) {
                if (($item['store'] ?? '') === $this->source) {
                    return true;
                }

                $store = $item['store'] ?? 'general';

                return ($store === 'general' || $store === '') && $this->locationMatchesCountry($item, 'XK');
            }));
        }

        if ($this->source === 'driloni') {
            return array_values(array_filter(
                $items,
                fn (array $item) => ($item['store'] ?? '') === 'driloni'
            ));
        }

        return array_values(array_filter(
            $items,
            fn (array $item) => ($item['store'] ?? 'general') !== 'driloni'
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $parsed
     * @return array<int, array<string, mixed>>
     */
    private function filterForIntent(array $items, array $parsed): array
    {
        return array_values(array_filter($items, function (array $item) use ($parsed) {
            if (CategoryCatalog::isAutomotive($parsed['category'] ?? '')) {
                if (! empty($parsed['search_country_code']) && ! $this->locationMatchesCountry($item, (string) $parsed['search_country_code'])) {
                    return false;
                }

                if (! empty($parsed['brand']) && ! $this->matchesAutomotiveBrand($item, (string) $parsed['brand'])) {
                    return false;
                }

                if (! empty($parsed['model'])) {
                    $wanted = mb_strtolower((string) $parsed['model']);
                    $title = mb_strtolower($item['title'] ?? '');
                    $tags = array_map('mb_strtolower', $item['tags'] ?? []);
                    if (! str_contains(str_replace(' ', '', $title), str_replace(' ', '', $wanted))
                        && ! in_array($wanted, $tags, true)) {
                        return false;
                    }
                }

                if (! empty($parsed['year']) && ! empty($item['year']) && (int) $item['year'] !== (int) $parsed['year']) {
                    return false;
                }
            }

            if (! empty($parsed['max_price']) && ! empty($item['price'])) {
                $limit = (float) $parsed['max_price'];
                $price = (float) $item['price'];
                $itemCurrency = $item['currency'] ?? 'EUR';
                $queryCurrency = $parsed['currency'] ?? $itemCurrency;
                if ($itemCurrency === $queryCurrency && $price > $limit) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function locationMatchesCountry(array $item, string $code): bool
    {
        $loc = mb_strtolower($item['location'] ?? '');

        return match (strtoupper($code)) {
            'CH' => (bool) preg_match('/switzerland|schweiz|zürich|zurich|bern|geneva|basel|lausanne/', $loc),
            'XK' => str_contains($loc, 'kosovo') || str_contains($loc, 'pristina') || str_contains($loc, 'ferizaj'),
            'DE' => str_contains($loc, 'germany') || str_contains($loc, 'munich') || str_contains($loc, 'berlin') || str_contains($loc, 'stuttgart'),
            'AL' => str_contains($loc, 'albania') || str_contains($loc, 'tirana'),
            'NL' => (bool) preg_match('/netherlands|holland|nederland|amsterdam|rotterdam|utrecht|den haag|eindhoven|groningen|tilburg|breda|almere|haarlem/i', $loc),
            'US' => (bool) preg_match('/united states|usa|miami|new york|los angeles|california|texas|florida/', $loc),
            'AE' => (bool) preg_match('/uae|dubai|abu dhabi|emirates/', $loc),
            'GB' => (bool) preg_match('/united kingdom|england|london|manchester|uk/', $loc),
            'AT' => str_contains($loc, 'austria') || str_contains($loc, 'vienna'),
            default => str_contains($loc, mb_strtolower($code)),
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function matchesAutomotiveBrand(array $item, string $brand): bool
    {
        $brand = mb_strtolower($brand);
        $title = mb_strtolower($item['title'] ?? '');
        $tags = array_map('mb_strtolower', $item['tags'] ?? []);
        $needles = match (true) {
            str_contains($brand, 'mercedes') => ['mercedes', 'mercedes-benz', 'benz'],
            str_contains($brand, 'volkswagen') => ['volkswagen', 'vw'],
            default => [$brand],
        };

        foreach ($needles as $needle) {
            if (str_contains($title, $needle) || in_array($needle, $tags, true)) {
                return true;
            }
        }

        return false;
    }

    private function mapSourceToKey(): string
    {
        return str_replace('.', '_', $this->source);
    }

    private function displaySourceName(): string
    {
        if (SwissCarMarketplaces::url($this->source)) {
            return SwissCarMarketplaces::label($this->source);
        }

        if (DutchCarMarketplaces::url($this->source)) {
            return DutchCarMarketplaces::label($this->source);
        }

        if (KosovoMarketplaces::url($this->source)) {
            return KosovoMarketplaces::label($this->source);
        }

        return match ($this->source) {
            'mobile.de' => 'mobile.de',
            'autoscout24', 'autoscout24_ch' => 'AutoScout24 Switzerland',
            'ebay' => 'eBay',
            'etsy' => 'Etsy',
            'amazon' => 'Amazon',
            'google_shopping' => 'Google Shopping',
            'facebook_marketplace' => 'Facebook Marketplace',
            'driloni' => 'Driloni Sportswear',
            'tutti' => 'Tutti.ch',
            'ricardo' => 'Ricardo.ch',
            default => ucfirst(str_replace('_', ' ', $this->source)),
        };
    }

    private function listingUrl(?string $fallback): string
    {
        $catalogUrl = SwissCarMarketplaces::url($this->source)
            ?? DutchCarMarketplaces::url($this->source)
            ?? KosovoMarketplaces::url($this->source);
        if ($catalogUrl) {
            return $catalogUrl;
        }

        return $fallback ?: '#';
    }
}
