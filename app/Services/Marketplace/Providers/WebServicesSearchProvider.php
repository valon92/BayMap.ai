<?php

namespace App\Services\Marketplace\Providers;

use App\Contracts\FederatedSearchProviderInterface;
use App\Services\Marketplace\WebServicesBridgeService;
use App\Support\WebServicesIntentParser;

class WebServicesSearchProvider implements FederatedSearchProviderInterface
{
    public function __construct(private WebServicesBridgeService $bridge) {}

    public function sourceKey(): string
    {
        return 'web_services_bridge';
    }

    public function label(): string
    {
        return 'BuyMap Web Services';
    }

    public function mode(): string
    {
        return 'live';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function priority(): int
    {
        return 12;
    }

    public function supportsCategory(string $category): bool
    {
        return $category === 'ai_software';
    }

    public function getSourceName(): string
    {
        return $this->bridge->getSourceName();
    }

    /**
     * @param  array<string, mixed>  $parsedQuery
     * @param  array<string, mixed>  $expandedFilters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $parsedQuery, array $expandedFilters): array
    {
        if (! WebServicesIntentParser::isActive($parsedQuery)
            && ! WebServicesIntentParser::isWebServicesQuery((string) ($parsedQuery['raw_query'] ?? ''))) {
            return [];
        }

        return $this->bridge->search($parsedQuery, $expandedFilters);
    }
}
