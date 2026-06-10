<?php

namespace App\Support;

/**
 * Swiss real-estate marketplace catalog for targeted CH property searches.
 */
class SwissRealEstateMarketplaces
{
  /** @var array<string, array{label: string, url: string}> */
  private const CATALOG = [
    'homegate_ch' => [
      'label' => 'Homegate',
      'url' => 'https://www.homegate.ch',
    ],
    'immoscout24_ch' => [
      'label' => 'ImmoScout24 Switzerland',
      'url' => 'https://www.immoscout24.ch',
    ],
    'newhome_ch' => [
      'label' => 'newhome.ch',
      'url' => 'https://www.newhome.ch',
    ],
    'comparis_immobilien' => [
      'label' => 'Comparis Immobilien',
      'url' => 'https://www.comparis.ch/immobilien',
    ],
    'immobilier_ch' => [
      'label' => 'Immobilier.ch',
      'url' => 'https://www.immobilier.ch',
    ],
    'flatfox_ch' => [
      'label' => 'Flatfox',
      'url' => 'https://www.flatfox.ch',
    ],
    'anibis_immobilien' => [
      'label' => 'anibis.ch Immobilien',
      'url' => 'https://www.anibis.ch',
    ],
  ];

  /**
   * @return array<int, string>
   */
  public static function keys(): array
  {
    return array_keys(self::CATALOG);
  }

  /**
   * @return array<int, string>
   */
  public static function labels(): array
  {
    return array_values(array_map(fn (array $m) => $m['label'], self::CATALOG));
  }

  public static function label(string $source): string
  {
    $key = strtolower(str_replace(['.', ' '], '_', trim($source)));

    return self::CATALOG[$key]['label'] ?? '';
  }

  public static function url(string $source): ?string
  {
    $key = strtolower(str_replace(['.', ' '], '_', trim($source)));

    return self::CATALOG[$key]['url'] ?? null;
  }

  public static function isPlatform(string $source): bool
  {
    $key = strtolower(str_replace(['.', ' '], '_', trim($source)));

    return isset(self::CATALOG[$key]);
  }

  /**
   * @param  array<int, string>  $targets
   */
  public static function isTarget(string $source, array $targets): bool
  {
    $key = strtolower(str_replace(['.', ' '], '_', trim($source)));

    if (! isset(self::CATALOG[$key])) {
      return false;
    }

    if ($targets === []) {
      return true;
    }

    foreach ($targets as $target) {
      if (strtolower(str_replace(['.', ' '], '_', trim($target))) === $key) {
        return true;
      }
    }

    return false;
  }
}
