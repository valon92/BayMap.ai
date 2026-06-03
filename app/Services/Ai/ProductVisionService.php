<?php

namespace App\Services\Ai;

use App\Support\SearchLocale;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Analyzes product photos via Gemini or OpenAI vision.
 */
class ProductVisionService
{
    public function __construct(
        private AiProviderResolver $providers,
        private GeminiApiService $gemini,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(
        string $imageBase64,
        ?string $userHint = null,
        ?array $geo = null,
        ?string $locale = 'en',
    ): array {
        $order = $this->providers->fallbackOrder();
        $lastError = null;

        foreach ($order as $provider) {
            try {
                return match ($provider) {
                    'gemini' => $this->analyzeWithGemini($imageBase64, $userHint, $geo, $locale),
                    'openai' => $this->analyzeWithOpenAi($imageBase64, $userHint, $geo, $locale),
                    default => throw new \RuntimeException('Unknown vision provider'),
                };
            } catch (\Throwable $e) {
                $lastError = $e;
                Log::warning("{$provider} vision failed", ['error' => $e->getMessage()]);
            }
        }

        throw $lastError ?? new \RuntimeException('No AI vision provider configured. Set GEMINI_API_KEY or OPENAI_API_KEY.');
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeWithGemini(
        string $imageBase64,
        ?string $userHint,
        ?array $geo,
        ?string $locale,
    ): array {
        $mime = $this->detectMime($imageBase64);
        $lang = SearchLocale::descriptionLanguage($locale);
        $locale = SearchLocale::normalize($locale);
        $locCtx = $this->locationContext($geo);
        $hint = $userHint ? "User note: {$userHint}" : 'No extra text from user.';

        $decoded = $this->gemini->generateJson(
            'You are BuyMap.ai visual product expert. Analyze shopping product photos and return JSON only.',
            [
                ['text' => ParserPrompts::visionUser($lang, $locCtx, $hint)],
                [
                    'inlineData' => [
                        'mimeType' => $mime,
                        'data' => $imageBase64,
                    ],
                ],
            ],
            config('gemini.vision_model')
        );

        $decoded['vision'] = true;
        $decoded['parser'] = 'gemini-vision';
        $decoded['locale'] = $locale;

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeWithOpenAi(
        string $imageBase64,
        ?string $userHint,
        ?array $geo,
        ?string $locale,
    ): array {
        $apiKey = config('openai.api_key');
        if (! $apiKey) {
            throw new \RuntimeException('OPENAI_API_KEY required for image search.');
        }

        $mime = $this->detectMime($imageBase64);
        $dataUri = "data:{$mime};base64,{$imageBase64}";
        $lang = SearchLocale::descriptionLanguage($locale);
        $locale = SearchLocale::normalize($locale);
        $locCtx = $this->locationContext($geo);
        $hint = $userHint ? "User note: {$userHint}" : 'No extra text from user.';

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('openai.vision_model', 'gpt-4o-mini'),
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are BuyMap.ai visual product expert. Analyze shopping product photos and return JSON only.',
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => ParserPrompts::visionUser($lang, $locCtx, $hint),
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => ['url' => $dataUri, 'detail' => 'low'],
                            ],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('OpenAI Vision failed', ['status' => $response->status()]);
            throw new \RuntimeException('Vision API error: '.$response->status());
        }

        $content = $response->json('choices.0.message.content');
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('Invalid vision JSON response.');
        }

        $decoded['vision'] = true;
        $decoded['parser'] = 'openai-vision';
        $decoded['locale'] = $locale;

        return $decoded;
    }

    /**
     * @param  array<string, mixed>|null  $geo
     */
    private function locationContext(?array $geo): string
    {
        if (! $geo) {
            return '';
        }

        $location = trim(($geo['city'] ?? '').', '.($geo['country'] ?? ''));

        return $location ? "Buyer location: {$location}. Prefer local/regional availability." : '';
    }

    private function detectMime(string $base64): string
    {
        $bin = base64_decode(substr($base64, 0, 32), true);
        if ($bin === false) {
            return 'image/jpeg';
        }
        if (str_starts_with($bin, "\x89PNG")) {
            return 'image/png';
        }
        if (str_starts_with($bin, 'RIFF') && str_contains(substr($bin, 0, 12), 'WEBP')) {
            return 'image/webp';
        }

        return 'image/jpeg';
    }
}
