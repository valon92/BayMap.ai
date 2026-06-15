<?php

namespace App\Support;

/**
 * Deep-link builders for domain registrars, hosting & business email (BuyMap bridge).
 */
class WebServicesBridgeUrls
{
    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, array<string, mixed>>
     */
    public static function options(array $parsed): array
    {
        return WebServicesProviderCatalog::optionsForParsed($parsed);
    }
}
