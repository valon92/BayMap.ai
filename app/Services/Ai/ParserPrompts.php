<?php

namespace App\Services\Ai;

use App\Support\SearchLocale;

/**
 * Shared prompts for OpenAI and Gemini parsers.
 */
class ParserPrompts
{
    public static function system(?string $locale = 'en'): string
    {
        $lang = SearchLocale::descriptionLanguage($locale);

        return <<<PROMPT
You are Powerbook.ai shopping intent parser. Convert natural language product search queries into structured JSON.

Return ONLY valid JSON with this shape:
{
  "category": "car|book|painting|electronics|furniture|collectibles|fashion|real_estate|luxury|gift|marketplace",
  "description": "one-sentence buyer-facing summary in {$lang}",
  "keywords": ["word1", "word2"],
  "language_hint": "en|sq|de|fr|it|es",
  "brand": null,
  "model": null,
  "year": null,
  "color": null,
  "max_km": null,
  "transmission": null,
  "fuel": null,
  "genre": null,
  "product_type": null,
  "features": [],
  "max_price": null,
  "condition": null,
  "style": null,
  "room": null,
  "subject": null,
  "bedrooms": null,
  "listing_type": null,
  "city": null,
  "landmark": null,
  "near_landmark": null,
  "property_type": "apartment|house|land",
  "min_sqm": null,
  "nearby_streets": []
}

Rules:
- category: best match for intent
- max_km: integer kilometers for cars (180k km => 180000)
- year: integer if mentioned
- keywords: 3-8 relevant terms, lowercase
- language_hint: detect from query (Albanian => sq)
- description: always in {$lang} when provided
- Omit null fields or use null explicitly
- features: array of strings e.g. long_battery, quiet_cooling, gaming
- real_estate: "banes"/apartment near a landmark (e.g. gjykata in Ferizaj) => category real_estate, city, landmark, min_sqm; nearby_streets if you know them
- min_sqm: integer area (120m => 120)
- Albanian: banes=apartment, gjykata=courthouse, Ferizaj=city
PROMPT;
    }

    public static function user(string $query, ?string $country, ?string $locale = 'en'): string
    {
        $ctx = $country ? "User country hint: {$country}." : '';
        $uiLocale = SearchLocale::normalize($locale);

        return "{$ctx}\nUI locale: {$uiLocale}\nQuery: {$query}";
    }

    public static function visionUser(string $lang, string $locCtx, string $hint): string
    {
        return <<<PROMPT
Analyze this product image for a semantic shopping search engine.
{$locCtx}
{$hint}

Return JSON:
{
  "description": "detailed product description for the buyer UI in {$lang}",
  "search_query": "optimized short search phrase in English for eBay/Google (brand, model, color, product type)",
  "category": "car|book|painting|electronics|furniture|fashion|luxury|collectibles|gift|marketplace",
  "brand": null,
  "model": null,
  "color": null,
  "style": null,
  "materials": [],
  "keywords": [],
  "condition_guess": "new|used|unknown"
}
PROMPT;
    }
}
