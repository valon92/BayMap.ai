<?php

namespace App\Support;

/**
 * Rule-based automotive intent from Albanian/English/Dutch car queries.
 */
class AutomotiveIntentParser
{
    /** @var array<string, string> */
    private const COLORS = [
        'e zezë' => 'black',
        'e zeze' => 'black',
        'zezë' => 'black',
        'zeze' => 'black',
        'e bardhë' => 'white',
        'e bardhe' => 'white',
        'bardhë' => 'white',
        'bardhe' => 'white',
        'hiri' => 'grey',
        'gri' => 'grey',
        'grey' => 'grey',
        'gray' => 'grey',
        'black' => 'black',
        'white' => 'white',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function fromQuery(string $query): array
    {
        $lower = mb_strtolower(trim($query));
        $result = [];

        if (preg_match('/\b(audi|bmw|mercedes|mercedes-benz|volkswagen|vw|toyota|honda|ford|porsche|skoda|seat)\b/u', $lower, $m)) {
            $brand = strtolower($m[1]);
            $result['brand'] = match ($brand) {
                'vw' => 'Volkswagen',
                'mercedes', 'mercedes-benz' => 'Mercedes-Benz',
                default => ucfirst($brand),
            };
        }

        if (preg_match('/\b(gle|glc|gla|gls|glb|eqc|eqe|eqs|cls|ml|sl|amg)\b/i', $query, $m)) {
            $result['model'] = strtoupper($m[1]);
        } elseif (preg_match('/\b(q[3578]|x[13567]|a[34678])\b/i', $query, $m)) {
            $result['model'] = strtoupper($m[1]);
        } elseif (preg_match('/\b([ecsa])[-\s]?class\b/i', $query, $m)) {
            $result['model'] = strtoupper($m[1]).'-Class';
        }

        if (preg_match('/\b(20\d{2}|19\d{2})\b/', $query, $m)) {
            $result['year'] = (int) $m[1];
        }

        if (preg_match('/\b(\d+)\s*k\s*(?:km|klm|kilomet)/ui', $lower, $m)) {
            $result['max_km'] = (int) $m[1] * 1000;
        } elseif (preg_match('/\b(?:deri|up to|max|under)\s*(?:ne|në|in)?\s*(\d+)\s*(?:k\s*)?(?:km|klm)/ui', $lower, $m)) {
            $km = (int) $m[1];
            $result['max_km'] = $km < 1000 ? $km * 1000 : $km;
        }

        if (preg_match('/\b(dizell|diesel|tdi)\b/ui', $lower)) {
            $result['fuel'] = 'diesel';
        } elseif (preg_match('/\b(benzin|petrol|benzinë|benzine|tfsi)\b/ui', $lower)) {
            $result['fuel'] = 'petrol';
        } elseif (preg_match('/\b(elektrik|electric|ev)\b/ui', $lower)) {
            $result['fuel'] = 'electric';
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

        return $result;
    }
}
