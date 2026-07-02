<?php

namespace App\Services\Ai;

use App\Support\CategoryCatalog;

/**
 * Converts natural-language shopping queries into structured attributes.
 * Uses OpenAI/Gemini when configured; falls back to semantic intent engine.
 */
class AiRequestParserService
{
    public function __construct(
        private AiProviderResolver $providers,
        private OpenAiParserService $openAi,
        private GeminiParserService $gemini,
        private SemanticIntentParserService $semantic,
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

                return $this->semantic->refine($parsed, $query, $locale);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("{$provider} parser failed, trying next", ['error' => $e->getMessage()]);
            }
        }

        return $this->semantic->parse($query, $country, $locale);
    }
}

