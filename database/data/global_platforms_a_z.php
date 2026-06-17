<?php

/**
 * Global platform catalog A–Z — all BuyMap.ai sales categories.
 * Merged with config/live_platforms.php during catalog:sync.
 *
 * @return array<string, array<string, mixed>>
 */
$search = fn (string $path) => ['search_template' => $path];

return [

    // ══════════════════════════════════════════════════════════════════
    // AI SOFTWARE
    // ══════════════════════════════════════════════════════════════════
    'microsoft_store' => ['adapter' => 'generic', 'label' => 'Microsoft Store', 'country' => 'WW', 'global' => true, 'categories' => ['ai_software'], 'base_url' => 'https://apps.microsoft.com', 'priority' => 10, ...$search('/search?query={query}')],
    'adobe_marketplace' => ['adapter' => 'generic', 'label' => 'Adobe Marketplace', 'country' => 'WW', 'global' => true, 'categories' => ['ai_software'], 'base_url' => 'https://exchange.adobe.com', 'priority' => 15, ...$search('/search?q={query}')],
    'appsumo' => ['adapter' => 'generic', 'label' => 'AppSumo', 'country' => 'WW', 'global' => true, 'categories' => ['ai_software'], 'base_url' => 'https://appsumo.com', 'priority' => 20, ...$search('/search/?query={query}')],
    'g2_software' => ['adapter' => 'generic', 'label' => 'G2 Software', 'country' => 'WW', 'global' => true, 'categories' => ['ai_software'], 'base_url' => 'https://www.g2.com', 'priority' => 25, ...$search('/search?query={query}')],
    'capterra' => ['adapter' => 'generic', 'label' => 'Capterra', 'country' => 'WW', 'global' => true, 'categories' => ['ai_software'], 'base_url' => 'https://www.capterra.com', 'priority' => 30, ...$search('/search/?search={query}')],
    'envato_market' => ['adapter' => 'generic', 'label' => 'Envato Market', 'country' => 'WW', 'global' => true, 'categories' => ['ai_software', 'media_streaming'], 'base_url' => 'https://themeforest.net', 'priority' => 35, ...$search('/search/{query}')],
    'jetbrains' => ['adapter' => 'generic', 'label' => 'JetBrains', 'country' => 'WW', 'global' => true, 'categories' => ['ai_software'], 'base_url' => 'https://www.jetbrains.com', 'priority' => 40, ...$search('/search/?q={query}')],
    'openai_store' => ['adapter' => 'generic', 'label' => 'OpenAI', 'country' => 'WW', 'global' => true, 'categories' => ['ai_software'], 'base_url' => 'https://openai.com', 'priority' => 5, 'provider_type' => 'platform'],

    // ══════════════════════════════════════════════════════════════════
    // AUTOMOTIVE (extra global)
    // ══════════════════════════════════════════════════════════════════
    'cargurus_uk' => ['adapter' => 'automotive', 'label' => 'CarGurus UK', 'country' => 'GB', 'categories' => ['automotive'], 'base_url' => 'https://www.cargurus.co.uk', 'priority' => 20, ...$search('/Cars/search?search={query}')],
    'autotrader_uk' => ['adapter' => 'automotive', 'label' => 'AutoTrader UK', 'country' => 'GB', 'categories' => ['automotive'], 'base_url' => 'https://www.autotrader.co.uk', 'priority' => 15, ...$search('/car-search?keywords={query}')],
    'gumtree_cars_uk' => ['adapter' => 'automotive', 'label' => 'Gumtree Cars UK', 'country' => 'GB', 'categories' => ['automotive'], 'base_url' => 'https://www.gumtree.com', 'priority' => 30, ...$search('/search?search_category=cars&q={query}')],
    'leboncoin_fr' => ['adapter' => 'automotive', 'label' => 'Leboncoin Auto', 'country' => 'FR', 'categories' => ['automotive', 'marketplace'], 'base_url' => 'https://www.leboncoin.fr', 'priority' => 15, ...$search('/recherche?text={query}&category=2')],
    'lacentrale_fr' => ['adapter' => 'automotive', 'label' => 'La Centrale', 'country' => 'FR', 'categories' => ['automotive'], 'base_url' => 'https://www.lacentrale.fr', 'priority' => 20, ...$search('/listing?makesModelsCommercialNames={query}')],
    'subito_it' => ['adapter' => 'automotive', 'label' => 'Subito Auto', 'country' => 'IT', 'categories' => ['automotive', 'marketplace'], 'base_url' => 'https://www.subito.it', 'priority' => 20, ...$search('/annunci-italia/vendita/auto/?q={query}')],
    'autoscout24_it' => ['adapter' => 'automotive', 'label' => 'AutoScout24 Italy', 'country' => 'IT', 'categories' => ['automotive'], 'base_url' => 'https://www.autoscout24.it', 'priority' => 15, ...$search('/lst?sort=standard&desc=0&cy=I&search_id=&query={query}')],
    'willhaben_at' => ['adapter' => 'automotive', 'label' => 'Willhaben Auto', 'country' => 'AT', 'categories' => ['automotive', 'marketplace'], 'base_url' => 'https://www.willhaben.at', 'priority' => 15, ...$search('/iad/gebrauchtwagen/auto/gebrauchtwagenboerse?keyword={query}')],
    'marktplaats_nl' => ['adapter' => 'automotive', 'label' => 'Marktplaats Auto', 'country' => 'NL', 'categories' => ['automotive', 'marketplace'], 'base_url' => 'https://www.marktplaats.nl', 'priority' => 18, ...$search('/q/{query}/auto-motor/')],
    'carmax_us' => ['adapter' => 'automotive', 'label' => 'CarMax', 'country' => 'US', 'categories' => ['automotive'], 'base_url' => 'https://www.carmax.com', 'priority' => 25, ...$search('/cars/{query}')],
    'edmunds_us' => ['adapter' => 'automotive', 'label' => 'Edmunds', 'country' => 'US', 'categories' => ['automotive'], 'base_url' => 'https://www.edmunds.com', 'priority' => 30, ...$search('/car-inventory/search?search={query}')],

    // ══════════════════════════════════════════════════════════════════
    // BEAUTY
    // ══════════════════════════════════════════════════════════════════
    'douglas_de' => ['adapter' => 'generic', 'label' => 'Douglas Germany', 'country' => 'DE', 'categories' => ['beauty'], 'base_url' => 'https://www.douglas.de', 'priority' => 10, ...$search('/de/search?q={query}')],
    'douglas_ch' => ['adapter' => 'generic', 'label' => 'Douglas Switzerland', 'country' => 'CH', 'categories' => ['beauty'], 'base_url' => 'https://www.douglas.ch', 'priority' => 10, ...$search('/ch/search?q={query}')],
    'sephora_de' => ['adapter' => 'generic', 'label' => 'Sephora Germany', 'country' => 'DE', 'categories' => ['beauty'], 'base_url' => 'https://www.sephora.de', 'priority' => 12, ...$search('/de/search?keyword={query}')],
    'sephora_us' => ['adapter' => 'generic', 'label' => 'Sephora US', 'country' => 'US', 'categories' => ['beauty'], 'base_url' => 'https://www.sephora.com', 'priority' => 12, ...$search('/search?keyword={query}')],
    'flaconi_de' => ['adapter' => 'generic', 'label' => 'Flaconi', 'country' => 'DE', 'categories' => ['beauty'], 'base_url' => 'https://www.flaconi.de', 'priority' => 15, ...$search('/parfum/?q={query}')],
    'notino_de' => ['adapter' => 'generic', 'label' => 'Notino Germany', 'country' => 'DE', 'categories' => ['beauty'], 'base_url' => 'https://www.notino.de', 'priority' => 18, ...$search('/search/?q={query}')],
    'notino_ch' => ['adapter' => 'generic', 'label' => 'Notino Switzerland', 'country' => 'CH', 'categories' => ['beauty'], 'base_url' => 'https://www.notino.ch', 'priority' => 18, ...$search('/search/?q={query}')],
    'lookfantastic_uk' => ['adapter' => 'generic', 'label' => 'Lookfantastic UK', 'country' => 'GB', 'categories' => ['beauty'], 'base_url' => 'https://www.lookfantastic.com', 'priority' => 20, ...$search('/search/?q={query}')],
    'boots_beauty' => ['adapter' => 'generic', 'label' => 'Boots Beauty', 'country' => 'GB', 'categories' => ['beauty', 'health_wellness'], 'base_url' => 'https://www.boots.com', 'priority' => 22, ...$search('/search?searchTerm={query}')],
    'ulta_us' => ['adapter' => 'generic', 'label' => 'Ulta Beauty', 'country' => 'US', 'categories' => ['beauty'], 'base_url' => 'https://www.ulta.com', 'priority' => 15, ...$search('/search?search={query}')],
    'parfumdreams_de' => ['adapter' => 'generic', 'label' => 'Parfumdreams', 'country' => 'DE', 'categories' => ['beauty'], 'base_url' => 'https://www.parfumdreams.de', 'priority' => 25, ...$search('/search/?search={query}')],
    'marionnaud_fr' => ['adapter' => 'generic', 'label' => 'Marionnaud', 'country' => 'FR', 'categories' => ['beauty'], 'base_url' => 'https://www.marionnaud.fr', 'priority' => 20, ...$search('/search?q={query}')],

    // ══════════════════════════════════════════════════════════════════
    // CONSTRUCTION
    // ══════════════════════════════════════════════════════════════════
    'hornbach_de' => ['adapter' => 'generic', 'label' => 'Hornbach Germany', 'country' => 'DE', 'categories' => ['construction', 'home_furniture'], 'base_url' => 'https://www.hornbach.de', 'priority' => 10, ...$search('/shop/suche/sortiment/{query}')],
    'hornbach_ch' => ['adapter' => 'generic', 'label' => 'Hornbach Switzerland', 'country' => 'CH', 'categories' => ['construction', 'home_furniture'], 'base_url' => 'https://www.hornbach.ch', 'priority' => 10, ...$search('/shop/suche/sortiment/{query}')],
    'bauhaus_de' => ['adapter' => 'generic', 'label' => 'Bauhaus Germany', 'country' => 'DE', 'categories' => ['construction'], 'base_url' => 'https://www.bauhaus.info', 'priority' => 12, ...$search('/search?q={query}')],
    'bauhaus_ch' => ['adapter' => 'generic', 'label' => 'Bauhaus Switzerland', 'country' => 'CH', 'categories' => ['construction'], 'base_url' => 'https://www.bauhaus.ch', 'priority' => 12, ...$search('/search?q={query}')],
    'obi_de' => ['adapter' => 'generic', 'label' => 'OBI Germany', 'country' => 'DE', 'categories' => ['construction', 'home_furniture'], 'base_url' => 'https://www.obi.de', 'priority' => 15, ...$search('/search/{query}')],
    'obi_ch' => ['adapter' => 'generic', 'label' => 'OBI Switzerland', 'country' => 'CH', 'categories' => ['construction'], 'base_url' => 'https://www.obi.ch', 'priority' => 15, ...$search('/search/{query}')],
    'toom_de' => ['adapter' => 'generic', 'label' => 'toom Baumarkt', 'country' => 'DE', 'categories' => ['construction'], 'base_url' => 'https://toom.de', 'priority' => 18, ...$search('/suche/?q={query}')],
    'brico_be' => ['adapter' => 'generic', 'label' => 'Brico Belgium', 'country' => 'BE', 'categories' => ['construction'], 'base_url' => 'https://www.brico.be', 'priority' => 22, ...$search('/fr/search?q={query}')],
    'leroy_merlin_fr' => ['adapter' => 'generic', 'label' => 'Leroy Merlin France', 'country' => 'FR', 'categories' => ['construction', 'home_furniture'], 'base_url' => 'https://www.leroymerlin.fr', 'priority' => 12, ...$search('/search?q={query}')],
    'leroy_merlin_es' => ['adapter' => 'generic', 'label' => 'Leroy Merlin Spain', 'country' => 'ES', 'categories' => ['construction'], 'base_url' => 'https://www.leroymerlin.es', 'priority' => 15, ...$search('/search?q={query}')],
    'home_depot_us' => ['adapter' => 'generic', 'label' => 'Home Depot', 'country' => 'US', 'categories' => ['construction', 'home_furniture'], 'base_url' => 'https://www.homedepot.com', 'priority' => 10, ...$search('/s/{query}')],
    'lowes_us' => ['adapter' => 'generic', 'label' => "Lowe's", 'country' => 'US', 'categories' => ['construction', 'home_furniture'], 'base_url' => 'https://www.lowes.com', 'priority' => 12, ...$search('/search?searchTerm={query}')],
    'screwfix_uk' => ['adapter' => 'generic', 'label' => 'Screwfix UK', 'country' => 'GB', 'categories' => ['construction'], 'base_url' => 'https://www.screwfix.com', 'priority' => 15, ...$search('/search?search={query}')],
    'toolstation_uk' => ['adapter' => 'generic', 'label' => 'Toolstation UK', 'country' => 'GB', 'categories' => ['construction'], 'base_url' => 'https://www.toolstation.com', 'priority' => 18, ...$search('/search?q={query}')],
    'praktiker_xk' => ['adapter' => 'generic', 'label' => 'Praktiker Kosovo', 'country' => 'XK', 'categories' => ['construction'], 'base_url' => 'https://www.praktiker-ks.com', 'priority' => 20, ...$search('/search?q={query}')],

    // ══════════════════════════════════════════════════════════════════
    // ELECTRONICS (extra)
    // ══════════════════════════════════════════════════════════════════
    'fnac_fr' => ['adapter' => 'generic', 'label' => 'Fnac France', 'country' => 'FR', 'categories' => ['electronics_tech', 'gaming_entertainment'], 'base_url' => 'https://www.fnac.com', 'priority' => 20, ...$search('/SearchResult/ResultList.aspx?Search={query}')],
    'darty_fr' => ['adapter' => 'generic', 'label' => 'Darty France', 'country' => 'FR', 'categories' => ['electronics_tech', 'home_appliances'], 'base_url' => 'https://www.darty.com', 'priority' => 22, ...$search('/nav/recherche?text={query}')],
    'currys_uk' => ['adapter' => 'generic', 'label' => 'Currys UK', 'country' => 'GB', 'categories' => ['electronics_tech', 'home_appliances'], 'base_url' => 'https://www.currys.co.uk', 'priority' => 18, ...$search('/search?q={query}')],
    'argos_uk' => ['adapter' => 'generic', 'label' => 'Argos UK', 'country' => 'GB', 'categories' => ['electronics_tech', 'home_appliances', 'gaming_entertainment'], 'base_url' => 'https://www.argos.co.uk', 'priority' => 20, ...$search('/search/{query}')],
    'newegg_us' => ['adapter' => 'generic', 'label' => 'Newegg', 'country' => 'US', 'categories' => ['electronics_tech', 'gaming_entertainment'], 'base_url' => 'https://www.newegg.com', 'priority' => 18, ...$search('/p/pl?d={query}')],
    'b_and_h_us' => ['adapter' => 'generic', 'label' => 'B&H Photo', 'country' => 'US', 'categories' => ['electronics_tech'], 'base_url' => 'https://www.bhphotovideo.com', 'priority' => 22, ...$search('/c/search?q={query}')],
    'expert_ch' => ['adapter' => 'generic', 'label' => 'expert Switzerland', 'country' => 'CH', 'categories' => ['electronics_tech', 'home_appliances'], 'base_url' => 'https://www.expert.ch', 'priority' => 25, ...$search('/search?q={query}')],

    // ══════════════════════════════════════════════════════════════════
    // FASHION (extra global)
    // ══════════════════════════════════════════════════════════════════
    'shein_ww' => ['adapter' => 'generic', 'label' => 'SHEIN', 'country' => 'WW', 'global' => true, 'categories' => ['fashion', 'beauty'], 'base_url' => 'https://www.shein.com', 'priority' => 25, ...$search('/search?keyword={query}')],
    'temu_ww' => ['adapter' => 'generic', 'label' => 'Temu', 'country' => 'WW', 'global' => true, 'categories' => ['fashion', 'electronics_tech', 'home_furniture'], 'base_url' => 'https://www.temu.com', 'priority' => 28, ...$search('/search_result.html?search_key={query}')],
    'uniqlo_de' => ['adapter' => 'generic', 'label' => 'Uniqlo Germany', 'country' => 'DE', 'categories' => ['fashion'], 'base_url' => 'https://www.uniqlo.com', 'priority' => 30, ...$search('/de/de/search?q={query}')],
    'mango_de' => ['adapter' => 'generic', 'label' => 'Mango Germany', 'country' => 'DE', 'categories' => ['fashion'], 'base_url' => 'https://shop.mango.com', 'priority' => 32, ...$search('/de/search?kw={query}')],
    'primark_uk' => ['adapter' => 'generic', 'label' => 'Primark UK', 'country' => 'GB', 'categories' => ['fashion'], 'base_url' => 'https://www.primark.com', 'priority' => 28, ...$search('/en-gb/search?q={query}')],
    'next_uk' => ['adapter' => 'generic', 'label' => 'Next UK', 'country' => 'GB', 'categories' => ['fashion', 'home_furniture'], 'base_url' => 'https://www.next.co.uk', 'priority' => 25, ...$search('/search?w={query}')],
    'nordstrom_us' => ['adapter' => 'generic', 'label' => 'Nordstrom', 'country' => 'US', 'categories' => ['fashion', 'beauty', 'luxury_collectibles'], 'base_url' => 'https://www.nordstrom.com', 'priority' => 20, ...$search('/sr?keyword={query}')],
    'macys_us' => ['adapter' => 'generic', 'label' => "Macy's", 'country' => 'US', 'categories' => ['fashion', 'home_furniture'], 'base_url' => 'https://www.macys.com', 'priority' => 22, ...$search('/shop/featured/{query}')],

    // ══════════════════════════════════════════════════════════════════
    // FINANCE FINTECH
    // ══════════════════════════════════════════════════════════════════
    'revolut' => ['adapter' => 'generic', 'label' => 'Revolut', 'country' => 'WW', 'global' => true, 'categories' => ['finance_fintech'], 'base_url' => 'https://www.revolut.com', 'priority' => 10, 'provider_type' => 'platform'],
    'wise' => ['adapter' => 'generic', 'label' => 'Wise', 'country' => 'WW', 'global' => true, 'categories' => ['finance_fintech'], 'base_url' => 'https://wise.com', 'priority' => 12, 'provider_type' => 'platform'],
    'n26_de' => ['adapter' => 'generic', 'label' => 'N26 Germany', 'country' => 'DE', 'categories' => ['finance_fintech'], 'base_url' => 'https://n26.com', 'priority' => 15, 'provider_type' => 'platform'],
    'n26_ch' => ['adapter' => 'generic', 'label' => 'N26 Switzerland', 'country' => 'CH', 'categories' => ['finance_fintech'], 'base_url' => 'https://n26.com', 'priority' => 15, 'provider_type' => 'platform'],
    'paypal_marketplace' => ['adapter' => 'generic', 'label' => 'PayPal Shopping', 'country' => 'WW', 'global' => true, 'categories' => ['finance_fintech', 'marketplace'], 'base_url' => 'https://www.paypal.com', 'priority' => 20, 'provider_type' => 'platform'],
    'klarna_shopping' => ['adapter' => 'generic', 'label' => 'Klarna Shopping', 'country' => 'WW', 'global' => true, 'categories' => ['finance_fintech', 'fashion'], 'base_url' => 'https://www.klarna.com', 'priority' => 18, 'provider_type' => 'platform'],
    'stripe_marketplace' => ['adapter' => 'generic', 'label' => 'Stripe Apps', 'country' => 'WW', 'global' => true, 'categories' => ['finance_fintech', 'ai_software'], 'base_url' => 'https://marketplace.stripe.com', 'priority' => 25, 'provider_type' => 'platform'],

    // ══════════════════════════════════════════════════════════════════
    // GROCERY
    // ══════════════════════════════════════════════════════════════════
    'rewe_de' => ['adapter' => 'generic', 'label' => 'REWE Germany', 'country' => 'DE', 'categories' => ['grocery'], 'base_url' => 'https://www.rewe.de', 'priority' => 10, ...$search('/lebensmittel/suche?search={query}')],
    'edeka_de' => ['adapter' => 'generic', 'label' => 'EDEKA Germany', 'country' => 'DE', 'categories' => ['grocery'], 'base_url' => 'https://www.edeka.de', 'priority' => 12, ...$search('/suche.jsp?query={query}')],
    'kaufland_grocery' => ['adapter' => 'generic', 'label' => 'Kaufland Grocery', 'country' => 'DE', 'categories' => ['grocery', 'marketplace'], 'base_url' => 'https://www.kaufland.de', 'priority' => 15, ...$search('/s/?search_value={query}')],
    'migros_ch' => ['adapter' => 'generic', 'label' => 'Migros Switzerland', 'country' => 'CH', 'categories' => ['grocery'], 'base_url' => 'https://www.migros.ch', 'priority' => 10, ...$search('/de/search?q={query}')],
    'coop_ch' => ['adapter' => 'generic', 'label' => 'Coop Switzerland', 'country' => 'CH', 'categories' => ['grocery'], 'base_url' => 'https://www.coop.ch', 'priority' => 12, ...$search('/de/search?q={query}')],
    'tesco_uk' => ['adapter' => 'generic', 'label' => 'Tesco UK', 'country' => 'GB', 'categories' => ['grocery'], 'base_url' => 'https://www.tesco.com', 'priority' => 10, ...$search('/groceries/en-GB/search?query={query}')],
    'sainsburys_uk' => ['adapter' => 'generic', 'label' => "Sainsbury's UK", 'country' => 'GB', 'categories' => ['grocery'], 'base_url' => 'https://www.sainsburys.co.uk', 'priority' => 12, ...$search('/gol-ui/SearchResults/{query}')],
    'asda_uk' => ['adapter' => 'generic', 'label' => 'ASDA UK', 'country' => 'GB', 'categories' => ['grocery'], 'base_url' => 'https://groceries.asda.com', 'priority' => 14, ...$search('/search/{query}')],
    'ah_nl' => ['adapter' => 'generic', 'label' => 'Albert Heijn', 'country' => 'NL', 'categories' => ['grocery'], 'base_url' => 'https://www.ah.nl', 'priority' => 10, ...$search('/zoeken?query={query}')],
    'jumbo_nl' => ['adapter' => 'generic', 'label' => 'Jumbo Netherlands', 'country' => 'NL', 'categories' => ['grocery'], 'base_url' => 'https://www.jumbo.com', 'priority' => 12, ...$search('/zoeken?searchType=keyword&searchTerms={query}')],
    'carrefour_fr' => ['adapter' => 'generic', 'label' => 'Carrefour France', 'country' => 'FR', 'categories' => ['grocery', 'electronics_tech'], 'base_url' => 'https://www.carrefour.fr', 'priority' => 10, ...$search('/s?q={query}')],
    'instacart_us' => ['adapter' => 'generic', 'label' => 'Instacart', 'country' => 'US', 'categories' => ['grocery'], 'base_url' => 'https://www.instacart.com', 'priority' => 15, ...$search('/store/s?k={query}')],
    'whole_foods_us' => ['adapter' => 'generic', 'label' => 'Whole Foods Market', 'country' => 'US', 'categories' => ['grocery', 'health_wellness'], 'base_url' => 'https://www.amazon.com', 'priority' => 18, ...$search('/wholefoodsmarket/s?k={query}')],
    'spar_xk' => ['adapter' => 'generic', 'label' => 'Spar Kosovo', 'country' => 'XK', 'categories' => ['grocery'], 'base_url' => 'https://www.spar-ks.com', 'priority' => 20, ...$search('/search?q={query}')],
    'viva_ks' => ['adapter' => 'generic', 'label' => 'Viva Fresh Kosovo', 'country' => 'XK', 'categories' => ['grocery'], 'base_url' => 'https://www.vivafreshonline.com', 'priority' => 22, ...$search('/search?q={query}')],

    // ══════════════════════════════════════════════════════════════════
    // HEALTH WELLNESS
    // ══════════════════════════════════════════════════════════════════
    'dm_de' => ['adapter' => 'generic', 'label' => 'dm-drogerie markt', 'country' => 'DE', 'categories' => ['health_wellness', 'beauty'], 'base_url' => 'https://www.dm.de', 'priority' => 10, ...$search('/search?query={query}')],
    'rossmann_de' => ['adapter' => 'generic', 'label' => 'Rossmann Germany', 'country' => 'DE', 'categories' => ['health_wellness', 'beauty'], 'base_url' => 'https://www.rossmann.de', 'priority' => 12, ...$search('/de/search?q={query}')],
    'muller_de' => ['adapter' => 'generic', 'label' => 'Müller Germany', 'country' => 'DE', 'categories' => ['health_wellness', 'beauty'], 'base_url' => 'https://www.mueller.de', 'priority' => 14, ...$search('/search/?q={query}')],
    'holland_barrett_uk' => ['adapter' => 'generic', 'label' => 'Holland & Barrett UK', 'country' => 'GB', 'categories' => ['health_wellness'], 'base_url' => 'https://www.hollandandbarrett.com', 'priority' => 15, ...$search('/shop/search?search={query}')],
    'walgreens_us' => ['adapter' => 'generic', 'label' => 'Walgreens', 'country' => 'US', 'categories' => ['health_wellness', 'beauty'], 'base_url' => 'https://www.walgreens.com', 'priority' => 12, ...$search('/search/results.jsp?Ntt={query}')],
    'cvs_us' => ['adapter' => 'generic', 'label' => 'CVS Pharmacy', 'country' => 'US', 'categories' => ['health_wellness'], 'base_url' => 'https://www.cvs.com', 'priority' => 14, ...$search('/search?searchTerm={query}')],
    'apodiscounter_de' => ['adapter' => 'generic', 'label' => 'Apodiscounter', 'country' => 'DE', 'categories' => ['health_wellness'], 'base_url' => 'https://www.apodiscounter.de', 'priority' => 18, ...$search('/search?q={query}')],
    'shop_apotheke_de' => ['adapter' => 'generic', 'label' => 'Shop Apotheke', 'country' => 'DE', 'categories' => ['health_wellness'], 'base_url' => 'https://www.shop-apotheke.com', 'priority' => 16, ...$search('/search.htm?q={query}')],
    'amavita_ch' => ['adapter' => 'generic', 'label' => 'Amavita Switzerland', 'country' => 'CH', 'categories' => ['health_wellness'], 'base_url' => 'https://www.amavita.ch', 'priority' => 18, ...$search('/de/search?q={query}')],
    'sunstore_ch' => ['adapter' => 'generic', 'label' => 'Sun Store Switzerland', 'country' => 'CH', 'categories' => ['health_wellness'], 'base_url' => 'https://www.sunstore.ch', 'priority' => 20, ...$search('/de/search?q={query}')],

    // ══════════════════════════════════════════════════════════════════
    // HOME FURNITURE
    // ══════════════════════════════════════════════════════════════════
    'ikea_de' => ['adapter' => 'generic', 'label' => 'IKEA Germany', 'country' => 'DE', 'categories' => ['home_furniture'], 'base_url' => 'https://www.ikea.com', 'priority' => 5, ...$search('/de/de/search/?q={query}')],
    'ikea_ch' => ['adapter' => 'generic', 'label' => 'IKEA Switzerland', 'country' => 'CH', 'categories' => ['home_furniture'], 'base_url' => 'https://www.ikea.com', 'priority' => 5, ...$search('/ch/de/search/?q={query}')],
    'ikea_uk' => ['adapter' => 'generic', 'label' => 'IKEA UK', 'country' => 'GB', 'categories' => ['home_furniture'], 'base_url' => 'https://www.ikea.com', 'priority' => 5, ...$search('/gb/en/search/?q={query}')],
    'ikea_us' => ['adapter' => 'generic', 'label' => 'IKEA US', 'country' => 'US', 'categories' => ['home_furniture'], 'base_url' => 'https://www.ikea.com', 'priority' => 5, ...$search('/us/en/search/?q={query}')],
    'home24_de' => ['adapter' => 'generic', 'label' => 'Home24 Germany', 'country' => 'DE', 'categories' => ['home_furniture'], 'base_url' => 'https://www.home24.de', 'priority' => 12, ...$search('/search/?q={query}')],
    'wayfair_us' => ['adapter' => 'generic', 'label' => 'Wayfair US', 'country' => 'US', 'categories' => ['home_furniture'], 'base_url' => 'https://www.wayfair.com', 'priority' => 10, ...$search('/keyword.php?keyword={query}')],
    'wayfair_uk' => ['adapter' => 'generic', 'label' => 'Wayfair UK', 'country' => 'GB', 'categories' => ['home_furniture'], 'base_url' => 'https://www.wayfair.co.uk', 'priority' => 10, ...$search('/keyword.php?keyword={query}')],
    'xxxlutz_de' => ['adapter' => 'generic', 'label' => 'XXXLutz Germany', 'country' => 'DE', 'categories' => ['home_furniture'], 'base_url' => 'https://www.xxxlutz.de', 'priority' => 15, ...$search('/s/?search={query}')],
    'xxxlutz_ch' => ['adapter' => 'generic', 'label' => 'XXXLutz Switzerland', 'country' => 'CH', 'categories' => ['home_furniture'], 'base_url' => 'https://www.xxxlutz.ch', 'priority' => 15, ...$search('/s/?search={query}')],
    'moebel_pfister_ch' => ['adapter' => 'generic', 'label' => 'Möbel Pfister', 'country' => 'CH', 'categories' => ['home_furniture'], 'base_url' => 'https://www.pfister.ch', 'priority' => 18, ...$search('/de/search?q={query}')],
    'made_de' => ['adapter' => 'generic', 'label' => 'Made.com Germany', 'country' => 'DE', 'categories' => ['home_furniture'], 'base_url' => 'https://www.made.com', 'priority' => 20, ...$search('/de/search?q={query}')],
    'westwing_de' => ['adapter' => 'generic', 'label' => 'Westwing Germany', 'country' => 'DE', 'categories' => ['home_furniture', 'fashion'], 'base_url' => 'https://www.westwing.de', 'priority' => 22, ...$search('/search/?q={query}')],
    'jysk_de' => ['adapter' => 'generic', 'label' => 'JYSK Germany', 'country' => 'DE', 'categories' => ['home_furniture'], 'base_url' => 'https://www.jysk.de', 'priority' => 25, ...$search('/search?q={query}')],

    // ══════════════════════════════════════════════════════════════════
    // INDUSTRIAL B2B
    // ══════════════════════════════════════════════════════════════════
    'alibaba_ww' => ['adapter' => 'generic', 'label' => 'Alibaba', 'country' => 'WW', 'global' => true, 'categories' => ['industrial_b2b', 'marketplace'], 'base_url' => 'https://www.alibaba.com', 'priority' => 5, 'provider_type' => 'marketplace', ...$search('/trade/search?SearchText={query}')],
    'amazon_business' => ['adapter' => 'generic', 'label' => 'Amazon Business', 'country' => 'WW', 'global' => true, 'categories' => ['industrial_b2b', 'electronics_tech'], 'base_url' => 'https://www.amazon.com', 'priority' => 8, ...$search('/s?k={query}&i=industrial')],
    'thomasnet_us' => ['adapter' => 'generic', 'label' => 'Thomasnet', 'country' => 'US', 'categories' => ['industrial_b2b'], 'base_url' => 'https://www.thomasnet.com', 'priority' => 12, ...$search('/search.html?cov=NA&which=suppliers&what={query}')],
    'europages_ww' => ['adapter' => 'generic', 'label' => 'Europages', 'country' => 'WW', 'global' => true, 'categories' => ['industrial_b2b'], 'base_url' => 'https://www.europages.co.uk', 'priority' => 15, ...$search('/companies/{query}.html')],
    'wlw_de' => ['adapter' => 'generic', 'label' => 'wlw (Wer liefert was)', 'country' => 'DE', 'categories' => ['industrial_b2b'], 'base_url' => 'https://www.wlw.de', 'priority' => 10, ...$search('/de/suche?q={query}')],
    'industrystock_de' => ['adapter' => 'generic', 'label' => 'IndustryStock', 'country' => 'DE', 'categories' => ['industrial_b2b'], 'base_url' => 'https://www.industrystock.com', 'priority' => 18, ...$search('/en/search?q={query}')],
    'machinio_ww' => ['adapter' => 'generic', 'label' => 'Machinio', 'country' => 'WW', 'global' => true, 'categories' => ['industrial_b2b', 'construction'], 'base_url' => 'https://www.machinio.com', 'priority' => 20, ...$search('/search?q={query}')],
    'global_sources' => ['adapter' => 'generic', 'label' => 'Global Sources', 'country' => 'WW', 'global' => true, 'categories' => ['industrial_b2b'], 'base_url' => 'https://www.globalsources.com', 'priority' => 22, ...$search('/search?query={query}')],

    // ══════════════════════════════════════════════════════════════════
    // LUXURY COLLECTIBLES
    // ══════════════════════════════════════════════════════════════════
    'chrono24_ww' => ['adapter' => 'generic', 'label' => 'Chrono24', 'country' => 'WW', 'global' => true, 'categories' => ['luxury_collectibles'], 'base_url' => 'https://www.chrono24.com', 'priority' => 5, ...$search('/search/index.htm?query={query}')],
    'stockx_ww' => ['adapter' => 'generic', 'label' => 'StockX', 'country' => 'WW', 'global' => true, 'categories' => ['luxury_collectibles', 'fashion', 'sports_outdoor'], 'base_url' => 'https://stockx.com', 'priority' => 8, ...$search('/search?s={query}')],
    'farfetch_ww' => ['adapter' => 'generic', 'label' => 'Farfetch', 'country' => 'WW', 'global' => true, 'categories' => ['luxury_collectibles', 'fashion'], 'base_url' => 'https://www.farfetch.com', 'priority' => 10, ...$search('/shopping/search/items.aspx?q={query}')],
    'vestiaire_ww' => ['adapter' => 'generic', 'label' => 'Vestiaire Collective', 'country' => 'WW', 'global' => true, 'categories' => ['luxury_collectibles', 'fashion'], 'base_url' => 'https://www.vestiairecollective.com', 'priority' => 12, ...$search('/search/?q={query}')],
    'gold_ah_de' => ['adapter' => 'generic', 'label' => 'Gold.de', 'country' => 'DE', 'categories' => ['luxury_collectibles', 'finance_fintech'], 'base_url' => 'https://www.gold.de', 'priority' => 18, 'provider_type' => 'platform'],
    'heritage_auctions' => ['adapter' => 'generic', 'label' => 'Heritage Auctions', 'country' => 'US', 'categories' => ['luxury_collectibles'], 'base_url' => 'https://www.ha.com', 'priority' => 20, ...$search('/c/search-results.zx?term={query}')],
    'sothebys' => ['adapter' => 'generic', 'label' => "Sotheby's", 'country' => 'WW', 'global' => true, 'categories' => ['luxury_collectibles'], 'base_url' => 'https://www.sothebys.com', 'priority' => 15, 'provider_type' => 'agency', ...$search('/en/search.html?q={query}')],
    'christies' => ['adapter' => 'generic', 'label' => "Christie's", 'country' => 'WW', 'global' => true, 'categories' => ['luxury_collectibles'], 'base_url' => 'https://www.christies.com', 'priority' => 16, 'provider_type' => 'agency', ...$search('/en/search?q={query}')],

    // ══════════════════════════════════════════════════════════════════
    // MEDIA STREAMING
    // ══════════════════════════════════════════════════════════════════
    'audible_ww' => ['adapter' => 'generic', 'label' => 'Audible', 'country' => 'WW', 'global' => true, 'categories' => ['media_streaming', 'online_education'], 'base_url' => 'https://www.audible.com', 'priority' => 10, ...$search('/search?keywords={query}')],
    'spotify_ww' => ['adapter' => 'generic', 'label' => 'Spotify', 'country' => 'WW', 'global' => true, 'categories' => ['media_streaming'], 'base_url' => 'https://open.spotify.com', 'priority' => 12, 'provider_type' => 'platform'],
    'apple_music' => ['adapter' => 'generic', 'label' => 'Apple Music', 'country' => 'WW', 'global' => true, 'categories' => ['media_streaming'], 'base_url' => 'https://music.apple.com', 'priority' => 14, 'provider_type' => 'platform'],
    'steam' => ['adapter' => 'generic', 'label' => 'Steam', 'country' => 'WW', 'global' => true, 'categories' => ['media_streaming', 'gaming_entertainment'], 'base_url' => 'https://store.steampowered.com', 'priority' => 8, ...$search('/search/?term={query}')],
    'gog' => ['adapter' => 'generic', 'label' => 'GOG.com', 'country' => 'WW', 'global' => true, 'categories' => ['media_streaming', 'gaming_entertainment'], 'base_url' => 'https://www.gog.com', 'priority' => 15, ...$search('/games?query={query}')],
    'epic_games' => ['adapter' => 'generic', 'label' => 'Epic Games Store', 'country' => 'WW', 'global' => true, 'categories' => ['media_streaming', 'gaming_entertainment'], 'base_url' => 'https://store.epicgames.com', 'priority' => 12, ...$search('/en-US/browse?q={query}')],
    'playstation_store' => ['adapter' => 'generic', 'label' => 'PlayStation Store', 'country' => 'WW', 'global' => true, 'categories' => ['media_streaming', 'gaming_entertainment'], 'base_url' => 'https://store.playstation.com', 'priority' => 18, ...$search('/search/{query}')],
    'xbox_store' => ['adapter' => 'generic', 'label' => 'Xbox Store', 'country' => 'WW', 'global' => true, 'categories' => ['media_streaming', 'gaming_entertainment'], 'base_url' => 'https://www.xbox.com', 'priority' => 18, ...$search('/games/search?q={query}')],

    // ══════════════════════════════════════════════════════════════════
    // ONLINE EDUCATION
    // ══════════════════════════════════════════════════════════════════
    'thalia_de' => ['adapter' => 'generic', 'label' => 'Thalia Germany', 'country' => 'DE', 'categories' => ['online_education'], 'base_url' => 'https://www.thalia.de', 'priority' => 10, ...$search('/shop/home/suche/?sq={query}')],
    'hugendubel_de' => ['adapter' => 'generic', 'label' => 'Hugendubel', 'country' => 'DE', 'categories' => ['online_education'], 'base_url' => 'https://www.hugendubel.de', 'priority' => 12, ...$search('/de/search/{query}')],
    'amazon_books_ww' => ['adapter' => 'generic', 'label' => 'Amazon Books', 'country' => 'WW', 'global' => true, 'categories' => ['online_education'], 'base_url' => 'https://www.amazon.com', 'priority' => 5, ...$search('/s?k={query}&i=stripbooks')],
    'waterstones_uk' => ['adapter' => 'generic', 'label' => 'Waterstones UK', 'country' => 'GB', 'categories' => ['online_education'], 'base_url' => 'https://www.waterstones.com', 'priority' => 14, ...$search('/books/search/term/{query}')],
    'barnes_noble_us' => ['adapter' => 'generic', 'label' => 'Barnes & Noble', 'country' => 'US', 'categories' => ['online_education'], 'base_url' => 'https://www.barnesandnoble.com', 'priority' => 12, ...$search('/s/{query}')],
    'coursera_ww' => ['adapter' => 'generic', 'label' => 'Coursera', 'country' => 'WW', 'global' => true, 'categories' => ['online_education', 'ai_software'], 'base_url' => 'https://www.coursera.org', 'priority' => 8, 'provider_type' => 'platform', ...$search('/search?query={query}')],
    'udemy_ww' => ['adapter' => 'generic', 'label' => 'Udemy', 'country' => 'WW', 'global' => true, 'categories' => ['online_education'], 'base_url' => 'https://www.udemy.com', 'priority' => 10, 'provider_type' => 'platform', ...$search('/courses/search/?q={query}')],
    'edx_ww' => ['adapter' => 'generic', 'label' => 'edX', 'country' => 'WW', 'global' => true, 'categories' => ['online_education'], 'base_url' => 'https://www.edx.org', 'priority' => 12, 'provider_type' => 'platform', ...$search('/search?q={query}')],
    'skillshare_ww' => ['adapter' => 'generic', 'label' => 'Skillshare', 'country' => 'WW', 'global' => true, 'categories' => ['online_education'], 'base_url' => 'https://www.skillshare.com', 'priority' => 15, 'provider_type' => 'platform', ...$search('/search?query={query}')],
    'linkedin_learning' => ['adapter' => 'generic', 'label' => 'LinkedIn Learning', 'country' => 'WW', 'global' => true, 'categories' => ['online_education', 'ai_software'], 'base_url' => 'https://www.linkedin.com', 'priority' => 14, 'provider_type' => 'platform', ...$search('/learning/search?keywords={query}')],

    // ══════════════════════════════════════════════════════════════════
    // PETS
    // ══════════════════════════════════════════════════════════════════
    'zooplus_de' => ['adapter' => 'generic', 'label' => 'Zooplus Germany', 'country' => 'DE', 'categories' => ['pets'], 'base_url' => 'https://www.zooplus.de', 'priority' => 10, ...$search('/search/results?q={query}')],
    'zooplus_ch' => ['adapter' => 'generic', 'label' => 'Zooplus Switzerland', 'country' => 'CH', 'categories' => ['pets'], 'base_url' => 'https://www.zooplus.ch', 'priority' => 10, ...$search('/search/results?q={query}')],
    'fressnapf_de' => ['adapter' => 'generic', 'label' => 'Fressnapf Germany', 'country' => 'DE', 'categories' => ['pets'], 'base_url' => 'https://www.fressnapf.de', 'priority' => 12, ...$search('/search/?q={query}')],
    'chewy_us' => ['adapter' => 'generic', 'label' => 'Chewy', 'country' => 'US', 'categories' => ['pets'], 'base_url' => 'https://www.chewy.com', 'priority' => 10, ...$search('/s?query={query}')],
    'petco_us' => ['adapter' => 'generic', 'label' => 'Petco', 'country' => 'US', 'categories' => ['pets'], 'base_url' => 'https://www.petco.com', 'priority' => 12, ...$search('/shop/en/petcostore/search?query={query}')],
    'petsmart_us' => ['adapter' => 'generic', 'label' => 'PetSmart', 'country' => 'US', 'categories' => ['pets'], 'base_url' => 'https://www.petsmart.com', 'priority' => 14, ...$search('/search/?q={query}')],
    'pets_at_home_uk' => ['adapter' => 'generic', 'label' => 'Pets at Home UK', 'country' => 'GB', 'categories' => ['pets'], 'base_url' => 'https://www.petsathome.com', 'priority' => 12, ...$search('/search?searchTerm={query}')],
    'bitiba_de' => ['adapter' => 'generic', 'label' => 'Bitiba', 'country' => 'DE', 'categories' => ['pets'], 'base_url' => 'https://www.bitiba.de', 'priority' => 18, ...$search('/search/results?q={query}')],
    'petworld_ch' => ['adapter' => 'generic', 'label' => 'Petworld Switzerland', 'country' => 'CH', 'categories' => ['pets'], 'base_url' => 'https://www.petworld.ch', 'priority' => 16, ...$search('/search?q={query}')],

    // ══════════════════════════════════════════════════════════════════
    // REAL ESTATE (extra)
    // ══════════════════════════════════════════════════════════════════
    'immobilienscout24_de' => ['adapter' => 'generic', 'label' => 'ImmobilienScout24 Germany', 'country' => 'DE', 'categories' => ['real_estate'], 'base_url' => 'https://www.immobilienscout24.de', 'priority' => 10, ...$search('/Suche/de/{query}')],
    'immowelt_de' => ['adapter' => 'generic', 'label' => 'Immowelt Germany', 'country' => 'DE', 'categories' => ['real_estate'], 'base_url' => 'https://www.immowelt.de', 'priority' => 12, ...$search('/liste/{query}/adressen')],
    'funda_nl' => ['adapter' => 'generic', 'label' => 'Funda Netherlands', 'country' => 'NL', 'categories' => ['real_estate'], 'base_url' => 'https://www.funda.nl', 'priority' => 10, ...$search('/zoeken/{query}')],
    'seloger_fr' => ['adapter' => 'generic', 'label' => 'SeLoger France', 'country' => 'FR', 'categories' => ['real_estate'], 'base_url' => 'https://www.seloger.com', 'priority' => 10, ...$search('/list.htm?projects=2&types=1&places=[{query}]')],
    'idealista_es' => ['adapter' => 'generic', 'label' => 'Idealista Spain', 'country' => 'ES', 'categories' => ['real_estate'], 'base_url' => 'https://www.idealista.com', 'priority' => 12, ...$search('/buscar/{query}')],
    'zillow_us' => ['adapter' => 'generic', 'label' => 'Zillow', 'country' => 'US', 'categories' => ['real_estate'], 'base_url' => 'https://www.zillow.com', 'priority' => 8, ...$search('/homes/{query}_rb')],
    'realtor_us' => ['adapter' => 'generic', 'label' => 'Realtor.com', 'country' => 'US', 'categories' => ['real_estate'], 'base_url' => 'https://www.realtor.com', 'priority' => 10, ...$search('/realestateandhomes-search/{query}')],
    'merrjep_real_estate_xk' => ['adapter' => 'generic', 'label' => 'MerrJep Real Estate Kosovo', 'country' => 'XK', 'categories' => ['real_estate'], 'base_url' => 'https://www.merrjep.com', 'priority' => 15, ...$search('/search?q={query}&category=real-estate')],

    // ══════════════════════════════════════════════════════════════════
    // TRAVEL
    // ══════════════════════════════════════════════════════════════════
    'booking_ww' => ['adapter' => 'generic', 'label' => 'Booking.com', 'country' => 'WW', 'global' => true, 'categories' => ['travel'], 'base_url' => 'https://www.booking.com', 'priority' => 5, 'provider_type' => 'agency', ...$search('/searchresults.html?ss={query}')],
    'expedia_ww' => ['adapter' => 'generic', 'label' => 'Expedia', 'country' => 'WW', 'global' => true, 'categories' => ['travel'], 'base_url' => 'https://www.expedia.com', 'priority' => 8, 'provider_type' => 'agency', ...$search('/Hotel-Search?destination={query}')],
    'airbnb_ww' => ['adapter' => 'generic', 'label' => 'Airbnb', 'country' => 'WW', 'global' => true, 'categories' => ['travel', 'real_estate'], 'base_url' => 'https://www.airbnb.com', 'priority' => 6, 'provider_type' => 'agency', ...$search('/s/{query}/homes')],
    'kayak_ww' => ['adapter' => 'generic', 'label' => 'Kayak', 'country' => 'WW', 'global' => true, 'categories' => ['travel'], 'base_url' => 'https://www.kayak.com', 'priority' => 10, 'provider_type' => 'aggregator', ...$search('/flights/{query}')],
    'skyscanner_ww' => ['adapter' => 'generic', 'label' => 'Skyscanner', 'country' => 'WW', 'global' => true, 'categories' => ['travel'], 'base_url' => 'https://www.skyscanner.net', 'priority' => 8, 'provider_type' => 'aggregator', ...$search('/transport/flights/{query}')],
    'tripadvisor_ww' => ['adapter' => 'generic', 'label' => 'TripAdvisor', 'country' => 'WW', 'global' => true, 'categories' => ['travel'], 'base_url' => 'https://www.tripadvisor.com', 'priority' => 12, 'provider_type' => 'agency', ...$search('/Search?q={query}')],
    'hotels_com' => ['adapter' => 'generic', 'label' => 'Hotels.com', 'country' => 'WW', 'global' => true, 'categories' => ['travel'], 'base_url' => 'https://www.hotels.com', 'priority' => 14, 'provider_type' => 'agency', ...$search('/search.do?q-destination={query}')],
    'trivago_ww' => ['adapter' => 'generic', 'label' => 'Trivago', 'country' => 'WW', 'global' => true, 'categories' => ['travel'], 'base_url' => 'https://www.trivago.com', 'priority' => 16, 'provider_type' => 'aggregator', ...$search('/en?search={query}')],
    'omio_ww' => ['adapter' => 'generic', 'label' => 'Omio', 'country' => 'WW', 'global' => true, 'categories' => ['travel'], 'base_url' => 'https://www.omio.com', 'priority' => 18, 'provider_type' => 'aggregator', ...$search('/search/{query}')],
    'sbb_ch' => ['adapter' => 'generic', 'label' => 'SBB Switzerland', 'country' => 'CH', 'categories' => ['travel'], 'base_url' => 'https://www.sbb.ch', 'priority' => 12, 'provider_type' => 'agency', ...$search('/en/buying/search.html?query={query}')],
    'deutsche_bahn' => ['adapter' => 'generic', 'label' => 'Deutsche Bahn', 'country' => 'DE', 'categories' => ['travel'], 'base_url' => 'https://www.bahn.de', 'priority' => 12, 'provider_type' => 'agency', ...$search('/en/view/search/{query}')],
    'flixbus_ww' => ['adapter' => 'generic', 'label' => 'FlixBus', 'country' => 'WW', 'global' => true, 'categories' => ['travel'], 'base_url' => 'https://www.flixbus.com', 'priority' => 20, 'provider_type' => 'agency', ...$search('/search?departureCity={query}')],

];
