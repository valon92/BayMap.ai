<?php

namespace App\Services\Ai;

use App\Support\ShoeSize;
use Illuminate\Support\Facades\Log;

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
    /** @var array<string, array<string>> */
    private array $categoryKeywords = [
        'car' => ['audi', 'bmw', 'mercedes', 'volkswagen', 'toyota', 'honda', 'ford', 'km', 'mileage', 'sedan', 'suv', 'diesel', 'petrol', 'automatic', 'manual'],
        'book' => ['book', 'novel', 'thriller', 'psychological', 'author', 'paperback', 'hardcover', 'isbn', 'reading'],
        'painting' => ['painting', 'canvas', 'artwork', 'vintage', 'oil', 'acrylic', 'poster', 'frame'],
        'electronics' => ['laptop', 'phone', 'gaming', 'gpu', 'cpu', 'tablet', 'headphones', 'monitor', 'battery', 'cooling'],
        'furniture' => ['sofa', 'chair', 'table', 'desk', 'bed', 'wardrobe', 'couch', 'living room'],
        'collectibles' => ['collectible', 'vintage', 'rare', 'limited edition', 'coin', 'stamp', 'trading card'],
        'fashion' => ['dress', 'shoes', 'jacket', 'shirt', 'handbag', 'sneakers', 'watch', 'jewelry'],
        'real_estate' => [
            'apartment', 'house', 'flat', 'bedroom', 'sqm', 'm²', 'rent', 'mortgage', 'property',
            'banes', 'banese', 'banesa', 'apartament', 'patundsh', 'gjykata', 'ferizaj', 'qira', 'blerje',
        ],
        'luxury' => ['rolex', 'louis vuitton', 'gucci', 'chanel', 'luxury', 'designer', 'premium'],
        'gift' => ['gift', 'birthday', 'anniversary', 'present'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function parse(string $query, ?string $country = null, ?string $locale = 'en'): array
    {
        foreach ($this->providers->fallbackOrder() as $provider) {
            try {
                return match ($provider) {
                    'gemini' => $this->gemini->parse($query, $country, $locale),
                    'openai' => $this->openAi->parse($query, $country, $locale),
                    default => throw new \RuntimeException('Unknown AI provider'),
                };
            } catch (\Throwable $e) {
                Log::warning("{$provider} parser failed, trying next", ['error' => $e->getMessage()]);
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
        $scores = [];
        foreach ($this->categoryKeywords as $category => $keywords) {
            $scores[$category] = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($query, $keyword)) {
                    $scores[$category]++;
                }
            }
        }

        arsort($scores);
        $top = array_key_first($scores);

        return ($scores[$top] ?? 0) > 0 ? $top : 'marketplace';
    }

    /**
     * @return array<string, mixed>
     */
    private function extractCategoryAttributes(string $query, string $category): array
    {
        return match ($category) {
            'car' => $this->parseCarQuery($query),
            'book' => $this->parseBookQuery($query),
            'painting' => $this->parsePaintingQuery($query),
            'electronics' => $this->parseElectronicsQuery($query),
            'furniture' => $this->parseFurnitureQuery($query),
            'fashion', 'luxury' => $this->parseFashionQuery($query),
            'real_estate' => $this->parseRealEstateQuery($query),
            default => $this->parseGenericQuery($query),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCarQuery(string $query): array
    {
        $brands = ['audi', 'bmw', 'mercedes', 'mercedes-benz', 'volkswagen', 'vw', 'toyota', 'honda', 'ford', 'porsche', 'skoda', 'seat'];
        $data = ['brand' => null, 'model' => null, 'year' => null, 'color' => null, 'max_km' => null, 'transmission' => null, 'fuel' => null];

        foreach ($brands as $brand) {
            if (preg_match('/\b' . preg_quote($brand, '/') . '\b/i', $query, $m)) {
                $data['brand'] = ucfirst(str_replace('vw', 'Volkswagen', strtolower($m[0])));
                if ($data['brand'] === 'Mercedes-benz') {
                    $data['brand'] = 'Mercedes-Benz';
                }
                break;
            }
        }

        if (preg_match('/\b(q\d|x\d|a\d)\b/i', $query, $m)) {
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

    /**
     * @return array<string, mixed>
     */
    private function parseBookQuery(string $query): array
    {
        return array_filter([
            'genre' => $this->matchFirst($query, ['thriller', 'psychological', 'romance', 'sci-fi', 'fantasy', 'mystery', 'biography']),
            'length' => str_contains($query, 'short') ? 'short' : (str_contains($query, 'long') ? 'long' : null),
            'ending' => str_contains($query, 'unexpected ending') || str_contains($query, 'twist') ? 'unexpected' : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePaintingQuery(string $query): array
    {
        return array_filter([
            'style' => $this->matchFirst($query, ['vintage', 'modern', 'abstract', 'impressionist', 'minimalist']),
            'room' => $this->matchFirst($query, ['living room', 'bedroom', 'office', 'kitchen']),
            'subject' => $this->matchFirst($query, ['flower', 'landscape', 'portrait', 'abstract', 'nature']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseElectronicsQuery(string $query): array
    {
        return array_filter([
            'product_type' => $this->matchFirst($query, ['laptop', 'phone', 'tablet', 'monitor', 'headphones', 'gpu', 'console']),
            'features' => array_values(array_filter([
                str_contains($query, 'battery') ? 'long_battery' : null,
                str_contains($query, 'quiet') || str_contains($query, 'cooling') ? 'quiet_cooling' : null,
                str_contains($query, 'gaming') ? 'gaming' : null,
            ])),
            'max_price' => $this->extractMaxPrice($query),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFurnitureQuery(string $query): array
    {
        return array_filter([
            'item' => $this->matchFirst($query, ['sofa', 'chair', 'table', 'desk', 'bed', 'wardrobe']),
            'room' => $this->matchFirst($query, ['living room', 'bedroom', 'office', 'dining']),
            'style' => $this->matchFirst($query, ['modern', 'scandinavian', 'vintage', 'minimal']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<int, string>
     */
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
