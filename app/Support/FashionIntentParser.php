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

        return $result;
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
            'cap', 'hat', 'beanie', 'kapa', 'kapela', 'kapelë', 'kapele' => ['cap', 'kapel', 'kapele', 'kapela', 'hat', 'beanie', 'snapback', 'club cap', 'df club', 'fitted', 'trucker', 'mütze', 'mutze', 'kappe', 'badekappe', 'baseballmütze', 'baseballmutze'],
            default => [$type],
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
        $isKids = (bool) preg_match('/\b(jr\.?|junior|kids|kid|children|child|fëmij|femij|vogël|vogel|infant|toddler)\b/u', $title);
        $isWomen = (bool) preg_match('/\b(women|woman|female|femra|dama|for her|wmns|w\s|ladies)\b/u', $title);
        $isMen = (bool) preg_match('/\b(men|man|male|meshkuj|for him|homme)\b/u', $title);

        return match ($gender) {
            'male', 'men' => ! $isKids && ! $isWomen,
            'female', 'women' => ! $isKids && ! $isMen,
            default => true,
        };
    }

    private static function titleContainsNeedle(string $title, string $needle): bool
    {
        if (mb_strlen($needle) <= 3) {
            return preg_match('/\b'.preg_quote($needle, '/').'\b/u', $title) === 1;
        }

        return str_contains($title, $needle);
    }
}
