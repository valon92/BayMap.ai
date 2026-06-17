<?php

namespace App\Services\Marketplace;

use App\Services\Marketplace\Scrapers\ProductListingNormalizer;
use Illuminate\Support\Facades\Log;

/**
 * Runs Browse AI robots for configured anti-bot platforms (mobile.de, heycar, …).
 */
class BrowseAiScrapeService
{
    public function __construct(private BrowseAiClient $client) {}

    public function shouldUse(string $platformKey): bool
    {
        if (! $this->client->isConfigured()) {
            return false;
        }

        $platform = (array) config("browse_ai.platforms.{$platformKey}", []);

        return ! empty($platform['robot_id']);
    }

    public function fetchHtml(string $platformKey, string $url): string
    {
        $payload = $this->runPlatformRobot($platformKey, $url);
        if ($payload === null) {
            return '';
        }

        $html = $this->extractHtml($payload, $platformKey);
        if ($html === '' || $this->isBlockedHtml($html)) {
            return '';
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $parsedQuery
     * @return array<int, array<string, mixed>>
     */
    public function scrapeListings(string $platformKey, string $url, array $platform, array $parsedQuery): array
    {
        $payload = $this->runPlatformRobot($platformKey, $url);
        if ($payload === null) {
            return [];
        }

        $items = $this->mapCapturedLists($payload, $platformKey, $platform);
        if ($items !== []) {
            return ProductListingNormalizer::filterForIntent($items, $parsedQuery);
        }

        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function runPlatformRobot(string $platformKey, string $url): ?array
    {
        $config = (array) config("browse_ai.platforms.{$platformKey}", []);
        $robotId = (string) ($config['robot_id'] ?? '');
        if ($robotId === '') {
            return null;
        }

        $urlInput = (string) ($config['url_input'] ?? 'originUrl');
        $input = [$urlInput => $url];

        foreach ((array) ($config['extra_input'] ?? []) as $key => $value) {
            $input[(string) $key] = (string) $value;
        }

        Log::info('Browse AI scrape started', [
            'platform' => $platformKey,
            'robot_id' => $robotId,
            'url' => $url,
        ]);

        return $this->client->runRobotAndWait($robotId, $input);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractHtml(array $payload, string $platformKey): string
    {
        $texts = (array) ($payload['capturedTexts'] ?? []);
        $candidates = array_merge(
            (array) config("browse_ai.platforms.{$platformKey}.html_fields", []),
            ['Page HTML', 'page_html', 'html', 'Full Page HTML', 'full_page_html'],
        );

        foreach ($candidates as $field) {
            $value = $texts[$field] ?? null;
            if (is_string($value) && strlen($value) > 500) {
                return $value;
            }
        }

        foreach ($texts as $value) {
            if (is_string($value) && strlen($value) > 2000 && str_contains($value, '<html')) {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    private function mapCapturedLists(array $payload, string $platformKey, array $platform): array
    {
        $lists = (array) ($payload['capturedLists'] ?? []);
        if ($lists === []) {
            return [];
        }

        $config = (array) config("browse_ai.platforms.{$platformKey}", []);
        $listName = (string) ($config['list_name'] ?? '');
        $rows = $listName !== '' && isset($lists[$listName]) && is_array($lists[$listName])
            ? $lists[$listName]
            : $this->firstNonEmptyList($lists);

        if (! is_array($rows) || $rows === []) {
            return [];
        }

        $fields = (array) ($config['fields'] ?? [
            'title' => 'Title',
            'price' => 'Price',
            'url' => 'URL',
            'image' => 'Image',
            'location' => 'Location',
        ]);

        $storeKey = (string) ($platform['_key'] ?? $platformKey);
        $items = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = trim((string) ($row[$fields['title'] ?? 'Title'] ?? $row['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $priceRaw = (string) ($row[$fields['price'] ?? 'Price'] ?? $row['price'] ?? '');
            $price = $this->parsePrice($priceRaw);
            $category = (string) ($config['category'] ?? $platform['category'] ?? 'automotive');
            $minPrice = $category === 'real_estate' ? 50000 : 800;
            if ($price > 0 && $price < $minPrice) {
                continue;
            }

            $url = trim((string) ($row[$fields['url'] ?? 'URL'] ?? $row['url'] ?? $row['link'] ?? '#'));
            $baseUrl = rtrim((string) ($platform['base_url'] ?? ''), '/');
            if ($url !== '#' && ! str_starts_with($url, 'http')) {
                $url = $baseUrl.$url;
            }

            $image = $row[$fields['image'] ?? 'Image'] ?? $row['image'] ?? null;
            $location = trim((string) ($row[$fields['location'] ?? 'Location'] ?? $row['location'] ?? ($platform['location'] ?? 'Germany')));

            $baseItem = [
                'product_id' => md5($title.$url),
                'title' => $title,
                'price' => $price,
                'image' => is_string($image) ? $image : null,
                'url' => $url,
                'location' => $location,
                'condition' => 'used',
            ];

            if ($category === 'real_estate') {
                $items[] = ProductListingNormalizer::finalize($platform, $storeKey, array_merge($baseItem, [
                    'category' => 'real_estate',
                    'property_type' => 'apartment',
                    'country_code' => strtoupper((string) ($platform['country'] ?? 'CH')),
                    'currency' => (string) ($platform['currency'] ?? 'CHF'),
                    'live' => true,
                ]));
            } else {
                $items[] = ProductListingNormalizer::finalizeAutomotive($platform, $storeKey, array_merge($baseItem, [
                    'brand' => $this->brandFromTitle($title),
                    'model' => $this->modelFromTitle($title),
                    'year' => $this->yearFromTitle($title),
                ]));
            }

            if (count($items) >= 20) {
                break;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $lists
     * @return array<int, mixed>
     */
    private function firstNonEmptyList(array $lists): array
    {
        foreach ($lists as $rows) {
            if (is_array($rows) && $rows !== []) {
                return $rows;
            }
        }

        return [];
    }

    private function parsePrice(string $raw): float
    {
        $digits = preg_replace('/[^\d]/', '', $raw) ?? '';

        return $digits !== '' ? (float) $digits : 0.0;
    }

    private function yearFromTitle(string $title): ?int
    {
        if (preg_match('/\b(19|20)\d{2}\b/', $title, $match)) {
            return (int) $match[0];
        }

        return null;
    }

    private function brandFromTitle(string $title): ?string
    {
        $known = ['audi', 'bmw', 'mercedes', 'volkswagen', 'vw', 'porsche', 'opel', 'ford', 'toyota'];
        $lower = mb_strtolower($title);
        foreach ($known as $brand) {
            if (preg_match('/\b'.preg_quote($brand, '/').'\b/ui', $lower)) {
                return $brand === 'vw' ? 'volkswagen' : $brand;
            }
        }

        return null;
    }

    private function modelFromTitle(string $title): ?string
    {
        if (preg_match('/\bgolf\b/i', $title)) {
            return 'golf';
        }

        return null;
    }

    private function isBlockedHtml(string $html): bool
    {
        $lower = mb_strtolower($html);

        return str_contains($lower, 'access denied')
            || str_contains($lower, 'zugriff verweigert')
            || str_contains($lower, 'bot verification');
    }
}
