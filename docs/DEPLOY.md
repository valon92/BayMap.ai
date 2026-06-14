# BuyMap.ai — Deploy (Render + Vercel)

BuyMap.ai është **Laravel 10 + Vue 3**. Nuk përdor databazë (MVP stateless).

**Arquitetura e rekomanduar:**

| Shërbim | Roli |
|---------|------|
| **Render** | Backend + API + SPA (PHP, scraping, AI) |
| **Vercel** | Domain `buymap.ai` + proxy/CDN para Render |

> cPanel **nuk përdoret më**. Mos ngarko në shared hosting.

---

## Opsioni A — Vetëm Render (më i thjeshti)

1. [render.com](https://render.com) → **New → Blueprint** → lidh repo `valon92/BayMap.ai`
2. Render lexon `render.yaml` automatikisht
3. Pas deploy, vendos env vars (shiko më poshtë)
4. **Custom domain** te Render → `buymap.ai` + `www.buymap.ai`
5. DNS te Namecheap:
   - **CNAME** `@` ose `www` → URL e Render (`buymap-api.onrender.com`)

Test:

- `https://YOUR-APP.onrender.com/api/health` → `{"status":"ok"}`
- Faqja kryesore → Vue SPA

---

## Opsioni B — Render + Vercel (rekomandohet për domain)

### 1. Deploy API në Render

Ndiq **Opsioni A** deri sa Render të jetë live. Ruaj URL-në:

```
https://buymap-api.onrender.com
```

### 2. Konfiguro Vercel si proxy

1. [vercel.com](https://vercel.com) → **Add New Project** → importo repo-në
2. **Framework Preset:** Other
3. **Build Command:** *(bosh — Vercel vetëm proxy)*
4. **Output Directory:** *(bosh)*
5. Në `vercel.json`, zëvendëso `YOUR_RENDER_APP_URL` me URL-në reale të Render
6. Deploy

Vercel ridrejton **të gjithë trafikun** te Render — SPA, API, assets.

### 3. Domain `buymap.ai` në Vercel

1. Vercel → Project → **Settings → Domains** → shto `buymap.ai` dhe `www.buymap.ai`
2. Namecheap DNS (shembull):

| Type | Host | Value |
|------|------|-------|
| **A** | `@` | `76.76.21.21` |
| **CNAME** | `www` | `cname.vercel-dns.com` |

(Vlerat e sakta shfaqen në Vercel pas shtimit të domain-it.)

### 4. Env në Render pas Vercel domain

```env
APP_URL=https://buymap.ai
```

---

## Variabla mjedisi (Render)

Të detyrueshme:

| Variable | Vlerë |
|----------|-------|
| `APP_KEY` | Gjenerohet automatikisht nga `render.yaml` ose `php artisan key:generate --show` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://buymap.ai` |
| `SESSION_DRIVER` | `array` |
| `CACHE_DRIVER` | `file` |
| `LOG_CHANNEL` | `stderr` |

AI & marketplace (opsionale por e nevojshme për kërkim live):

| Variable | Përshkrimi |
|----------|------------|
| `AI_PROVIDER` | `auto` \| `openai` \| `gemini` |
| `OPENAI_API_KEY` | Parser AI |
| `GEMINI_API_KEY` | Fallback AI |
| `EBAY_CLIENT_ID` / `EBAY_CLIENT_SECRET` | eBay live |
| `SERPAPI_KEY` | Google Shopping |

Render → **Environment** → shto çdo key. **Mos i commit-o në Git.**

---

## Build lokal (para deploy)

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan config:cache
php artisan route:cache
```

---

## Health check

```bash
curl -s https://buymap.ai/api/health
curl -s -X POST https://buymap.ai/api/search \
  -H "Content-Type: application/json" \
  -d '{"q":"macbook air ne zvicerr"}'
```

---

## Përditësim pas ndryshimeve në Git

| Platformë | Si përditësohet |
|-----------|-----------------|
| **Render** | Auto-deploy nga `main` (nëse aktiv) ose Manual Deploy |
| **Vercel** | Auto-deploy nga `main` (vetëm `vercel.json` ndryshon rrallë) |

Pas push në GitHub, Render rindërton automatikisht.

---

## Probleme të zakonshme

| Problem | Zgjidhje |
|---------|----------|
| 500 në Render | Shiko **Logs** → `storage/logs` via stderr; kontrollo `APP_KEY` |
| Faqe pa CSS | Build dështoi — kontrollo `npm run build` në Render logs |
| API timeout | Render free tier: 30s limit; kërkim i gjatë mund të duhet plan paid |
| Mixed content / HTTP | Vendos `APP_URL=https://...` dhe ri-deploy |
| Domain nuk hapet | Prit DNS 15 min–24h; kontrollo CNAME/A records |

---

## Skedarë konfigurimi

| Skedar | Qëllimi |
|--------|---------|
| `render.yaml` | Blueprint Render (PHP, build, health) |
| `vercel.json` | Proxy te Render + security headers |
| `Procfile` | Railway / alternativë |
| `.env.example` | Lista e plotë e env vars |

---

## Çfarë u hoq

- `docs/DEPLOY_CPANEL.md` — deploy në shared cPanel **nuk mbështetet më**
- Mos përdor `public_html`, addon domain, ose IP `162.0.232.61` për BuyMap
