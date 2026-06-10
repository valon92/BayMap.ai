<?php

namespace App\Support;

/**
 * Detects Kosovo fashion catalog searches (brand + gender) that need live retailer scraping.
 */
class KosovoFashionIntent
{
    /**
     * Fashion / veshje in Kosovo — fan out to all KosovoFashionPlatforms workers.
     *
     * @param  array<string, mixed>  $parsed
     */
    public static function isKosovoFashionSearch(array $parsed): bool
    {
        $category = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');

        return in_array($category, ['fashion', 'sports_outdoor'], true);
    }

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

        $productType = mb_strtolower((string) ($parsed['product_type'] ?? ''));
        if (self::isFootwearType($productType)) {
            if (! empty($parsed['size']) || ! empty($parsed['vision'])) {
                return true;
            }
        }

        $gender = mb_strtolower((string) ($parsed['gender'] ?? ''));
        if (in_array($gender, ['male', 'men', 'meshkuj'], true)) {
            return true;
        }

        $query = mb_strtolower((string) ($parsed['raw_query'] ?? $parsed['search_query'] ?? ''));

        if (! empty($parsed['size']) && ! empty($parsed['max_price'])) {
            if (self::isFootwearType($productType) || self::queryMentionsFootwear($query)) {
                return true;
            }
        }

        return (bool) preg_match(
            '/\b(puma|nike|adidas|reebok|meshkuj|patika|atlete|per meshkuj|këpucë|kepuce|shoes|sneakers|trainers|sport)\b/u',
            $query
        );
    }

    public static function isFootwearType(string $type): bool
    {
        return in_array(mb_strtolower(trim($type)), ['shoes', 'sneakers', 'trainers', 'boots'], true);
    }

    public static function queryMentionsFootwear(string $query): bool
    {
        return (bool) preg_match(
            '/\b(shoes|sneakers|trainers|patika|atlete|këpucë|kepuce|mabthje|mbathje|sport)\b/u',
            mb_strtolower($query)
        );
    }
}
