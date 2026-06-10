<?php

namespace App\Support;

/**
 * Automotive paint color extraction and matching (DE marketplaces).
 */
class AutomotiveColorResolver
{
    /** @var array<string, array<int, string>> */
    private const TONE_ALIASES = [
        'white' => ['white', 'weiss', 'weiß', 'ivory', 'pearl', 'bardh', 'bardhe', 'polar', 'candy', 'pure-white', 'pure white'],
        'black' => ['black', 'schwarz', 'zez', 'zeze', 'midnight', 'obsidian'],
        'grey' => ['grey', 'gray', 'grau', 'anthrazit', 'graphite', 'hiri', 'gri', 'silvergrey'],
        'silver' => ['silver', 'silber', 'argento'],
        'blue' => ['blue', 'blau', 'navy', 'kalter', 'kaltër', 'blu'],
        'red' => ['red', 'rot', 'kuq', 'burgundy', 'weinrot'],
        'green' => ['green', 'gruen', 'grün', 'gjebr', 'olive'],
        'yellow' => ['yellow', 'gelb'],
        'orange' => ['orange'],
        'brown' => ['brown', 'braun', 'beige', 'bronze'],
    ];

    public static function extractFromAutoScoutUrl(string $path): ?string
    {
        $path = mb_strtolower($path);

        if (preg_match('/-(weiss|wei[sß]|schwarz|silber|grau|anthrazit|rot|blau|beige|gruen|grün|gelb|orange|braun|gold|violett|perlmutt)-/u', $path, $match)) {
            return self::normalizeToken($match[1]);
        }

        return null;
    }

    public static function extractFromText(string $text): ?string
    {
        $lower = mb_strtolower($text);

        foreach (self::TONE_ALIASES as $tone => $aliases) {
            foreach ($aliases as $alias) {
                if (preg_match('/\b'.preg_quote($alias, '/').'\b/u', $lower)) {
                    return $tone;
                }
            }
        }

        return null;
    }

    public static function matchesWanted(?string $productColor, string $wantedColor, string $title = '', bool $allowUnknown = false): bool
    {
        $wantedTone = self::toneFor($wantedColor);
        if ($wantedTone === null) {
            return true;
        }

        if ($productColor !== null && $productColor !== '') {
            $productTone = self::toneFor($productColor) ?? self::toneFor(self::normalizeToken($productColor));

            return $productTone === $wantedTone;
        }

        $fromTitle = self::extractFromText($title);
        if ($fromTitle !== null) {
            return $fromTitle === $wantedTone;
        }

        return $allowUnknown;
    }

    public static function germanSearchKeyword(string $wantedColor): ?string
    {
        return match (self::toneFor($wantedColor)) {
            'white' => 'weiß',
            'black' => 'schwarz',
            'grey' => 'grau',
            'silver' => 'silber',
            'blue' => 'blau',
            'red' => 'rot',
            'green' => 'grün',
            'yellow' => 'gelb',
            'orange' => 'orange',
            'brown' => 'braun',
            default => null,
        };
    }

    public static function extractFromKleinanzeigenBody(string $body): ?string
    {
        if (preg_match('/Lackierung:\s*([^"\\\n]+)/iu', $body, $match)) {
            $tone = self::extractFromText($match[1]);
            if ($tone !== null) {
                return $tone;
            }
        }
        if (preg_match('/Farbe:\s*([^"\\\n]+)/iu', $body, $match)) {
            return self::extractFromText($match[1]);
        }

        return null;
    }

    public static function toneFor(string $color): ?string
    {
        $color = self::normalizeToken($color);

        foreach (self::TONE_ALIASES as $tone => $aliases) {
            if ($color === $tone) {
                return $tone;
            }
            foreach ($aliases as $alias) {
                if ($color === self::normalizeToken($alias)) {
                    return $tone;
                }
            }
        }

        return null;
    }

    public static function autoScoutBodyColorCode(string $wantedColor): ?string
    {
        return match (self::toneFor($wantedColor)) {
            'white' => 'WHITE',
            'black' => 'BLACK',
            'grey', 'silver' => 'GREY',
            'blue' => 'BLUE',
            'red' => 'RED',
            'green' => 'GREEN',
            'yellow' => 'YELLOW',
            'orange' => 'ORANGE',
            'brown' => 'BROWN',
            default => null,
        };
    }

    private static function normalizeToken(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $value);
    }
}
