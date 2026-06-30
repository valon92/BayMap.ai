<?php

namespace App\Services\Marketplace\Scrapers;

use App\Services\Marketplace\BrowseAiScrapeService;
use App\Services\Marketplace\PlaywrightScrapeService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScraperHttpClient
{
    public function __construct(
        private BrowseAiScrapeService $browseAi,
        private PlaywrightScrapeService $playwright,
        private PageQualityGuard $pageQuality,
    ) {}

    public function get(
        string $url,
        ?string $locale = null,
        ?string $referer = null,
        ?string $platformKey = null,
        ?int $timeoutSeconds = null,
    ): string {
        $preferPlaywright = $this->playwright->shouldPrefer($platformKey);

        if ($preferPlaywright) {
            $viaPlaywright = $this->fetchViaPlaywright($url, $locale, $referer, $platformKey);
            if ($viaPlaywright !== '') {
                return $viaPlaywright;
            }
        }

        $html = $this->fetchDirect($url, $locale, $referer, $timeoutSeconds);
        if ($this->playwright->isUsableHtml($html, $platformKey)) {
            return $html;
        }

        if (! $preferPlaywright) {
            $viaPlaywright = $this->fetchViaPlaywright($url, $locale, $referer, $platformKey);
            if ($viaPlaywright !== '') {
                return $viaPlaywright;
            }
        }

        if ($platformKey !== null && $this->browseAi->shouldUse($platformKey)) {
            $viaBrowse = $this->browseAi->fetchHtml($platformKey, $url);
            if ($viaBrowse !== '' && $this->playwright->isUsableHtml($viaBrowse, $platformKey)) {
                Log::info('Live platform fetched via Browse AI', [
                    'platform' => $platformKey,
                    'url' => $url,
                    'bytes' => strlen($viaBrowse),
                ]);

                return $viaBrowse;
            }
        }

        return $html !== '' && ! $this->pageQuality->isBlocked($html) ? $html : '';
    }

    private function fetchViaPlaywright(
        string $url,
        ?string $locale,
        ?string $referer,
        ?string $platformKey,
    ): string {
        if (! $this->playwright->isConfigured()) {
            return '';
        }

        $html = $this->playwright->fetchHtml($url, $locale, $referer, $platformKey);
        if ($this->playwright->isUsableHtml($html, $platformKey)) {
            return $html;
        }

        return '';
    }

    private function fetchDirect(string $url, ?string $locale, ?string $referer, ?int $timeoutSeconds = null): string
    {
        $timeoutSeconds ??= (int) config('live_platforms.listing_timeout_seconds', 18);
        $timeoutSeconds = min(
            $timeoutSeconds,
            (int) config('valon.live_platform_timeout_seconds', 18)
        );
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
            $response = Http::timeout($timeoutSeconds)
                ->connectTimeout(min(10, $timeoutSeconds))
                ->withHeaders($headers)
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Live platform fetch failed', ['url' => $url, 'status' => $response->status()]);

                return '';
            }

            $body = (string) $response->body();
            if ($this->pageQuality->isBlocked($body)) {
                Log::info('Live platform blocked by bot protection', ['url' => $url]);

                return '';
            }

            return $body;
        } catch (\Throwable $e) {
            Log::warning('Live platform fetch error', ['url' => $url, 'error' => $e->getMessage()]);

            return '';
        }
    }
}
