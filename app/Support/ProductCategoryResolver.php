<?php

namespace App\Support;

/**
 * Rule-based product → BuyMap category mapping from natural-language queries.
 */
class ProductCategoryResolver
{
  /** @var array<string, string> keyword => canonical category */
  private const KEYWORDS = [
    'iphone' => 'electronics_tech',
    'ipad' => 'electronics_tech',
    'macbook' => 'electronics_tech',
    'laptop' => 'electronics_tech',
    'telefon' => 'electronics_tech',
    'telefoni' => 'electronics_tech',
    'smartphone' => 'electronics_tech',
    'samsung' => 'electronics_tech',
    'playstation' => 'gaming_entertainment',
    'xbox' => 'gaming_entertainment',
    'nintendo' => 'gaming_entertainment',
    'skuter' => 'sports_outdoor',
    'skuteri' => 'sports_outdoor',
    'scooter' => 'sports_outdoor',
    'e-scooter' => 'sports_outdoor',
    'biciklet' => 'sports_outdoor',
    'bike' => 'sports_outdoor',
    'shtepi' => 'real_estate',
    'shtëpi' => 'real_estate',
    'shtepine' => 'real_estate',
    'banes' => 'real_estate',
    'banesa' => 'real_estate',
    'apartament' => 'real_estate',
    'apartment' => 'real_estate',
    'house' => 'real_estate',
    'villa' => 'real_estate',
    'patundsh' => 'real_estate',
    'immobil' => 'real_estate',
    'vetur' => 'automotive',
    'veture' => 'automotive',
    'makina' => 'automotive',
    'car' => 'automotive',
    'auto' => 'automotive',
    'atlete' => 'fashion',
    'këpuc' => 'fashion',
    'kepuc' => 'fashion',
    'nike' => 'fashion',
    'adidas' => 'fashion',
    'libër' => 'online_education',
    'liber' => 'online_education',
    'book' => 'online_education',
  ];

  public static function categoryFromQuery(string $query): ?string
  {
    $lower = mb_strtolower($query);

    foreach (self::KEYWORDS as $keyword => $category) {
      if (preg_match('/\b'.preg_quote($keyword, '/').'\b/u', $lower)) {
        return $category;
      }
    }

    return null;
  }

  /**
   * @param  array<string, mixed>  $parsed
   * @return array<string, mixed>
   */
  public static function enrich(array $parsed, string $rawQuery): array
  {
    $detected = self::categoryFromQuery($rawQuery);
    if ($detected === null) {
      return $parsed;
    }

    $current = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');
    $shouldOverride = $current === 'marketplace'
      || ($detected === 'automotive' && $current === 'real_estate')
      || ($detected === 'electronics_tech' && in_array($current, ['marketplace', 'real_estate'], true))
      || ($detected === 'sports_outdoor' && $current === 'marketplace')
      || ($detected === 'real_estate' && $current === 'marketplace');

    if ($shouldOverride) {
      $parsed['category'] = $detected;
    }

    if ($detected === 'real_estate' && empty($parsed['product_type'])) {
      $parsed['product_type'] = 'property';
    }

    if ($detected === 'sports_outdoor' && preg_match('/\b(skuter|scooter)/ui', $rawQuery)) {
      $parsed['product_type'] = 'scooter';
    }

    return $parsed;
  }
}
