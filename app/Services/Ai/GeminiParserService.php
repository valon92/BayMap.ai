<?php

namespace App\Services\Ai;

/**
 * Parses shopping queries via Google Gemini (JSON mode).
 */
class GeminiParserService
{
    public function __construct(
        private GeminiApiService $gemini,
        private OpenAiParserService $normalizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function parse(string $query, ?string $country = null, ?string $locale = 'en'): array
    {
        $decoded = $this->gemini->generateJson(
            ParserPrompts::system($locale),
            [['text' => ParserPrompts::user($query, $country, $locale)]],
            config('gemini.model')
        );

        return $this->normalizer->normalizeDecoded($decoded, $query, $country, 'gemini');
    }
}
