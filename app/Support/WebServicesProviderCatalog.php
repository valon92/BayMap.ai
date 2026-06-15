<?php

namespace App\Support;

/**
 * Domain registrars, hosting & email providers — ranked by market popularity.
 */
class WebServicesProviderCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function domainProviders(): array
    {
        return [
            self::domain(1, 'GoDaddy', 'godaddy', 'godaddy.com', '#00A4A6', ['ai' => 69.99, 'com' => 11.99, 'default' => 14.99],
                fn (string $d) => 'https://www.godaddy.com/domainsearch/find?domainToCheck='.rawurlencode($d)),
            self::domain(2, 'Namecheap', 'namecheap', 'namecheap.com', '#DE3723', ['ai' => 59.99, 'com' => 9.98, 'default' => 12.99],
                fn (string $d) => 'https://www.namecheap.com/domains/registration/results/?domain='.rawurlencode($d)),
            self::domain(3, 'Cloudflare Registrar', 'cloudflare', 'cloudflare.com', '#F38020', ['ai' => 60.00, 'com' => 9.77, 'default' => 10.46],
                fn (string $d) => 'https://domains.cloudflare.com/?domain='.rawurlencode($d)),
            self::domain(4, 'Hostinger', 'hostinger', 'hostinger.com', '#673DE6', ['ai' => 54.99, 'com' => 9.99, 'default' => 11.99],
                fn (string $d) => 'https://www.hostinger.com/domain-name-search?domain='.rawurlencode($d)),
            self::domain(5, 'Squarespace Domains', 'squarespace', 'squarespace.com', '#000000', ['ai' => 65.00, 'com' => 12.00, 'default' => 15.00],
                fn (string $d) => 'https://domains.squarespace.com/domain-search?query='.rawurlencode($d)),
            self::domain(6, 'IONOS', 'ionos', 'ionos.com', '#003D8F', ['ai' => 62.00, 'com' => 1.00, 'default' => 12.00],
                fn (string $d) => 'https://www.ionos.com/domains/domain-search?domain='.rawurlencode($d)),
            self::domain(7, 'Porkbun', 'porkbun', 'porkbun.com', '#F2762E', ['ai' => 55.12, 'com' => 9.68, 'default' => 10.99],
                fn (string $d) => 'https://porkbun.com/checkout/search?q='.rawurlencode($d)),
            self::domain(8, 'Dynadot', 'dynadot', 'dynadot.com', '#1A6BFF', ['ai' => 58.00, 'com' => 9.99, 'default' => 11.50],
                fn (string $d) => 'https://www.dynadot.com/domain/search?domain='.rawurlencode($d)),
            self::domain(9, 'Name.com', 'namecom', 'name.com', '#E85D25', ['ai' => 64.99, 'com' => 12.99, 'default' => 14.99],
                fn (string $d) => 'https://www.name.com/domain/search/'.rawurlencode($d)),
            self::domain(10, 'Hover', 'hover', 'hover.com', '#2D9CDB', ['ai' => 79.99, 'com' => 15.99, 'default' => 17.99],
                fn (string $d) => 'https://www.hover.com/domains/results?q='.rawurlencode($d)),
            self::domain(11, 'OVHcloud', 'ovh', 'ovh.com', '#123F6D', ['ai' => 55.00, 'com' => 8.99, 'default' => 10.99],
                fn (string $d) => 'https://www.ovhcloud.com/en/domains/tld/'.rawurlencode(self::tld($d) ?: 'com').'/'),
            self::domain(12, 'Domain.com', 'domaincom', 'domain.com', '#0066CC', ['ai' => 59.99, 'com' => 9.99, 'default' => 13.99],
                fn (string $d) => 'https://www.domain.com/registration/?flow=domain&search='.rawurlencode($d)),
            self::domain(13, 'Gandi', 'gandi', 'gandi.net', '#7C3AED', ['ai' => 72.00, 'com' => 15.50, 'default' => 16.50],
                fn (string $d) => 'https://shop.gandi.net/en/domain/suggest?search='.rawurlencode($d)),
            self::domain(14, 'DreamHost', 'dreamhost', 'dreamhost.com', '#0073EC', ['ai' => 59.95, 'com' => 9.99, 'default' => 12.95],
                fn (string $d) => 'https://www.dreamhost.com/domains/search/?domain='.rawurlencode($d)),
            self::domain(15, 'Network Solutions', 'networksolutions', 'networksolutions.com', '#00529B', ['ai' => 69.99, 'com' => 14.99, 'default' => 16.99],
                fn (string $d) => 'https://www.networksolutions.com/domain-name-registration/domain-search.jsp?domain='.rawurlencode($d)),
            self::domain(16, 'Register.com', 'registercom', 'register.com', '#C41230', ['ai' => 64.99, 'com' => 12.99, 'default' => 15.99],
                fn (string $d) => 'https://www.register.com/domains/domain-search-results?domain='.rawurlencode($d)),
            self::domain(17, 'Atom.com', 'atom', 'atom.com', '#6C5CE7', ['ai' => 0, 'com' => 0, 'default' => 0],
                fn (string $d) => $d !== ''
                    ? 'https://www.atom.com/name/'.rawurlencode($d)
                    : 'https://www.atom.com/domain-search'),
            self::domain(18, 'Sedo Marketplace', 'sedo', 'sedo.com', '#E35205', ['ai' => 0, 'com' => 0, 'default' => 0],
                fn (string $d) => $d !== ''
                    ? 'https://sedo.com/search/?keyword='.rawurlencode($d)
                    : 'https://sedo.com/buy-domains/'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function hostingProviders(): array
    {
        return [
            self::hosting(1, 'Hostinger', 'hostinger_hosting', 'hostinger.com', '#673DE6', 2.99, 'https://www.hostinger.com/web-hosting'),
            self::hosting(2, 'Bluehost', 'bluehost', 'bluehost.com', '#196BDE', 3.95, 'https://www.bluehost.com/hosting'),
            self::hosting(3, 'SiteGround', 'siteground', 'siteground.com', '#449F0A', 3.99, 'https://www.siteground.com/web-hosting.htm'),
            self::hosting(4, 'GoDaddy Hosting', 'godaddy_hosting', 'godaddy.com', '#00A4A6', 4.99, 'https://www.godaddy.com/hosting/web-hosting'),
            self::hosting(5, 'IONOS Hosting', 'ionos_hosting', 'ionos.com', '#003D8F', 1.00, 'https://www.ionos.com/hosting'),
            self::hosting(6, 'Cloudflare', 'cloudflare_hosting', 'cloudflare.com', '#F38020', 0.00, 'https://www.cloudflare.com/plans/'),
            self::hosting(7, 'DigitalOcean', 'digitalocean', 'digitalocean.com', '#0080FF', 4.00, 'https://www.digitalocean.com/pricing'),
            self::hosting(8, 'AWS Lightsail', 'aws_lightsail', 'aws.amazon.com', '#FF9900', 3.50, 'https://aws.amazon.com/lightsail/pricing/'),
            self::hosting(9, 'Hetzner', 'hetzner', 'hetzner.com', '#D50C2D', 4.15, 'https://www.hetzner.com/cloud'),
            self::hosting(10, 'OVHcloud', 'ovh_hosting', 'ovh.com', '#123F6D', 3.50, 'https://www.ovhcloud.com/en/web-hosting/'),
            self::hosting(11, 'Vultr', 'vultr', 'vultr.com', '#007BFC', 2.50, 'https://www.vultr.com/pricing/'),
            self::hosting(12, 'A2 Hosting', 'a2hosting', 'a2hosting.com', '#F89C1B', 2.99, 'https://www.a2hosting.com/web-hosting'),
            self::hosting(13, 'DreamHost', 'dreamhost_hosting', 'dreamhost.com', '#0073EC', 2.59, 'https://www.dreamhost.com/hosting/'),
            self::hosting(14, 'InMotion Hosting', 'inmotion', 'inmotionhosting.com', '#E31837', 2.99, 'https://www.inmotionhosting.com/web-hosting'),
            self::hosting(15, 'WP Engine', 'wpengine', 'wpengine.com', '#0ECAD4', 20.00, 'https://wpengine.com/plans/'),
            self::hosting(16, 'Kinsta', 'kinsta', 'kinsta.com', '#5333ED', 35.00, 'https://kinsta.com/pricing/'),
            self::hosting(17, 'ScalaHosting', 'scalahosting', 'scalahosting.com', '#FF6B00', 3.95, 'https://www.scalahosting.com/web-hosting.html'),
            self::hosting(18, 'GreenGeeks', 'greengeeks', 'greengeeks.com', '#3D8C40', 2.95, 'https://www.greengeeks.com/web-hosting'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function emailProviders(): array
    {
        return [
            self::email(1, 'Google Workspace', 'google_workspace', 'google.com', '#4285F4', 5.75, 'https://workspace.google.com/'),
            self::email(2, 'Microsoft 365', 'microsoft_365', 'microsoft.com', '#0078D4', 5.60, 'https://www.microsoft.com/microsoft-365/business'),
            self::email(3, 'Zoho Mail', 'zoho_mail', 'zoho.com', '#E42527', 1.00, 'https://www.zoho.com/mail/'),
            self::email(4, 'Proton Mail Business', 'proton_mail', 'proton.me', '#6D4AFF', 6.99, 'https://proton.me/business'),
            self::email(5, 'IONOS Email', 'ionos_email', 'ionos.com', '#003D8F', 1.00, 'https://www.ionos.com/office-solutions'),
            self::email(6, 'Namecheap Email', 'namecheap_email', 'namecheap.com', '#DE3723', 1.24, 'https://www.namecheap.com/hosting/email/'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function sslProviders(): array
    {
        return [
            self::ssl(1, 'Cloudflare SSL', 'cloudflare_ssl', 'cloudflare.com', '#F38020', 0.00, 'https://www.cloudflare.com/ssl/'),
            self::ssl(2, 'Namecheap SSL', 'namecheap_ssl', 'namecheap.com', '#DE3723', 5.99, 'https://www.namecheap.com/security/ssl-certificates/'),
            self::ssl(3, 'GoDaddy SSL', 'godaddy_ssl', 'godaddy.com', '#00A4A6', 69.99, 'https://www.godaddy.com/web-security/ssl-certificate'),
            self::ssl(4, "Let's Encrypt", 'letsencrypt', 'letsencrypt.org', '#003A70', 0.00, 'https://letsencrypt.org/getting-started/'),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, array<string, mixed>>
     */
    public static function optionsForParsed(array $parsed): array
    {
        $domain = (string) ($parsed['domain_query'] ?? $parsed['domain_name'] ?? '');
        $types = WebServicesIntentParser::requestedTypes($parsed);

        if ($types === []) {
            $type = mb_strtolower((string) ($parsed['web_service_type'] ?? $parsed['product_type'] ?? 'domain'));
            $types = $type === 'combo' || $type === 'website' ? ['domain', 'hosting'] : [$type];
        }

        $merged = [];
        $seen = [];

        foreach ($types as $type) {
            foreach (self::buildOptions($type, $domain) as $option) {
                $key = strtolower((string) ($option['source_key'] ?? ''));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $merged[] = $option;
            }
        }

        return $merged;
    }

    /**
     * @return array<int, string>
     */
    public static function providerFilterOptions(): array
    {
        $keys = [];
        foreach (array_merge(self::domainProviders(), self::hostingProviders()) as $provider) {
            $keys[] = (string) ($provider['source_key'] ?? '');
        }

        return array_values(array_unique(array_filter($keys)));
    }

    public static function logoUrl(string $host): string
    {
        $host = preg_replace('#^https?://#', '', trim($host));
        $host = explode('/', (string) $host)[0];

        return 'https://www.google.com/s2/favicons?domain='.rawurlencode($host).'&sz=128';
    }

    /**
     * @param  array<string, mixed>  $provider
     * @return array{price: float, price_label: string, billing_period: string, price_on_request: bool}
     */
    public static function priceMeta(string $type, array $provider, string $domain): array
    {
        $type = mb_strtolower($type);

        if ($type === 'domain') {
            $tld = self::tld($domain) ?: 'com';
            $prices = $provider['domain_prices'] ?? [];
            $price = (float) ($prices[$tld] ?? $prices['default'] ?? 0);

            if ($price <= 0) {
                return [
                    'price' => 0.0,
                    'price_label' => 'marketplace',
                    'billing_period' => 'yearly',
                    'price_on_request' => true,
                ];
            }

            return [
                'price' => $price,
                'price_label' => 'from €'.number_format($price, 2, '.', '').'/yr',
                'billing_period' => 'yearly',
                'price_on_request' => false,
            ];
        }

        $monthly = (float) ($provider['monthly_from'] ?? 0);

        if ($monthly <= 0 && $type === 'ssl') {
            return [
                'price' => 0.0,
                'price_label' => 'free',
                'billing_period' => 'yearly',
                'price_on_request' => false,
            ];
        }

        if ($monthly <= 0) {
            return [
                'price' => 0.0,
                'price_label' => 'free tier',
                'billing_period' => 'monthly',
                'price_on_request' => false,
            ];
        }

        return [
            'price' => $monthly,
            'price_label' => 'from €'.number_format($monthly, 2, '.', '').'/mo',
            'billing_period' => 'monthly',
            'price_on_request' => false,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function buildOptions(string $type, string $domain): array
    {
        $providers = match (mb_strtolower($type)) {
            'hosting' => self::hostingProviders(),
            'email' => self::emailProviders(),
            'ssl' => self::sslProviders(),
            default => self::domainProviders(),
        };

        $options = [];

        foreach ($providers as $provider) {
            $url = $provider['url'] ?? '#';
            if (is_callable($url)) {
                $url = $url($domain);
            }

            $priceMeta = self::priceMeta($type, $provider, $domain);

            $options[] = [
                'web_service_type' => mb_strtolower($type),
                'label' => (string) ($provider['label'] ?? ''),
                'url' => (string) $url,
                'source_key' => (string) ($provider['source_key'] ?? ''),
                'provider_rank' => (int) ($provider['rank'] ?? 99),
                'logo_url' => self::logoUrl((string) ($provider['host'] ?? '')),
                'brand_color' => (string) ($provider['color'] ?? '#4F46E5'),
                'brand_bg' => self::brandBg((string) ($provider['color'] ?? '#4F46E5')),
                ...$priceMeta,
            ];
        }

        usort($options, fn ($a, $b) => ($a['provider_rank'] ?? 99) <=> ($b['provider_rank'] ?? 99));

        return $options;
    }

  /**
     * @param  array<string, float>  $prices
     * @param  callable(string): string  $urlBuilder
     * @return array<string, mixed>
     */
    private static function domain(
        int $rank,
        string $label,
        string $sourceKey,
        string $host,
        string $color,
        array $prices,
        callable $urlBuilder,
    ): array {
        return [
            'rank' => $rank,
            'label' => $label,
            'source_key' => $sourceKey,
            'host' => $host,
            'color' => $color,
            'domain_prices' => $prices,
            'url' => $urlBuilder,
        ];
    }

    private static function hosting(int $rank, string $label, string $sourceKey, string $host, string $color, float $monthly, string $url): array
    {
        return [
            'rank' => $rank,
            'label' => $label,
            'source_key' => $sourceKey,
            'host' => $host,
            'color' => $color,
            'monthly_from' => $monthly,
            'url' => $url,
        ];
    }

    private static function email(int $rank, string $label, string $sourceKey, string $host, string $color, float $monthly, string $url): array
    {
        return self::hosting($rank, $label, $sourceKey, $host, $color, $monthly, $url);
    }

    private static function ssl(int $rank, string $label, string $sourceKey, string $host, string $color, float $yearly, string $url): array
    {
        return [
            'rank' => $rank,
            'label' => $label,
            'source_key' => $sourceKey,
            'host' => $host,
            'color' => $color,
            'monthly_from' => $yearly,
            'url' => $url,
        ];
    }

    private static function tld(string $domain): string
    {
        $domain = trim(mb_strtolower($domain));
        $dot = mb_strrpos($domain, '.');

        return $dot === false ? '' : mb_substr($domain, $dot + 1);
    }

    private static function brandBg(string $color): string
    {
        $color = ltrim($color, '#');
        if (strlen($color) !== 6) {
            return 'rgba(79, 70, 229, 0.1)';
        }

        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));

        return "rgba({$r}, {$g}, {$b}, 0.12)";
    }
}
