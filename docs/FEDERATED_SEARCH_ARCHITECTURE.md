# Federated Search Architecture

BuyMap.ai is a **stateless, real-time Federated Search Platform** and **AI-powered Meta Search Engine**. Products are never stored in a local database — every search queries external marketplaces live and returns normalized, aggregated results.

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

---

# New Core Module: AI Federated Product Search Agent Network

BuyMap.ai adds a next-generation core module called **“AI Federated Product Search Agent Network”**.

## Objective

Implement an AI-powered product discovery workflow where users describe exactly what they want to buy in natural language.

- The goal is **not** to show similar products.
- The goal is to find the **EXACT product** that best matches the user's intent across multiple online marketplaces.

## Example user query

User enters:

> “Puma size 42.5 under 120 CHF in Switzerland”

## AI Analysis Layer — AI Intent Analysis Engine

Create an AI Intent Analysis Engine that extracts structured attributes from the user request.

Example output:

```json
{
  "category": "shoes",
  "brand": "Puma",
  "size": "42.5",
  "max_price": 120,
  "currency": "CHF",
  "country": "Switzerland"
}
```

The AI must support:

- multilingual requests
- semantic understanding
- attribute extraction
- intent detection
- missing information inference

## AI Search Orchestrator

After parsing the request, create an AI Search Orchestrator.

The orchestrator must build a search plan and launch multiple marketplace search agents simultaneously.

Example agents:

- `GalaxusAgent`
- `ZalandoAgent`
- `DigitecAgent`
- `AmazonAgent`
- `EbayAgent`
- `LocalMarketplaceAgent`

Each agent receives **normalized search parameters** and searches independently.

All agents must run **in parallel**.

## Federated Search Layer

Implement a federated search architecture with these requirements:

- search multiple sources simultaneously
- aggregate responses
- normalize product data
- unify currencies
- standardize attributes
- merge marketplace results
- support future provider integrations

**Do not store products in a database.** Use real-time search architecture.

## Product Aggregation Layer — Product Aggregation Service

Create a Product Aggregation Service.

Responsibilities:

- combine marketplace results
- normalize product fields
- standardize pricing
- standardize attributes
- create unified product objects

Unified structure:

```json
{
  "title": "",
  "price": "",
  "currency": "",
  "marketplace": "",
  "location": "",
  "image": "",
  "url": "",
  "attributes": {}
}
```

## Deduplication Engine

Implement product deduplication.

If the same product appears on multiple platforms:

- Puma RS-X — Galaxus CHF 109
- Puma RS-X — Zalando CHF 115
- Puma RS-X — Amazon CHF 119

Group them together and display:

Product:

> Puma RS-X

Available at:

- Galaxus CHF 109
- Zalando CHF 115
- Amazon CHF 119

Allow users to compare prices instantly.

## AI Ranking Engine

Create an intelligent ranking system.

Ranking factors:

- exact attribute match
- semantic similarity
- price relevance
- location relevance
- availability
- seller quality

Prioritize **exact intent matching**.

BuyMap.ai should always attempt to find exactly what the user described.

## Location Priority System

Use IP geolocation.

Priority order:

1. User country
2. Neighboring countries
3. Regional marketplaces
4. International marketplaces

Example:

User location: Switzerland

Priority:

- Switzerland
- Germany
- Austria
- France
- Italy
- Europe
- Global

## AI Generated Filters

After results are returned, generate dynamic filters automatically (category-specific).

Example for shoes:

- size
- color
- price range
- brand
- marketplace
- availability
- delivery speed

Filters must be generated based on the detected product category.

Do not use static filters.

## Result Page

Each product card must display:

- image
- title
- AI Match Score
- price comparison
- seller list
- location
- availability
- AI explanation (“Why this matches”)

Example “Why this matches”:

- Correct brand
- Exact size
- Within budget
- Available in Switzerland

## Platform philosophy

BuyMap.ai is not a marketplace.

BuyMap.ai is an **AI-powered Federated Semantic Product Discovery Platform**.

