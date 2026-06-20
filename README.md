# BuyMap.ai · AI Federated Product Search

[![GitHub](https://img.shields.io/badge/GitHub-valon92%2FBayMap.ai-181717?logo=github)](https://github.com/valon92/BayMap.ai)

**Describe it. BuyMap finds it.** · *Search less. Discover smarter.*  
*Albanian: Trego çfarë kërkon — BuyMap e gjen.*

BuyMap.ai is **not a traditional marketplace**. It is a **federated AI meta search platform**: users describe what they want in natural language (or a photo); the AI understands intent, queries multiple marketplaces in real time, aggregates and compares results, ranks by exact match — **no database, no registration, no login**.

- **Live demo / product:** [buymap.ai](https://buymap.ai)
- **Repository:** [github.com/valon92/BayMap.ai](https://github.com/valon92/BayMap.ai)

- Product vision: **[docs/PLATFORM_VISION.md](docs/PLATFORM_VISION.md)**
- Federated architecture: **[docs/FEDERATED_SEARCH_ARCHITECTURE.md](docs/FEDERATED_SEARCH_ARCHITECTURE.md)**

## Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 10 (API-only, stateless) |
| Frontend | Vue 3 SPA + Vue Router |
| Styling | Tailwind CSS 3, glassmorphism dark UI |
| Build | Vite 5 |
| Data | Static JSON mock datasets |

## Quick start

```bash
git clone https://github.com/valon92/BayMap.ai.git
cd BayMap.ai

# Install PHP dependencies
composer install

# Install & build frontend
npm install
npm run build

# Configure environment
cp .env.example .env
php artisan key:generate

# Run development servers
php artisan serve
# In another terminal:
npm run dev
```

Open [http://localhost:8000](http://localhost:8000)

### Development (hot reload)

```bash
php artisan serve
npm run dev
```

### Test on iPhone / phone on same Wi‑Fi

`php artisan serve` (default port 8000) listens only on **127.0.0.1** — your phone **cannot** open `localhost`, `127.0.0.1`, or your **public internet IP** from another device.

Use the Mac’s **Wi‑Fi LAN IP** (e.g. `192.168.0.111` — System Settings → Network → Wi‑Fi → Details).

**Option A — production build (simplest, no hot reload):**

```bash
npm run lan
# or: npm run iphone
```

On your phone (**same Wi‑Fi**, not mobile data), open in **Safari**:

**http://192.168.0.111:8000**

(Replace the IP with your Mac’s LAN address. Do **not** use `localhost` or your public internet IP.)

**Option B — hot reload on phone + Mac:**

```bash
npm run dev:lan
```

Then open **http://192.168.0.111:8000** on the iPhone.

Stop LAN servers: `npm run lan:stop`

**Important:** Do not run plain `php artisan serve` + `npm run dev` for iPhone testing. Vite serves assets from `localhost:5173`, which on the phone points to the iPhone itself — blank page. Use `npm run lan` or `npm run dev:lan` instead.

If the page never loads: router **AP isolation** / guest Wi‑Fi often blocks phone→Mac. Try Mac **Personal Hotspot** and connect the iPhone to it, then use the hotspot IP.

## API endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/health` | Health check |
| GET | `/api/geo` | IP geolocation (ip-api.com / ipapi.co) |
| GET | `/api/trending` | Trending searches |
| GET | `/api/examples` | Example prompts (EN/SQ) |
| POST | `/api/search` | Full AI search pipeline |
| GET | `/api/search?q=...` | Search (query string) |

### Example search

```bash
curl -X POST http://localhost:8000/api/search \
  -H "Content-Type: application/json" \
  -d '{"q":"Audi A6 2020 white under 180k km","locale":"en"}'
```

## Architecture

```
app/
├── Contracts/MarketplaceSearchInterface.php   # Plug real APIs here
├── Http/Controllers/Api/
├── Services/
│   ├── Ai/AiRequestParserService.php          # NL → structured JSON
│   ├── Geo/GeoLocationService.php             # Free IP APIs
│   ├── Marketplace/MockMarketplaceService.php # Mock providers
│   └── Search/SearchOrchestratorService.php   # 7-step pipeline orchestration
resources/js/
├── views/          # Home, Results
├── components/     # Search, Cards, Filters, Background
├── i18n/           # en, sq, de, fr, it (EN fallback)
└── services/api.js
storage/data/
├── products/*.json # Mock marketplace inventory
└── trending.json
```

## AI parsing & vision (Gemini or OpenAI)

Keys stay **server-side only** in `.env` (never commit). Choose provider:

```env
AI_PROVIDER=auto
```

| `AI_PROVIDER` | Behavior |
|---------------|----------|
| `auto` | Gemini if `GEMINI_API_KEY` is set, else OpenAI, else rule-based |
| `gemini` | Google Gemini only |
| `openai` | OpenAI only |

### Google Gemini (recommended)

Create a key in [Google AI Studio](https://aistudio.google.com/app/apikey). Restrict it to **Generative Language API** for production.

```env
GEMINI_API_KEY=your-key
GEMINI_MODEL=gemini-2.0-flash
GEMINI_VISION_MODEL=gemini-2.0-flash
GEMINI_ENABLED=true
```

You may use `GOOGLE_API_KEY` instead of `GEMINI_API_KEY` (same as Google’s docs).

### OpenAI (fallback)

```env
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
OPENAI_VISION_MODEL=gpt-4o-mini
OPENAI_ENABLED=true
```

Get keys at [platform.openai.com/api-keys](https://platform.openai.com/api-keys).  
If all AI providers fail, the app falls back to the rule-based parser automatically.

## eBay Browse API (real listings)

Register at [developer.ebay.com](https://developer.ebay.com/) and create **Production** (or Sandbox) keys.

```env
EBAY_CLIENT_ID=your-app-id
EBAY_CLIENT_SECRET=your-cert-id
EBAY_MARKETPLACE_ID=EBAY_DE
EBAY_SANDBOX=false
EBAY_ENABLED=true
```

API used: `GET /buy/browse/v1/item_summary/search?q=...`

When configured, **eBay returns live listings**; other sources stay mock until integrated.

Test:
```bash
php artisan config:clear
curl -s -X POST http://127.0.0.1:8765/api/search \
  -H "Content-Type: application/json" \
  -d '{"q":"laptop gaming"}' | python3 -c "import sys,json; d=json.load(sys.stdin); print([r['source'] for r in d['results'][:5]])"
```

## Plugging in real marketplaces

1. Create `app/Services/Marketplace/MobileDeService.php` implementing `MarketplaceSearchInterface`
2. Register in `MarketplaceAggregator` instead of `MockMarketplaceService`
3. Add API keys to `.env` (never commit secrets)

## Multi-language

- **English** (`en`) and **Albanian** (`sq`) via `resources/js/i18n/locales/`
- Auto-detect from IP country (XK, AL → `sq`) or browser language
- Manual toggle in header

## Deployment

Full guide: **[docs/DEPLOY.md](docs/DEPLOY.md)**

### Recommended: Render + Vercel

| Platform | Role |
|----------|------|
| **Render** | Laravel API, AI search, live scraping, Vue SPA |
| **Vercel** | Custom domain `buymap.ai` — proxies all traffic to Render |

**Render** — use included `render.yaml`:

1. [render.com](https://render.com) → **New Blueprint** → connect `valon92/BayMap.ai`
2. Set secrets in Environment: `OPENAI_API_KEY`, `GEMINI_API_KEY`, optional `EBAY_*`, `SERPAPI_KEY`
3. Set `APP_URL=https://buymap.ai` after domain is live
4. Health: `GET /api/health`

**Vercel** — domain front (optional):

1. Import repo on [vercel.com](https://vercel.com)
2. Edit `vercel.json` → replace `YOUR_RENDER_APP_URL` with your Render URL (e.g. `buymap-api.onrender.com`)
3. Add domain `buymap.ai` in Vercel → update Namecheap DNS

**Railway** (alternative): uses `Procfile` — set `APP_KEY` and API keys in variables.

### Local production build test

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan serve --host=0.0.0.0 --port=8765
```

## Domain (Namecheap → buymap.ai)

### With Vercel (recommended)

1. Point DNS to Vercel (A/CNAME records shown in Vercel dashboard)
2. Vercel proxies to Render — set `APP_URL=https://buymap.ai` on Render

### Render only (no Vercel)

1. Add custom domain in Render dashboard
2. Namecheap **CNAME** `www` → your Render hostname
3. Set `APP_URL=https://buymap.ai`

## Environment variables

| Variable | Description |
|----------|-------------|
| `APP_URL` | Production URL |
| `SESSION_DRIVER` | Use `array` (stateless) |
| `VITE_API_URL` | API base (default `/api`) |

## Monetization (prepared)

- `affiliate_ready` flag on products
- `sponsored` slot boosting in ranker
- `config/powerbook.php` monetization section

## License

MIT — built as MVP for BuyMap.ai
