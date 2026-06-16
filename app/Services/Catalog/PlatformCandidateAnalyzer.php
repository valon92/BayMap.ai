<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Category;
use App\Models\Catalog\Country;
use App\Models\Catalog\CountryCatalogStat;
use App\Models\Catalog\Platform;
use App\Models\Catalog\PlatformCandidate;
use App\Support\CategoryCatalog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Technical analysis of a marketplace candidate before it enters live routing.
 */
class PlatformCandidateAnalyzer
{
    private const TIMEOUT = 15;

    /**
     * @return array<string, mixed>
     */
    public function analyze(PlatformCandidate $candidate): array
    {
        $candidate->update(['status' => PlatformCandidate::STATUS_ANALYZING]);

        $url = $candidate->base_url;
        $analysis = [
            'url' => $url,
            'reachable' => false,
            'http_status' => null,
            'ssl' => str_starts_with($url, 'https'),
            'adapter_signals' => [],
            'category_signals' => [],
            'warnings' => [],
            'checked_at' => now()->toIso8601String(),
        ];

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['User-Agent' => 'BuyMap-CatalogBot/1.0'])
                ->get($url);

            $analysis['reachable'] = $response->successful();
            $analysis['http_status'] = $response->status();
            $html = (string) $response->body();
            $lower = mb_strtolower($html);

            $analysis['adapter_signals'] = $this->detectAdapterSignals($lower);
            $analysis['category_signals'] = $this->detectCategorySignals($lower);
            $analysis['has_product_listings'] = $this->hasListingSignals($lower);
            $analysis['title'] = $this->extractTitle($html);

            if (! $analysis['has_product_listings']) {
                $analysis['warnings'][] = 'No obvious product listing patterns detected on homepage.';
            }
        } catch (\Throwable $e) {
            $analysis['warnings'][] = 'Fetch failed: '.$e->getMessage();
        }

        $adapterGuess = $this->guessAdapter($analysis['adapter_signals']);
        $categorySlug = $this->guessPrimaryCategory($analysis['category_signals']);

        $candidate->update([
            'status' => PlatformCandidate::STATUS_NEEDS_REVIEW,
            'adapter_guess' => $adapterGuess,
            'analysis' => $analysis,
            'analyzed_at' => now(),
            'category_id' => $categorySlug
                ? Category::query()->where('slug', $categorySlug)->value('id')
                : $candidate->category_id,
            'trust_estimate' => $this->estimateTrust($analysis),
        ]);

        app(CountryCatalogStatsService::class)->refreshForCountry($candidate->country_id);

        return $analysis;
    }

    /**
     * @return array<int, string>
     */
    private function detectAdapterSignals(string $html): array
    {
        $signals = [];

        $map = [
            'woocommerce' => ['woocommerce', 'wp-content/plugins/woocommerce', 'class="woocommerce'],
            'cscart' => ['cs-cart', 'cscart', 'dispatch=products'],
            'shopify' => ['cdn.shopify.com', 'shopify-section'],
            'automotive' => ['row-listing', 'autoscout', 'mobile.de', 'vetura', 'makina'],
            'gy_digital' => ['gy-digital', 'jumbo-ks'],
        ];

        foreach ($map as $adapter => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($html, $needle)) {
                    $signals[] = $adapter;
                    break;
                }
            }
        }

        return array_values(array_unique($signals));
    }

    /**
     * @return array<int, string>
     */
    private function detectCategorySignals(string $html): array
    {
        $signals = [];
        $map = [
            'automotive' => ['vetur', 'makina', 'auto', 'car listing', 'mileage', 'km'],
            'fashion' => ['fashion', 'veshj', 'dress', 'shoes', 'nike', 'adidas'],
            'electronics_tech' => ['laptop', 'phone', 'iphone', 'elektronik', 'mediamarkt'],
            'gaming_entertainment' => ['lodr', 'toy', 'game', 'console'],
            'real_estate' => ['apartment', 'banes', 'real estate', 'immobilien'],
        ];

        foreach ($map as $category => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($html, $needle)) {
                    $signals[] = $category;
                    break;
                }
            }
        }

        return array_values(array_unique($signals));
    }

    private function hasListingSignals(string $html): bool
    {
        return (bool) preg_match(
            '/product|listing|price|€|\bcart\b|shop|shpallje|vetura|artikel|article/i',
            $html,
        );
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
            return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return null;
    }

    /**
     * @param  array<int, string>  $signals
     */
    private function guessAdapter(array $signals): string
    {
        foreach (['woocommerce', 'cscart', 'shopify', 'automotive', 'gy_digital'] as $adapter) {
            if (in_array($adapter, $signals, true)) {
                return $adapter;
            }
        }

        return 'generic';
    }

    /**
     * @param  array<int, string>  $signals
     */
    private function guessPrimaryCategory(array $signals): ?string
    {
        foreach ($signals as $signal) {
            $normalized = CategoryCatalog::normalize($signal);
            if ($normalized !== 'marketplace') {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function estimateTrust(array $analysis): int
    {
        $score = 40;
        if ($analysis['reachable'] ?? false) {
            $score += 25;
        }
        if ($analysis['ssl'] ?? false) {
            $score += 10;
        }
        if ($analysis['has_product_listings'] ?? false) {
            $score += 15;
        }
        if (($analysis['adapter_signals'] ?? []) !== []) {
            $score += 10;
        }

        return min(100, $score);
    }
}
