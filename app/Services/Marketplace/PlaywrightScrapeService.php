<?php

namespace App\Services\Marketplace;

use App\Services\Marketplace\Scrapers\PageQualityGuard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Headless Chromium fetch via Playwright (Node script) for anti-bot marketplaces.
 */
class PlaywrightScrapeService
{
    public function __construct(private PageQualityGuard $pageQuality) {}

    public function isConfigured(): bool
    {
        if (! config('playwright.enabled')) {
            return false;
        }

        $script = (string) config('playwright.script');

        return is_file($script)
            && is_readable($script)
            && $this->resolveNodeBinary() !== null;
    }

    public function shouldPrefer(?string $platformKey): bool
    {
        if ($platformKey === null || $platformKey === '' || ! $this->isConfigured()) {
            return false;
        }

        $key = strtolower($platformKey);
        foreach ((array) config('playwright.prefer_key_patterns', []) as $pattern) {
            $pattern = strtolower(trim((string) $pattern));
            if ($pattern !== '' && str_contains($key, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public function isUsableHtml(string $html, ?string $platformKey = null): bool
    {
        return $html !== ''
            && ! $this->pageQuality->isBlocked($html)
            && ! $this->pageQuality->isLowQuality($html, $platformKey);
    }

    public function fetchHtml(
        string $url,
        ?string $locale = null,
        ?string $referer = null,
        ?string $platformKey = null,
    ): string {
        if (! $this->isConfigured()) {
            return '';
        }

        $timeout = (int) config('playwright.timeout_seconds', 35);
        $payload = [
            'url' => $url,
            'locale' => $locale ?? 'en-US',
            'referer' => $referer ?? '',
            'timeout' => $timeout * 1000,
            'headless' => (bool) config('playwright.headless', true),
            'waitUntil' => (string) config('playwright.wait_until', 'domcontentloaded'),
            'waitSelector' => $this->waitSelectorForPlatform($platformKey),
        ];

        $node = $this->resolveNodeBinary();
        if ($node === null) {
            Log::warning('Playwright skipped — node binary not found', [
                'configured' => (string) config('playwright.node_binary'),
                'hint' => 'Set PLAYWRIGHT_NODE=/opt/homebrew/bin/node in .env',
            ]);

            return '';
        }

        $script = (string) config('playwright.script');

        try {
            $result = Process::timeout($timeout + 10)
                ->input(json_encode($payload, JSON_THROW_ON_ERROR))
                ->run([$node, $script]);

            if (! $result->successful()) {
                Log::warning('Playwright fetch process failed', [
                    'url' => $url,
                    'platform' => $platformKey,
                    'exit_code' => $result->exitCode(),
                    'stderr' => mb_substr($result->errorOutput(), 0, 500),
                ]);

                return '';
            }

            $decoded = json_decode(trim($result->output()), true);
            if (! is_array($decoded) || empty($decoded['ok']) || ! is_string($decoded['html'] ?? null)) {
                Log::warning('Playwright fetch returned invalid payload', [
                    'url' => $url,
                    'platform' => $platformKey,
                    'error' => is_array($decoded) ? ($decoded['error'] ?? 'unknown') : 'invalid_json',
                ]);

                return '';
            }

            $html = $decoded['html'];
            Log::info('Live platform fetched via Playwright', [
                'platform' => $platformKey,
                'url' => $url,
                'bytes' => strlen($html),
            ]);

            return $html;
        } catch (\Throwable $e) {
            Log::warning('Playwright fetch error', [
                'url' => $url,
                'platform' => $platformKey,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    private function waitSelectorForPlatform(?string $platformKey): string
    {
        if ($platformKey === null || $platformKey === '') {
            return (string) config('playwright.default_wait_selector', '');
        }

        $selector = config("live_platforms.playwright_wait_selectors.{$platformKey}");
        if (is_string($selector) && $selector !== '') {
            return $selector;
        }

        if (str_contains($platformKey, 'amazon')) {
            return '[data-component-type="s-search-result"], .s-result-item';
        }

        if (str_contains($platformKey, 'autodoc')) {
            return '.listing-item, .listing-item__name';
        }

        if (str_contains($platformKey, 'mobile_de')) {
            return '[data-testid="result-list-entry"], .cBox-body--resultlist';
        }

        return (string) config('playwright.default_wait_selector', '');
    }

    private function resolveNodeBinary(): ?string
    {
        $candidates = array_values(array_unique(array_filter([
            (string) config('playwright.node_binary', ''),
            (string) env('NODE_BINARY', ''),
            '/opt/homebrew/bin/node',
            '/usr/local/bin/node',
            '/usr/bin/node',
        ])));

        foreach ($candidates as $candidate) {
            if ($candidate === 'node' || $candidate === '') {
                continue;
            }
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        foreach (['node', '/opt/homebrew/bin/node', '/usr/local/bin/node'] as $command) {
            try {
                $probe = Process::timeout(3)->run(['sh', '-lc', 'command -v '.escapeshellarg($command)]);
                $resolved = trim($probe->output());
                if ($probe->successful() && $resolved !== '' && is_executable($resolved)) {
                    return $resolved;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            $probe = Process::timeout(3)->env([
                'PATH' => '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:'.(string) getenv('PATH'),
            ])->run(['sh', '-lc', 'command -v node']);
            $resolved = trim($probe->output());
            if ($probe->successful() && $resolved !== '' && is_executable($resolved)) {
                return $resolved;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
