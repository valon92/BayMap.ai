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

        if (preg_match('/\b(?:deri|under|bis|up to)\s*([\d\s.,\']+?)\s*(mij(?:e)?|mil|million|milion|mili|k|tys|tausend|thousand)?\s*(?:euro|eur|ero|€|franga|franc|chf|usd|\$|funta|funt|funte|pound|pounds|gbp|£)\b/ui', $lower, $m)) {
            return self::buildLimit($m[1], $m[2] ?? '', $currency, $m[0]);
        }

        if (preg_match('/\b([\d\s.,\']+)\s*(mij(?:e)?|mil|million|mili|milion)\s*(?:funta|funt|funte|pound|pounds|gbp|£)\b/ui', $lower, $m)) {
            return self::buildLimit($m[1], $m[2], 'GBP', 'pound');
        }

        if (preg_match('/\b(\d+)(milion|million|mili)\s*(?:funta|funt|funte|pound|pounds|gbp|£)/ui', $lower, $m)) {
            return self::buildLimit($m[1], $m[2], 'GBP', 'pound');
        }

        if (preg_match('/(?:qmim\w*|çmim\w*|price|budget)\s+max\s*([\d\s.,\']+?)(?:\s*(mij(?:e)?|mil|k|tys))?\b/ui', $lower, $m)) {
            return self::buildLimit($m[1], $m[2] ?? '', $currency, '');
        }

        if (preg_match('/([\d\s.,\']+)\s*(mij(?:e)?|mil|tys|k)\s*(?:euro|eur|ero|€)\b/ui', $lower, $m)) {
            return self::buildLimit($m[1], $m[2], 'EUR', 'euro');
        }

        if (preg_match('/([\d\s.,\']+)\s*(?:euro|eur|ero|€)\b/ui', $lower, $m)) {
            return self::buildLimit($m[1], '', 'EUR', 'euro');
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
        if (preg_match('/\b(gbp|£|pound|pounds|funta|funt|funte)\b/ui', $lower)) {
            return 'GBP';
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
        $multiplierHint = mb_strtolower(trim($amountRaw.' '.$kSuffix.' '.$token));
        if (self::hasMillionMultiplier($multiplierHint)) {
            $amount *= 1_000_000;
        } elseif (self::hasThousandsMultiplier($multiplierHint) && $amount < 1000) {
            $amount *= 1000;
        } elseif ($kSuffix !== '' || preg_match('/\d\s*k\b/i', $amountRaw)) {
            $amount *= 1000;
        }

        return array_filter([
            'max_price' => $amount,
            'currency' => $currency,
        ]);
    }

    private static function hasThousandsMultiplier(string $text): bool
    {
        return (bool) preg_match('/\b(mij(?:e)?|mil|tys|tsd|tausend|thousand)\b/u', $text);
    }

    private static function hasMillionMultiplier(string $text): bool
    {
        return (bool) preg_match('/\b(milion|million|mili)\b/u', $text);
    }

    private static function normalizeCurrencyToken(string $token): string
    {
        $t = mb_strtolower(trim($token));

        return match (true) {
            str_contains($t, 'franc'),
            str_contains($t, 'fr.'),
            str_contains($t, 'chf'),
            str_contains($t, 'franga') => 'CHF',
            str_contains($t, 'usd'), $t === '$' => 'USD',
            str_contains($t, 'gbp'), str_contains($t, 'pound'), str_contains($t, 'funta'),
            str_contains($t, 'funt'), $t === '£' => 'GBP',
            default => 'EUR',
        };
    }
}
