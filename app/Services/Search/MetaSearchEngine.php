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
        $passthrough = [];
        $toCluster = [];

        foreach ($products as $product) {
            if ($this->isQuoteListing($product) || $this->isWebServiceListing($product)) {
                $passthrough[] = $product;

                continue;
            }
            $toCluster[] = $product;
        }

        $clusters = $this->cluster($toCluster);

        $meta = array_values(array_filter(array_map(function (array $cluster) {
            $listing = $this->buildMetaListing($cluster);

            return $listing !== [] ? $listing : null;
        }, $clusters)));

        return array_merge($passthrough, $meta);
    }

    /**
     * Bridge listings (train/bus compare links) have no upfront price — keep them out of price clustering.
     *
     * @param  array<string, mixed>  $product
     */
    private function isQuoteListing(array $product): bool
    {
        if (! empty($product['price_on_request'])) {
            return true;
        }

        $mode = mb_strtolower((string) ($product['travel_mode'] ?? $product['product_type'] ?? ''));

        return ($product['category'] ?? '') === 'travel'
            && in_array($mode, ['train', 'bus', 'tren', 'autobus'], true);
    }

    /**
     * Web infrastructure bridge listings (domain, hosting, email) have no upfront price.
     *
     * @param  array<string, mixed>  $product
     */
    private function isWebServiceListing(array $product): bool
    {
        if (($product['category'] ?? '') !== 'ai_software') {
            return false;
        }

        $type = mb_strtolower((string) ($product['web_service_type'] ?? $product['product_type'] ?? ''));

        return in_array($type, ['domain', 'hosting', 'email', 'ssl', 'website'], true)
            || isset($product['provider_rank']);
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
        $cluster = array_values(array_filter($cluster, fn ($p) => (float) ($p['price'] ?? $p['price_eur'] ?? 0) > 0));

        if ($cluster === []) {
            return [];
        }

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
        $offers = [];
        $seenOfferKeys = [];

        foreach ($cluster as $p) {
            $price = (float) ($p['price'] ?? $p['price_eur'] ?? 0);
            if ($price <= 0) {
                continue;
            }

            $offerKey = strtolower((string) ($p['source_key'] ?? '')).'|'.(string) ($p['url'] ?? '');
            if (isset($seenOfferKeys[$offerKey])) {
                continue;
            }
            $seenOfferKeys[$offerKey] = true;

            $offers[] = [
                'source' => $p['source'] ?? '',
                'source_key' => $p['source_key'] ?? '',
                'price' => $price,
                'price_eur' => (float) ($p['price_eur'] ?? $price),
                'currency' => $p['currency'] ?? 'EUR',
                'location' => $p['location'] ?? '',
                'url' => $p['url'] ?? '#',
                'live' => (bool) ($p['live'] ?? false),
                'condition' => $p['condition'] ?? 'used',
            ];
        }

        $primary = $best;
        $primary['offer_count'] = max(1, count($offers));
        $primary['best_price_eur'] = $min;
        $primary['price_spread_eur'] = round(max(0, $max - $min), 2);
        $primary['alternate_sources'] = array_values(array_filter($sources, fn ($s) => $s !== ($best['source'] ?? '')));
        $primary['offers'] = $offers;
        $primary['is_best_offer'] = true;

        if (count($offers) > 1) {
            $uniqueSources = array_values(array_unique(array_map(fn ($o) => $o['source'], $offers)));
            $primary['meta_label'] = count($offers).' offers · from €'.number_format($min, 0).' on '.implode(', ', array_slice($uniqueSources, 0, 3));
        }

        return $primary;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function clusterKey(array $product): string
    {
        if (! empty($product['fingerprint'])) {
            return 'fp:'.(string) $product['fingerprint'];
        }

        $brand = $this->tagValue($product, 'brand');
        $model = $this->tagValue($product, 'model') ?? $this->tagValue($product, 'series');
        if ($brand !== null && $model !== null) {
            return 'bm:'.substr(md5(mb_strtolower($brand.'|'.$model)), 0, 16);
        }

        $sourceKey = (string) ($product['source_key'] ?? '');
        if (LivePlatformRegistry::isLivePlatform($sourceKey) && ! empty($product['id'])) {
            return $sourceKey.':'.(string) $product['id'];
        }

        return $this->fallbackKey($product);
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function tagValue(array $product, string $needle): ?string
    {
        $tags = $product['tags'] ?? [];
        if (! is_array($tags)) {
            return null;
        }

        foreach ($tags as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '') {
                continue;
            }
            if (str_starts_with(mb_strtolower($tag), mb_strtolower($needle).':')) {
                return trim(substr($tag, strlen($needle) + 1));
            }
        }

        return null;
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
