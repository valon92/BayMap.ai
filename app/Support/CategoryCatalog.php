<?php

namespace App\Support;

/**
 * Powerbook.ai category catalog — 20 verticals with category-specific filters.
 */
class CategoryCatalog
{
    /** @var array<string, string> Legacy slug => canonical slug */
    private const LEGACY = [
        'car' => 'automotive',
        'electronics' => 'electronics_tech',
        'furniture' => 'home_furniture',
        'luxury' => 'luxury_collectibles',
        'collectibles' => 'luxury_collectibles',
        'painting' => 'luxury_collectibles',
        'gift' => 'luxury_collectibles',
        'book' => 'online_education',
    ];

    /** @var array<int, string> Ordered canonical slugs (rank 1–20) */
    public const ALL = [
        'electronics_tech',
        'fashion',
        'home_appliances',
        'grocery',
        'beauty',
        'automotive',
        'home_furniture',
        'health_wellness',
        'gaming_entertainment',
        'ai_software',
        'construction',
        'online_education',
        'travel',
        'pets',
        'sports_outdoor',
        'real_estate',
        'industrial_b2b',
        'finance_fintech',
        'media_streaming',
        'luxury_collectibles',
    ];

    public static function normalize(?string $category): string
    {
        $slug = strtolower(trim((string) $category));
        $slug = self::LEGACY[$slug] ?? $slug;

        return in_array($slug, self::ALL, true) ? $slug : 'marketplace';
    }

    /**
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        return self::ALL;
    }

    public static function is(string $category, string $canonical): bool
    {
        return self::normalize($category) === $canonical;
    }

    public static function isAutomotive(string $category): bool
    {
        return self::is($category, 'automotive');
    }

    public static function isLocalFashion(string $category): bool
    {
        return in_array(self::normalize($category), ['fashion', 'sports_outdoor', 'luxury_collectibles'], true);
    }

    /** Mock JSON dataset key under storage/data/products/ */
    public static function datasetKey(string $category): string
    {
        switch (self::normalize($category)) {
            case 'automotive':
                return 'car';
            case 'fashion':
            case 'sports_outdoor':
            case 'luxury_collectibles':
                return 'fashion';
            case 'real_estate':
                return 'real_estate';
            case 'electronics_tech':
            case 'gaming_entertainment':
            case 'home_appliances':
                return 'electronics';
            default:
                return 'marketplace';
        }
    }

