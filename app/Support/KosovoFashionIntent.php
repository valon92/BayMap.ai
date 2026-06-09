<?php

namespace App\Support;

/**
 * Detects Kosovo fashion catalog searches (brand + gender) that need live retailer scraping.
 */
class KosovoFashionIntent
{
    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function isBrandedCatalogSearch(array $parsed): bool
    {
        $category = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');
        if (! in_array($category, ['fashion', 'sports_outdoor'], true)) {
            return false;
        }

        if (! empty($parsed['brand'])) {
            return true;
        }

        $gender = mb_strtolower((string) ($parsed['gender'] ?? ''));
        if (in_array($gender, ['male', 'men', 'meshkuj'], true)) {
            return true;
        }

        $query = mb_strtolower((string) ($parsed['raw_query'] ?? ''));

        return (bool) preg_match(
            '/\b(puma|nike|adidas|reebok|meshkuj|patika|atlete|per meshkuj|këpucë|kepuce)\b/u',
            $query
        );
    }
}
