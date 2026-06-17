<?php

namespace App\Support;

/**
 * Rule-based electronics intent from Albanian/English queries.
 */
class ElectronicsIntentParser
{
    /** @var array<string, string> */
    private const COLORS = [
        'e zezë' => 'black',
        'e zeze' => 'black',
        'zezë' => 'black',
        'zeze' => 'black',
        'zez' => 'black',
        'black' => 'black',
        'e bardhë' => 'white',
        'e bardhe' => 'white',
        'bardhë' => 'white',
        'bardhe' => 'white',
        'white' => 'white',
        'blue' => 'blue',
        'gold' => 'gold',
        'silver' => 'silver',
        'purple' => 'purple',
        'green' => 'green',
    ];

    /** @var array<int, string> */
    private const LAPTOP_SIGNALS = [
        'laptop', 'notebook', 'macbook', 'chromebook', 'ultrabook',
        'kompjuter', 'kompjuter portativ', 'rog', 'legion', 'omen', 'predator', 'tuf', 'nitro', 'stealth',
    ];

    /** @var array<int, string> */
    private const PHONE_SIGNALS = [
        'iphone', 'telefon', 'smartphone', 'galaxy', 'pixel', 'redmi', 'xiaomi',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function fromQuery(string $query): array
    {
        $lower = mb_strtolower(trim($query));
        $result = [];

        if (preg_match('/\biphone\s*(\d+)\s*pro\s*max\b/iu', $lower, $m)) {
            $result['brand'] = 'apple';
            $result['product_type'] = 'phone';
            $result['model'] = "iPhone {$m[1]} Pro Max";
        } elseif (preg_match('/\biphone\s*(\d+)pro\s*max\b/iu', $lower, $m)) {
            $result['brand'] = 'apple';
            $result['product_type'] = 'phone';
            $result['model'] = "iPhone {$m[1]} Pro Max";
        } elseif (preg_match('/\biphone\s*(\d+\s*(?:pro\s*)?(?:max)?(?:\s*pro)?(?:\s*max)?)/iu', $lower, $m)) {
            $result['brand'] = 'apple';
            $result['product_type'] = 'phone';
            $result['model'] = self::normalizeIphoneModel(trim(preg_replace('/\s+/', ' ', $m[0]))) ?? trim(preg_replace('/\s+/', ' ', $m[0]));
        } elseif (preg_match('/\biphone\b/u', $lower)) {
            $result['brand'] = 'apple';
            $result['product_type'] = 'phone';
        } elseif (preg_match('/\bsamsung\s+galaxy\s*(s\d+\s*(?:ultra|plus|fe)?|a\d+)/iu', $lower, $m)) {
            $result['brand'] = 'samsung';
            $result['product_type'] = 'phone';
            $result['model'] = trim($m[0]);
        } elseif (preg_match('/\b(macbook\s*(?:air|pro)?)\b/u', $lower, $m)) {
            $result['brand'] = 'apple';
            $result['product_type'] = 'laptop';
            $result['model'] = trim(preg_replace('/\s+/', ' ', $m[1]));
        } elseif (preg_match('/\b(ipad\s*(?:pro|air|mini)?)\b/u', $lower, $m)) {
            $result['brand'] = 'apple';
            $result['product_type'] = 'tablet';
            $result['model'] = trim(preg_replace('/\s+/', ' ', $m[1]));
        } elseif (preg_match('/\b(airpods(?:\s*(?:pro|max|3|2))?)\b/u', $lower, $m)) {
            $result['brand'] = 'apple';
            $result['product_type'] = 'headphones';
            $result['model'] = trim(preg_replace('/\s+/', ' ', $m[1]));
        } elseif (self::mentionsLaptop($lower)) {
            $result['product_type'] = 'laptop';
        }

        $features = self::extractFeatures($lower);
        if ($features !== []) {
            $result['features'] = $features;
        }

        if (empty($result['product_type']) && $features !== [] && ! self::mentionsPhone($lower)) {
            if (in_array('gaming', $features, true) || in_array('long_battery', $features, true)) {
                $result['product_type'] = 'laptop';
            }
        }

        if (preg_match('/\b(gaming|loj[aë]ra)\b/u', $lower)) {
            $result['product_type'] = $result['product_type'] ?? 'laptop';
        }

        $storage = self::extractStorage($query);
        if ($storage !== null) {
            $result['storage'] = $storage;
        }

        if (preg_match('/\b(\d+)\s*gb\s*ram\b/i', $query, $m)) {
            $result['ram'] = $m[1].'GB';
        }

        foreach (self::COLORS as $needle => $canonical) {
            if (str_contains($lower, $needle)) {
                $result['color'] = $canonical;
                break;
            }
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    public static function extractFeatures(string $lower): array
    {
        $features = [];

        if (preg_match('/\b(gaming|loj[aë]ra|rtx|geforce)\b/u', $lower)) {
            $features[] = 'gaming';
        }

        if (preg_match('/\b(bateri|battery|autonomi|all[- ]day)\b/u', $lower)) {
            $features[] = 'long_battery';
        }

        if (preg_match('/\b(ftohje|cooling|quiet|silent|qet[eë]|thermals|fans?)\b/u', $lower)) {
            $features[] = 'quiet_cooling';
        }

        return array_values(array_unique($features));
    }

    public static function extractStorage(string $query): ?string
    {
        if (! preg_match('/(\d+)\s*gb\b/i', $query, $m)) {
            return null;
        }

        $gb = (int) $m[1];

        if ($gb >= 120 && $gb <= 127) {
            $gb = 128;
        } elseif ($gb >= 250 && $gb <= 257) {
            $gb = 256;
        } elseif ($gb >= 500 && $gb <= 520) {
            $gb = 512;
        }

        return $gb >= 1024 ? '1TB' : $gb.'GB';
    }

    public static function normalizeIphoneModel(?string $model): ?string
    {
        if ($model === null || trim($model) === '') {
            return null;
        }

        $clean = mb_strtolower(trim($model));
        if (preg_match('/iphone\s*(\d+)\s*pro\s*max/u', $clean, $m)) {
            return "iPhone {$m[1]} Pro Max";
        }
        if (preg_match('/iphone\s*(\d+)pro\s*max/u', $clean, $m)) {
            return "iPhone {$m[1]} Pro Max";
        }
        if (preg_match('/iphone\s*(\d+)\s*pro/u', $clean, $m)) {
            return "iPhone {$m[1]} Pro";
        }

        return trim($model);
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public static function productMatchesModel(array $product, string $model): bool
    {
        $normalized = self::normalizeIphoneModel($model) ?? $model;
        $wanted = mb_strtolower(str_replace(' ', '', $normalized));
        $title = mb_strtolower(str_replace(' ', '', $product['title'] ?? ''));
        $itemModel = mb_strtolower(str_replace(' ', '', (string) ($product['model'] ?? '')));
        $tags = array_map(fn ($t) => mb_strtolower(str_replace(' ', '', (string) $t)), $product['tags'] ?? []);

        if ($itemModel !== '' && (str_contains($itemModel, $wanted) || str_contains($wanted, $itemModel))) {
            return true;
        }

        if (str_contains($title, $wanted) || in_array($wanted, $tags, true)) {
            return true;
        }

        if (preg_match('/iphone(\d+)promax/u', $wanted, $wm)) {
            if (preg_match('/iphone(\d+)promax/u', $title, $tm)) {
                return $wm[1] === $tm[1];
            }
        }

        return false;
    }

    public static function isElectronicsQuery(string $query): bool
    {
        return self::fromQuery($query) !== [];
    }

    /**
     * Extract brand, RAM, storage, screen size, chip, etc. from a product listing title.
     *
     * @return array<string, mixed>
     */
    public static function attributesFromTitle(string $title): array
    {
        $attrs = self::fromQuery($title);
        $lower = mb_strtolower($title);

        if (preg_match('/\b(\d+)\s*GB\s*-\s*(\d+)\s*(GB|TB)\b/ui', $title, $m)) {
            $attrs['ram'] = ((int) $m[1]).'GB';
            $attrs['storage'] = strtoupper($m[3]) === 'TB'
                ? ((int) $m[2]).'TB'
                : ((int) $m[2]).'GB';
        } elseif (preg_match('/\b(\d+)\s*GB\s*-\s*(\d+)\s*GB\b/ui', $title, $m)) {
            $attrs['ram'] = ((int) $m[1]).'GB';
            $attrs['storage'] = ((int) $m[2]).'GB';
        }

        if (empty($attrs['storage']) && preg_match('/\b(\d+)\s*TB\b/i', $title, $m)) {
            $attrs['storage'] = ((int) $m[1]).'TB';
        }

        if (preg_match('/(\d+[,.]?\d*)\s*(?:Zoll|")/ui', $title, $m)) {
            $attrs['display_size'] = str_replace(',', '.', $m[1]).'"';
        }

        if (preg_match('/\bApple\s+(M\d+(?:\s*Pro)?(?:\s*Max)?)\b/ui', $title, $m)) {
            $attrs['chip'] = trim($m[1]);
        }

        if (preg_match('/\((20\d{2})\)/', $title, $m)) {
            $attrs['year'] = (int) $m[1];
        }

        if (empty($attrs['brand'])) {
            foreach (['apple', 'samsung', 'dell', 'hp', 'lenovo', 'asus', 'acer', 'microsoft', 'sony', 'lg'] as $brand) {
                if (str_contains($lower, $brand)) {
                    $attrs['brand'] = $brand;
                    break;
                }
            }
        }

        if (empty($attrs['product_type'])) {
            $attrs['product_type'] = match (true) {
                str_contains($lower, 'macbook') || str_contains($lower, 'notebook') || str_contains($lower, 'laptop') => 'laptop',
                str_contains($lower, 'iphone') || str_contains($lower, 'galaxy') || str_contains($lower, 'smartphone') => 'phone',
                str_contains($lower, 'ipad') => 'tablet',
                str_contains($lower, 'airpods') || str_contains($lower, 'headphone') => 'headphones',
                str_contains($lower, 'monitor') || str_contains($lower, 'display') => 'monitor',
                default => null,
            };
        }

        return array_filter($attrs, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<int, string>  $features
     */
    public static function productMatchesFeatures(array $product, array $features): bool
    {
        if ($features === []) {
            return true;
        }

        $title = mb_strtolower($product['title'] ?? '');
        $tags = array_map('mb_strtolower', $product['tags'] ?? []);
        $haystack = $title.' '.implode(' ', $tags);

        foreach ($features as $feature) {
            if (! self::featureMatches($feature, $haystack, $tags)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $tags
     */
    public static function productMatchesType(array $product, string $type): bool
    {
        $type = mb_strtolower(trim($type));
        $title = mb_strtolower($product['title'] ?? '');
        $tags = array_map('mb_strtolower', $product['tags'] ?? []);
        $productType = mb_strtolower((string) ($product['product_type'] ?? ''));

        if ($productType === $type) {
            return true;
        }

        $needles = match ($type) {
            'phone' => ['phone', 'iphone', 'smartphone', 'galaxy', 'telefon'],
            'laptop' => ['laptop', 'macbook', 'notebook', 'rog', 'legion', 'omen', 'predator', 'nitro', 'stealth', 'tuf', 'kompjuter portativ'],
            'tablet' => ['tablet', 'ipad'],
            'headphones' => ['headphones', 'airpods', 'earbuds', 'headset'],
            'monitor' => ['monitor', 'display', 'ekran'],
            default => [$type],
        };

        foreach ($needles as $needle) {
            if (str_contains($title, $needle) || in_array($needle, $tags, true)) {
                return true;
            }
        }

        if ($type === 'laptop' && (str_contains($title, 'iphone') || in_array('phone', $tags, true) || in_array('iphone', $tags, true))) {
            return false;
        }

        return false;
    }

    private static function mentionsLaptop(string $lower): bool
    {
        foreach (self::LAPTOP_SIGNALS as $signal) {
            if (str_contains($lower, $signal)) {
                return true;
            }
        }

        return false;
    }

    private static function mentionsPhone(string $lower): bool
    {
        foreach (self::PHONE_SIGNALS as $signal) {
            if (str_contains($lower, $signal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $tags
     */
    private static function featureMatches(string $feature, string $haystack, array $tags): bool
    {
        if (in_array($feature, $tags, true)) {
            return true;
        }

        return match ($feature) {
            'gaming' => (bool) preg_match('/gaming|rog|legion|omen|predator|nitro|stealth|tuf|rtx|geforce|loj[aë]ra/u', $haystack),
            'long_battery' => (bool) preg_match('/long_battery|long battery|battery|bateri|autonomi|10\s*hr|10h|all[- ]day/u', $haystack),
            'quiet_cooling' => (bool) preg_match('/quiet_cooling|quiet cooling|silent|quiet fans|ftohje|cooling|thermals|qet[eë]/u', $haystack),
            default => str_contains($haystack, str_replace('_', ' ', $feature)) || in_array($feature, $tags, true),
        };
    }
}
