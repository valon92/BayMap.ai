<?php

namespace App\Support;

/**
 * Rule-based fashion intent from Albanian/English shoe & clothing queries.
 */
class FashionIntentParser
{
    /** @var array<int, string> */
    private const BRANDS = [
        'puma', 'nike', 'adidas', 'reebok', 'new balance', 'jordan', 'converse',
        'vans', 'asics', 'fila', 'under armour', 'salomon', 'hoka',
        'boss', 'hugo boss',
    ];

    /** @var array<string, string> */
    private const COLORS = [
        'kaltër' => 'blue',
        'kalter' => 'blue',
        'kalt' => 'blue',
        'blue' => 'blue',
        'bardhë' => 'white',
        'bardhe' => 'white',
        'bardh' => 'white',
        'white' => 'white',
        'zezë' => 'black',
        'zeze' => 'black',
        'zez' => 'black',
        'black' => 'black',
        'kuq' => 'red',
        'red' => 'red',
        'gri' => 'grey',
        'grey' => 'gray',
        'gray' => 'grey',
        'jeshil' => 'green',
        'green' => 'green',
    ];

    /** @var array<string, string> */
    private const PRODUCT_TYPES = [
        'patika' => 'sneakers',
        'atlete' => 'sneakers',
        'sneakers' => 'sneakers',
        'sneaker' => 'sneakers',
        'këpucë sportive' => 'sneakers',
        'kepuce sportive' => 'sneakers',
        'këpuc sportive' => 'sneakers',
        'kepuc sportive' => 'sneakers',
        'mabthje' => 'shoes',
        'mbathje' => 'shoes',
        'këpucë' => 'shoes',
        'kepuce' => 'shoes',
        'këpuc' => 'shoes',
        'shoes' => 'shoes',
        'boots' => 'boots',
        'çizme' => 'boots',
        'cizme' => 'boots',
        'trainers' => 'trainers',
        'xhaket' => 'jacket',
        'jacket' => 'jacket',
        'dress' => 'dress',
        'fustan' => 'dress',
        'cap' => 'cap',
        'kapa' => 'cap',
        'kapela' => 'cap',
        'kapelë' => 'cap',
        'kapele' => 'cap',
        'hat' => 'cap',
        'beanie' => 'cap',
        'blouse' => 'blouse',
        'bluz' => 'blouse',
        'bluze' => 'blouse',
        'bluzë' => 'blouse',
        'jeans' => 'jeans',
        'xhinse' => 'jeans',
        'xhins' => 'jeans',
        'denim' => 'jeans',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function fromQuery(string $query): array
    {
        $lower = mb_strtolower(trim($query));
        $result = [];

        foreach (self::BRANDS as $brand) {
            if (preg_match('/\b'.preg_quote($brand, '/').'\b/u', $lower)) {
                $result['brand'] = $brand;
                break;
            }
        }

        $colors = [];
        foreach (self::COLORS as $needle => $canonical) {
            if (str_contains($lower, $needle)) {
                $colors[] = $canonical;
            }
        }
        $colors = array_values(array_unique($colors));
        if (count($colors) === 1) {
            $result['color'] = $colors[0];
        } elseif (count($colors) > 1) {
            $result['color'] = 'multicolor';
            $result['colors'] = $colors;
        }

        foreach (self::PRODUCT_TYPES as $needle => $type) {
            if (str_contains($lower, $needle)) {
                $result['product_type'] = $type;
                break;
            }
        }

        if (preg_match('/ngjyr[aëë].*t[eë]\s+ndryshme|multi\s?-?\s?color|multicolor|multi\s?colour|shumë\s+ngjyra|shume\s+ngjyra|different\s+colou?rs/u', $lower)) {
            $result['color'] = 'multicolor';
        }

        $size = ShoeSize::extractFromText($query);
        if ($size !== null) {
            $result['size'] = $size;
        }

        if (! empty($result['brand']) && empty($result['product_type']) && ! empty($result['size'])) {
            $result['product_type'] = 'sneakers';
        }

        if (str_contains($lower, 'femra') || str_contains($lower, 'women') || str_contains($lower, 'dama')) {
            $result['gender'] = 'women';
        } elseif (str_contains($lower, 'meshkuj') || str_contains($lower, 'men') || str_contains($lower, 'burra')) {
            $result['gender'] = 'men';
        }

        $apparelSize = self::extractApparelSize($query);
        if ($apparelSize !== null) {
            $result['size'] = $apparelSize;
        }

        return $result;
    }

    public static function extractApparelSize(string $text): ?string
    {
        if (preg_match('/\b(?:size|madh[eë]sia|nr|numri)\s*[:#]?\s*(XXS|XS|S|M|L|XL|XXL|XXXL|2XL|3XL)\b/ui', $text, $m)) {
            return strtoupper($m[1]);
        }

        if (preg_match('/\b(XXS|XS|S|M|L|XL|XXL|XXXL|2XL|3XL)\b/u', $text, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    /**
     * English marketplace query for international fashion searches.
     *
     * @param  array<string, mixed>  $parsed
     */
    public static function marketplaceQuery(array $parsed): string
    {
        $parts = [];
        $gender = CategoryCatalog::normalizeGender((string) ($parsed['gender'] ?? ''));

        if (in_array($gender, ['men', 'male'], true)) {
            $parts[] = 'men';
        } elseif (in_array($gender, ['women', 'female'], true)) {
            $parts[] = 'women';
        }

        $type = self::normalizeType((string) ($parsed['product_type'] ?? ''));
        if ($type !== '') {
            $parts[] = self::marketplaceSearchTerm($type, $gender);
        }

        if (! empty($parsed['brand'])) {
            $parts[] = str_replace('_', ' ', FashionFilterCatalog::slugify((string) $parsed['brand']));
        }

        $color = mb_strtolower((string) ($parsed['color'] ?? ''));
        if ($color !== '' && $color !== 'multicolor') {
            $parts[] = $color;
        } elseif ($color === 'multicolor') {
            $parts[] = 'multicolor';
        }

        $size = strtoupper(trim((string) ($parsed['size'] ?? '')));
        if ($size !== '' && preg_match('/^(XXS|XS|S|M|L|XL|XXL|XXXL|2XL|3XL)$/u', $size)) {
            $parts[] = 'size '.$size;
        }

        return trim(implode(' ', array_filter($parts)));
    }

    public static function normalizeType(string $type): string
    {
        $type = mb_strtolower(trim($type));
        if ($type === '') {
            return '';
        }

        if (isset(self::PRODUCT_TYPES[$type])) {
            return self::PRODUCT_TYPES[$type];
        }

        if (preg_match('/\b(sneaker|patika|atlete|trainer|këpucë sportive|kepuce sportive|sportive)\b/u', $type)) {
            return 'sneakers';
        }

        if (preg_match('/\b(këpuc|kepuc|shoe|mbathje|mabthje)\b/u', $type)) {
            return str_contains($type, 'sport') || str_contains($type, 'sneaker') || str_contains($type, 'atlete')
                ? 'sneakers'
                : 'shoes';
        }

        foreach (self::PRODUCT_TYPES as $needle => $canonical) {
            if (str_contains($type, $needle)) {
                return $canonical;
            }
        }

        return $type;
    }

    /**
     * Explicit fashion keywords from the buyer query (overrides AI when present).
     *
     * @return array<string, mixed>
     */
    public static function explicitFromQuery(string $query): array
    {
        return self::fromQuery($query);
    }

    /**
     * Marketplace search term for a normalized product type + gender context.
     */
    public static function marketplaceSearchTerm(string $type, ?string $gender = null): string
    {
        $type = self::normalizeType($type);
        $gender = CategoryCatalog::normalizeGender($gender ?? '');

        if ($type === 'blouse' && in_array($gender, ['men', 'male'], true)) {
            return 'dress shirt';
        }

        $needles = FashionFilterCatalog::typeNeedles($type);

        return $needles[0] ?? str_replace('_', ' ', $type);
    }

    public static function isFashionQuery(string $query): bool
    {
        $parsed = self::fromQuery($query);

        return ! empty($parsed['brand'])
            || ! empty($parsed['product_type'])
            || ! empty($parsed['size']);
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public static function productMatchesType(array $product, string $type): bool
    {
        $type = self::normalizeType($type);
        if ($type === '') {
            return true;
        }

        $title = mb_strtolower((string) ($product['title'] ?? ''));
        $tags = array_map('mb_strtolower', $product['tags'] ?? []);
        $itemType = mb_strtolower((string) ($product['product_type'] ?? ''));

        if ($itemType === $type || str_contains($itemType, $type)) {
            return true;
        }

        $needles = match ($type) {
            'sneakers', 'trainers' => ['sneaker', 'trainer', 'atlete', 'patika', 'këpuc', 'kepuc', 'shoe', 'running', 'court', 'slipstream', 'smash', 'r78', 'caven', 'anzarun', 'rebound', 'flyer', 'st runner', 'disperse', 'softride', 'x-cell', 'flexfocus', 'easy rider', 'extend lite', 'trinity', 'runtamed'],
            'shoes' => ['shoe', 'sneaker', 'atlete', 'patika', 'këpuc', 'kepuc', 'trainer', 'boot', 'çizme', 'cizme'],
            'boots' => ['boot', 'çizme', 'cizme', 'timberland'],
            'jacket' => ['jacket', 'xhaket', 'xhaketa', 'blouson', 'windbreaker'],
            'dress' => ['dress', 'fustan', 'robe'],
            'pants' => ['pant', 'trener', 'track', 'jogger'],
            'shirt' => ['shirt', 'tee', 'bluz', 't-shirt', 'polo'],
            'blouse' => ['blouse', 'bluz', 'bluze', 'bluse', 'top', 'camiseta', 'camisa', 'blusa'],
            'cap', 'hat', 'beanie', 'kapa', 'kapela', 'kapelë', 'kapele' => ['cap', 'kapel', 'kapele', 'kapela', 'hat', 'beanie', 'snapback', 'club cap', 'df club', 'fitted', 'trucker', 'mütze', 'mutze', 'kappe', 'badekappe', 'baseballmütze', 'baseballmutze'],
            default => FashionFilterCatalog::typeNeedles($type),
        };

        $exclude = match ($type) {
            'sneakers', 'trainers', 'shoes' => ['jacket', 'xhaket', 'pantallona', 'pants', 'track pant', 'shorts', 'shorce', 't-shirt', ' tee', 'bluz', 'dress', 'fustan', 'backpack', 'çant', 'cant', ' suit', 'sweatshirt', 'hoodie'],
            'cap', 'hat', 'beanie', 'kapa', 'kapela', 'kapelë', 'kapele' => ['captain', 'captainbinde', 'armband', 'binde', 'schlapphut', 'bucket'],
            default => [],
        };

        foreach ($exclude as $bad) {
            if (str_contains($title, $bad)) {
                return false;
            }
        }

        foreach ($needles as $needle) {
            if (self::titleContainsNeedle($title, $needle) || in_array($needle, $tags, true)) {
                return true;
            }
        }

        if (KosovoFashionIntent::isFootwearType($type)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public static function matchesColor(array $product, string $color, bool $allowUnknown = false): bool
    {
        $color = mb_strtolower(trim($color));
        if ($color === '' || $color === 'multicolor') {
            return true;
        }

        $title = mb_strtolower((string) ($product['title'] ?? ''));
        $tags = array_map('mb_strtolower', $product['tags'] ?? []);

        $aliases = match ($color) {
            'black' => ['black', 'zez', 'zeze', 'schwarz', 'noir', 'negro'],
            'white' => ['white', 'bardh', 'bardhe', 'weiss', 'blanc'],
            'grey', 'gray' => ['grey', 'gray', 'gri', 'hiri', 'silver', 'grau'],
            'blue' => ['blue', 'blu', 'navy', 'kalter', 'kaltër'],
            'red' => ['red', 'kuq', 'rot'],
            'green' => ['green', 'jeshil', 'grün'],
            default => [$color],
        };

        foreach ($aliases as $alias) {
            if (str_contains($title, $alias) || in_array($alias, $tags, true)) {
                return true;
            }
        }

        return $allowUnknown;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public static function matchesGender(array $product, string $gender): bool
    {
        $gender = CategoryCatalog::normalizeGender($gender);
        if ($gender === null || $gender === '') {
            return true;
        }

        $title = mb_strtolower((string) ($product['title'] ?? ''));
        $url = mb_strtolower((string) ($product['url'] ?? ''));
        $store = mb_strtolower((string) ($product['store'] ?? $product['source'] ?? ''));
        $itemGender = CategoryCatalog::normalizeGender((string) ($product['gender'] ?? ''));

        if ($itemGender === 'women' && in_array($gender, ['men', 'male'], true)) {
            return false;
        }
        if ($itemGender === 'men' && in_array($gender, ['women', 'female'], true)) {
            return false;
        }
        if ($itemGender === 'men' && in_array($gender, ['men', 'male'], true)) {
            return true;
        }
        if ($itemGender === 'women' && in_array($gender, ['women', 'female'], true)) {
            return true;
        }
        if ($itemGender === 'unisex') {
            return true;
        }

        $isKids = (bool) preg_match('/\b(jr\.?|junior|kids|kid|children|child|fëmij|femij|vogël|vogel|infant|toddler)\b/u', $title);
        $isWomen = (bool) preg_match('/\b(women|woman|womens|women\'s|female|femra|dama|for her|wmns|ladies|lady\'s|maternity|bride|femme)\b/u', $title.' '.$url.' '.$store);
        $isMen = (bool) preg_match('/\b(men|man|mens|men\'s|male|meshkuj|for him|homme|masculine)\b/u', $title.' '.$url);

        return match ($gender) {
            'male', 'men' => ! $isKids && ! $isWomen && ($isMen || self::isLikelyMensListing($product)),
            'female', 'women' => ! $isKids && ! $isMen && ($isWomen || ! self::isLikelyMensListing($product)),
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private static function isLikelyMensListing(array $product): bool
    {
        $title = mb_strtolower((string) ($product['title'] ?? ''));
        $type = self::normalizeType((string) ($product['product_type'] ?? ''));

        if ($type === 'blouse' && ! preg_match('/\b(dress shirt|button.?down|oxford|formal shirt)\b/u', $title)) {
            return false;
        }

        return preg_match('/\b(dress shirt|button.?down|oxford|formal shirt|mens|men\'s)\b/u', $title) === 1;
    }

    private static function titleContainsNeedle(string $title, string $needle): bool
    {
        if (mb_strlen($needle) <= 3) {
            return preg_match('/\b'.preg_quote($needle, '/').'\b/u', $title) === 1;
        }

        return str_contains($title, $needle);
    }
}
