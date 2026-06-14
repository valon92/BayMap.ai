<?php

namespace App\Services\Marketplace\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScraperHttpClient
{
    public function get(string $url, ?string $locale = null, ?string $referer = null): string
    {
        $acceptLanguage = match ($locale) {
            'de-DE' => 'de-DE,de;q=0.9,en;q=0.8',
            'de-CH' => 'de-CH,de;q=0.9,en;q=0.8',
            'nl-NL' => 'nl-NL,nl;q=0.9,en;q=0.8',
            'en-GB' => 'en-GB,en;q=0.9',
            default => 'en-US,en;q=0.9',
        };

        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => $acceptLanguage,
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
        ];

        if ($referer !== null && $referer !== '') {
            $headers['Referer'] = $referer;
        }

        try {
            $response = Http::timeout((int) config('live_platforms.timeout_seconds', 60))
                ->withHeaders($headers)
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Live platform fetch failed', ['url' => $url, 'status' => $response->status()]);

                return '';
            }

            $body = (string) $response->body();
            if ($this->isBlockedPage($body)) {
                Log::info('Live platform blocked by bot protection', ['url' => $url]);

                return '';
            }

            return $body;
        } catch (\Throwable $e) {
            Log::warning('Live platform fetch error', ['url' => $url, 'error' => $e->getMessage()]);

            return '';
        }
    }

    private function isBlockedPage(string $html): bool
    {
        if ($html === '') {
            return false;
        }

        if (str_contains($html, 'Bot Verification') || str_contains($html, 'Verifying that you are not a robot')) {
            return true;
        }

        if (strlen($html) < 12000 && str_contains($html, 'Just a moment...')) {
            return true;
        }

        return false;
    }
}