Mission:

- help users find exactly what they want without manually searching through dozens of websites

Principle:

> Users describe. AI discovers. BuyMap connects.

Tagline:

> “Describe it. BuyMap finds it.”

---

# Global Provider Agent Ecosystem

BuyMap.ai evolves into a **Global AI-Powered Federated Discovery Platform** with a modular, scalable, provider-based, country-aware architecture.

## Objective

Transform BuyMap.ai into a worldwide discovery layer that supports search across multiple industries using specialized **AI Provider Agents**.

The platform must:

- intelligently route searches to the correct provider network
- detect user intent and category automatically
- operate globally with country-aware provider selection

## Global Agent Orchestrator

Create a **Global Search Orchestrator**.

Responsibilities:

- analyze user request
- detect category
- detect country
- detect city
- detect language
- select provider agents
- launch federated searches
- aggregate results
- remove duplicates
- rank results
- generate AI filters

---

## Vehicle Provider Agents

Create a **Vehicle Agent Network**.

Supported vehicle types:

- Cars
- Trucks
- Motorcycles
- Vans
- Electric vehicles
- Boats

### Example agents

**Europe:**

- `MobileDeAgent`
- `AutoScout24Agent`
- `AutoUncleAgent`

**Switzerland:**

- `AutoScout24CHAgent`
- `CarForYouAgent`

**United States:**

- `CarsComAgent`
- `CarGurusAgent`
- `AutoTraderAgent`

### Functions

- vehicle discovery
- price comparison
- dealer comparison
- mileage filtering
- vehicle ranking

---

## Real Estate Provider Agents

Create a **Real Estate Agent Network**.

Supported property types:

- Houses
- Apartments
- Villas
- Commercial properties
- Land
- Rentals

### Example agents

**Switzerland:**

- `HomegateAgent`
- `ImmoScout24CHAgent`

**Germany:**

- `ImmoweltAgent`
- `ImmoScout24DEAgent`

**United States:**

- `ZillowAgent`
- `RealtorAgent`
- `RedfinAgent`

### Functions

- property discovery
- city-based search
- neighborhood ranking
- investment analysis

---

## Jobs Provider Agents

Create a **Global Jobs Agent Network**.

### Example agents

- `LinkedInJobsAgent`
- `IndeedAgent`
- `GlassdoorAgent`
- `JobScout24Agent`
- `StepStoneAgent`

### Functions

- job discovery
- salary comparison
- remote jobs
- local jobs
- AI ranking by user profile

---

## Books Provider Agents

Create a **Books Discovery Network**.

### Example agents

- `GoogleBooksAgent`
- `OpenLibraryAgent`
- `AmazonBooksAgent`
- `AbeBooksAgent`

### Functions

- semantic book discovery
- author search
- genre matching
- language filtering
- new and used books

---

## Fashion Provider Agents

Create a **Fashion Agent Network**.

### Example agents

- `ZalandoAgent`
- `AboutYouAgent`
- `FarfetchAgent`
- `ASOSAgent`
- `HMAgent`

### Functions

- size matching
- style matching
- color matching
- price comparison
- availability checking

---

## Electronics Provider Agents

Create an **Electronics Agent Network**.

### Example agents

- `AmazonAgent`
- `BestBuyAgent`
- `DigitecAgent`
- `GalaxusAgent`
- `NeweggAgent`

### Functions

- specification comparison
- performance ranking
- AI product recommendations
- technical filtering

---

## Travel Provider Agents

Create a **Travel Discovery Network**.

Supported travel types:

- Flights
- Hotels
- Vacation packages
- Car rentals
- Experiences

### Example agents

- `BookingAgent`
- `ExpediaAgent`
- `SkyscannerAgent`
- `KayakAgent`
- `AirbnbAgent`

### Functions

- travel comparison
- hotel comparison
- flight aggregation
- destination recommendations

---

## Local Business Provider Agents

Create a **Local Business Discovery Network**.

