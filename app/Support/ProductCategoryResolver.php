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
    'autopjese' => 'automotive_parts',
    'autopjesë' => 'automotive_parts',
    'pjesë' => 'automotive_parts',
    'pjese' => 'automotive_parts',
    'spare part' => 'automotive_parts',
    'ersatzteil' => 'automotive_parts',
    'ricambi' => 'automotive_parts',
    'atlete' => 'fashion',
    'këpuc' => 'fashion',
    'kepuc' => 'fashion',
    'nike' => 'fashion',
    'adidas' => 'fashion',
    'libër' => 'online_education',
    'liber' => 'online_education',
    'book' => 'online_education',
    'piano' => 'gaming_entertainment',
    'pianino' => 'gaming_entertainment',
    'lodër' => 'gaming_entertainment',
    'loder' => 'gaming_entertainment',
    'lodra' => 'gaming_entertainment',
    'lojë' => 'gaming_entertainment',
    'loje' => 'gaming_entertainment',
    'instrument' => 'gaming_entertainment',
    'gitar' => 'gaming_entertainment',
    'drum' => 'gaming_entertainment',
    'violin' => 'gaming_entertainment',
  ];

  /** @var array<int, string> */
  private const WEB_SERVICE_SIGNALS = [
    'domain', 'domen', 'domenë', 'domena', 'domenen', 'hosting', 'hostim', 'email', 'mail', 'ssl', 'registrar',
  ];

    public static function categoryFromQuery(string $query): ?string
    {
        $lower = mb_strtolower($query);

        if (WebServicesIntentParser::isWebServicesQuery($query)) {
            return 'ai_software';
        }

        if (self::isChildrenToyVehicleQuery($query)) {
            return 'gaming_entertainment';
        }

        if (IndustrialB2BIntentParser::isIndustrialQuery($query)) {
            return 'industrial_b2b';
        }

        if (TravelIntentParser::isTravelQuery($query)) {
            return 'travel';
        }

        if (AutomotivePartsIntentParser::isPartsSearch([], $query)
      && ! AutomotivePartsIntentParser::isVehiclePurchaseQuery([], $query)) {
      return 'automotive_parts';
    }

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
    if (self::isChildrenToyVehicleQuery($rawQuery)) {
      $parsed['category'] = 'gaming_entertainment';
      $parsed['product_type'] = 'toy_car';
    }

    $detected = self::categoryFromQuery($rawQuery);
    if ($detected === null) {
      return $parsed;
    }

    $current = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');
    $shouldOverride = $current === 'marketplace'
      || ($detected === 'industrial_b2b' && $current !== 'travel')
      || ($detected === 'automotive_parts' && $current !== 'travel')
      || ($detected === 'automotive' && $current === 'real_estate')
      || ($detected === 'electronics_tech' && in_array($current, ['marketplace', 'real_estate'], true))
      || ($detected === 'sports_outdoor' && $current === 'marketplace')
      || ($detected === 'real_estate' && $current === 'marketplace')
      || ($detected === 'gaming_entertainment' && in_array($current, ['marketplace', 'fashion', 'sports_outdoor'], true))
      || ($detected === 'ai_software' && $current !== 'travel');

    if ($shouldOverride) {
      $parsed['category'] = $detected;
    }

    if ($detected === 'real_estate' && empty($parsed['product_type'])) {
      $parsed['product_type'] = 'property';
    }

    if ($detected === 'sports_outdoor' && preg_match('/\b(skuter|scooter)/ui', $rawQuery)) {
      $parsed['product_type'] = 'scooter';
    }

    if ($detected === 'gaming_entertainment' && empty($parsed['product_type'])) {
      $parsed['product_type'] = self::detectToyProductType($rawQuery);
    }

    if (self::isChildrenToyVehicleQuery($rawQuery)) {
      $parsed['category'] = 'gaming_entertainment';
      $parsed['product_type'] = 'toy_car';
    }

    if ($detected === null && self::isNonFashionProductIntent($parsed, $rawQuery)) {
      $parsed['category'] = 'gaming_entertainment';
      if (empty($parsed['product_type'])) {
        $parsed['product_type'] = self::detectToyProductType($rawQuery);
      }
    }

    return $parsed;
  }

  /**
   * Fashion Kosovo scrapers must not run for toys, instruments, etc.
   *
   * @param  array<string, mixed>  $parsed
   */
  public static function isNonFashionProductIntent(array $parsed, ?string $query = null): bool
  {
    $query = $query ?? (string) ($parsed['raw_query'] ?? $parsed['search_query'] ?? '');
    $lower = mb_strtolower($query);
    $type = mb_strtolower((string) ($parsed['product_type'] ?? ''));

    $nonFashionTypes = [
      'piano', 'keyboard', 'guitar', 'violin', 'drum', 'instrument',
      'toy', 'toy_car', 'lodra', 'lodër', 'loder', 'lego', 'doll', 'puzzle', 'game', 'console',
    ];

    if (in_array($type, $nonFashionTypes, true)) {
      return true;
    }

    if (self::isChildrenToyVehicleQuery($query)) {
      return true;
    }

    return (bool) preg_match(
      '/\b(piano|pianino|lodër|loder|lodra|gitar|violin|drum|instrument|keyboard musical|baby piano|piano për fëmij|piano per femij|lodër për fëmij|loder per femij)\b/u',
      $lower,
    );
  }

  /**
   * Ride-on / toy cars for children — not real automotive listings.
   */
  public static function isChildrenToyVehicleQuery(string $query): bool
  {
    $lower = mb_strtolower(trim($query));

    $hasVehicle = (bool) preg_match(
      '/\b(vetur[aëe]?|vetura|veture|makina|makine|automjet|automjete|car|cars|auto)\b/u',
      $lower,
    );
    $hasChild = (bool) preg_match(
      '/\b(femij|fëmij|foshnj|bebe|baby|toddler|kids|kid|child|children|vogel|vogël|i vogel|te vogel|të vogël|per femij|për fëmij)\b/u',
      $lower,
    );
    $hasToySignal = (bool) preg_match(
      '/\b(lodër|loder|lodra|toy|lojë|loje|ecje|telekomand|ride[\s-]?on)\b/u',
      $lower,
    );

    if ($hasVehicle && ($hasChild || $hasToySignal)) {
      return true;
    }

    return (bool) preg_match(
      '/\b(vetur[aëe]?|makina|makine|automjet)\s+(?:per|për|per\s+)?(?:femij|fëmij|foshnj|bebe|vogel|vogël)\b/u',
      $lower,
    );
  }

  /**
   * @param  array<string, mixed>  $parsed
   */
  public static function isFashionPlatformRelevant(array $parsed, ?string $query = null): bool
  {
    if (self::isNonFashionProductIntent($parsed, $query)) {
      return false;
    }

    $category = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');

    if (in_array($category, ['fashion', 'sports_outdoor'], true)) {
      return true;
    }

    return KosovoFashionIntent::isBrandedCatalogSearch($parsed);
  }

  private static function detectToyProductType(string $query): string
  {
    $lower = mb_strtolower($query);

    if (self::isChildrenToyVehicleQuery($query)) {
      return 'toy_car';
    }

    if (preg_match('/\bpiano\b/u', $lower)) {
      return 'piano';
    }

    if (preg_match('/\b(lodër|loder|lodra|toy|lojë|loje)\b/u', $lower)) {
      return 'toy';
    }

    return 'toy';
  }
}
