<?php

namespace App\Contracts;

/**
 * Connector contract for the federated search platform.
 * Each marketplace / API implements this adapter — products are never stored locally.
 */
interface FederatedSearchProviderInterface extends MarketplaceSearchInterface
{
    public function sourceKey(): string;

    public function label(): string;

    /** @return 'live'|'demo' */
    public function mode(): string;

    public function isAvailable(): bool;

    public function priority(): int;

    public function supportsCategory(string $category): bool;
}
