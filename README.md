# Power-Book-AI-Agent-Search · Powerbook.ai

**Describe it. Powerbook finds it.** · *Search less. Discover smarter.*  
*Albanian: Trego çfarë kërkon — Powerbook e gjen.*

Powerbook.ai is **not a traditional marketplace**. It is a **federated AI meta search platform**: users describe what they want in natural language (or a photo); the AI understands intent, queries multiple marketplaces in real time, aggregates and compares results, ranks by exact match — **no database, no registration, no login**.

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

Use the Mac’s **Wi‑Fi LAN IP** (e.g. `192.168.1.114` — System Settings → Network → Wi‑Fi → Details).

```bash
npm run lan
```

If you see **“Address already in use”**, the server may already be running — run `npm run lan` again (it prints the iPhone URL) or open the URL below.

On your phone (**same Wi‑Fi**, not mobile data), open in **Safari**:

**http://192.168.1.114:8766**

(Replace the IP with your Mac’s LAN address from **System Settings → Network → Wi‑Fi**. Do **not** use your public internet IP or `localhost`.)

Stop LAN server: `npm run lan:stop`

**Important:** Do not run `npm run dev` (Vite) at the same time as `npm run lan`. Vite makes Laravel serve `localhost:5173` assets — on iPhone that points to the phone, so the page stays blank. ipko works because it only uses `public/build/`.

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

## Deployment (free tiers)

### Recommended: Render or Railway (full Laravel)

**Render** — use included `render.yaml`:

1. Connect GitHub repo on [render.com](https://render.com)
2. Set environment: `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_DRIVER=array`
3. Build runs `composer install`, `npm run build` automatically

**Railway**:

1. Connect repo at [railway.app](https://railway.app)
2. Uses `Procfile` — set `APP_KEY` in variables
3. Point Namecheap DNS CNAME to Railway URL

### Cloudflare / VPS

Deploy as standard PHP app with `public/` as web root. Run `npm run build` on deploy.

### Vercel / Netlify

Laravel requires PHP runtime — use Render/Railway for the API. For split deployment:

- Host Laravel API on Render
- Set `VITE_API_URL=https://your-api.onrender.com/api` before `npm run build`
- Optional static CDN for assets only

## Domain (Namecheap → Powerbook.ai)

### Same cPanel as arontrade.net (shared hosting)

Use a **separate addon domain folder** — do **not** upload into `public_html` (that is arontrade.net).

Full step-by-step: **[docs/DEPLOY_CPANEL.md](docs/DEPLOY_CPANEL.md)**

Summary:
1. Namecheap DNS: **A** `@` and `www` → server IP (`162.0.232.61`)
2. cPanel → Addon domain `powerbook.ai` → document root `/home/aronqbxm/powerbook.ai/public`
3. Clone repo, `composer install --no-dev`, `npm run build`, configure `.env`
4. SSL via AutoSSL in cPanel

### Cloud (Render / Railway)

1. Add CNAME record: `@` or `www` → your Render/Railway host
2. Enable HTTPS on host
3. Set `APP_URL=https://powerbook.ai` in production `.env`

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

MIT — built as MVP for Powerbook.ai
