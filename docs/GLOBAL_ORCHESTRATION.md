# Global AI Worker Orchestration — BuyMap.ai

BuyMap.ai is **not** a marketplace. It is an AI discovery and matching layer between users and external providers worldwide.

## Architecture

```
User query (natural language)
    ↓
AI Understanding Layer (SearchIntent)
    ↓
Location Intelligence (explicit location OR IP → city → country → region → global)
    ↓
Provider Discovery Engine (country + category registry)
    ↓
Dynamic Worker Generation (ephemeral, stateless, per search session)
    ↓
Parallel Worker Execution (ParallelWorkerExecutor / Horizon queue)
    ↓
Result Normalization (common listing schema)
    ↓
Meta Search (cross-platform price comparison)
    ↓
AI Matching Engine (weighted ranking)
    ↓
Multi-seller product cards
```

## Search Intent Object

Built by `SearchIntentFactory` from AI-parsed attributes:

| Field | Example |
|-------|---------|
| category | `vehicle`, `electronics`, `real_estate` |
| brand / model | `Audi`, `A6` |
| specifications | year, mileage, bedrooms, RAM |
| budget | max_price + currency |
| location | country, city, scope |

Exposed in API response: `meta.search_intent` and `meta.valon.search_intent`.

## Provider Discovery

`ProviderDiscoveryEngine` resolves live and mock providers from:

- `config/live_platforms.php` — platform registry
- `LocalMarketplaceResolver` — unified country + category routing
- `LivePlatformRegistry::discover()` — scope and fan-out limits

Each provider has: country, category, connector type, priority score.

## Dynamic Workers

Workers are **not** permanent. For each search:

1. `ValonIntentEngine` analyzes intent
2. `AgentActivationService` selects relevant agents
3. `ValonTaskSplitter` generates one worker per provider
4. `ValonWorkerRunner` executes via `ParallelWorkerExecutor`

Workers exist only for the current search session.

## Parallel Execution

| Mode | Config | Notes |
|------|--------|-------|
| Chunked sequential | default | `ORCHESTRATION_MAX_CONCURRENCY=8` |
| Process fork | `ORCHESTRATION_ENABLE_FORK=true` | CLI + pcntl only |
| Queue (Horizon) | `ORCHESTRATION_USE_QUEUE=true` | `ValonWorkerSearchJob` |

Fault isolation: one worker failure does not block others.

## Ranking Weights

Configured in `config/orchestration.php`:

| Factor | Weight |
|--------|--------|
| Specification match | 40% |
| Semantic similarity | 25% |
| Location relevance | 15% |
| Price relevance | 10% |
| Provider trust | 10% |

Implemented in `WeightedRankingEngine`.

## Multi-seller View

`MetaSearchEngine` clusters identical products by fingerprint or brand+model, then merges offers into one card with price comparison and direct seller links.

## Environment Variables

```env
ORCHESTRATION_MAX_WORKERS=24
ORCHESTRATION_MAX_CONCURRENCY=8
ORCHESTRATION_WORKER_TIMEOUT=15
ORCHESTRATION_ENABLE_FORK=false
ORCHESTRATION_USE_QUEUE=false
ORCHESTRATION_MIN_RESULTS=3
```

## Key Files

| Component | Path |
|-----------|------|
| Search Intent DTO | `app/Data/SearchIntent.php` |
| Intent factory | `app/Services/Orchestration/SearchIntentFactory.php` |
| Provider discovery | `app/Services/Orchestration/ProviderDiscoveryEngine.php` |
| Parallel executor | `app/Services/Orchestration/ParallelWorkerExecutor.php` |
| Valon orchestrator | `app/Services/Valon/ValonOrchestrator.php` |
| Worker runner | `app/Services/Valon/ValonWorkerRunner.php` |
| Meta clustering | `app/Services/Search/MetaSearchEngine.php` |
| Ranking | `app/Services/Search/WeightedRankingEngine.php` |
| Queue job | `app/Jobs/ValonWorkerSearchJob.php` |