    /**
     * Score query against category keywords (rules fallback).
     *
     * @return array<string, int>
     */
    public static function scoreQuery(string $query): array
    {
        $lower = mb_strtolower($query);
        $scores = [];

        foreach (self::keywords() as $slug => $words) {
            $scores[$slug] = 0;
            foreach ($words as $word) {
                if (str_contains($lower, mb_strtolower($word))) {
                    $scores[$slug]++;
                }
            }
        }

        return $scores;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function keywords(): array
    {
        return [
            'electronics_tech' => ['laptop', 'phone', 'iphone', 'samsung', 'tablet', 'headphones', 'smartwatch', 'macbook', 'gpu', 'cpu', 'monitor', 'telefon', 'kompjuter'],
            'fashion' => ['dress', 'shoes', 'jacket', 'shirt', 'handbag', 'sneakers', 'patika', 'këpucë', 'kepuce', 'veshje', 'modë', 'mode', 'pantallona'],
            'home_appliances' => ['fridge', 'refrigerator', 'washing machine', 'oven', 'microwave', 'vacuum', 'dishwasher', 'frigorifer', 'lavatrice'],
            'grocery' => ['grocery', 'food', 'organic', 'supermarket', 'ushqim', 'produkt ushqimor', 'gluten', 'dairy', 'snacks'],
            'beauty' => ['makeup', 'skincare', 'perfume', 'shampoo', 'cosmetic', 'kozmetikë', 'kozmetike', 'bukuri', 'serum', 'lipstick'],
            'automotive' => ['audi', 'bmw', 'mercedes', 'volkswagen', 'toyota', 'honda', 'ford', 'km', 'mileage', 'sedan', 'suv', 'diesel', 'vetur', 'veture', 'makina', 'car'],
            'home_furniture' => ['sofa', 'chair', 'table', 'desk', 'bed', 'wardrobe', 'couch', 'living room', 'mobilje', 'dollap', 'karrige'],
            'health_wellness' => ['supplement', 'vitamin', 'fitness', 'yoga', 'wellness', 'protein', 'shëndet', 'shendet', 'gym', 'massage'],
            'gaming_entertainment' => ['ps5', 'playstation', 'xbox', 'nintendo', 'switch', 'gaming', 'game', 'console', 'lojë', 'loje'],
            'ai_software' => ['ai tool', 'chatgpt', 'saas', 'software', 'subscription', 'api', 'plugin', 'copilot', 'llm'],
            'construction' => ['cement', 'concrete', 'drill', 'hammer', 'construction', 'ndërtim', 'ndertim', 'material ndertimi', 'tools', 'scaffold'],
            'online_education' => ['course', 'udemy', 'certification', 'training', 'book', 'libër', 'liber', 'learn', 'edukim', 'kurs', 'tutorial'],
            'travel' => ['flight', 'hotel', 'travel', 'trip', 'vacation', 'udhëtim', 'udhetim', 'resort', 'airbnb', 'turizëm', 'turizem'],
            'pets' => ['dog', 'cat', 'pet food', 'kafshë', 'kafshe', 'qen', 'mace', 'pet', 'aquarium'],
            'sports_outdoor' => ['bike', 'bicycle', 'camping', 'hiking', 'football', 'sport', 'outdoor', 'ski', 'futboll', 'atletik'],
            'real_estate' => ['apartment', 'house', 'flat', 'bedroom', 'sqm', 'm2', 'rent', 'banes', 'banesa', 'apartament', 'patundsh', 'qira', 'blerje', 'gjykata', 'ferizaj'],
            'industrial_b2b' => ['industrial', 'wholesale', 'machinery', 'b2b', 'warehouse', 'forklift', 'pallet', 'industri'],
            'finance_fintech' => ['insurance', 'loan', 'credit card', 'fintech', 'bank', 'invest', 'sigurim', 'kredi', 'financ'],
            'media_streaming' => ['netflix', 'spotify', 'streaming', 'subscription', 'movie', 'music', 'film', 'serial', 'media'],
            'luxury_collectibles' => ['rolex', 'louis vuitton', 'gucci', 'chanel', 'luxury', 'collectible', 'vintage', 'watch', 'luks', 'art', 'painting', 'coin'],
        ];
    }

    /**
     * All parsed attribute keys the AI may return across categories.
     *
     * @return array<int, string>
     */
    public static function parsedFieldKeys(): array
    {
        return [
            'description', 'brand', 'model', 'year', 'color', 'max_km', 'transmission', 'fuel',
            'genre', 'product_type', 'features', 'max_price', 'condition', 'style', 'size',
            'room', 'subject', 'bedrooms', 'listing_type', 'city', 'landmark', 'near_landmark',
            'property_type', 'min_sqm', 'nearby_streets', 'currency', 'search_country', 'search_country_code',
            'storage', 'ram', 'gender', 'appliance_type', 'energy_class', 'dietary', 'skin_type',
            'platform', 'billing', 'use_case', 'material_type', 'tool_type', 'level', 'format',
            'destination', 'travel_type', 'travelers', 'pet_type', 'sport_type', 'equipment_type',
            'industry', 'media_type', 'authenticity', 'seller_type', 'item', 'quantity', 'colors',
        ];
    }

    /**
     * Category-specific dynamic filters for the results UI.
     *
     * @param  array<string, mixed>  $parsed
     * @return array<int, array<string, mixed>>
     */
    public static function buildFilters(array $parsed, ?string $locale = 'en'): array
    {
        $category = self::normalize($parsed['category'] ?? 'marketplace');
        $sq = $locale === 'sq';

        if ($category === 'marketplace') {
            return array_merge(
                self::commonFilters($parsed, $sq, 10, 10000),
                self::sortFilter($sq),
            );
        }

        $filters = match ($category) {
            'electronics_tech' => array_merge(
                self::select('product_type', $sq ? 'Lloji' : 'Type', ['phone', 'laptop', 'tablet', 'headphones', 'smartwatch', 'monitor'], $parsed['product_type'] ?? null),
                self::select('brand', $sq ? 'Marka' : 'Brand', ['apple', 'samsung', 'sony', 'dell', 'hp', 'lenovo'], isset($parsed['brand']) ? mb_strtolower((string) $parsed['brand']) : null),
                self::select('storage', $sq ? 'Memoria' : 'Storage', ['64GB', '128GB', '256GB', '512GB', '1TB'], $parsed['storage'] ?? null),
                self::select('ram', 'RAM', ['8GB', '16GB', '32GB', '64GB'], $parsed['ram'] ?? null),
                self::conditionFilter($parsed, $sq, ['new', 'used', 'refurbished']),
                self::priceFilter($parsed, $sq, 100, 5000),
            ),
            'fashion' => array_merge(
                self::sizeFilter($parsed, $sq),
                self::select('brand', $sq ? 'Marka' : 'Brand', ['adidas', 'nike', 'puma', 'zara', 'h&m', 'gucci'], isset($parsed['brand']) ? mb_strtolower((string) $parsed['brand']) : null),
                self::select('product_type', $sq ? 'Lloji' : 'Type', ['sneakers', 'shoes', 'dress', 'jacket', 'shirt', 'jeans'], $parsed['product_type'] ?? null),
                self::select('gender', $sq ? 'Gjinia' : 'Gender', ['men', 'women', 'unisex', 'kids'], $parsed['gender'] ?? null),
                self::colorFilter($parsed, $sq),
                self::conditionFilter($parsed, $sq, ['new', 'used']),
                self::priceFilter($parsed, $sq, 10, 800),
            ),
            'home_appliances' => array_merge(
                self::select('appliance_type', $sq ? 'Pajisja' : 'Appliance', ['fridge', 'washing_machine', 'oven', 'microwave', 'vacuum', 'dishwasher'], $parsed['appliance_type'] ?? null),
                self::select('brand', $sq ? 'Marka' : 'Brand', ['bosch', 'siemens', 'samsung', 'lg', 'whirlpool'], isset($parsed['brand']) ? mb_strtolower((string) $parsed['brand']) : null),
                self::select('energy_class', $sq ? 'Klasa energjise' : 'Energy class', ['A', 'B', 'C', 'D'], $parsed['energy_class'] ?? null),
                self::conditionFilter($parsed, $sq),
                self::priceFilter($parsed, $sq, 50, 5000),
            ),
            'grocery' => array_merge(
                self::select('product_type', $sq ? 'Kategoria' : 'Category', ['fresh', 'dairy', 'snacks', 'beverages', 'organic'], $parsed['product_type'] ?? null),
                self::select('dietary', $sq ? 'Dietë' : 'Dietary', ['organic', 'gluten_free', 'vegan', 'halal', 'sugar_free'], $parsed['dietary'] ?? null),
                self::select('brand', $sq ? 'Marka' : 'Brand', ['local', 'bio', 'premium'], isset($parsed['brand']) ? mb_strtolower((string) $parsed['brand']) : null),
                self::priceFilter($parsed, $sq, 1, 200),
            ),
            'beauty' => array_merge(
                self::select('product_type', $sq ? 'Lloji' : 'Type', ['skincare', 'makeup', 'perfume', 'haircare', 'bodycare'], $parsed['product_type'] ?? null),
                self::select('brand', $sq ? 'Marka' : 'Brand', ['loreal', 'nivea', 'dior', 'chanel', 'mac'], isset($parsed['brand']) ? mb_strtolower((string) $parsed['brand']) : null),
                self::select('skin_type', $sq ? 'Lloji i lëkurës' : 'Skin type', ['dry', 'oily', 'combination', 'sensitive'], $parsed['skin_type'] ?? null),
                self::select('gender', $sq ? 'Gjinia' : 'Gender', ['women', 'men', 'unisex'], $parsed['gender'] ?? null),
                self::priceFilter($parsed, $sq, 5, 300),
            ),
            'automotive' => self::automotiveFilters($parsed, $sq),
            'home_furniture' => array_merge(
                self::select('item', $sq ? 'Artikulli' : 'Item', ['sofa', 'chair', 'table', 'desk', 'bed', 'wardrobe'], $parsed['item'] ?? null),
                self::select('room', $sq ? 'Dhoma' : 'Room', ['living room', 'bedroom', 'office', 'dining', 'kitchen'], $parsed['room'] ?? null),
                self::select('material', $sq ? 'Materiali' : 'Material', ['wood', 'metal', 'fabric', 'leather', 'glass'], $parsed['material_type'] ?? null),
                self::select('style', $sq ? 'Stili' : 'Style', ['modern', 'scandinavian', 'vintage', 'minimal', 'industrial'], $parsed['style'] ?? null),
                self::conditionFilter($parsed, $sq),
                self::priceFilter($parsed, $sq, 30, 15000),
            ),
            'health_wellness' => array_merge(
                self::select('product_type', $sq ? 'Lloji' : 'Type', ['supplements', 'fitness', 'yoga', 'medical', 'wearable'], $parsed['product_type'] ?? null),
                self::select('brand', $sq ? 'Marka' : 'Brand', ['optimum', 'garmin', 'fitbit', 'philips'], isset($parsed['brand']) ? mb_strtolower((string) $parsed['brand']) : null),
                self::priceFilter($parsed, $sq, 10, 1000),
            ),
            'gaming_entertainment' => array_merge(
                self::select('platform', $sq ? 'Platforma' : 'Platform', ['ps5', 'xbox', 'pc', 'switch', 'mobile'], $parsed['platform'] ?? null),
                self::select('product_type', $sq ? 'Lloji' : 'Type', ['game', 'console', 'controller', 'headset', 'vr'], $parsed['product_type'] ?? null),
                self::select('genre', $sq ? 'Zhanri' : 'Genre', ['action', 'rpg', 'sports', 'racing', 'strategy'], $parsed['genre'] ?? null),
                self::conditionFilter($parsed, $sq),
                self::priceFilter($parsed, $sq, 15, 800),
            ),
            'ai_software' => array_merge(
                self::select('product_type', $sq ? 'Lloji' : 'Type', ['saas', 'plugin', 'api', 'desktop', 'mobile_app'], $parsed['product_type'] ?? null),
                self::select('billing', $sq ? 'Faturimi' : 'Billing', ['monthly', 'yearly', 'lifetime', 'free'], $parsed['billing'] ?? null),
                self::select('use_case', $sq ? 'Përdorimi' : 'Use case', ['writing', 'coding', 'design', 'marketing', 'analytics'], $parsed['use_case'] ?? null),
                self::priceFilter($parsed, $sq, 0, 500),
            ),
            'construction' => array_merge(
                self::select('tool_type', $sq ? 'Mjeti' : 'Tool type', ['power_tool', 'hand_tool', 'safety', 'measurement'], $parsed['tool_type'] ?? null),
                self::select('material_type', $sq ? 'Materiali' : 'Material', ['cement', 'steel', 'wood', 'insulation', 'plumbing'], $parsed['material_type'] ?? null),
                self::select('brand', $sq ? 'Marka' : 'Brand', ['bosch', 'makita', 'dewalt', 'hilti'], isset($parsed['brand']) ? mb_strtolower((string) $parsed['brand']) : null),
                self::priceFilter($parsed, $sq, 5, 10000),
            ),
            'online_education' => array_merge(
                self::select('subject', $sq ? 'Fusha' : 'Subject', ['programming', 'business', 'language', 'design', 'marketing'], $parsed['subject'] ?? null),
                self::select('level', $sq ? 'Niveli' : 'Level', ['beginner', 'intermediate', 'advanced'], $parsed['level'] ?? null),
                self::select('format', $sq ? 'Formati' : 'Format', ['course', 'ebook', 'certification', 'bootcamp'], $parsed['format'] ?? null),
                self::select('language', $sq ? 'Gjuha' : 'Language', ['en', 'sq', 'de', 'fr'], $parsed['language'] ?? null),
                self::priceFilter($parsed, $sq, 0, 500),
            ),
            'travel' => array_merge(
                self::select('travel_type', $sq ? 'Lloji' : 'Type', ['flight', 'hotel', 'package', 'car_rental', 'activity'], $parsed['travel_type'] ?? null),
                self::textFilter('destination', $sq ? 'Destinacioni' : 'Destination', $parsed['destination'] ?? null),
                self::rangeFilter('travelers', $sq ? 'Udhëtarët' : 'Travelers', 1, 10, $parsed['travelers'] ?? null),
                self::priceFilter($parsed, $sq, 50, 10000),
            ),
            'pets' => array_merge(
                self::select('pet_type', $sq ? 'Kafsha' : 'Pet', ['dog', 'cat', 'bird', 'fish', 'other'], $parsed['pet_type'] ?? null),
                self::select('product_type', $sq ? 'Lloji' : 'Type', ['food', 'toys', 'accessories', 'health', 'bedding'], $parsed['product_type'] ?? null),
                self::select('brand', $sq ? 'Marka' : 'Brand', ['royal canin', 'purina', 'hills'], isset($parsed['brand']) ? mb_strtolower((string) $parsed['brand']) : null),
                self::priceFilter($parsed, $sq, 5, 500),
            ),
            'sports_outdoor' => array_merge(
                self::select('sport_type', $sq ? 'Sporti' : 'Sport', ['football', 'running', 'cycling', 'hiking', 'ski', 'gym'], $parsed['sport_type'] ?? null),
                self::select('product_type', $sq ? 'Lloji' : 'Type', ['shoes', 'equipment', 'clothing', 'accessories'], $parsed['product_type'] ?? null),
                self::sizeFilter($parsed, $sq),
                self::select('brand', $sq ? 'Marka' : 'Brand', ['nike', 'adidas', 'puma', 'decathlon'], isset($parsed['brand']) ? mb_strtolower((string) $parsed['brand']) : null),
                self::conditionFilter($parsed, $sq),
                self::priceFilter($parsed, $sq, 10, 3000),
            ),
            'real_estate' => array_merge(
                self::rangeFilter('min_sqm', $sq ? 'Sipërfaqja min (m²)' : 'Min area (m²)', 20, 500, $parsed['min_sqm'] ?? null),
                self::rangeFilter('bedrooms', $sq ? 'Dhoma' : 'Bedrooms', 1, 8, $parsed['bedrooms'] ?? null),
                self::select('listing_type', $sq ? 'Lloji' : 'Listing', ['rent', 'sale'], $parsed['listing_type'] ?? null),
                self::select('property_type', $sq ? 'Prona' : 'Property', ['apartment', 'house', 'land', 'commercial'], $parsed['property_type'] ?? null),
                self::priceFilter($parsed, $sq, 100, 500000, $sq ? 'Çmimi (€)' : 'Price (€)'),
            ),
            'industrial_b2b' => array_merge(
                self::select('equipment_type', $sq ? 'Pajisja' : 'Equipment', ['machinery', 'tools', 'safety', 'packaging', 'logistics'], $parsed['equipment_type'] ?? null),
                self::select('industry', $sq ? 'Industria' : 'Industry', ['manufacturing', 'construction', 'food', 'textile', 'automotive'], $parsed['industry'] ?? null),
                self::conditionFilter($parsed, $sq, ['new', 'used', 'refurbished']),
                self::priceFilter($parsed, $sq, 100, 100000),
            ),
            'finance_fintech' => array_merge(
                self::select('product_type', $sq ? 'Produkti' : 'Product', ['insurance', 'loan', 'credit_card', 'investment', 'payment'], $parsed['product_type'] ?? null),
                self::select('brand', $sq ? 'Ofruesi' : 'Provider', ['bank', 'fintech', 'insurer'], isset($parsed['brand']) ? mb_strtolower((string) $parsed['brand']) : null),
                self::priceFilter($parsed, $sq, 0, 50000),
            ),
            'media_streaming' => array_merge(
                self::select('media_type', $sq ? 'Media' : 'Media type', ['subscription', 'movie', 'series', 'music', 'audiobook'], $parsed['media_type'] ?? null),
                self::select('platform', $sq ? 'Platforma' : 'Platform', ['netflix', 'spotify', 'disney', 'youtube', 'amazon'], $parsed['platform'] ?? null),
                self::select('genre', $sq ? 'Zhanri' : 'Genre', ['action', 'comedy', 'documentary', 'kids', 'news'], $parsed['genre'] ?? null),
                self::priceFilter($parsed, $sq, 0, 100),
            ),
            'luxury_collectibles' => array_merge(
                self::select('brand', $sq ? 'Marka' : 'Brand', ['rolex', 'gucci', 'louis vuitton', 'chanel', 'hermes'], isset($parsed['brand']) ? mb_strtolower((string) $parsed['brand']) : null),
                self::select('product_type', $sq ? 'Lloji' : 'Type', ['watch', 'handbag', 'jewelry', 'art', 'collectible', 'coin'], $parsed['product_type'] ?? null),
                self::select('authenticity', $sq ? 'Autenticiteti' : 'Authenticity', ['verified', 'with_certificate', 'unverified'], $parsed['authenticity'] ?? null),
                self::rangeFilter('year', $sq ? 'Viti' : 'Year', 1950, (int) date('Y'), $parsed['year'] ?? null),
                self::conditionFilter($parsed, $sq, ['new', 'used', 'vintage']),
                self::priceFilter($parsed, $sq, 100, 500000),
            ),
            default => self::commonFilters($parsed, $sq, 10, 10000),
        };

        return array_merge($filters, self::sortFilter($sq));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function automotiveFilters(array $parsed, bool $sq): array
    {
        $currency = $parsed['currency'] ?? 'EUR';
        $targetCountry = ! empty($parsed['search_target'])
            ? ($parsed['search_country'] ?? null)
            : null;
        $priceLabel = ($sq ? 'Çmimi max' : 'Max price').' ('.$currency.')';
        $countryOptions = array_values(array_unique(array_filter([
            $targetCountry,
            $sq ? 'Botë (universal)' : 'Worldwide',
            'Kosovo', 'Germany', 'Switzerland', 'Netherlands', 'Albania', 'Italy', 'Austria', 'France', 'United Kingdom', 'United States', 'UAE',
        ])));

        return array_merge(
            self::rangeFilter('year', $sq ? 'Viti' : 'Year', 1995, (int) date('Y'), $parsed['year'] ?? null),
            self::rangeFilter('max_km', $sq ? 'Km max' : 'Max mileage', 0, 300000, $parsed['max_km'] ?? null),
            self::colorFilter($parsed, $sq),
            self::select('transmission', $sq ? 'Transmisioni' : 'Transmission', ['automatic', 'manual'], $parsed['transmission'] ?? null),
            self::select('fuel', $sq ? 'Karburanti' : 'Fuel', ['petrol', 'diesel', 'electric', 'hybrid'], $parsed['fuel'] ?? null),
            [[
                'key' => 'price',
                'type' => 'range',
                'label' => $priceLabel,
                'min' => 1000,
                'max' => $currency === 'CHF' ? 80000 : 150000,
                'value' => $parsed['max_price'] ?? null,
            ]],
            self::select('country', $sq ? 'Vendi' : 'Country', $countryOptions, $targetCountry),
            self::select('condition', $sq ? 'Gjendja' : 'Condition', ['new', 'used', 'certified'], $parsed['condition'] ?? 'used'),
            self::select('seller_type', $sq ? 'Shitësi' : 'Seller', ['dealer', 'private'], $parsed['seller_type'] ?? null),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function sortFilter(bool $sq): array
    {
        return [[
            'key' => 'sort',
            'type' => 'sort',
            'label' => $sq ? 'Rendit sipas çmimit' : 'Sort by price',
            'options' => [
                ['value' => 'relevance', 'label' => $sq ? 'Relevancë AI (default)' : 'AI relevance (default)'],
                ['value' => 'price_asc', 'label' => $sq ? 'Më i lirë → më i shtrenjtë' : 'Lowest to highest'],
                ['value' => 'price_desc', 'label' => $sq ? 'Më i shtrenjtë → më i lirë' : 'Highest to lowest'],
            ],
            'value' => 'relevance',
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function commonFilters(array $parsed, bool $sq, int $min, int $max): array
    {
        return array_merge(
            self::priceFilter($parsed, $sq, $min, $max),
            self::conditionFilter($parsed, $sq, ['new', 'used', 'vintage']),
        );
    }

    /**
     * @param  array<int, string|null>  $options
     * @return array<int, array<string, mixed>>
     */
    private static function select(string $key, string $label, array $options, mixed $value): array
    {
        return [[
            'key' => $key,
            'type' => 'select',
            'label' => $label,
            'options' => $options,
            'value' => $value,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function textFilter(string $key, string $label, mixed $value): array
    {
        return [[
            'key' => $key,
            'type' => 'text',
            'label' => $label,
            'value' => $value,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function rangeFilter(string $key, string $label, int $min, int $max, mixed $value): array
    {
        return [[
            'key' => $key,
            'type' => 'range',
            'label' => $label,
            'min' => $min,
            'max' => $max,
            'value' => $value,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function priceFilter(array $parsed, bool $sq, int $min, int $max, ?string $label = null): array
    {
        return [[
            'key' => 'price',
            'type' => 'range',
            'label' => $label ?? ($sq ? 'Çmimi max (€)' : 'Max price (€)'),
            'min' => $min,
            'max' => $max,
            'value' => $parsed['max_price'] ?? null,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function colorFilter(array $parsed, bool $sq): array
    {
        return self::select(
            'color',
            $sq ? 'Ngjyra' : 'Color',
            ['black', 'white', 'red', 'blue', 'grey', 'green', 'silver', 'multicolor'],
            $parsed['color'] ?? null,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function sizeFilter(array $parsed, bool $sq): array
    {
        return [[
            'key' => 'size',
            'type' => 'number',
            'label' => $sq ? 'Numri (EU)' : 'Size (EU)',
            'min' => 35,
            'max' => 48,
            'step' => 0.5,
            'value' => $parsed['size'] ?? null,
        ]];
    }

    /**
     * @param  array<int, string>  $options
     * @return array<int, array<string, mixed>>
     */
    private static function conditionFilter(array $parsed, bool $sq, array $options = ['new', 'used', 'refurbished']): array
    {
        return self::select('condition', $sq ? 'Gjendja' : 'Condition', $options, $parsed['condition'] ?? null);
    }
}