Supported business types:

- Restaurants
- Cafes
- Hotels
- Dentists
- Architects
- Lawyers
- Shops
- Services

### Example agents

- `GoogleBusinessAgent`
- `YelpAgent`
- `TripAdvisorAgent`

### Functions

- local search
- proximity ranking
- review aggregation
- business comparison

---

## Global Location System

Implement a location hierarchy:

```
Continent
  → Country
    → State / Province
      → Region
        → City
          → District
            → Neighborhood
```

Search priority:

1. User city
2. User region
3. User country
4. Neighboring countries
5. Continent
6. Global

---

## AI Ranking Engine (Global)

All providers must return **normalized results**.

Ranking factors:

- exact intent match
- semantic relevance
- location relevance
- availability
- quality
- reputation
- popularity
- value for money

---

## Platform Philosophy (Global)

BuyMap.ai is **not a marketplace**.

BuyMap.ai is a **global AI-powered discovery layer** connecting users with products, properties, jobs, travel opportunities, businesses, and services across the internet.

Principle:

> Users describe.  
> AI understands.  
> Provider Agents search.  
> BuyMap aggregates.  
> Users discover exactly what they need.

---

# Extension: Global AI Agent Router & Multi-Provider Federated Search System

> **Important constraint:** This is an **EXTENSION** to the existing BuyMap.ai project.  
> Do **not** change existing core architecture, remove current functionality, or replace UI/base flow.  
> Only **add** the layers described below.

## Objective

Enhance BuyMap.ai into a fully modular **AI Agent-based Federated Search Engine** that dynamically activates provider agents based on user intent.

Users already input natural language queries. This extension adds:

- AI automatically selects category
- AI activates correct provider agents
- AI runs parallel searches across platforms
- AI aggregates + ranks results in real time

## What to add (extension layers only)

| Layer | Service | Purpose |
|-------|---------|---------|
| Router | `AIAgentRouter` | Category + location + agent selection |
| Registry | `ProviderAgentRegistry` | Pluggable agent modules |
| Execution | `AgentExecutionEngine` | Parallel agent execution |
| Aggregation | `FederatedResultAggregator` | Merge, normalize, dedupe |
| Intelligence | Global Multi-Category Layer | Cross-category routing |

Existing components (`AiRequestParserService`, `SearchOrchestratorService`, `FederatedSearchCoordinator`, UI) remain unchanged. New layers plug in **after** intent parsing and **before** results rendering.

---

## Core Feature: AI Agent Router (new layer)

Create a new service: **`AIAgentRouter`**

Responsibilities:

1. Analyze user query (uses existing NLP layer — do not replace)
2. Detect category automatically:
   - `vehicles`
   - `real_estate`
   - `electronics`
   - `fashion`
   - `books`
   - `jobs`
   - `travel`
   - `local_services`
3. Detect:
   - country (IP-based)
   - language
   - currency
   - budget
   - product attributes
4. Return a structured execution plan

Example output:

```json
{
  "category": "vehicles",
  "country": "Switzerland",
  "agents": [
    "AutoScout24Agent",
    "MobileDeAgent",
    "CarGurusAgent",
    "LocalCarMarketplaceAgent"
  ]
}
```

---

## Core Feature: Provider Agent Registry (new layer)

Create a centralized registry: **`ProviderAgentRegistry`**

Each agent must implement:

```typescript
interface ProviderAgent {
  search(query): Promise<Product[]>
  normalize(data): Product[]
  sourceName: string
}
```

Agents **must** be pluggable modules — addable without touching core logic.

---

## Supported Agent Networks

### Vehicles

- `MobileDeAgent`
- `AutoScout24Agent`
- `AutoTraderAgent`
- `CarGurusAgent`
- `LocalCarMarketplaceAgent`

### Real Estate

- `ZillowAgent`
- `HomegateAgent`
- `ImmoScout24Agent`
- `RightmoveAgent`

### Electronics

