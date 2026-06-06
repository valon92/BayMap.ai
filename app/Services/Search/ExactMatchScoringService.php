<?php

namespace App\Services\Search;

use App\Support\CategoryCatalog;
use App\Support\CountryMatcher;
use App\Support\ElectronicsIntentParser;
use App\Support\ShoeSize;

/**
 * Exact product discovery scoring — prioritizes precise intent match over loose similarity.
 */
class ExactMatchScoringService
{
    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsed
     */
    public function exactMatchBonus(array $product, array $parsed): int
    {
        $bonus = 0;
        $title = mb_strtolower($product['title'] ?? '');
        $tags = array_map('mb_strtolower', $product['tags'] ?? []);

        if ($this->matchesBrand($product, $parsed, $title, $tags)) {
            $bonus += 8;
        }

        if ($this->matchesModel($parsed, $title, $tags)) {
            $bonus += 15;
        }

        if ($this->matchesStorage($parsed, $product, $title)) {
            $bonus += 12;
        }

        if ($this->matchesColor($parsed, $title, $tags)) {
            $bonus += 10;
        }

        if ($this->matchesSize($product, $parsed)) {
            $bonus += 14;
        }

        if ($this->matchesAutomotiveExact($product, $parsed, $title)) {
            $bonus += 18;
        }

        if ($this->matchesElectronicsType($product, $parsed)) {
            $bonus += 20;
        }

        if ($this->matchesElectronicsFeatures($product, $parsed)) {
            $bonus += 12;
        }

        if ($this->conflictsElectronicsType($product, $parsed)) {
            $bonus -= 40;
        }

        return $bonus;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<int, string>  $tags
     */
    private function matchesBrand(array $product, array $parsed, string $title, array $tags): bool
    {
        if (empty($parsed['brand'])) {
            return false;
        }

        $brand = mb_strtolower((string) $parsed['brand']);
        $needles = match ($brand) {
            'apple' => ['apple', 'iphone', 'ipad', 'macbook'],
            'samsung' => ['samsung', 'galaxy'],
            default => [$brand],
        };

        foreach ($needles as $needle) {
            if (str_contains($title, $needle) || in_array($needle, $tags, true)) {
                return true;
            }
        }

        return ! empty($product['brand']) && mb_strtolower((string) $product['brand']) === $brand;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<int, string>  $tags
     */
    private function matchesModel(array $parsed, string $title, array $tags): bool
    {
        if (empty($parsed['model'])) {
            return false;
        }

        $wanted = mb_strtolower(str_replace(' ', '', (string) $parsed['model']));
        $normalizedTitle = str_replace(' ', '', $title);

        return str_contains($normalizedTitle, $wanted) || in_array(mb_strtolower((string) $parsed['model']), $tags, true);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $product
     */
    private function matchesStorage(array $parsed, array $product, string $title): bool
    {
        if (empty($parsed['storage'])) {
            return false;
        }

        $storage = strtoupper((string) $parsed['storage']);

        return str_contains(strtoupper($title), $storage)
            || strtoupper((string) ($product['storage'] ?? '')) === $storage;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<int, string>  $tags
     */
    private function matchesColor(array $parsed, string $title, array $tags): bool
    {
        if (empty($parsed['color'])) {
            return false;
        }

        $color = mb_strtolower((string) $parsed['color']);
        if ($color === 'multicolor') {
            $tones = ! empty($parsed['colors']) && is_array($parsed['colors'])
                ? array_map('mb_strtolower', $parsed['colors'])
                : ['black', 'white', 'grey', 'gray', 'blue', 'red', 'silver'];

            foreach ($tones as $tone) {
                if ($this->colorAliasMatch($tone, $title, $tags)) {
                    return true;
                }
            }

            return false;
        }

        return $this->colorAliasMatch($color, $title, $tags);
    }

  /**
     * @param  array<int, string>  $tags
     */
    private function colorAliasMatch(string $color, string $title, array $tags): bool
    {
        $aliases = match ($color) {
            'black' => ['black', 'graphite', 'midnight', 'zez', 'zeze'],
            'white' => ['white', 'silver', 'starlight', 'bardh', 'bardhe', 'ivory', 'pearl'],
            'grey', 'gray' => ['grey', 'gray', 'silver', 'graphite', 'hiri', 'gri'],
            'blue' => ['blue', 'navy', 'azure', 'kalter', 'kaltër', 'blu'],
            default => [$color],
        };

        foreach ($aliases as $alias) {
            if (str_contains($title, $alias) || in_array($alias, $tags, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsed
     */
    private function matchesSize(array $product, array $parsed): bool
    {
        if (empty($parsed['size'])) {
            return false;
        }

        return ShoeSize::productHasSize($product, (string) $parsed['size']);
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsed
     */
    private function matchesAutomotiveExact(array $product, array $parsed, string $title): bool
    {
        if (! CategoryCatalog::isAutomotive($parsed['category'] ?? '')) {
            return false;
        }

        if (empty($parsed['year']) || empty($parsed['model'])) {
            return false;
        }

        $yearOk = ! empty($product['year'])
            ? (int) $product['year'] === (int) $parsed['year']
            : str_contains($title, (string) $parsed['year']);

        $modelOk = str_contains(str_replace(' ', '', $title), str_replace(' ', '', mb_strtolower((string) $parsed['model'])));

        return $yearOk && $modelOk;
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsed
     */
    private function matchesElectronicsType(array $product, array $parsed): bool
    {
        if (empty($parsed['product_type']) || ! CategoryCatalog::isElectronics($parsed['category'] ?? '')) {
            return false;
        }

        return ElectronicsIntentParser::productMatchesType($product, (string) $parsed['product_type']);
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsed
     */
    private function matchesElectronicsFeatures(array $product, array $parsed): bool
    {
        if (empty($parsed['features']) || ! is_array($parsed['features']) || ! CategoryCatalog::isElectronics($parsed['category'] ?? '')) {
            return false;
        }

        return ElectronicsIntentParser::productMatchesFeatures($product, $parsed['features']);
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsed
     */
    private function conflictsElectronicsType(array $product, array $parsed): bool
    {
        if (($parsed['product_type'] ?? '') !== 'laptop' || ! CategoryCatalog::isElectronics($parsed['category'] ?? '')) {
            return false;
        }

        return ElectronicsIntentParser::productMatchesType($product, 'phone');
    }

    /**
     * Location priority bonus: local country → nearby → regional → global.
     *
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $parsed
     */
    public function locationPriorityBonus(array $product, array $parsed): int
    {
        $location = mb_strtolower($product['location'] ?? '');

        if (! empty($parsed['search_target']) && ! empty($parsed['search_country'])) {
            $matches = CountryMatcher::locationMatchesFilter(
                (string) ($product['location'] ?? ''),
                (string) $parsed['search_country'],
                isset($product['country_code']) ? (string) $product['country_code'] : null,
            );

            return $matches ? 20 : -15;
        }

        $visitorCountry = mb_strtolower((string) ($parsed['search_country'] ?? $parsed['country'] ?? ''));
        if ($visitorCountry !== '' && str_contains($location, $visitorCountry)) {
            return 18;
        }

        $nearby = ['kosovo', 'albania', 'north macedonia', 'serbia', 'montenegro', 'germany', 'switzerland', 'austria'];
        foreach ($nearby as $region) {
            if (str_contains($location, $region)) {
                return 8;
            }
        }

        if (str_contains($location, 'international') || str_contains($location, 'online')) {
            return 2;
        }

        return 0;
    }
}
