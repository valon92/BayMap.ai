<?php

namespace App\Support;

/**
 * Extracts max price and currency from natural-language shopping queries.
 */
class PriceIntentParser
{
    /**
     * @return array{max_price?: int, currency?: string}
     */
    public static function fromQuery(string $query): array
    {
        $lower = mb_strtolower($query);
        $currency = self::detectCurrency($lower);

        if (preg_match('/(?:qmim\s+deri|\bderi\b|\bunder\b|\bbis\b|\bmax\b|\bup to\b)\s*([\d\s.,\']+)\s*(k)?\s*(franga|franc|chf|eur|euro|€|\$|usd|lek)?/ui', $lower, $m)) {
            return self::buildLimit($m[1], $m[2] ?? '', $currency, $m[3] ?? '');
        }

        if (preg_match('/([\d\s.,\']+)\s*(franga|franc|chf|eur|euro|€|\$|usd)\b/ui', $lower, $m)) {
            return self::buildLimit($m[1], '', self::normalizeCurrencyToken($m[2]), $m[2]);
        }

        if (preg_match('/under\s+€?\s*([\d\s.,\']+)/i', $query, $m)) {
            return self::buildLimit($m[1], '', 'EUR', 'eur');
        }

        return [];
    }

    private static function detectCurrency(string $lower): string
    {
        if (preg_match('/\b(franga|franc|chf|swiss franc|fr\.?)\b/ui', $lower)) {
            return 'CHF';
        }
        if (preg_match('/\b(eur|euro|€)\b/ui', $lower)) {
            return 'EUR';
        }
        if (preg_match('/\b(usd|\$|dollar)\b/ui', $lower)) {
            return 'USD';
        }

        return 'EUR';
    }

    /**
     * @return array{max_price?: int, currency?: string}
     */
    private static function buildLimit(string $amountRaw, string $kSuffix, string $currency, string $token): array
    {
        if ($token !== '') {
            $currency = self::normalizeCurrencyToken($token);
        }

        $digits = preg_replace('/[^\d]/', '', $amountRaw);
        if ($digits === '') {
            return [];
        }

        $amount = (int) $digits;
        if ($kSuffix !== '' || preg_match('/\d\s*k\b/i', $amountRaw)) {
            $amount *= 1000;
        }

        return array_filter([
            'max_price' => $amount,
            'currency' => $currency,
        ]);
    }

    private static function normalizeCurrencyToken(string $token): string
    {
        $t = mb_strtolower(trim($token));

        return match (true) {
            str_contains($t, 'franc'), $t === 'chf', str_contains($t, 'franga') => 'CHF',
            str_contains($t, 'usd'), $t === '$' => 'USD',
            default => 'EUR',
        };
    }
}
