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
        $model = $listing->model ?? $this->extractModel($listing->title, $listing->tags);
        $storage = $listing->storage ?? $this->extractStorage($listing->title, $listing->tags);
        $year = $listing->year ?? $this->extractYear($listing->title, $listing->tags);
        $fingerprint = $this->fingerprint($listing->title, $brand, $storage);
        $images = $listing->images !== [] ? $listing->images : (is_array($raw['images'] ?? null) ? $raw['images'] : []);
        $images = array_values(array_unique(array_filter(
            $images,
            fn ($url) => is_string($url) && $url !== '' && ! $this->isPlaceholderImage($url),
        )));
        $image = $listing->image !== '' && ! $this->isPlaceholderImage($listing->image)
            ? $listing->image
            : ($images[0] ?? '');

        return new SearchListing(
            id: $listing->id,
            title: $listing->title,
            image: $image,
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
            model: $model,
            storage: $storage,
            year: $year,
            mileage: $listing->mileage,
            sellerType: $listing->sellerType,
            store: $listing->store,
            priceEur: $priceEur,
            fingerprint: $fingerprint,
            fuel: $listing->fuel,
            color: $listing->color,
            transmission: $listing->transmission,
            engineLiters: $listing->engineLiters,
            countryCode: $listing->countryCode,
            images: $images,
            extensions: array_merge($listing->extensions, array_filter([
                'power_hp' => $raw['power_hp'] ?? null,
                'power_kw' => $raw['power_kw'] ?? null,
                'electric_range_km' => $raw['electric_range_km'] ?? null,
                'body_type' => $raw['body_type'] ?? null,
                'first_registration' => $raw['first_registration'] ?? null,
                'consumption' => $raw['consumption'] ?? null,
                'specs' => $raw['specs'] ?? null,
                'category' => $raw['category'] ?? null,
                'product_type' => $raw['product_type'] ?? null,
                'gender' => $raw['gender'] ?? null,
                'sizes' => $raw['sizes'] ?? null,
                'rooms' => $raw['rooms'] ?? null,
                'area_sqm' => $raw['area_sqm'] ?? null,
                'property_type' => $raw['property_type'] ?? null,
                'format' => $raw['format'] ?? null,
                'genre' => $raw['genre'] ?? null,
                'author' => $raw['author'] ?? null,
                'ram' => $raw['ram'] ?? null,
                'chip' => $raw['chip'] ?? null,
                'display_size' => $raw['display_size'] ?? null,
            ], fn ($v) => $v !== null && $v !== '' && $v !== [])),
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

    /**
     * @param  array<int, string>  $tags
     */
    private function extractModel(string $title, array $tags): ?string
    {
        $haystack = strtoupper($title.' '.implode(' ', $tags));
        if (preg_match('/\b(GLE|GLC|GLA|GLS|GLB|EQC|EQE|EQS|CLS|ML|SL|AMG|Q[3578]|X[13567]|A[34678])\b/', $haystack, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    /**
     * @param  array<int, string>  $tags
     */
    private function extractYear(string $title, array $tags): ?int
    {
        $haystack = $title.' '.implode(' ', $tags);
        if (preg_match('/\b(20\d{2}|19\d{2})\b/', $haystack, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function isPlaceholderImage(string $url): bool
    {
        return str_contains($url, 'images.unsplash.com/photo-1618843479313')
            || str_contains($url, 'images.unsplash.com/photo-1472851294608');
    }
}
