<?php

namespace App\Services\Marketplace;

use App\Contracts\MarketplaceSearchInterface;
use App\Support\UniversalMarketplaceBridge;
use App\Support\WebServicesBridgeUrls;
use App\Support\WebServicesIntentParser;

/**
 * BuyMap web services bridge — domain registrars, hosting & email platforms.
 */
class WebServicesBridgeService implements MarketplaceSearchInterface
{
    public function getSourceName(): string
    {
        return 'BuyMap Web Services';
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $parsedQuery, array $expandedFilters): array
    {
        $parsedQuery = WebServicesIntentParser::finalize($parsedQuery);

        if (! WebServicesIntentParser::isActive($parsedQuery)) {
            return [];
        }

        $domain = (string) ($parsedQuery['domain_query'] ?? $parsedQuery['domain_name'] ?? '');
        $countryCode = UniversalMarketplaceBridge::resolveCountryCode($parsedQuery, $expandedFilters);
        $currency = UniversalMarketplaceBridge::currencyForCountry($countryCode);

        $items = [];
        foreach (WebServicesBridgeUrls::options($parsedQuery) as $option) {
            $items[] = $this->bridgeCard($option, $countryCode, $currency, $domain);
        }

        usort($items, function ($a, $b) {
            $typeCmp = strcmp(
                (string) ($a['web_service_type'] ?? ''),
                (string) ($b['web_service_type'] ?? ''),
            );
            if ($typeCmp !== 0) {
                return $typeCmp;
            }

            return ($a['provider_rank'] ?? 99) <=> ($b['provider_rank'] ?? 99);
        });

        return $items;
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    private function bridgeCard(
        array $option,
        string $countryCode,
        string $currency,
        string $domain,
    ): array {
        $label = (string) ($option['label'] ?? 'Web service');
        $mode = (string) ($option['web_service_type'] ?? 'domain');
        $rank = (int) ($option['provider_rank'] ?? 99);
        $price = (float) ($option['price'] ?? 0);
        $priceOnRequest = (bool) ($option['price_on_request'] ?? false);

        $subtitle = match (true) {
            $mode === 'domain' && $domain !== '' => $domain,
            default => self::typeLabel($mode),
        };

        return [
            'id' => 'web-bridge-'.md5($label.($option['url'] ?? '')),
            'title' => $label,
            'subtitle' => $subtitle,
            'image' => (string) ($option['logo_url'] ?? ''),
            'logo_url' => (string) ($option['logo_url'] ?? ''),
            'brand_color' => (string) ($option['brand_color'] ?? '#4F46E5'),
            'brand_bg' => (string) ($option['brand_bg'] ?? 'rgba(79, 70, 229, 0.1)'),
            'price' => $price,
            'price_label' => (string) ($option['price_label'] ?? ''),
            'billing_period' => (string) ($option['billing_period'] ?? 'yearly'),
            'price_on_request' => $priceOnRequest,
            'currency' => $currency,
            'location' => 'Global',
            'country_code' => $countryCode,
            'category' => 'ai_software',
            'product_type' => $mode,
            'web_service_type' => $mode,
            'domain_name' => $domain !== '' ? $domain : null,
            'provider_rank' => $rank,
            'match_score' => max(50, 100 - $rank),
            'condition' => 'new',
            'url' => (string) ($option['url'] ?? '#'),
            'source' => $label,
            'source_key' => (string) ($option['source_key'] ?? 'web_services_bridge'),
            'affiliate_ready' => true,
            'sponsored' => false,
            'tags' => [$mode, 'web', 'bridge', 'live'],
            'live' => true,
        ];
    }

    private static function typeLabel(string $mode): string
    {
        return match ($mode) {
            'hosting' => 'Web hosting',
            'email' => 'Business email',
            'ssl' => 'SSL certificate',
            'website' => 'Website & domain',
            default => 'Domain name',
        };
    }
}