- `AmazonAgent`
- `DigitecAgent`
- `BestBuyAgent`
- `NeweggAgent`

### Fashion

- `ZalandoAgent`
- `ASOSAgent`
- `AboutYouAgent`

### Books

- `AmazonBooksAgent`
- `GoogleBooksAgent`
- `OpenLibraryAgent`

### Jobs

- `LinkedInJobsAgent`
- `IndeedAgent`
- `GlassdoorAgent`

### Travel

- `BookingAgent`
- `ExpediaAgent`
- `SkyscannerAgent`
- `AirbnbAgent`

### Local Services

- `GoogleMapsAgent`
- `YelpAgent`
- `TripAdvisorAgent`

---

## Core Feature: Parallel Agent Execution Engine (new layer)

Create: **`AgentExecutionEngine`**

Requirements:

- **All** agents execute in parallel (`Promise.all` / async concurrency)
- No sequential calls allowed
- Must handle timeouts per agent
- Must gracefully degrade if one agent fails

Example:

```javascript
const results = await Promise.all([
  MobileDeAgent.search(query),
  AutoScout24Agent.search(query),
  CarGurusAgent.search(query),
]);
```

---

## Core Feature: Federated Result Aggregator (new layer)

Create: **`FederatedResultAggregator`**

Responsibilities:

- merge results from all agents
- normalize product schema
- remove duplicates (same product across platforms)
- unify pricing formats
- standardize attributes

Unified schema:

```json
{
  "title": "",
  "price": "",
  "currency": "",
  "location": "",
  "images": [],
  "source": "",
  "url": "",
  "attributes": {},
  "match_score": 0
}
```

---

## Enhancement: AI Ranking Engine

Enhance the **existing** ranking system (do not replace). Add weighted scoring:

| Factor | Weight |
|--------|--------|
| Exact intent match | 40% |
| Attribute match (size, model, year, specs) | 20% |
| Price relevance | 15% |
| Location proximity | 10% |
| Availability | 10% |
| Platform trust score | 5% |

**Important:** Prioritize **EXACT MATCH** over similar results.

---

## Enhancement: Location Intelligence Layer

Implement IP-based location detection with hierarchy:

1. City
2. Country
3. Neighboring countries
4. Regional expansion
5. Global fallback

Agents must adapt search based on location.

---

## Enhancement: Dynamic Filter Generation

Filters must be generated **per category** dynamically (extends existing `CategoryCatalog` — do not replace).

Examples:

| Category | Dynamic filters |
|----------|-----------------|
| Vehicles | brand, model, year, mileage, fuel, transmission |
| Real Estate | rooms, price, size, rent/buy, location |
| Electronics | specs, RAM, storage, brand |
| Fashion | size, color, brand |

Do **not** use static filters.

---

## Global Search Flow (extension pipeline)

This pipeline **extends** the existing search layer only. Core UI and base flow remain unchanged.

```
User Query
    ↓
AI Intent Parser          ← existing (unchanged)
    ↓
AI Agent Router           ← NEW
    ↓
Provider Agent Registry   ← NEW
    ↓
Parallel Execution Engine ← NEW
    ↓
Federated Aggregator      ← NEW
    ↓
Deduplication Engine      ← existing (enhanced)
    ↓
AI Ranking Engine         ← existing (enhanced)
    ↓
Dynamic Filters Generator ← existing (enhanced)
    ↓
UI Results Renderer       ← existing (unchanged)
```

---

## Important Rules

- Do **not** store products in database
- All data is real-time only
- All searches are API / scraping / external sources
- System must be fully modular and scalable
- New agents must be addable without touching core logic
- Do **not** modify existing core architecture
- Do **not** remove current functionality
- Do **not** replace UI or base flow

---

## Final Goal

BuyMap.ai becomes:

> **"A Universal AI Federated Search Engine for all products, services, jobs, travel, vehicles, and real estate worldwide."**

Principle:

> Users describe.  
> AI understands.  
> Agents search globally.  
> BuyMap delivers exact results in seconds.
