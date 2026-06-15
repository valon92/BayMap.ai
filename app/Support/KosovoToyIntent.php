<?php

namespace App\Support;

/**
 * Detects Kosovo children's toy / instrument catalog searches.
 */
class KosovoToyIntent
{
    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function isToySearch(array $parsed): bool
    {
        if (ProductCategoryResolver::isChildrenToyVehicleQuery(
            (string) ($parsed['raw_query'] ?? $parsed['search_query'] ?? ''),
        )) {
            return true;
        }

        if (ProductCategoryResolver::isNonFashionProductIntent($parsed)) {
            return true;
        }

        $category = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');
        $type = mb_strtolower((string) ($parsed['product_type'] ?? ''));

        return $category === 'gaming_entertainment'
            && in_array($type, ['piano', 'toy', 'toy_car', 'keyboard', 'instrument'], true);
    }

    /**
     * Skip mock Amazon / MerrJep demo cards when live toy retailers are configured.
     *
     * @param  array<string, mixed>  $parsed
     */
    public static function shouldSkipDemoFallback(array $parsed): bool
    {
        if (! self::isToySearch($parsed)) {
            return false;
        }

        $country = strtoupper((string) ($parsed['search_country_code'] ?? ''));

        return $country === 'XK' && KosovoToyPlatforms::count() > 0;
    }

    /**
     * Extra search terms to fan out across toy retailers (piano often needs synonyms).
     *
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    public static function searchTerms(array $parsed): array
    {
        $raw = mb_strtolower((string) ($parsed['raw_query'] ?? $parsed['search_query'] ?? ''));
        $type = mb_strtolower((string) ($parsed['product_type'] ?? ''));

        if ($type === 'toy_car' || ProductCategoryResolver::isChildrenToyVehicleQuery($raw)) {
            return ['automjet femije', 'makina me telekomand', 'makine ecje', 'makina garash', 'makina'];
        }

        if ($type === 'piano' || preg_match('/\bpiano\b/u', $raw)) {
            return ['piano', 'xilofon', 'marimba', 'instrument muzikor'];
        }

        if (preg_match('/\b(lodra|loder|lodër|loje|lojë|toy)\b/u', $raw)) {
            return ['lodra', 'lojra'];
        }

        if (preg_match('/\b(gitar|drum|violin|instrument)\b/u', $raw)) {
            return ['instrument muzikor', 'lodra muzikore'];
        }

        return ['lodra'];
    }

    public static function titleMatchesIntent(string $title, array $parsed): bool
    {
        $type = mb_strtolower((string) ($parsed['product_type'] ?? ''));
        $lower = mb_strtolower($title);

        return match ($type) {
            'toy_car' => (bool) preg_match(
                '/(?:makin[aëe]?|automjet|vetur|car|telekomand|garash|ecje|stunt|skuter|kamion|truck|moto|bmw|porsche|mercedes|audi|ferrari|jeep)/ui',
                $lower,
            ) && ! preg_match(
                '/(?:peshqir|kov[eë]|bagazh|mbulese|tapet|ngjit[eë]s|çantë|cante|plazh|sticker|pizham|furç|dhëmb|pambuk|puzzle|qese)/ui',
                $lower,
            ),
            'piano' => (bool) preg_match(
                '/\b(piano|xilofon|xylophone|xilopiano|marimba|metalofon|mikrofon|instrument|muzik|keyboard|tast|dj|drum|daull|daulle|tinguj)\b/u',
                $lower,
            ) || (str_contains($lower, 'little lot') && str_contains($lower, 'muzik')),
            'toy' => true,
            default => true,
        };
    }
}
