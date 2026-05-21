<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side Gemini REST client (Generative Language API).
 */
class GeminiApiService
{
    /**
     * @param  array<int, array<string, mixed>>  $userParts
     * @return array<string, mixed>
     */
    public function generateJson(string $systemPrompt, array $userParts, ?string $model = null): array
    {
        $apiKey = config('gemini.api_key');
        if (! $apiKey) {
            throw new \RuntimeException('GEMINI_API_KEY (or GOOGLE_API_KEY) is not configured.');
        }

        $model = $model ?? config('gemini.model', 'gemini-2.0-flash');
        $url = rtrim(config('gemini.base_url'), '/')."/models/{$model}:generateContent";

        $response = Http::timeout(config('gemini.timeout', 25))
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->post($url, [
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => $userParts,
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if (! $response->successful()) {
            $apiMessage = $response->json('error.message');
            Log::warning('Gemini API failed', [
                'status' => $response->status(),
                'model' => $model,
                'body' => $response->json(),
            ]);
            $message = match ($response->status()) {
                401, 403 => 'Invalid Gemini API key. Set GEMINI_API_KEY in .env (Google AI Studio).',
                429 => 'Gemini quota exceeded. '.$this->shortApiMessage($apiMessage),
                400 => $this->shortApiMessage($apiMessage) ?: 'Gemini bad request (400).',
                default => 'Gemini API error '.$response->status().': '.$this->shortApiMessage($apiMessage),
            };
            throw new \RuntimeException($message);
        }

        $text = $this->extractText($response->json());
        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('Invalid JSON from Gemini.');
        }

        return $decoded;
    }

    private function extractText(?array $payload): string
    {
        $parts = $payload['candidates'][0]['content']['parts'] ?? [];
        $chunks = [];
        foreach ($parts as $part) {
            if (! empty($part['text'])) {
                $chunks[] = $part['text'];
            }
        }

        $text = trim(implode('', $chunks));
        if ($text === '') {
            throw new \RuntimeException('Empty Gemini response.');
        }

        return $text;
    }

    private function shortApiMessage(?string $message): string
    {
        if (! is_string($message) || $message === '') {
            return '';
        }

        return strlen($message) > 200 ? substr($message, 0, 197).'…' : $message;
    }
}
