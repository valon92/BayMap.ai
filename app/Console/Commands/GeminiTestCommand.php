<?php

namespace App\Console\Commands;

use App\Services\Ai\AiProviderResolver;
use App\Services\Ai\GeminiApiService;
use Illuminate\Console\Command;

class GeminiTestCommand extends Command
{
    protected $signature = 'gemini:test {--query= : Optional test query in Albanian or English}';

    protected $description = 'Test Gemini API key and connection from .env';

    public function handle(GeminiApiService $gemini, AiProviderResolver $providers): int
    {
        $this->info('BuyMap — Gemini API test');
        $this->newLine();

        $key = config('gemini.api_key');
        if (! $key) {
            $this->error('GEMINI_API_KEY (or GOOGLE_API_KEY) is empty in .env');

            return self::FAILURE;
        }

        $this->line('  AI_PROVIDER: '.config('ai.provider'));
        $this->line('  GEMINI_MODEL: '.config('gemini.model'));
        $this->line('  Key length: '.strlen($key).' chars');
        $this->line('  Resolver: '.($providers->canUseGemini() ? 'gemini OK' : 'gemini not available'));
        $this->newLine();

        $userText = $this->option('query')
            ?: 'Kthe vetëm JSON: {"status":"ok","message":"BuyMap Gemini funksionon"}';

        $this->comment('Sending test request…');

        try {
            $start = microtime(true);
            $result = $gemini->generateJson(
                'You are a test assistant. Reply with valid JSON only, no markdown.',
                [['text' => $userText]],
            );
            $ms = (int) round((microtime(true) - $start) * 1000);

            $this->info("Success ({$ms} ms)");
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed: '.$e->getMessage());
            $this->newLine();

            if (str_contains($e->getMessage(), 'quota') || str_contains($e->getMessage(), '429')) {
                $this->warn('Çelësi është OK — problemi është kuota Google (jo kodi yt).');
                $this->line('  • Prit 1–24 orë (free tier reset) ose aktivizo billing:');
                $this->line('    https://aistudio.google.com → Settings → Plan / billing');
                $this->line('  • Deri atëherë përdor OpenAI: AI_PROVIDER=openai në .env');
            } elseif (str_contains($e->getMessage(), 'not available in your country')) {
                $this->warn('Gemini free tier nuk mbështetet në vendin tënd — aktivizo billing në Google AI Studio.');
                $this->line('  • Ose përdor: AI_PROVIDER=openai');
            } else {
                $this->warn('Tips:');
                $this->line('  • php artisan config:clear');
                $this->line('  • https://aistudio.google.com/app/apikey');
            }

            return self::FAILURE;
        }
    }
}
