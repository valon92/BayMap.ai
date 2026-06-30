<?php

/**
 * Global auto parts & machinery retailers — online sellers by country.
 * Merged during catalog:sync (see CatalogSyncService).
 *
 * @return array<string, array<string, mixed>>
 */
$search = fn (string $path) => ['search_template' => $path];

return array_merge(

    // ── Worldwide / multi-country ────────────────────────────────────────
    [
        'rockauto_ww' => ['adapter' => 'generic', 'label' => 'RockAuto', 'country' => 'WW', 'global' => true, 'categories' => ['automotive_parts'], 'base_url' => 'https://www.rockauto.com', 'priority' => 8, ...$search('/en/catalog/{query}')],
        'amazon_automotive_ww' => ['adapter' => 'generic', 'label' => 'Amazon Automotive', 'country' => 'WW', 'global' => true, 'categories' => ['automotive_parts'], 'base_url' => 'https://www.amazon.com', 'priority' => 12, ...$search('/s?k={query}&i=automotive')],
        'ebay_motors_ww' => ['adapter' => 'generic', 'label' => 'eBay Motors', 'country' => 'WW', 'global' => true, 'categories' => ['automotive_parts', 'automotive'], 'base_url' => 'https://www.ebay.com', 'priority' => 10, ...$search('/sch/i.html?_nkw={query}&_sacat=6000')],
        'machinio_parts_ww' => ['adapter' => 'generic', 'label' => 'Machinio Machinery', 'country' => 'WW', 'global' => true, 'categories' => ['automotive_parts', 'industrial_b2b'], 'base_url' => 'https://www.machinio.com', 'priority' => 18, ...$search('/search?q={query}')],
        'truck1_ww' => ['adapter' => 'generic', 'label' => 'Truck1 Truck Parts', 'country' => 'WW', 'global' => true, 'categories' => ['automotive_parts', 'industrial_b2b'], 'base_url' => 'https://www.truck1.eu', 'priority' => 22, ...$search('/search?q={query}')],
    ],

    // ── Germany (DE) ───────────────────────────────────────────────────
    [
        'autodoc_de' => ['adapter' => 'generic', 'label' => 'Autodoc Germany', 'country' => 'DE', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.de', 'priority' => 5, ...$search('/search?keyword={query}')],
        'kfzteile24_de' => ['adapter' => 'generic', 'label' => 'kfzteile24', 'country' => 'DE', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.kfzteile24.de', 'priority' => 6, ...$search('/index.cgi?rm=articleSearch&search={query}')],
        'atp_autoteile_de' => ['adapter' => 'generic', 'label' => 'ATP Autoteile', 'country' => 'DE', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.atp-autoteile.de', 'priority' => 8, ...$search('/de/search?query={query}')],
        'mister_auto_de' => ['adapter' => 'generic', 'label' => 'Mister Auto Germany', 'country' => 'DE', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.mister-auto.de', 'priority' => 9, ...$search('/de/c/search?q={query}')],
        'oscaro_de' => ['adapter' => 'generic', 'label' => 'Oscaro Germany', 'country' => 'DE', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.oscaro.de', 'priority' => 10, ...$search('/de/search?q={query}')],
        'carparts_germany_de' => ['adapter' => 'generic', 'label' => 'Carparts Germany', 'country' => 'DE', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.carparts.com', 'priority' => 14, ...$search('/search?q={query}')],
        'amazon_automotive_de' => ['adapter' => 'generic', 'label' => 'Amazon.de Automotive', 'country' => 'DE', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.amazon.de', 'priority' => 11, ...$search('/s?k={query}&i=automotive')],
        'reifen_de' => ['adapter' => 'generic', 'label' => 'Reifen.com', 'country' => 'DE', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.reifen.com', 'priority' => 20, ...$search('/de-de/search?q={query}')],
        'motointegrator_de' => ['adapter' => 'generic', 'label' => 'Motointegrator Germany', 'country' => 'DE', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.motointegrator.de', 'priority' => 12, ...$search('/de/search/{query}')],
        'pro4matic_de' => [
            'adapter' => 'generic',
            'label' => 'Pro4matic',
            'country' => 'DE',
            'categories' => ['automotive_parts'],
            'base_url' => 'https://pro4matic.com/de',
            'currency' => 'EUR',
            'locale' => 'de-DE',
            'location' => 'Germany',
            'priority' => 4,
            'scraper' => 'pro4matic',
            'search_template' => '/pesquisa.php?search={query}',
        ],
    ],

    // ── Switzerland (CH) ─────────────────────────────────────────────────
    [
        'autodoc_ch' => ['adapter' => 'generic', 'label' => 'Autodoc Switzerland', 'country' => 'CH', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.ch', 'priority' => 5, ...$search('/search?keyword={query}')],
        'oscaro_ch' => ['adapter' => 'generic', 'label' => 'Oscaro Switzerland', 'country' => 'CH', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.oscaro.ch', 'priority' => 8, ...$search('/ch/search?q={query}')],
        'car_part_ch' => ['adapter' => 'generic', 'label' => 'car-part.ch', 'country' => 'CH', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.car-part.ch', 'priority' => 10, ...$search('/search?q={query}')],
        'allparts_ch' => ['adapter' => 'generic', 'label' => 'Allparts Switzerland', 'country' => 'CH', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.allparts.ch', 'priority' => 12, ...$search('/search?q={query}')],
        'amazon_automotive_ch' => ['adapter' => 'generic', 'label' => 'Amazon.ch Automotive', 'country' => 'CH', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.amazon.de', 'priority' => 14, ...$search('/s?k={query}&i=automotive')],
    ],

    // ── Austria (AT) ─────────────────────────────────────────────────────
    [
        'autodoc_at' => ['adapter' => 'generic', 'label' => 'Autodoc Austria', 'country' => 'AT', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.at', 'priority' => 5, ...$search('/search?keyword={query}')],
        'autoparts24_at' => ['adapter' => 'generic', 'label' => 'Autoparts24 Austria', 'country' => 'AT', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autoparts24.at', 'priority' => 8, ...$search('/search?q={query}')],
        'mister_auto_at' => ['adapter' => 'generic', 'label' => 'Mister Auto Austria', 'country' => 'AT', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.mister-auto.at', 'priority' => 10, ...$search('/at/c/search?q={query}')],
        'willhaben_parts_at' => ['adapter' => 'generic', 'label' => 'Willhaben Auto Parts', 'country' => 'AT', 'categories' => ['automotive_parts', 'marketplace'], 'base_url' => 'https://www.willhaben.at', 'priority' => 15, ...$search('/iad/autoteile?keyword={query}')],
    ],

    // ── France (FR) ──────────────────────────────────────────────────────
    [
        'oscaro_fr' => ['adapter' => 'generic', 'label' => 'Oscaro France', 'country' => 'FR', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.oscaro.com', 'priority' => 5, ...$search('/fr/search?q={query}')],
        'mister_auto_fr' => ['adapter' => 'generic', 'label' => 'Mister Auto France', 'country' => 'FR', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.mister-auto.com', 'priority' => 6, ...$search('/fr/c/search?q={query}')],
        'autodoc_fr' => ['adapter' => 'generic', 'label' => 'Autodoc France', 'country' => 'FR', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.fr', 'priority' => 7, ...$search('/search?keyword={query}')],
        'norauto_fr' => ['adapter' => 'generic', 'label' => 'Norauto France', 'country' => 'FR', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.norauto.fr', 'priority' => 8, ...$search('/fr/search?q={query}')],
        'gsf_fr' => ['adapter' => 'generic', 'label' => 'GSF Car Parts France', 'country' => 'FR', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.gsf.fr', 'priority' => 12, ...$search('/recherche?q={query}')],
        'amazon_automotive_fr' => ['adapter' => 'generic', 'label' => 'Amazon.fr Automotive', 'country' => 'FR', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.amazon.fr', 'priority' => 14, ...$search('/s?k={query}&i=automotive')],
    ],

    // ── Italy (IT) ───────────────────────────────────────────────────────
    [
        'autodoc_it' => ['adapter' => 'generic', 'label' => 'Autodoc Italy', 'country' => 'IT', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.it', 'priority' => 5, ...$search('/search?keyword={query}')],
        'mister_auto_it' => ['adapter' => 'generic', 'label' => 'Mister Auto Italy', 'country' => 'IT', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.mister-auto.it', 'priority' => 7, ...$search('/it/c/search?q={query}')],
        'ricambi_auto_it' => ['adapter' => 'generic', 'label' => 'RicambiAuto.it', 'country' => 'IT', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.ricambiauto.it', 'priority' => 9, ...$search('/search?q={query}')],
        'amazon_automotive_it' => ['adapter' => 'generic', 'label' => 'Amazon.it Automotive', 'country' => 'IT', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.amazon.it', 'priority' => 12, ...$search('/s?k={query}&i=automotive')],
    ],

    // ── Spain (ES) ─────────────────────────────────────────────────────
    [
        'autodoc_es' => ['adapter' => 'generic', 'label' => 'Autodoc Spain', 'country' => 'ES', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.es', 'priority' => 5, ...$search('/search?keyword={query}')],
        'norauto_es' => ['adapter' => 'generic', 'label' => 'Norauto Spain', 'country' => 'ES', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.norauto.es', 'priority' => 8, ...$search('/es/search?q={query}')],
        'mister_auto_es' => ['adapter' => 'generic', 'label' => 'Mister Auto Spain', 'country' => 'ES', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.mister-auto.es', 'priority' => 9, ...$search('/es/c/search?q={query}')],
        'amazon_automotive_es' => ['adapter' => 'generic', 'label' => 'Amazon.es Automotive', 'country' => 'ES', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.amazon.es', 'priority' => 12, ...$search('/s?k={query}&i=automotive')],
    ],

    // ── Portugal (PT) ────────────────────────────────────────────────────
    [
        'autodoc_pt' => ['adapter' => 'generic', 'label' => 'Autodoc Portugal', 'country' => 'PT', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.pt', 'priority' => 6, ...$search('/search?keyword={query}')],
        'norauto_pt' => ['adapter' => 'generic', 'label' => 'Norauto Portugal', 'country' => 'PT', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.norauto.pt', 'priority' => 9, ...$search('/pt/search?q={query}')],
    ],

    // ── Netherlands (NL) ─────────────────────────────────────────────────
    [
        'winparts_nl' => ['adapter' => 'generic', 'label' => 'Winparts Netherlands', 'country' => 'NL', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.winparts.nl', 'priority' => 5, ...$search('/en/search?q={query}')],
        'autodoc_nl' => ['adapter' => 'generic', 'label' => 'Autodoc Netherlands', 'country' => 'NL', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.nl', 'priority' => 7, ...$search('/search?keyword={query}')],
        'marktplaats_parts_nl' => ['adapter' => 'generic', 'label' => 'Marktplaats Auto Parts', 'country' => 'NL', 'categories' => ['automotive_parts', 'marketplace'], 'base_url' => 'https://www.marktplaats.nl', 'priority' => 12, ...$search('/q/{query}/auto-onderdelen/')],
        'amazon_automotive_nl' => ['adapter' => 'generic', 'label' => 'Amazon.nl Automotive', 'country' => 'NL', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.amazon.nl', 'priority' => 14, ...$search('/s?k={query}&i=automotive')],
    ],

    // ── Belgium (BE) ───────────────────────────────────────────────────
    [
        'autodoc_be' => ['adapter' => 'generic', 'label' => 'Autodoc Belgium', 'country' => 'BE', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.be', 'priority' => 6, ...$search('/search?keyword={query}')],
        'oscaro_be' => ['adapter' => 'generic', 'label' => 'Oscaro Belgium', 'country' => 'BE', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.oscaro.be', 'priority' => 8, ...$search('/be/search?q={query}')],
    ],

    // ── Poland (PL) ────────────────────────────────────────────────────
    [
        'autodoc_pl' => ['adapter' => 'generic', 'label' => 'Autodoc Poland', 'country' => 'PL', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.pl', 'priority' => 5, ...$search('/search?keyword={query}')],
        'intercars_pl' => ['adapter' => 'generic', 'label' => 'Inter Cars Poland', 'country' => 'PL', 'categories' => ['automotive_parts'], 'base_url' => 'https://intercars.eu', 'priority' => 6, ...$search('/en/search?q={query}')],
        'motointegrator_pl' => ['adapter' => 'generic', 'label' => 'Motointegrator Poland', 'country' => 'PL', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.motointegrator.pl', 'priority' => 8, ...$search('/pl/search/{query}')],
    ],

    // ── Czech Republic (CZ) ──────────────────────────────────────────────
    [
        'autodoc_cz' => ['adapter' => 'generic', 'label' => 'Autodoc Czechia', 'country' => 'CZ', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.cz', 'priority' => 6, ...$search('/search?keyword={query}')],
    ],

    // ── Romania (RO) ─────────────────────────────────────────────────────
    [
        'autodoc_ro' => ['adapter' => 'generic', 'label' => 'Autodoc Romania', 'country' => 'RO', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.ro', 'priority' => 6, ...$search('/search?keyword={query}')],
    ],

    // ── United Kingdom (GB) ──────────────────────────────────────────────
    [
        'eurocarparts_uk' => ['adapter' => 'generic', 'label' => 'Euro Car Parts UK', 'country' => 'GB', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.eurocarparts.com', 'priority' => 5, ...$search('/search?searchTerm={query}')],
        'gsf_uk' => ['adapter' => 'generic', 'label' => 'GSF Car Parts UK', 'country' => 'GB', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.gsf.co.uk', 'priority' => 6, ...$search('/search?q={query}')],
        'halfords_uk' => ['adapter' => 'generic', 'label' => 'Halfords UK', 'country' => 'GB', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.halfords.com', 'priority' => 8, ...$search('/motoring/search/?text={query}')],
        'carparts4less_uk' => ['adapter' => 'generic', 'label' => 'CarParts4Less UK', 'country' => 'GB', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.carparts4less.co.uk', 'priority' => 9, ...$search('/search?q={query}')],
        'amazon_automotive_uk' => ['adapter' => 'generic', 'label' => 'Amazon.co.uk Automotive', 'country' => 'GB', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.amazon.co.uk', 'priority' => 11, ...$search('/s?k={query}&i=automotive')],
        'autodoc_uk' => ['adapter' => 'generic', 'label' => 'Autodoc UK', 'country' => 'GB', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.co.uk', 'priority' => 7, ...$search('/search?keyword={query}')],
    ],

    // ── United States (US) ─────────────────────────────────────────────
    [
        'rockauto_us' => ['adapter' => 'generic', 'label' => 'RockAuto US', 'country' => 'US', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.rockauto.com', 'priority' => 5, ...$search('/en/catalog/{query}')],
        'autozone_us' => ['adapter' => 'generic', 'label' => 'AutoZone', 'country' => 'US', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autozone.com', 'priority' => 6, ...$search('/search?searchText={query}')],
        'advanceautoparts_us' => ['adapter' => 'generic', 'label' => 'Advance Auto Parts', 'country' => 'US', 'categories' => ['automotive_parts'], 'base_url' => 'https://shop.advanceautoparts.com', 'priority' => 7, ...$search('/web/SearchResults?s={query}')],
        'oreillyauto_us' => ['adapter' => 'generic', 'label' => "O'Reilly Auto Parts", 'country' => 'US', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.oreillyauto.com', 'priority' => 8, ...$search('/search?q={query}')],
        'carparts_us' => ['adapter' => 'generic', 'label' => 'CarParts.com', 'country' => 'US', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.carparts.com', 'priority' => 9, ...$search('/search?q={query}')],
        'napaonline_us' => ['adapter' => 'generic', 'label' => 'NAPA Auto Parts', 'country' => 'US', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.napaonline.com', 'priority' => 10, ...$search('/en/search?text={query}')],
        'amazon_automotive_us' => ['adapter' => 'generic', 'label' => 'Amazon.com Automotive', 'country' => 'US', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.amazon.com', 'priority' => 11, ...$search('/s?k={query}&i=automotive')],
    ],

    // ── Canada (CA) ──────────────────────────────────────────────────────
    [
        'canadian_tire_auto_ca' => ['adapter' => 'generic', 'label' => 'Canadian Tire Auto', 'country' => 'CA', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.canadiantire.ca', 'priority' => 8, ...$search('/en/auto-parts/search/{query}.html')],
        'amazon_automotive_ca' => ['adapter' => 'generic', 'label' => 'Amazon.ca Automotive', 'country' => 'CA', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.amazon.ca', 'priority' => 10, ...$search('/s?k={query}&i=automotive')],
    ],

    // ── Australia (AU) ───────────────────────────────────────────────────
    [
        'supercheap_auto_au' => ['adapter' => 'generic', 'label' => 'Supercheap Auto Australia', 'country' => 'AU', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.supercheapauto.com.au', 'priority' => 6, ...$search('/search?q={query}')],
        'repco_au' => ['adapter' => 'generic', 'label' => 'Repco Australia', 'country' => 'AU', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.repco.com.au', 'priority' => 8, ...$search('/en/search?q={query}')],
    ],

    // ── Turkey (TR) ────────────────────────────────────────────────────
    [
        'autodoc_tr' => ['adapter' => 'generic', 'label' => 'Autodoc Turkey', 'country' => 'TR', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.tr', 'priority' => 8, ...$search('/search?keyword={query}')],
        'n11_auto_tr' => ['adapter' => 'generic', 'label' => 'N11 Auto Parts Turkey', 'country' => 'TR', 'categories' => ['automotive_parts', 'marketplace'], 'base_url' => 'https://www.n11.com', 'priority' => 12, ...$search('/arama?q={query}')],
    ],

    // ── Nordic ───────────────────────────────────────────────────────────
    [
        'autodoc_se' => ['adapter' => 'generic', 'label' => 'Autodoc Sweden', 'country' => 'SE', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.se', 'priority' => 8, ...$search('/search?keyword={query}')],
        'autodoc_no' => ['adapter' => 'generic', 'label' => 'Autodoc Norway', 'country' => 'NO', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.no', 'priority' => 8, ...$search('/search?keyword={query}')],
        'autodoc_dk' => ['adapter' => 'generic', 'label' => 'Autodoc Denmark', 'country' => 'DK', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.dk', 'priority' => 8, ...$search('/search?keyword={query}')],
    ],

    // ── Balkans ──────────────────────────────────────────────────────────
    [
        'autodoc_al' => ['adapter' => 'generic', 'label' => 'Autodoc Albania', 'country' => 'AL', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.al', 'priority' => 6, ...$search('/search?keyword={query}')],
        'merrjep_parts_xk' => ['adapter' => 'generic', 'label' => 'MerrJep Auto Parts Kosovo', 'country' => 'XK', 'categories' => ['automotive_parts', 'marketplace'], 'base_url' => 'https://www.merrjep.com', 'priority' => 8, ...$search('/search?q={query}&category=auto-parts')],
        'autopjes_online_xk' => ['adapter' => 'generic', 'label' => 'Autopjesë Online Kosovo', 'country' => 'XK', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autopjesonline.com', 'priority' => 10, ...$search('/search?q={query}')],
        'emag_auto_ro' => ['adapter' => 'generic', 'label' => 'eMAG Auto Romania', 'country' => 'RO', 'categories' => ['automotive_parts', 'marketplace'], 'base_url' => 'https://www.emag.ro', 'priority' => 10, ...$search('/search/{query}')],
        'emag_bg' => ['adapter' => 'generic', 'label' => 'eMAG Auto Bulgaria', 'country' => 'BG', 'categories' => ['automotive_parts', 'marketplace'], 'base_url' => 'https://www.emag.bg', 'priority' => 10, ...$search('/search/{query}')],
    ],

    // ── Central / Eastern Europe (Autodoc network) ───────────────────────
    [
        'autodoc_hu' => ['adapter' => 'generic', 'label' => 'Autodoc Hungary', 'country' => 'HU', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.hu', 'priority' => 8, ...$search('/search?keyword={query}')],
        'autodoc_gr' => ['adapter' => 'generic', 'label' => 'Autodoc Greece', 'country' => 'GR', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.gr', 'priority' => 8, ...$search('/search?keyword={query}')],
        'autodoc_hr' => ['adapter' => 'generic', 'label' => 'Autodoc Croatia', 'country' => 'HR', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.hr', 'priority' => 8, ...$search('/search?keyword={query}')],
        'autodoc_si' => ['adapter' => 'generic', 'label' => 'Autodoc Slovenia', 'country' => 'SI', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.si', 'priority' => 8, ...$search('/search?keyword={query}')],
        'autodoc_sk' => ['adapter' => 'generic', 'label' => 'Autodoc Slovakia', 'country' => 'SK', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.sk', 'priority' => 8, ...$search('/search?keyword={query}')],
        'autodoc_ie' => ['adapter' => 'generic', 'label' => 'Autodoc Ireland', 'country' => 'IE', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.ie', 'priority' => 8, ...$search('/search?keyword={query}')],
        'autodoc_fi' => ['adapter' => 'generic', 'label' => 'Autodoc Finland', 'country' => 'FI', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.fi', 'priority' => 8, ...$search('/search?keyword={query}')],
        'autodoc_lt' => ['adapter' => 'generic', 'label' => 'Autodoc Lithuania', 'country' => 'LT', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.lt', 'priority' => 8, ...$search('/search?keyword={query}')],
        'autodoc_lv' => ['adapter' => 'generic', 'label' => 'Autodoc Latvia', 'country' => 'LV', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.lv', 'priority' => 8, ...$search('/search?keyword={query}')],
        'autodoc_ee' => ['adapter' => 'generic', 'label' => 'Autodoc Estonia', 'country' => 'EE', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.ee', 'priority' => 8, ...$search('/search?keyword={query}')],
        'autodoc_ua' => ['adapter' => 'generic', 'label' => 'Autodoc Ukraine', 'country' => 'UA', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.ua', 'priority' => 10, ...$search('/search?keyword={query}')],
        'autodoc_lu' => ['adapter' => 'generic', 'label' => 'Autodoc Luxembourg', 'country' => 'LU', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.lu', 'priority' => 10, ...$search('/search?keyword={query}')],
        'autodoc_rs' => ['adapter' => 'generic', 'label' => 'Autodoc Serbia', 'country' => 'RS', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.rs', 'priority' => 8, ...$search('/search?keyword={query}')],
    ],

    // ── Asia-Pacific ─────────────────────────────────────────────────────
    [
        'autodoc_jp' => ['adapter' => 'generic', 'label' => 'Autodoc Japan', 'country' => 'JP', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.jp', 'priority' => 12, ...$search('/search?keyword={query}')],
        'autodoc_in' => ['adapter' => 'generic', 'label' => 'Autodoc India', 'country' => 'IN', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.in', 'priority' => 12, ...$search('/search?keyword={query}')],
        'autodoc_br' => ['adapter' => 'generic', 'label' => 'Autodoc Brazil', 'country' => 'BR', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.com.br', 'priority' => 12, ...$search('/search?keyword={query}')],
        'autodoc_mx' => ['adapter' => 'generic', 'label' => 'Autodoc Mexico', 'country' => 'MX', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.mx', 'priority' => 12, ...$search('/search?keyword={query}')],
        'autodoc_za' => ['adapter' => 'generic', 'label' => 'Autodoc South Africa', 'country' => 'ZA', 'categories' => ['automotive_parts'], 'base_url' => 'https://www.autodoc.co.za', 'priority' => 14, ...$search('/search?keyword={query}')],
    ],
);
