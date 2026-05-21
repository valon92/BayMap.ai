<?php

namespace App\Services\Monetization;

/**
 * Prepares affiliate-ready outbound URLs (MVP: passthrough).
 * Extend with partner IDs when monetization goes live.
 */
class AffiliateLinkService
{
    public function wrap(string $url, string $source, ?string $productId = null): string
    {
        if (! config('powerbook.monetization.affiliate_enabled')) {
            return $url;
        }

        // Future: append affiliate query params per marketplace partner
        return $url;
    }
}
