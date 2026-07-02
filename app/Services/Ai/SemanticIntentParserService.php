<?php

namespace App\Services\Ai;

use App\Services\Search\QueryIntentEnricher;
use App\Support\CategoryCatalog;
use App\Support\IntentDescriptionBuilder;
use App\Support\IndustrialB2BIntentParser;
use App\Support\ProductCategoryResolver;

/**
 * Rule+semantic intent engine when cloud LLM keys are unavailable.
 * Uses the same enrichers as live search so category/attributes match routing.
 */
class SemanticIntentParserService
{
    public function __construct(
        private QueryIntentEnricher $enricher,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function parse(string $query, ?string $country = null, ?string $locale = 'en'): array
    {
        $detected = ProductCategoryResolver::categoryFromQuery($query);
        $category = $detected ?? $this->scoreCategory($query);

        $parsed = [
            'raw_query' => $query,
            'category' => $category,
            'country' => $country,
            'language_hint' => $this->languageHint($query),
            'parser' => 'semantic',
        ];

        if ($country !== null && $country !== '') {
            $parsed['search_country_code'] = strtoupper($country);
        }

        $parsed = $this->enricher->enrich($parsed, $query);
        $parsed['parser'] = 'semantic';
        $parsed['description'] = IntentDescriptionBuilder::build($parsed, $locale);

        if (empty($parsed['keywords'])) {
            $parsed['keywords'] = $this->keywords($query);
        }

        return array_filter($parsed, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * Refine LLM output with deterministic intent rules (category fixes, description).
     *
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public function refine(array $parsed, string $query, ?string $locale = 'en'): array
    {
        $parsed['raw_query'] = $query;
        $parsed = $this->enricher->enrich($parsed, $query);

        if (IndustrialB2BIntentParser::isIndustrialQuery($query)) {
            $parsed = IndustrialB2BIntentParser::merge($parsed, $query);
        }

        if (empty($parsed['description'])) {
            $parsed['description'] = IntentDescriptionBuilder::build($parsed, $locale);
        }

        return $parsed;
    }

    private function scoreCategory(string $query): string
    {
        $scores = CategoryCatalog::scoreQuery(mb_strtolower(trim($query)));
        arsort($scores);
        $top = array_key_first($scores);

        return ($scores[$top] ?? 0) > 0 ? CategoryCatalog::normalize((string) $top) : 'marketplace';
    }

    private function languageHint(string $query): string
    {
        $lower = mb_strtolower($query);
        foreach (['ç', 'ë', 'dhome', 'makine', 'makineri', 'libër', 'blerje', 'vetur', 'patika'] as $marker) {
            if (str_contains($lower, $marker)) {
                return 'sq';
            }
        }

        return 'en';
    }

    /**
     * @return array<int, string>
     */
    private function keywords(string $query): array
    {
        $stop = ['a', 'an', 'the', 'with', 'for', 'and', 'or', 'under', 'per', 'për', 'ne', 'në', 'me'];
        $words = preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', mb_strtolower($query))) ?: [];
        $keywords = array_filter($words, fn (string $w) => mb_strlen($w) > 2 && ! in_array($w, $stop, true));

        return array_values(array_unique($keywords));
    }
}
