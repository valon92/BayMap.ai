<?php

namespace App\Services\Valon;

/**
 * Merges and deduplicates Valon Worker outputs before ranking.
 */
class ValonAggregationEngine
{
    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    public function aggregate(array $results): array
    {
        $merged = [];
        $seen = [];

        foreach ($results as $item) {
            $key = $this->dedupeKey($item);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $merged[] = $item;
        }

        return $merged;
    }

    /**
     * @param  array<int, array<string, mixed>>  $existing
     * @param  array<int, array<string, mixed>>  $incoming
     * @return array<int, array<string, mixed>>
     */
    public function merge(array $existing, array $incoming): array
    {
        return $this->aggregate(array_merge($existing, $incoming));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function dedupeKey(array $item): string
    {
        if (! empty($item['fingerprint'])) {
            return 'fp:'.$item['fingerprint'].'|'.($item['source_key'] ?? '');
        }

        return ($item['id'] ?? $item['title'] ?? '').'|'.($item['source_key'] ?? $item['valon_platform'] ?? '');
    }
}
