<?php

namespace App\Services\Marketplace\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScraperHttpClient
{
    public function get(string $url, ?string $locale = null): string
    {
        $acceptLanguage = match ($locale) {
            'de-DE' => 'de-DE,de;q=0.9,en;q=0.8',
            'de-CH' => 'de-CH,de;q=0.9,en;q=0.8',
            'nl-NL' => 'nl-NL,nl;q=0.9,en;q=0.8',
            'en-GB' => 'en-GB,en;q=0.9',
            default => 'en-US,en;q=0.9',
        };

        try {
            $response = Http::timeout((int) config('live_platforms.timeout_seconds', 60))
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 BuyMap-ValonWorker/2.1',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => $acceptLanguage,
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Live platform fetch failed', ['url' => $url, 'status' => $response->status()]);

                return '';
            }

            return (string) $response->body();
        } catch (\Throwable $e) {
            Log::warning('Live platform fetch error', ['url' => $url, 'error' => $e->getMessage()]);

            return '';
        }
    }
}
