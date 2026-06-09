<?php

namespace App\Support;

/**
 * Detects physical / digital book shopping intent from natural language (EN + SQ).
 */
class BookIntentParser
{
    /** @var array<int, string> */
    private const BOOK_MARKERS = [
        'libër', 'liber', 'librin', 'librari', 'book', 'books', 'roman', 'novel',
        'thriller', 'psikologjik', 'paperback', 'hardcover', 'ebook', 'e-book',
        'audiobook', 'bestseller', 'papritur', 'short story', 'tregim',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function fromQuery(string $query): array
    {
        $lower = mb_strtolower(trim($query));
        if (! self::mentionsBook($lower)) {
            return [];
        }

        $intent = [
            'category' => 'online_education',
            'product_type' => 'book',
            'format' => self::detectFormat($lower),
            'genre' => self::detectGenre($lower),
        ];

        if (preg_match('/\b(psikologjik|psychological)\b/u', $lower)) {
            $intent['genre'] = 'psychological_thriller';
        }

        if (preg_match('/\b(shkurtër|shkurter|short)\b/u', $lower)) {
            $intent['length'] = 'short';
        }

        return array_filter($intent, fn ($v) => $v !== null && $v !== '');
    }

    public static function mentionsBook(string $lower): bool
    {
        foreach (self::BOOK_MARKERS as $marker) {
            if (preg_match('/\b'.preg_quote($marker, '/').'\b/u', $lower)) {
                return true;
            }
        }

        return false;
    }

    private static function detectFormat(string $lower): ?string
    {
        if (preg_match('/\b(e-?book|kindle|digital)\b/u', $lower)) {
            return 'ebook';
        }
        if (preg_match('/\b(audiobook|audio book)\b/u', $lower)) {
            return 'audiobook';
        }
        if (preg_match('/\b(hardcover|hardback)\b/u', $lower)) {
            return 'hardcover';
        }
        if (preg_match('/\b(paperback|libër|liber|book)\b/u', $lower)) {
            return 'paperback';
        }

        return 'paperback';
    }

    private static function detectGenre(string $lower): ?string
    {
        $map = [
            'thriller' => 'thriller',
            'psikologjik' => 'psychological_thriller',
            'psychological' => 'psychological_thriller',
            'mystery' => 'mystery',
            'romance' => 'romance',
            'fantasy' => 'fantasy',
            'sci-fi' => 'sci_fi',
            'biography' => 'biography',
            'horror' => 'horror',
        ];

        foreach ($map as $needle => $genre) {
            if (preg_match('/\b'.preg_quote($needle, '/').'\b/u', $lower)) {
                return $genre;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function productMatchesGenre(array $item, string $genre): bool
    {
        $wanted = mb_strtolower($genre);
        $title = mb_strtolower($item['title'] ?? '');
        $tags = array_map('mb_strtolower', $item['tags'] ?? []);

        $needles = match ($wanted) {
            'psychological_thriller' => ['psychological', 'thriller', 'psikologjik', 'mind-bending', 'twist'],
            'thriller' => ['thriller', 'suspense'],
            default => [$wanted],
        };

        foreach ($needles as $needle) {
            if (str_contains($title, $needle) || in_array($needle, $tags, true)) {
                return true;
            }
        }

        return $wanted === 'thriller' && str_contains($title, 'thriller');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function productMatchesType(array $item, string $type): bool
    {
        $type = mb_strtolower(trim($type));
        $bookTypes = ['book', 'libër', 'liber', 'librin', 'ebook', 'audiobook', 'paperback', 'hardcover', 'course'];

        if (! in_array($type, $bookTypes, true)) {
            return true;
        }

        $itemType = mb_strtolower((string) ($item['product_type'] ?? ''));
        if ($itemType !== '' && in_array($itemType, $bookTypes, true)) {
            return true;
        }

        $title = mb_strtolower($item['title'] ?? '');
        $tags = array_map('mb_strtolower', $item['tags'] ?? []);
        $needles = match ($type) {
            'ebook' => ['ebook', 'e-book', 'kindle', 'digital'],
            'audiobook' => ['audiobook', 'audio book'],
            'paperback' => ['paperback', 'libër', 'liber', 'book', 'roman', 'thriller', 'novel'],
            'hardcover' => ['hardcover', 'hardback'],
            'course' => ['course', 'kurs', 'certification'],
            default => ['book', 'libër', 'liber', 'roman', 'thriller', 'novel', 'paperback', 'bestseller'],
        };

        foreach ($needles as $needle) {
            if (str_contains($title, $needle) || in_array($needle, $tags, true)) {
                return true;
            }
        }

        return false;
    }
}
