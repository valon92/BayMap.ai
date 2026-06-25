<?php

namespace App\Services\Marketplace\Scrapers;

/**
 * Detect bot walls and useless HTML shells (no product listings).
 */
class PageQualityGuard
{
    public function isBlocked(string $html): bool
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

        $lower = mb_strtolower($html);
        if (str_contains($lower, 'zugriff verweigert') || str_contains($lower, 'access denied')) {
            return true;
        }

        if (str_contains($lower, 'datadome captcha') || str_contains($lower, 'captcha-delivery.com')) {
            return true;
        }

        if ($this->looksLikeBotChallengeWithoutListings($html)) {
            return true;
        }

        return false;
    }

    public function isLowQuality(string $html, ?string $platformKey = null): bool
    {
        if ($html === '' || $this->isBlocked($html)) {
            return true;
        }

        if (str_contains($html, 'errorpage-noPageTypeDefined') || str_contains($html, '"pageType":"errorpage"')) {
            return true;
        }

        if (strlen($html) < 2500) {
            return true;
        }

        if ($platformKey === null || $platformKey === '') {
            return false;
        }

        return $this->expectsListings($platformKey) && ! $this->hasListingMarkers($html, $platformKey);
    }

    public function expectsListings(string $platformKey): bool
    {
        $key = strtolower($platformKey);

        return str_contains($key, 'amazon')
            || str_contains($key, 'autodoc')
            || str_contains($key, 'kfzteile')
            || str_contains($key, 'mister_auto')
            || str_contains($key, 'oscaro')
            || str_contains($key, 'mobile_de')
            || str_contains($key, 'heycar')
            || str_contains($key, 'autoscout')
            || str_contains($key, 'ebay')
            || str_contains($key, 'pro4matic');
    }

    public function hasListingMarkers(string $html, string $platformKey): bool
    {
        $key = strtolower($platformKey);

        if (str_contains($key, 'amazon')) {
            return str_contains($html, 'data-component-type="s-search-result"')
                || str_contains($html, 's-result-item');
        }

        if (str_contains($key, 'autodoc')) {
            return str_contains($html, 'listing-item__name');
        }

        if (str_contains($key, 'kfzteile')) {
            return str_contains($html, 'articleSearch') || str_contains($html, 'articleRow');
        }

        if (str_contains($key, 'mister_auto') || str_contains($key, 'oscaro')) {
            return preg_match('/product|article|listing-item|search-result/i', $html) === 1;
        }

        if (str_contains($key, 'mobile_de') || str_contains($key, 'heycar') || str_contains($key, 'autoscout')) {
            return preg_match('/result-list|listing-item|vehicle/i', $html) === 1;
        }

        return str_contains($html, 'listing-item__name')
            || str_contains($html, 'data-product-id')
            || str_contains($html, 's-result-item')
            || str_contains($html, 'type="application/ld+json"');
    }

    private function looksLikeBotChallengeWithoutListings(string $html): bool
    {
        if ($this->hasListingMarkers($html, 'generic')) {
            return false;
        }

        $lower = mb_strtolower($html);

        return str_contains($lower, 'cf-browser-verification')
            || str_contains($lower, 'challenge-platform')
            || str_contains($lower, 'hcaptcha')
            || preg_match('/\bcaptcha\b/i', $html) === 1
            || (str_contains($lower, 'robot') && str_contains($lower, 'verify'));
    }
}
