<?php

namespace App\Services\Ai;

use App\Support\ShoeSize;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Parses shopping queries via OpenAI Chat Completions (JSON mode).
 */
class OpenAiParserService
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $query, ?string $country = null, ?string $locale = 'en'): array
    {
        $apiKey = config('openai.api_key');
        if (! $apiKey) {
            throw new \RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(config('openai.timeout', 20))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('openai.model', 'gpt-4o-mini'),
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => ParserPrompts::system($locale)],
                    ['role' => 'user', 'content' => ParserPrompts::user($query, $country, $locale)],
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('OpenAI parse failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            $message = match ($response->status()) {
                401 => 'Invalid OpenAI API key. Check OPENAI_API_KEY in .env (no quotes, no duplicate sk-proj- prefix).',
                429 => 'OpenAI rate limit exceeded.',
                default => 'OpenAI API error: '.$response->status(),
            };
            throw new \RuntimeException($message);
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || $content === '') {
            throw new \RuntimeException('Empty OpenAI response.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Invalid JSON from OpenAI.');
        }

        return $this->normalizeDecoded($decoded, $query, $country, 'openai');
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    public function normalizeDecoded(array $decoded, string $query, ?string $country, string $parser = 'openai'): array
    {
        $allowed = [
            'car', 'book', 'painting', 'electronics', 'furniture',
            'collectibles', 'fashion', 'real_estate', 'luxury', 'gift', 'marketplace',
        ];

        $category = $decoded['category'] ?? 'marketplace';
        if (! in_array($category, $allowed, true)) {
            $category = 'marketplace';
        }

        $result = [
            'raw_query' => $query,
            'category' => $category,
            'keywords' => array_values(array_filter($decoded['keywords'] ?? [], 'is_string')),
            'country' => $country,
            'language_hint' => $decoded['language_hint'] ?? 'en',
            'parser' => $parser,
        ];

        $optional = [
            'description', 'brand', 'model', 'year', 'color', 'max_km', 'transmission', 'fuel',
            'genre', 'product_type', 'features', 'max_price', 'condition',
            'style', 'size', 'shoe_size', 'room', 'subject', 'bedrooms', 'listing_type', 'length', 'ending', 'item',
            'city', 'landmark', 'near_landmark', 'property_type', 'min_sqm', 'nearby_streets',
            'currency', 'search_country', 'search_country_code',
        ];

        foreach ($optional as $key) {
            if (array_key_exists($key, $decoded) && $decoded[$key] !== null && $decoded[$key] !== '') {
                $result[$key] = $decoded[$key];
            }
        }

        if (isset($result['year'])) {
            $result['year'] = (int) $result['year'];
        }
        if (isset($result['max_km'])) {
            $result['max_km'] = (int) $result['max_km'];
        }
        if (isset($result['max_price'])) {
            $result['max_price'] = (int) $result['max_price'];
        }
        if (isset($result['min_sqm'])) {
            $result['min_sqm'] = (int) $result['min_sqm'];
        }

        $size = ShoeSize::normalize($result['size'] ?? null)
            ?? ShoeSize::normalize($result['shoe_size'] ?? null);
        if ($size !== null) {
            $result['size'] = $size;
        }
        unset($result['shoe_size']);

        return array_filter($result, fn ($v) => $v !== null && $v !== [] && $v !== '');
    }
}
