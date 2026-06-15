<?php

namespace App\Services\Marketplace\Scrapers;

use App\Services\Marketplace\Scrapers\Contracts\ScraperAdapterInterface;

class ScraperAdapterResolver
{
    /** @var array<string, ScraperAdapterInterface> */
    private array $adapters;

    public function __construct(
        CsCartScraperAdapter $csCart,
        WooCommerceScraperAdapter $wooCommerce,
        GenericHtmlScraperAdapter $generic,
        AutomotiveHtmlScraperAdapter $automotive,
        GyDigitalScraperAdapter $gyDigital,
    ) {
        $this->adapters = [
            $csCart->adapterKey() => $csCart,
            $wooCommerce->adapterKey() => $wooCommerce,
            $generic->adapterKey() => $generic,
            $automotive->adapterKey() => $automotive,
            $gyDigital->adapterKey() => $gyDigital,
        ];
    }

    public function for(string $adapterKey): ScraperAdapterInterface
    {
        return $this->adapters[$adapterKey] ?? $this->adapters['generic'];
    }
}
