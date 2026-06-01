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
        'sneakers' => 'sneakers',
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

    public static function isFashionQuery(string $query): bool
    {
        $parsed = self::fromQuery($query);

        return ! empty($parsed['brand'])
            || ! empty($parsed['product_type'])
            || ! empty($parsed['size']);
    }
}
