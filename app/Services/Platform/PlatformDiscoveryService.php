<?php

namespace App\Services\Platform;

use App\Support\CategoryCatalog;
use App\Support\LivePlatformRegistry;
use App\Support\PlatformCatalogBridge;
use App\Support\SearchScopeResolver;

/**
 * Automatically discovers and classifies marketplaces for a parsed user intent.
 * Targeted: country (+ optional city) + product category → local platforms.
 * Universal: worldwide platforms for the product category.
 */
class PlatformDiscoveryService
{
  /**
   * @param  array<string, mixed>  $parsed
   * @return array{
   *   scope: string,
   *   country_code: string,
   *   city: ?string,
   *   category: string,
   *   keys: array<int, string>,
   *   platforms: array<int, array{key: string, label: string, country: string, source: string}>
   * }
   */
  public function discover(array $parsed): array
  {
    $category = CategoryCatalog::normalize($parsed['category'] ?? 'marketplace');
    $scope = SearchScopeResolver::isUniversal($parsed) ? 'universal' : 'targeted';
    $countryCode = strtoupper((string) ($parsed['search_country_code'] ?? ''));
    $city = isset($parsed['search_city']) ? (string) $parsed['search_city'] : null;

    if ($scope === 'universal') {
      $keys = $this->globalKeysForCategory($category);
      $countryCode = 'WW';
    } else {
      $keys = $this->targetedKeys($countryCode, $category, $city, $parsed);
    }

    $cap = (int) config('live_platforms.max_workers_cap', 24);
    if (count($keys) > $cap) {
      $keys = array_slice($keys, 0, $cap);
    }

    return [
      'scope' => $scope,
      'country_code' => $countryCode,
      'city' => $city,
      'category' => $category,
      'keys' => $keys,
      'platforms' => $this->hydratePlatforms($keys),
    ];
  }

  /**
   * @param  array<string, mixed>  $parsed
   * @return array<int, string>
   */
  public function keys(array $parsed): array
  {
    return $this->discover($parsed)['keys'];
  }

  /**
   * @return array<int, string>
   */
  private function targetedKeys(string $countryCode, string $category, ?string $city, array $parsed): array
  {
    if ($countryCode === '') {
      return [];
    }

    $fromConfig = $this->configKeysFor($countryCode, $category, $city);
    $fromBridge = PlatformCatalogBridge::keysFor($countryCode, $category);
    $keys = array_values(array_unique(array_merge($fromConfig, $fromBridge)));

    return $this->sortKeys($keys);
  }

  /**
   * Worldwide scope: global cross-border marketplaces only (eBay, Amazon, Decathlon, etc.).
   *
   * @return array<int, string>
   */
  private function globalKeysForCategory(string $category): array
  {
    $keys = [];

    foreach (LivePlatformRegistry::all() as $key => $meta) {
      if ($this->matchesCategory($meta, $category) && $this->isGlobalPlatform($meta)) {
        $keys[] = $key;
      }
    }

    return $this->sortKeys(array_values(array_unique($keys)));
  }

  /**
   * @return array<int, string>
   */
  private function configKeysFor(string $countryCode, string $category, ?string $city): array
  {
    $keys = [];

    foreach (LivePlatformRegistry::all() as $key => $meta) {
      $platformCountry = strtoupper((string) ($meta['country'] ?? ''));
      if ($platformCountry !== $countryCode) {
        continue;
      }
      if (! $this->matchesCategory($meta, $category)) {
        continue;
      }
      if (! $this->matchesCity($meta, $city)) {
        continue;
      }
      $keys[] = $key;
    }

    return $keys;
  }

  /**
   * @param  array<string, mixed>  $meta
   */
  private function matchesCategory(array $meta, string $category): bool
  {
    $cats = (array) ($meta['categories'] ?? []);

    if (in_array($category, $cats, true)) {
      return true;
    }

    return $category === 'marketplace' && in_array('marketplace', $cats, true);
  }

  /**
   * @param  array<string, mixed>  $meta
   */
  private function matchesCity(array $meta, ?string $city): bool
  {
    $cities = (array) ($meta['cities'] ?? []);
    if ($cities === [] || $city === null || $city === '') {
      return true;
    }

    $cityLower = mb_strtolower($city);
    foreach ($cities as $allowed) {
      if (mb_strtolower((string) $allowed) === $cityLower) {
        return true;
      }
    }

    return false;
  }

  /**
   * @param  array<string, mixed>  $meta
   */
  private function isGlobalPlatform(array $meta): bool
  {
    return in_array(strtoupper((string) ($meta['country'] ?? '')), ['WW', 'GLOBAL', '*'], true)
      || ! empty($meta['global']);
  }

  /**
   * @param  array<int, string>  $keys
   * @return array<int, string>
   */
  private function sortKeys(array $keys): array
  {
    usort($keys, fn ($a, $b) => $this->priority($a) <=> $this->priority($b));

    return $keys;
  }

  private function priority(string $key): int
  {
    return (int) (LivePlatformRegistry::all()[$key]['priority'] ?? 50);
  }

  /**
   * @param  array<int, string>  $keys
   * @return array<int, array{key: string, label: string, country: string, source: string}>
   */
  private function hydratePlatforms(array $keys): array
  {
    $platforms = [];

    foreach ($keys as $key) {
      $meta = LivePlatformRegistry::all()[$key] ?? null;
      $platforms[] = [
        'key' => $key,
        'label' => LivePlatformRegistry::label($key),
        'country' => (string) ($meta['country'] ?? ''),
        'source' => $meta !== null ? 'live' : 'catalog',
      ];
    }

    return $platforms;
  }
}
