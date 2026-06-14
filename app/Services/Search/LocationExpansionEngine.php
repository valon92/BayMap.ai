<?php

namespace App\Services\Search;

/**
 * Progressive location expansion — widens search radius when local results are insufficient.
 *
 * Level 1: City → Level 2: Country → Level 3: Regional → Level 4: Europe → Level 5: Global
 */
class LocationExpansionEngine
{
    private int $currentLevelIndex = 0;

    /** @var array<int, array<string, mixed>> */
    private array $allTiers = [];

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     */
    public function initialize(array $tiers): void
    {
        $this->allTiers = $this->normalizeTiers($tiers);
        $this->currentLevelIndex = 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activeTiers(): array
    {
        if ($this->allTiers === []) {
            return [['level' => 'international', 'label' => 'International', 'suffix' => '']];
        }

        return array_slice($this->allTiers, 0, $this->currentLevelIndex + 1);
    }

    public function canExpand(): bool
    {
        return $this->currentLevelIndex < count($this->allTiers) - 1;
    }

    public function expand(): void
    {
        if ($this->canExpand()) {
            $this->currentLevelIndex++;
        }
    }

    public function currentLevel(): string
    {
        $active = $this->activeTiers();
        $last = end($active);

        return is_array($last) ? (string) ($last['level'] ?? 'international') : 'international';
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public function shouldExpand(int $resultCount, array $parsed = []): bool
    {
        if (! empty($parsed['search_target'])) {
            return false;
        }

        $threshold = (int) config('agent_pools.min_results_before_expand', 3);

        return $resultCount < $threshold && $this->canExpand();
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTiers(array $tiers): array
    {
        $normalized = [];
        $seen = [];

        foreach ($tiers as $tier) {
            $level = (string) ($tier['level'] ?? 'international');
            if (isset($seen[$level])) {
                continue;
            }
            $seen[$level] = true;
            $normalized[] = $tier;
        }

        $order = ['city', 'country', 'region', 'europe', 'international'];
        usort($normalized, function (array $a, array $b) use ($order) {
            $ia = array_search($a['level'] ?? 'international', $order, true);
            $ib = array_search($b['level'] ?? 'international', $order, true);

            return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
        });

        return $normalized !== [] ? $normalized : [
            ['level' => 'international', 'label' => 'International', 'suffix' => ''],
        ];
    }
}
