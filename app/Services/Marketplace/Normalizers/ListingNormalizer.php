<?php

namespace App\Services\Marketplace\Normalizers;

use App\Data\SearchListing;

/**
 * Standardizes raw provider payloads into SearchListing DTOs.
 */
class ListingNormalizer
{
    /**
     * @param  array<int, array<string, mixed>>  $rawListings
     * @return array<int, SearchListing>
     */
    public function normalizeMany(array $rawListings): array
    {
        return array_map(fn (array $row) => $this->normalize($row), $rawListings);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function normalize(array $raw): SearchListing
    {
        $listing = SearchListing::fromArray($raw);
        $priceEur = $this->toEur($listing->price, $listing->currency);
        $brand = $listing->brand ?? $this->extractBrand($listing->title, $listing->tags);
        $storage = $listing->storage ?? $this->extractStorage($listing->title, $listing->tags);
        $fingerprint = $this->fingerprint($listing->title, $brand, $storage);

        return new SearchListing(
            id: $listing->id,
            title: $listing->title,
            image: $listing->image ?: 'https://images.unsplash.com/photo-1472851294608-062f824d2349?w=800&q=80',
            price: $listing->price,
            currency: $listing->currency,
            location: $listing->location,
            condition: $listing->condition,
            url: $listing->url,
            source: $listing->source,
            sourceKey: $listing->sourceKey,
            live: $listing->live,
            affiliateReady: $listing->affiliateReady,
            sponsored: $listing->sponsored,
            tags: $listing->tags,
            brand: $brand,
            model: $listing->model,
            storage: $storage,
            priceEur: $priceEur,
            fingerprint: $fingerprint,
        );
    }

    /**
     * @param  array<int, string>  $tags
     * @return array<int, SearchListing>
     */
    public function toArrays(array $listings): array
    {
        return array_map(fn (SearchListing $l) => $l->toArray(), $listings);
    }

    public function toEur(float $price, string $currency): float
    {
        return match (strtoupper($currency)) {
            'CHF' => round($price * 1.05, 2),
            'USD' => round($price * 0.92, 2),
            'GBP' => round($price * 1.17, 2),
            default => round($price, 2),
        };
    }

    /**
     * @param  array<int, string>  $tags
     */
    public function fingerprint(string $title, ?string $brand, ?string $storage): string
    {
        $normalized = mb_strtolower($title);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?? $normalized;

        $tokens = array_values(array_filter(explode(' ', $normalized), fn ($t) => strlen($t) > 2));
        sort($tokens);

        $key = implode('|', array_filter([
            $brand ? mb_strtolower($brand) : null,
            $storage ? strtoupper($storage) : null,
            implode(' ', array_slice($tokens, 0, 8)),
        ]));

        return substr(md5($key), 0, 16);
    }

    /**
     * @param  array<int, string>  $tags
     */
    private function extractBrand(string $title, array $tags): ?string
    {
        $haystack = mb_strtolower($title.' '.implode(' ', $tags));
        foreach (['apple', 'iphone', 'samsung', 'galaxy', 'sony', 'dell', 'hp', 'lenovo', 'asus', 'puma', 'nike', 'adidas', 'audi', 'bmw', 'mercedes'] as $brand) {
            if (str_contains($haystack, $brand)) {
                return match ($brand) {
                    'iphone' => 'apple',
                    'galaxy' => 'samsung',
                    default => $brand,
                };
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $tags
     */
    private function extractStorage(string $title, array $tags): ?string
    {
        $haystack = strtoupper($title.' '.implode(' ', $tags));
        if (preg_match('/(\d+)\s*GB/', $haystack, $m)) {
            return $m[1].'GB';
        }
        if (preg_match('/(\d+)\s*TB/', $haystack, $m)) {
            return $m[1].'TB';
        }

        return null;
    }
}
