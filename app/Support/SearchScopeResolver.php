<?php

namespace App\Support;

/**
 * Detects whether a search is targeted (country/city) or universal (worldwide).
 */
class SearchScopeResolver
{
  private const UNIVERSAL_TOKENS = [
    'botë', 'bote', 'boten', 'botën', 'world', 'worldwide', 'global', 'globally',
    'universal', 'universally', 'everywhere', 'kudo', 'kudoq', 'kudoqë', 'kudoqe',
    'international', 'internacionale', 'planet', 'earth', 'gjithe bota', 'gjithë bota',
    'all countries', 'any country', 'anywhere',
  ];

  /**
   * @param  array<string, mixed>  $parsed
   */
  public static function isUniversal(array $parsed): bool
  {
    if (($parsed['search_scope'] ?? '') === 'universal') {
      return true;
    }

    $code = strtoupper((string) ($parsed['search_country_code'] ?? ''));
    if (in_array($code, ['WW', 'GLOBAL', '*'], true)) {
      return true;
    }

    $raw = mb_strtolower((string) ($parsed['raw_query'] ?? $parsed['search_query'] ?? $parsed['query'] ?? ''));

    foreach (self::UNIVERSAL_TOKENS as $token) {
      if (preg_match('/\b'.preg_quote($token, '/').'\b/u', $raw)) {
        return true;
      }
    }

    return false;
  }

  /**
   * @param  array<string, mixed>  $parsed
   * @return array<string, mixed>
   */
  public static function applyFromQuery(array $parsed, string $rawQuery): array
  {
    $lower = mb_strtolower($rawQuery);

    foreach (self::UNIVERSAL_TOKENS as $token) {
      if (preg_match('/\b'.preg_quote($token, '/').'\b/u', $lower)) {
        $parsed['search_scope'] = 'universal';
        $parsed['search_country_code'] = 'WW';
        $parsed['search_country'] = 'Worldwide';
        $parsed['search_target'] = true;
        $parsed['location_source'] = 'query';

        return $parsed;
      }
    }

    if (($parsed['search_scope'] ?? '') !== 'universal') {
      $parsed['search_scope'] = ! empty($parsed['search_country_code']) ? 'targeted' : 'auto';
    }

    return $parsed;
  }
}
