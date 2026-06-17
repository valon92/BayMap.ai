<?php

namespace App\Data;

/**
 * Normalized federated search result — unified shape across all marketplace connectors.
 * Stateless: never persisted to a database.
 */
final class SearchListing
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $image,
        public readonly float $price,
        public readonly string $currency,
        public readonly string $location,
        public readonly string $condition,
        public readonly string $url,
        public readonly string $source,
        public readonly string $sourceKey,
        public readonly bool $live = false,
        public readonly bool $affiliateReady = true,
        public readonly bool $sponsored = false,
        public readonly array $tags = [],
        public readonly ?string $brand = null,
        public readonly ?string $model = null,
        public readonly ?string $storage = null,
        public readonly ?int $year = null,
        public readonly ?int $mileage = null,
        public readonly ?string $sellerType = null,
        public readonly ?string $store = null,
        public readonly ?float $priceEur = null,
        public readonly ?string $fingerprint = null,
        public readonly ?string $fuel = null,
        public readonly ?string $color = null,
        public readonly ?string $transmission = null,
        public readonly ?float $engineLiters = null,
        public readonly ?string $countryCode = null,
        /** @var array<int, string> */
        public readonly array $images = [],
        /** @var array<string, mixed> */
        public readonly array $extensions = [],
    ) {}

    /** @var array<int, string> */
    private const KNOWN_KEYS = [
        'id', 'title', 'image', 'price', 'currency', 'location', 'condition', 'url', 'source',
        'source_key', 'live', 'affiliate_ready', 'sponsored', 'tags', 'brand', 'model', 'storage',
        'year', 'mileage', 'seller_type', 'store', 'price_eur', 'fingerprint', 'fuel', 'color',
        'transmission', 'engine_liters', 'country_code', 'images', 'match_score', 'availability',
        'valon_worker_id', 'valon_platform', '_provider_latency_ms', 'ai_explanation', 'extensions',
        '_marketplace_result_total', 'category', 'product_type', 'power_hp', 'power_kw',
        'electric_range_km', 'body_type', 'first_registration', 'consumption', 'specs',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $extensions = [];
        foreach ($data as $key => $value) {
            if (in_array($key, self::KNOWN_KEYS, true) || $value === null || $value === '' || $value === []) {
                continue;
            }
            $extensions[$key] = $value;
        }

        return new self(
            id: (string) ($data['id'] ?? uniqid('listing-', true)),
            title: (string) ($data['title'] ?? 'Listing'),
            image: (string) ($data['image'] ?? ''),
            price: (float) ($data['price'] ?? 0),
            currency: strtoupper((string) ($data['currency'] ?? 'EUR')),
            location: (string) ($data['location'] ?? ''),
            condition: (string) ($data['condition'] ?? 'used'),
            url: (string) ($data['url'] ?? '#'),
            source: (string) ($data['source'] ?? 'Marketplace'),
            sourceKey: (string) ($data['source_key'] ?? 'unknown'),
            live: (bool) ($data['live'] ?? false),
            affiliateReady: (bool) ($data['affiliate_ready'] ?? true),
            sponsored: (bool) ($data['sponsored'] ?? false),
            tags: is_array($data['tags'] ?? null) ? $data['tags'] : [],
            brand: isset($data['brand']) ? (string) $data['brand'] : null,
            model: isset($data['model']) ? (string) $data['model'] : null,
            storage: isset($data['storage']) ? (string) $data['storage'] : null,
            year: isset($data['year']) ? (int) $data['year'] : null,
            mileage: isset($data['mileage']) ? (int) $data['mileage'] : null,
            sellerType: isset($data['seller_type']) ? (string) $data['seller_type'] : null,
            store: isset($data['store']) ? (string) $data['store'] : null,
            priceEur: isset($data['price_eur']) ? (float) $data['price_eur'] : null,
            fingerprint: isset($data['fingerprint']) ? (string) $data['fingerprint'] : null,
            fuel: isset($data['fuel']) ? (string) $data['fuel'] : null,
            color: isset($data['color']) ? (string) $data['color'] : null,
            transmission: isset($data['transmission']) ? (string) $data['transmission'] : null,
            engineLiters: isset($data['engine_liters']) ? (float) $data['engine_liters'] : null,
            countryCode: isset($data['country_code']) ? (string) $data['country_code'] : null,
            images: is_array($data['images'] ?? null) ? array_values(array_filter($data['images'], 'is_string')) : [],
            extensions: $extensions,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $base = array_filter([
            'id' => $this->id,
            'title' => $this->title,
            'image' => $this->image,
            'price' => $this->price,
            'currency' => $this->currency,
            'price_eur' => $this->priceEur ?? $this->price,
            'location' => $this->location,
            'condition' => $this->condition,
            'url' => $this->url,
            'source' => $this->source,
            'source_key' => $this->sourceKey,
            'live' => $this->live,
            'affiliate_ready' => $this->affiliateReady,
            'sponsored' => $this->sponsored,
            'tags' => $this->tags,
            'brand' => $this->brand,
            'model' => $this->model,
            'storage' => $this->storage,
            'year' => $this->year,
            'mileage' => $this->mileage,
            'seller_type' => $this->sellerType,
            'store' => $this->store,
            'fingerprint' => $this->fingerprint,
            'fuel' => $this->fuel,
            'color' => $this->color,
            'transmission' => $this->transmission,
            'engine_liters' => $this->engineLiters,
            'country_code' => $this->countryCode,
            'images' => $this->images !== [] ? $this->images : null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);

        return array_merge($base, array_filter(
            $this->extensions,
            fn ($v) => $v !== null && $v !== '' && $v !== [],
        ));
    }

    public function withFingerprint(string $fingerprint): self
    {
        return new self(
            id: $this->id,
            title: $this->title,
            image: $this->image,
            price: $this->price,
            currency: $this->currency,
            location: $this->location,
            condition: $this->condition,
            url: $this->url,
            source: $this->source,
            sourceKey: $this->sourceKey,
            live: $this->live,
            affiliateReady: $this->affiliateReady,
            sponsored: $this->sponsored,
            tags: $this->tags,
            brand: $this->brand,
            model: $this->model,
            storage: $this->storage,
            year: $this->year,
            mileage: $this->mileage,
            sellerType: $this->sellerType,
            store: $this->store,
            priceEur: $this->priceEur,
            fingerprint: $fingerprint,
            fuel: $this->fuel,
            color: $this->color,
            transmission: $this->transmission,
            engineLiters: $this->engineLiters,
            countryCode: $this->countryCode,
            images: $this->images,
            extensions: $this->extensions,
        );
    }
}
