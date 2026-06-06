<?php

namespace App\Services\Agents;

use App\Support\CategoryCatalog;

/**
 * Resolves category → controlled agent pool definitions.
 */
class AgentPoolRegistry
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function poolForCategory(string $category): array
    {
        $category = CategoryCatalog::normalize($category);
        $pools = config('agent_pools.pools', []);

        if (isset($pools[$category]['extends'])) {
            $parent = (string) $pools[$category]['extends'];

            return $pools[$parent]['agents'] ?? $pools['default']['agents'] ?? [];
        }

        return $pools[$category]['agents'] ?? $pools['default']['agents'] ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function sourceKeysForCategory(string $category): array
    {
        $keys = [];
        foreach ($this->poolForCategory($category) as $agent) {
            foreach ($agent['sources'] ?? [] as $source) {
                $keys[] = $this->normalizeSourceKey($source);
            }
        }

        return array_values(array_unique($keys));
    }

    public function trustScore(string $sourceKey): int
    {
        $scores = config('agent_pools.trust_scores', []);
        $key = $this->normalizeSourceKey($sourceKey);

        return (int) ($scores[$key] ?? $scores['default'] ?? 70);
    }

    public function normalizeSourceKey(string $source): string
    {
        return strtolower(str_replace([' ', '-'], '_', trim($source)));
    }

    public function matchesSourceKey(string $providerKey, array $activatedSources): bool
    {
        if ($activatedSources === []) {
            return true;
        }

        $providerNorm = $this->normalizeSourceKey($providerKey);

        foreach ($activatedSources as $source) {
            $sourceNorm = $this->normalizeSourceKey($source);
            if ($providerNorm === $sourceNorm) {
                return true;
            }
            if (str_contains($providerNorm, $sourceNorm) || str_contains($sourceNorm, $providerNorm)) {
                return true;
            }
            if (str_replace('.', '_', $providerKey) === str_replace('.', '_', $source)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, float>
     */
    public function rankingWeights(): array
    {
        return config('agent_pools.ranking_weights', [
            'exact_match' => 0.40,
            'price_relevance' => 0.25,
            'location_proximity' => 0.15,
            'availability' => 0.10,
            'platform_trust' => 0.10,
        ]);
    }
}
