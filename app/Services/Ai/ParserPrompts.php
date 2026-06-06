<?php

namespace App\Services\Ai;

use App\Support\CategoryCatalog;
use App\Support\SearchLocale;

/**
 * Shared prompts for OpenAI and Gemini parsers.
 */
class ParserPrompts
{
    public static function system(?string $locale = 'en'): string
    {
        $lang = SearchLocale::descriptionLanguage($locale);
        $categories = implode('|', array_merge(CategoryCatalog::slugs(), ['marketplace']));

        return <<<PROMPT
You are BuyMap.ai shopping intent parser. Convert natural language product search queries into structured JSON.

Return ONLY valid JSON with this shape:
{
  "category": "{$categories}",
  "description": "one-sentence buyer-facing summary in {$lang}",
  "keywords": ["word1", "word2"],
  "language_hint": "en|sq|de|fr|it|es",
  "brand": null,
  "model": null,
  "year": null,
  "year_min": null,
  "year_max": null,
  "color": null,
  "max_km": null,
  "transmission": null,
  "fuel": null,
  "genre": null,
  "product_type": null,
  "features": [],
  "max_price": null,
  "currency": "EUR|CHF|USD",
  "search_country": null,
  "search_country_code": null,
  "condition": null,
  "style": null,
  "size": null,
  "shoe_size": null,
  "room": null,
  "subject": null,
  "bedrooms": null,
  "listing_type": null,
  "city": null,
  "landmark": null,
  "near_landmark": null,
  "property_type": "apartment|house|land",
  "min_sqm": null,
  "nearby_streets": [],
  "storage": null,
  "ram": null,
  "gender": null,
  "appliance_type": null,
  "energy_class": null,
  "dietary": null,
  "skin_type": null,
  "platform": null,
  "billing": null,
  "use_case": null,
  "material_type": null,
  "tool_type": null,
  "level": null,
  "format": null,
  "destination": null,
  "travel_type": null,
  "travelers": null,
  "pet_type": null,
  "sport_type": null,
  "equipment_type": null,
  "industry": null,
  "media_type": null,
  "authenticity": null,
  "seller_type": null,
  "item": null,
  "quantity": null,
  "language": null
}

Rules:
- category: pick ONE best match from the list. Use automotive for cars, electronics_tech for phones/laptops, fashion for clothing/shoes, real_estate for apartments/houses, etc.
- Set category-specific fields only (e.g. automotive: brand, model, year, max_km, fuel; fashion: size, brand, gender; travel: destination, travel_type)
- max_km: integer kilometers for cars (180k km => 180000)
- year: integer if mentioned
- keywords: 3-8 relevant terms, lowercase
- language_hint: detect from query (Albanian => sq)
- description: always in {$lang} when provided
- Omit null fields or use null explicitly
- features: array of strings e.g. long_battery, quiet_cooling, gaming
- electronics: "laptop gaming" / "kompjuter gaming" => product_type "laptop" + features ["gaming", ...]. Never return phones for laptop queries.
- real_estate: "banes"/apartment near a landmark (e.g. gjykata in Ferizaj) => category real_estate, city, landmark, min_sqm; nearby_streets if you know them
- min_sqm: integer area (120m => 120)
- Albanian: banes=apartment, gjykata=courthouse, Ferizaj=city
- fashion/shoes: size or shoe_size as EU number string (e.g. 42, 42.5, 43) when mentioned
- fashion: extract brand (puma, nike), color (Albanian: kalter/kaltër=blue, bardh/bardhë=white, zezë=black), product_type (patika=sneakers). Multiple colors => color "multicolor"
- cars: model must match query exactly (Q5 not A6). year as integer; year range "2016-2018" => year_min 2016, year_max 2018
- search_country / search_country_code: ONLY when buyer names where to buy. "ne zvicerr" => CH. If no place in query, leave null (platform uses visitor IP).
- max_price + currency: "deri 17500 franga" => max_price 17500, currency CHF. "under 20k euro" => EUR. Albanian "22 mij euro" => max_price 22000
- Do NOT set country to visitor hint when query names another country
- Do NOT infer search_country from visitor IP — that is handled separately
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
        $categories = implode('|', array_merge(CategoryCatalog::slugs(), ['marketplace']));

        return <<<PROMPT
Analyze this product image for a semantic shopping search engine.
{$locCtx}
{$hint}

Return JSON:
{
  "description": "detailed product description for the buyer UI in {$lang}",
  "search_query": "optimized short search phrase in English for eBay/Google (brand, model, color, product type)",
  "category": "{$categories}",
  "brand": null,
  "model": null,
  "color": null,
  "style": null,
  "size": null,
  "shoe_size": null,
  "materials": [],
  "keywords": [],
  "condition_guess": "new|used|unknown"
}

For footwear, always set size or shoe_size when visible on the product or stated by the user (EU sizing, e.g. 42.5).
PROMPT;
    }
}
