# Federated Search Architecture

Powerbook.ai is a **stateless, real-time Federated Search Platform** and **AI-powered Meta Search Engine**. Products are never stored in a local database — every search queries external marketplaces live and returns normalized, aggregated results.

## Positioning

| Role | Description |
|------|-------------|
| AI Semantic Search Engine | Understands natural language + photo intent |
| Federated Product Discovery | Queries many sources simultaneously |
| Intelligent Marketplace Aggregator | Normalizes and compares listings |
| AI Meta Search Platform | Clusters identical products, compares prices |
| Exact Product Discovery Engine | Prioritizes exact intent over loose similarity |

## Unified Pipeline

```
User query / photo
    ↓
AI Intent Parser (OpenAI / Gemini / rules)
    ↓
Query Intent Enricher (location, price, category-specific parsers)
    ↓
Location Tiers (city → country → region → global, IP-based)
    ↓
Federated Search Coordinator (multi-provider, real-time)
    ↓
Product Aggregation Layer (normalize → dedupe → EUR pricing)
    ↓
Meta Search Engine (cluster identical products, compare offers)
    ↓
Exact Match Ranking (brand, model, storage, color, location)
    ↓
Dynamic Filters + Paginated Results → redirect to original marketplace
```

## Core Components

### Provider connectors (`app/Services/Marketplace/Providers/`)

Each marketplace implements `FederatedSearchProviderInterface`:

- `EbaySearchProvider` — live eBay Browse API
- `SerpApiSearchProvider` — live Google Shopping (meta-layer)
- `MockSearchProvider` — demo connectors (Amazon, mobile.de, Etsy, …)

Register new connectors in `config/marketplaces.php`.

### Federated Search Coordinator

`FederatedSearchCoordinator` orchestrates real-time multi-source search:

- Selects providers by category, country, and expansion rules
- Queries location tiers (local first)
- Returns raw listings + per-source telemetry (`source_report`)

### Product Aggregation Layer

`ProductAggregationService` + `ListingNormalizer`:

- Standardizes all listings into `SearchListing` DTO
- Normalizes currency to EUR (`price_eur`)
- Extracts brand, storage, fingerprint
- Deduplicates by `id + source_key`

### Meta Search Engine

`MetaSearchEngine` clusters listings by fingerprint:

- Groups identical products across platforms
- Attaches `offers[]`, `offer_count`, `best_price_eur`, `price_spread_eur`
- Surfaces best price; user clicks through to original seller URL

### Exact Match Scoring

`ExactMatchScoringService` + `ProductRankingService`:

- Prioritizes **exact** brand, model, storage, color, size matches
- Location priority: local country → nearby → regional → global
- Penalizes wrong model/year (automotive)

## Normalized Product Schema

Every result includes:

| Field | Description |
|-------|-------------|
| `id` | Unique listing ID |
| `title` | Product title |
| `image` | Image URL |
| `source` / `source_key` | Marketplace name |
| `location` | Seller geography |
| `price` / `currency` / `price_eur` | Normalized pricing |
| `url` | Direct link to original listing |
| `match_score` | AI relevance (0–99) |
| `ai_explanation` | Why this listing matches |
| `offers` | Alternate sellers (meta search) |
| `offer_count` | Number of platforms carrying this product |
| `fingerprint` | Cross-platform identity hash |

## Adding a New Marketplace

1. Create `app/Services/Marketplace/Providers/YourProvider.php` implementing `FederatedSearchProviderInterface`
2. Map API response via `ListingNormalizer` or inline to standard array shape
3. Register in `config/marketplaces.php`
4. Add to `ProviderRegistry` if using a custom adapter type

No database migrations required.

## Supported / Planned Integrations

| Provider | Status |
|----------|--------|
| eBay | Live (OAuth) |
| Google Shopping | Live (SerpAPI) |
| Amazon | Demo connector |
| mobile.de | Demo connector |
| AutoScout24 | Demo connector |
| Etsy | Demo connector |
| Facebook Marketplace | Demo connector |
| Swiss car marketplaces | Demo connectors |
| Local stores (Driloni) | Demo (XK fashion) |

## Configuration

`config/marketplaces.php` — provider registry, timeouts, live result caps.

Environment variables for live sources: see `.env.example` (`EBAY_*`, `SERPAPI_*`).

## Design Principles

1. **No local product DB** — federated, real-time only
2. **Exact over similar** — find what the user truly wants
3. **Location-first** — IP geo prioritizes local/nearby results
4. **Transparent sourcing** — always show platform + direct seller link
5. **Scalable connectors** — one interface, many adapters
