<?php

namespace App\Support;

/**
 * Normalizes and compares EU / US shoe sizes for filters and ranking.
 */
class ShoeSize
{
    public static function normalize(?string $size): ?string
    {
        if ($size === null || $size === '') {
            return null;
        }

        $clean = str_replace(',', '.', trim($size));
        if (preg_match('/(\d{2}(?:\.\d)?)/', $clean, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function equals(?string $a, ?string $b): bool
    {
        $na = self::normalize($a);
        $nb = self::normalize($b);
        if ($na === null || $nb === null) {
            return false;
        }

        return abs((float) $na - (float) $nb) < 0.01;
    }

    /**
     * @param  array<int, string|float|int>  $available
     */
    public static function productHasSize(array $product, ?string $requested): bool
    {
        $requested = self::normalize($requested);
        if ($requested === null) {
            return true;
        }

        foreach ($product['sizes'] ?? [] as $size) {
            if (self::equals((string) $size, $requested)) {
                return true;
            }
        }

        $title = mb_strtolower($product['title'] ?? '');
        $needle = str_replace('.', ',', $requested);
        if (str_contains($title, $requested) || str_contains($title, $needle)) {
            return true;
        }

        return false;
    }

    public static function extractFromText(string $text): ?string
    {
        if (preg_match('/\b(?:size|nr|numri|numer|madh[eë]sia|number)\s*[:#]?\s*(\d{2}(?:[.,]\d)?)\b/ui', $text, $m)) {
            return self::normalize($m[1]);
        }
        if (preg_match('/\b(\d{2})[.,](\d)\b/u', $text, $m)) {
            return self::normalize($m[1].'.'.$m[2]);
        }
        if (preg_match('/\b(\d{2}(?:\.\d)?)\s*(?:eu|eur|eu\b)/ui', $text, $m)) {
            return self::normalize($m[1]);
        }

        return null;
    }
}
