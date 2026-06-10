<?php

namespace App\Services\Search;

use App\Support\LivePlatformRegistry;

/**
 * AI-powered meta search engine — clusters identical products across marketplaces,
 * compares prices and sellers, and surfaces the best offer.
 */
class MetaSearchEngine
{
    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    public function enrich(array $products): array
    {
        $clusters = $this->cluster($products);

        return array_values(array_map(function (array $cluster) {
            return $this->buildMetaListing($cluster);
        }, $clusters));
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function cluster(array $products): array
    {
        $groups = [];

        foreach ($products as $product) {
            $key = $this->clusterKey($product);
            $groups[$key][] = $product;
        }

        return array_values($groups);
    }

    /**
     * @param  array<int, array<string, mixed>>  $cluster
     * @return array<string, mixed>
     */
    private function buildMetaListing(array $cluster): array
    {
        usort($cluster, function ($a, $b) {
            $priceA = (float) ($a['price_eur'] ?? $a['price'] ?? PHP_INT_MAX);
            $priceB = (float) ($b['price_eur'] ?? $b['price'] ?? PHP_INT_MAX);
            if ($priceA !== $priceB) {
                return $priceA <=> $priceB;
            }

            return strcmp((string) ($a['source_key'] ?? ''), (string) ($b['source_key'] ?? ''));
        });

        $best = $cluster[0];
        $prices = array_map(fn ($p) => (float) ($p['price_eur'] ?? $p['price'] ?? 0), $cluster);
        $prices = array_values(array_filter($prices, fn ($p) => $p > 0));
        $min = $prices !== [] ? min($prices) : (float) ($best['price'] ?? 0);
        $max = $prices !== [] ? max($prices) : $min;

        $sources = array_values(array_unique(array_map(fn ($p) => (string) ($p['source'] ?? ''), $cluster)));
        $offers = array_map(fn ($p) => [
            'source' => $p['source'] ?? '',
            'source_key' => $p['source_key'] ?? '',
            'price' => $p['price'] ?? 0,
            'price_eur' => $p['price_eur'] ?? $p['price'] ?? 0,
            'currency' => $p['currency'] ?? 'EUR',
            'location' => $p['location'] ?? '',
            'url' => $p['url'] ?? '#',
            'live' => (bool) ($p['live'] ?? false),
            'condition' => $p['condition'] ?? 'used',
        ], $cluster);

        $primary = $best;
        $primary['offer_count'] = count($cluster);
        $primary['best_price_eur'] = $min;
        $primary['price_spread_eur'] = round(max(0, $max - $min), 2);
        $primary['alternate_sources'] = array_values(array_filter($sources, fn ($s) => $s !== ($best['source'] ?? '')));
        $primary['offers'] = $offers;
        $primary['is_best_offer'] = true;

        if (count($cluster) > 1) {
            $primary['meta_label'] = count($cluster).' offers · from €'.number_format($min, 0).' on '.implode(', ', array_slice($sources, 0, 3));
        }

        return $primary;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function clusterKey(array $product): string
    {
        $sourceKey = (string) ($product['source_key'] ?? '');
        if (LivePlatformRegistry::isLivePlatform($sourceKey) && ! empty($product['id'])) {
            return $sourceKey.':'.(string) $product['id'];
        }

        return $product['fingerprint'] ?? $this->fallbackKey($product);
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function fallbackKey(array $product): string
    {
        $title = mb_strtolower((string) ($product['title'] ?? ''));

        return substr(md5(preg_replace('/\s+/', ' ', $title) ?? $title), 0, 16);
    }
}
