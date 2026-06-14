# BuyMap.ai — Platform Vision

BuyMap.ai is **not a traditional marketplace**. It is an **AI-powered semantic product discovery engine**.

Users do **not** start with filters. They describe what they want in natural language. The AI agent understands intent, searches across sources, ranks matches, and **then** generates intelligent filters to refine results — live, without page reload.

---

## Core principles

| Principle | Implementation |
|-----------|----------------|
| Describe first | Large hero search box, example prompts, photo upload |
| No friction | No registration, login, or database |
| Stateless | Laravel API + JSON datasets + external APIs |
| Location-aware | Free IP geolocation (ip-api.com / ipapi.co) |
| Semantic ranking | Relevance, location, attributes — not price-only |
| Dynamic filters | Generated per category **after** AI analysis |
| Pluggable sources | eBay, SerpAPI, mocks, regional stores (e.g. Driloni in XK) |

---

## The 7-step AI search flow

### 1 — User input

Natural language or product photo.

Examples: *Audi A6 2020 white under 180k km*, *gaming laptop quiet cooling*, *black sneakers size 42.5*.

### 2 — AI analysis

NLP + intent detection + attribute extraction → structured JSON (category, brand, model, color, price limits, size, etc.).

Providers: OpenAI / Gemini (server-side keys only), with rule-based fallback.

### 3 — Intelligent expansion

Semantic expansion beyond exact keywords — e.g. white → glacier white, pearl white; similar trims/years for cars.

Implemented in `SearchExpansionService` (color variants, nearby countries, smart filters metadata).

### 4 — Geolocation & local priority

Automatic IP detection. Ranking priority:

1. User’s country  
2. Nearby countries  
3. EU / regional  
4. Worldwide  

Example (Kosovo): local listings → Albania, North Macedonia → Germany/EU → global.

### 5 — Internet-wide search

Aggregator architecture queries live APIs where configured (eBay, Google Shopping via SerpAPI) and demo datasets otherwise. Designed for future scrapers and marketplace connectors via `MarketplaceSearchInterface`.

### 6 — AI ranking engine

Each result: image, title, price, location, source, match %, “why this matches”, buy link.

Scoring: semantic relevance, location, brand/model/color/size, category rules — not lowest price first.

### 7 — Dynamic AI filters

**After** results load, filters are generated for the detected category (cars: year, km, transmission; books: genre; fashion: EU size; electronics: type, price; etc.). User refinements re-query live via Vue SPA.

---

## Design language

Futuristic AI aesthetic: dark mode, glassmorphism, sky/violet gradients, animated mesh background, skeleton loaders, minimal Apple/OpenAI-inspired layout.

---

## Languages

UI shows **two choices only**:

1. **EN** — always available (global default)  
2. **Regional** — from visitor IP (e.g. Kosovo → SQ, Italy → IT, China → 中文, Germany → DE)

No manual list of five languages in the header. English strings fill any missing regional keys.

AI descriptions adapt to the active UI locale where supported.

---

## Taglines

| Language | Primary | Alternative |
|----------|---------|-------------|
| English | Describe it. BuyMap finds it. | Search less. Discover smarter. |
| Albanian | Trego çfarë kërkon — BuyMap e gjen. | Kërko më pak. Zbulo më smart. |

---

## Stack (unchanged)

Laravel 10 · Vue 3 · Tailwind · Vite · Stateless services · `storage/data` mock products

Deploy: **Render** (API + app) + **Vercel** (domain/proxy). See [DEPLOY.md](DEPLOY.md).

---

## What BuyMap is not

- Not a shop cart or checkout platform  
- Not filter-first classifieds  
- Not user accounts or saved searches (MVP)  

It is a **discovery layer** between the buyer’s intent and listings across the internet.
