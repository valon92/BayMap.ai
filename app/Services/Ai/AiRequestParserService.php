<?php

namespace App\Services\Ai;

use App\Support\CategoryCatalog;
use App\Support\ElectronicsIntentParser;
use App\Support\ShoeSize;

/**
 * Converts natural-language shopping queries into structured attributes.
 * Uses OpenAI when configured; falls back to rule-based parser.
 */
class AiRequestParserService
{
    public function __construct(
        private AiProviderResolver $providers,
        private OpenAiParserService $openAi,
        private GeminiParserService $gemini,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function parse(string $query, ?string $country = null, ?string $locale = 'en'): array
    {
        foreach ($this->providers->fallbackOrder() as $provider) {
            try {
                switch ($provider) {
                    case 'gemini':
                        $parsed = $this->gemini->parse($query, $country, $locale);
                        break;
                    case 'openai':
                        $parsed = $this->openAi->parse($query, $country, $locale);
                        break;
                    default:
                        throw new \RuntimeException('Unknown AI provider');
                }
                $parsed['category'] = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');

                return $parsed;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("{$provider} parser failed, trying next", ['error' => $e->getMessage()]);
            }
        }

        return $this->parseWithRules($query, $country);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseWithRules(string $query, ?string $country): array
    {
        $normalized = mb_strtolower(trim($query));
        $category = $this->detectCategory($normalized);

        $result = [
            'raw_query' => $query,
            'category' => $category,
            'keywords' => $this->extractKeywords($normalized),
            'country' => $country,
            'language_hint' => $this->detectLanguageHint($query),
            'parser' => 'rules',
        ];

        return array_merge($result, $this->extractCategoryAttributes($normalized, $category));
    }

    private function detectCategory(string $query): string
    {
        $scores = CategoryCatalog::scoreQuery($query);
        arsort($scores);
        $top = array_key_first($scores);

        return ($scores[$top] ?? 0) > 0 ? CategoryCatalog::normalize($top) : 'marketplace';
    }

    /**
     * @return array<string, mixed>
     */
    private function extractCategoryAttributes(string $query, string $category): array
    {
        switch (CategoryCatalog::normalize($category)) {
            case 'automotive':
                return $this->parseCarQuery($query);
            case 'online_education':
                return $this->parseBookQuery($query);
            case 'luxury_collectibles':
                return array_merge($this->parsePaintingQuery($query), $this->parseFashionQuery($query));
            case 'electronics_tech':
            case 'gaming_entertainment':
                return $this->parseElectronicsQuery($query);
            case 'home_furniture':
                return $this->parseFurnitureQuery($query);
            case 'fashion':
            case 'sports_outdoor':
                return $this->parseFashionQuery($query);
            case 'real_estate':
                return $this->parseRealEstateQuery($query);
            default:
                return $this->parseGenericQuery($query);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCarQuery(string $query): array
    {
        $brands = ['audi', 'bmw', 'mercedes', 'mercedes-benz', 'volkswagen', 'vw', 'toyota', 'honda', 'ford', 'porsche', 'skoda', 'seat'];
        $data = ['brand' => null, 'model' => null, 'year' => null, 'color' => null, 'max_km' => null, 'transmission' => null, 'fuel' => null];

        foreach ($brands as $brand) {
            if (preg_match('/\b'.preg_quote($brand, '/').'\b/i', $query, $m)) {
                $data['brand'] = ucfirst(str_replace('vw', 'Volkswagen', strtolower($m[0])));
                if ($data['brand'] === 'Mercedes-benz') {
                    $data['brand'] = 'Mercedes-Benz';
                }
                break;
            }
        }

        if (preg_match('/\b(q\d|x\d|a\d)\b/i', $query, $m)) {
            $data['model'] = strtoupper($m[1]);
        } elseif (preg_match('/\b(gle|glc|gla|gls|glb|eqc|eqe|eqs|cls|ml|sl|amg)\b/i', $query, $m)) {
            $data['model'] = strtoupper($m[1]);
        } elseif (preg_match('/\bserie\s+(\d)\b/i', $query, $m)) {
            $data['model'] = $m[1].' Series';
        } elseif (preg_match('/\b([a-z]{1,3}\s?\d{1,2})\b/i', $query, $m) && $data['brand']) {
            $candidate = strtoupper(str_replace(' ', '', trim($m[1])));
            if (preg_match('/^[AQX]\d$/', $candidate)) {
                $data['model'] = $candidate;
            }
        }

        if (preg_match('/\b(20\d{2}|19\d{2})\b/', $query, $m)) {
            $data['year'] = (int) $m[1];
        }

        $colors = ['white', 'black', 'silver', 'grey', 'gray', 'red', 'blue', 'green', 'beige', 'brown'];
        foreach ($colors as $color) {
            if (str_contains($query, $color)) {
                $data['color'] = $color;
                break;
            }
        }

        if (preg_match('/under\s+(\d+)\s*k\s*km/i', $query, $m)) {
            $data['max_km'] = (int) $m[1] * 1000;
        } elseif (preg_match('/under\s+(\d+)\s*km/i', $query, $m)) {
            $data['max_km'] = (int) str_replace(['.', ','], '', $m[1]);
        } elseif (preg_match('/(\d+)\s*k\s*km/i', $query, $m)) {
            $data['max_km'] = (int) $m[1] * 1000;
        }

        if (str_contains($query, 'automatic')) {
            $data['transmission'] = 'automatic';
        } elseif (str_contains($query, 'manual')) {
            $data['transmission'] = 'manual';
        }

        if (str_contains($query, 'diesel')) {
            $data['fuel'] = 'diesel';
        } elseif (str_contains($query, 'petrol') || str_contains($query, 'gasoline')) {
            $data['fuel'] = 'petrol';
        } elseif (str_contains($query, 'electric') || str_contains($query, 'ev')) {
            $data['fuel'] = 'electric';
        }

        return array_filter($data, fn ($v) => $v !== null);
    }

    /** @return array<string, mixed> */
    private function parseBookQuery(string $query): array
    {
        return array_filter([
            'subject' => $this->matchFirst($query, ['programming', 'business', 'language', 'design', 'marketing']),
            'format' => $this->matchFirst($query, ['course', 'ebook', 'certification', 'paperback', 'hardcover']),
            'level' => $this->matchFirst($query, ['beginner', 'intermediate', 'advanced']),
            'genre' => $this->matchFirst($query, ['thriller', 'psychological', 'romance', 'sci-fi', 'fantasy', 'mystery', 'biography']),
        ]);
    }

    /** @return array<string, mixed> */
    private function parsePaintingQuery(string $query): array
    {
        return array_filter([
            'style' => $this->matchFirst($query, ['vintage', 'modern', 'abstract', 'impressionist', 'minimalist']),
            'room' => $this->matchFirst($query, ['living room', 'bedroom', 'office', 'kitchen']),
            'subject' => $this->matchFirst($query, ['flower', 'landscape', 'portrait', 'abstract', 'nature']),
            'product_type' => $this->matchFirst($query, ['painting', 'art', 'collectible', 'watch', 'coin']),
        ]);
    }

    /** @return array<string, mixed> */
    private function parseElectronicsQuery(string $query): array
    {
        $lower = mb_strtolower($query);
        $fromRules = ElectronicsIntentParser::fromQuery($query);

        $features = array_values(array_unique(array_merge(
            $fromRules['features'] ?? [],
            array_values(array_filter([
                preg_match('/\b(bateri|battery|autonomi)\b/u', $lower) ? 'long_battery' : null,
                preg_match('/\b(ftohje|cooling|quiet|silent|qet)\b/u', $lower) ? 'quiet_cooling' : null,
                preg_match('/\b(gaming|loj[aë]ra|rtx)\b/u', $lower) ? 'gaming' : null,
            ])),
        )));

        return array_filter(array_merge([
            'product_type' => $fromRules['product_type']
                ?? $this->matchFirst($query, ['laptop', 'phone', 'tablet', 'monitor', 'headphones', 'gpu', 'console', 'game']),
            'platform' => $this->matchFirst($query, ['ps5', 'xbox', 'pc', 'switch']),
            'features' => $features !== [] ? $features : null,
            'max_price' => $this->extractMaxPrice($query),
            'brand' => $fromRules['brand'] ?? null,
            'storage' => $fromRules['storage'] ?? null,
            'ram' => $fromRules['ram'] ?? null,
        ], array_diff_key($fromRules, ['features' => true])));
    }

    /** @return array<string, mixed> */
    private function parseFurnitureQuery(string $query): array
    {
        return array_filter([
            'item' => $this->matchFirst($query, ['sofa', 'chair', 'table', 'desk', 'bed', 'wardrobe']),
            'room' => $this->matchFirst($query, ['living room', 'bedroom', 'office', 'dining']),
            'style' => $this->matchFirst($query, ['modern', 'scandinavian', 'vintage', 'minimal']),
            'material_type' => $this->matchFirst($query, ['wood', 'metal', 'fabric', 'leather']),
        ]);
    }

    /** @return array<string, mixed> */
    private function parseRealEstateQuery(string $query): array
    {
        $lower = mb_strtolower($query);
        $data = [];

        if (preg_match('/(\d+)\s*(bedroom|br|dhoma)/i', $query, $m)) {
            $data['bedrooms'] = (int) $m[1];
        }
        if (preg_match('/(\d+)\s*(sqm|m²|m2|metra)/i', $query, $m)) {
            $data['min_sqm'] = (int) $m[1];
        } elseif (preg_match('/(\d{2,4})\s*m\b/i', $query, $m)) {
            $data['min_sqm'] = (int) $m[1];
        }

        if (str_contains($lower, 'ferizaj')) {
            $data['city'] = 'Ferizaj';
        }
        if (str_contains($lower, 'gjykat')) {
            $data['landmark'] = 'gjykata';
            $data['near_landmark'] = true;
        }
        if (str_contains($lower, 'banes') || str_contains($lower, 'apartament')) {
            $data['property_type'] = 'apartment';
        }

        $data['listing_type'] = str_contains($lower, 'qira') || str_contains($lower, 'rent')
            ? 'rent'
            : (str_contains($lower, 'blerje') || str_contains($lower, 'buy') ? 'sale' : null);

        return array_filter($data);
    }

    /** @return array<string, mixed> */
    private function parseFashionQuery(string $query): array
    {
        return array_filter([
            'product_type' => $this->matchFirst($query, ['sneakers', 'shoes', 'boots', 'trainers', 'patika', 'këpucë', 'kepuce']),
            'brand' => $this->matchFirst($query, ['adidas', 'nike', 'puma', 'reebok', 'new balance', 'jordan']),
            'color' => $this->matchFirst($query, ['black', 'white', 'red', 'blue', 'zezë', 'zeze', 'bardhë', 'bardhe']),
            'size' => ShoeSize::extractFromText($query),
            'max_price' => $this->extractMaxPrice($query),
            'condition' => $this->matchFirst($query, ['new', 'used', 'refurbished', 'like new']),
        ]);
    }

    /** @return array<string, mixed> */
    private function parseGenericQuery(string $query): array
    {
        return array_filter([
            'size' => ShoeSize::extractFromText($query),
            'max_price' => $this->extractMaxPrice($query),
            'condition' => $this->matchFirst($query, ['new', 'used', 'refurbished', 'like new']),
        ]);
    }

    private function extractMaxPrice(string $query): ?int
    {
        if (preg_match('/under\s+€?\s*(\d+)/i', $query, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/under\s+\$(\d+)/i', $query, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function matchFirst(string $query, array $options): ?string
    {
        foreach ($options as $option) {
            if (str_contains($query, strtolower($option))) {
                return $option;
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private function extractKeywords(string $query): array
    {
        $stopWords = ['a', 'an', 'the', 'with', 'for', 'and', 'or', 'under', 'me', 'i', 'want', 'need', 'looking'];
        $words = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $query));
        $keywords = array_filter($words ?? [], fn ($w) => strlen($w) > 2 && ! in_array($w, $stopWords, true));

        return array_values(array_unique($keywords));
    }

    private function detectLanguageHint(string $query): string
    {
        $albanianMarkers = ['ç', 'ë', 'dhome', 'makine', 'libër', 'kërkon', 'gjej', 'blerje'];
        $lower = mb_strtolower($query);
        foreach ($albanianMarkers as $marker) {
            if (str_contains($lower, $marker)) {
                return 'sq';
            }
        }

        return 'en';
    }
}

