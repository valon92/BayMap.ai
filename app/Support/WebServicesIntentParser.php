<?php

namespace App\Support;

/**
 * Domain, hosting, email & web infrastructure intent (Albanian + English).
 */
class WebServicesIntentParser
{
    /** @var array<int, string> */
    private const DOMAIN_SIGNALS = [
        'domain', 'domen', 'domenë', 'domena', 'domenen', 'domeni', 'subdomain', 'subdomen',
        'tld', 'dns', 'whois', 'registrar', 'regjistrim domeni', 'blerje domeni',
        'domain name', 'website name', 'site name', 'emer', 'emër', 'emri',
    ];

    /** @var array<int, string> */
    private const HOSTING_SIGNALS = [
        'hosting', 'hostim', 'web hosting', 'wordpress hosting', 'vps', 'server',
        'cloud hosting', 'shared hosting', 'managed hosting', 'web server',
    ];

    /** @var array<int, string> */
    private const EMAIL_SIGNALS = [
        'email', 'e-mail', 'mail', 'mailbox', 'inbox', 'workspace', 'google workspace',
        'microsoft 365', 'office 365', 'zoho mail', 'proton mail', 'business email',
        'postë elektronike', 'poste elektronike', 'email biznesi',
    ];

    /** @var array<int, string> */
    private const SSL_SIGNALS = [
        'ssl', 'tls', 'certificate', 'certifikatë', 'certifikate', 'https', 'wildcard ssl',
    ];

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    public static function requestedTypes(array $parsed): array
    {
        $types = $parsed['web_service_types'] ?? null;
        if (is_array($types) && $types !== []) {
            return array_values(array_unique(array_map('strval', $types)));
        }

        $type = mb_strtolower((string) ($parsed['web_service_type'] ?? $parsed['product_type'] ?? ''));

        if ($type === 'combo' || $type === 'website') {
            return ['domain', 'hosting'];
        }

        if (in_array($type, ['domain', 'hosting', 'email', 'ssl'], true)) {
            return [$type];
        }

        if (! empty($parsed['domain_query']) || ! empty($parsed['domain_name'])) {
            return ['domain'];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function isActive(array $parsed): bool
    {
        if (CategoryCatalog::normalize($parsed['category'] ?? '') !== 'ai_software') {
            return false;
        }

        if (self::requestedTypes($parsed) !== []) {
            return true;
        }

        return ! empty($parsed['domain_query']) || ! empty($parsed['domain_name']);
    }

    public static function isWebServicesQuery(string $query): bool
    {
        $lower = mb_strtolower(trim($query));

        if (self::extractDomain($query) !== null) {
            return true;
        }

        foreach (array_merge(self::DOMAIN_SIGNALS, self::HOSTING_SIGNALS, self::EMAIL_SIGNALS, self::SSL_SIGNALS) as $signal) {
            if (str_contains($lower, mb_strtolower($signal))) {
                return true;
            }
        }

        return (bool) preg_match('/\b[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.(?:bot|com|ai|io|net|org|dev|app|xyz|co|me|info|biz)\b/ui', $lower);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public static function fromQuery(string $query, array $parsed = []): array
    {
        if (! self::isWebServicesQuery($query)) {
            return $parsed;
        }

        $lower = mb_strtolower(trim($query));
        $result = [
            'category' => 'ai_software',
            'search_scope' => 'universal',
            'search_target' => false,
            'location_source' => 'query',
        ];

        $types = [];

        $domain = self::extractDomain($query);
        if ($domain !== null) {
            $result['domain_query'] = $domain;
            $result['domain_name'] = $domain;
            $types[] = 'domain';
        } elseif (self::matchesAny($lower, self::DOMAIN_SIGNALS)) {
            $types[] = 'domain';
        }

        if (self::matchesAny($lower, self::HOSTING_SIGNALS)
            || preg_match('/\bhosting\s+(?:per|për|for|për)\b/ui', $lower)) {
            $types[] = 'hosting';
        }

        if (self::matchesAny($lower, self::EMAIL_SIGNALS)) {
            $types[] = 'email';
        }

        if (self::matchesAny($lower, self::SSL_SIGNALS)) {
            $types[] = 'ssl';
        }

        $types = array_values(array_unique($types));

        if ($types === []) {
            $types = ['domain'];
        }

        if (preg_match('/\b(website|web\s*site|faqe\s*internet|faqe\s*web)\b/ui', $lower)
            && in_array('domain', $types, true)
            && in_array('hosting', $types, true)) {
            $result['web_service_type'] = 'website';
            $result['product_type'] = 'website';
        } elseif (count($types) > 1) {
            $result['web_service_type'] = 'combo';
            $result['product_type'] = 'combo';
        } else {
            $result['web_service_type'] = $types[0];
            $result['product_type'] = $types[0];
        }

        $result['web_service_types'] = $types;

        foreach ($result as $key => $value) {
            if ($value !== null && $value !== '') {
                $parsed[$key] = $value;
            }
        }

        return self::finalize($parsed);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public static function finalize(array $parsed): array
    {
        if (! self::isActive($parsed) && ! self::isWebServicesQuery((string) ($parsed['raw_query'] ?? ''))) {
            return $parsed;
        }

        $parsed['category'] = 'ai_software';
        $parsed['search_scope'] = 'universal';
        $parsed['search_target'] = false;

        unset(
            $parsed['gender'],
            $parsed['year'],
            $parsed['year_min'],
            $parsed['year_max'],
            $parsed['brand'],
            $parsed['model'],
            $parsed['color'],
            $parsed['size'],
            $parsed['max_km'],
            $parsed['features'],
            $parsed['search_countries'],
        );

        return $parsed;
    }

    public static function extractDomain(string $query): ?string
    {
        if (preg_match('/\b(?:domen(?:en|i|a|ë)?|domain|site|website)\s+([a-z0-9](?:[a-z0-9.-]{0,253}[a-z0-9])?)\b/ui', $query, $m)) {
            return self::normalizeDomain($m[1]);
        }

        if (preg_match('/\b([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.(?:bot|com|ai|io|net|org|dev|app|xyz|co|me|info|biz|eu|de|ch|uk))\b/ui', $query, $m)) {
            return self::normalizeDomain($m[1]);
        }

        if (preg_match('/\bemer\s+([a-z0-9][a-z0-9-]{1,62})\b/ui', $query, $m) && ! str_contains($m[1], '.')) {
            return self::normalizeDomain($m[1]);
        }

        return null;
    }

    private static function normalizeDomain(string $domain): string
    {
        return mb_strtolower(trim($domain, " .'\""));
    }

    /**
     * @param  array<int, string>  $needles
     */
    private static function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
