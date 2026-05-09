# SOCLY v3 — Co ještě chybí

> Pouze nedokončené položky. Vše hotové bylo odstraněno.
> Poslední aktualizace: 5. 5. 2026

---

## ✅ Implementováno (5. 5. 2026)

### Bezpečnost
- ✅ CSP — odstraněn `unsafe-eval` ze `SecurityHeaders.php`
- ✅ Brute-force login — `RateLimiter::for('login')` s klíčem `email|ip` v `AppServiceProvider`
- ✅ Admin deleteUser — mazání souborů (avatar, cover, posty) + `forceDelete()`

### Backend
- ✅ Změna hesla — `PUT /settings/password` + formulář v `Settings.vue`
- ✅ Změna emailu — `PUT /settings/email` + migrace `pending_email`
- ✅ GDPR export — `GET /settings/export` (ZIP s profil, posty, zprávy)
- ✅ Tipy — `POST /users/{user}/tip` s 80/20 split + notifikace
- ✅ Story views — `story_views` tabulka + `POST /stories/{story}/view`
- ✅ Cron: `subscriptions:expire` (daily), `stories:cleanup` (hourly), `posts:sync-counts` (hourly)
- ✅ Queue async — `ShouldBroadcast` místo `ShouldBroadcastNow` (kromě NewMessage)

### Logické chyby
- ✅ WallController N+1 konverzace — přepsáno na single SQL s JOIN + subquery
- ✅ ProfileController likes — `(int) $p->likes_count` místo `K` formátu
- ✅ PostInteraction — per-post channel `post.{id}` místo public `posts`
- ✅ Whisper typing — opraveno na server-side broadcasting
- ✅ Admin deleteUser — konzistentní `forceDelete()`

### Frontend
- ✅ Infinite scroll (IntersectionObserver) — již existoval
- ✅ Pull-to-refresh — `usePullToRefresh` composable
- ✅ Image lazy loading — `loading="lazy"` na všech `<img>`
- ✅ Video přehrávač v modalu — `<video>` s `controls playsinline`
- ✅ Swipe gesta pro tab switching — touchstart/touchend v `AuthenticatedLayout`
- ✅ a11y — `aria-label`, `role="dialog"`, `aria-modal`, `aria-current`
- ✅ EmptyState + ConfirmModal komponenty
- ✅ `useAction` composable (loading stavy)

### Databáze
- ✅ Performance indexy — migrace pro messages, posts, likes, follows, subscriptions

### Nové migrace (ke spuštění na VPS)
- `add_pending_email_to_users_table`
- `create_story_views_table`
- `add_performance_indexes`

---

## Zbývá (vyžaduje API klíče / VPS přístup)

### Admin 2FA (TOTP)
- Balíček: `composer require pragmarx/google2fa-laravel`
- Přidat `google2fa_secret` sloupec, middleware `Verify2FA`, Vue stránku.

### Live streaming (LiveKit)
- `composer require agence104/livekit-server-sdk`
- Vyžaduje: `LIVEKIT_API_KEY`, `LIVEKIT_API_SECRET`, `LIVEKIT_URL`

### AI moderace obsahu
- Vyžaduje: `OPENAI_API_KEY`

### i18n
- `npm install vue-i18n`, `locales/cs.json`, `createI18n()` v `app.js`

### CDN — Cloudflare R2
- Přesměrovat DNS, média na R2 s `cdn.socly.eu`

---

## Infrastruktura (VPS — po deployi)

### Redis aktivace
```
apt install redis-server php-redis
```
V `.env`: `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`

### Sentry instalace
```
composer require sentry/sentry-laravel
```
V `.env`: `SENTRY_LARAVEL_DSN=xxx`

### Zálohy databáze (cron)
```
0 3 * * * pg_dump socly > /backups/socly_$(date +\%F).sql && find /backups -mtime +30 -delete
```

### Firewall + SSL
```
ufw default deny incoming && ufw allow 22,80,443,8080/tcp && ufw enable
certbot renew --dry-run
```

---

## API klíče k obstarání (vše ZDARMA)

### 1. Resend — transakční emaily (FREE: 3 000 emailů/měsíc)
1. Jdi na https://resend.com/signup a registruj se (GitHub/Google/email)
2. Po přihlášení klikni **API Keys** → **Create API Key**
3. Pojmenuj klíč (např. `socly-production`), scope: **Full access**
4. Zkopíruj klíč (začíná `re_...`)
5. Do `.env` na VPS:
```env
RESEND_KEY=re_xxxxxxxxxx
```

### 2. Cloudflare R2 — media storage (FREE: 10 GB, 0 Kč za egress)
1. Registruj se na https://dash.cloudflare.com/sign-up (není potřeba karta)
2. V levém menu klikni **R2** → **Object Storage**
3. Klikni **Create bucket**, pojmenuj `socly-media`, region: **Automatic**
4. Jdi do **R2** → **Manage R2 API Tokens** → **Create API token**
5. Název: `socly`, permissions: **Object Read & Write**, bucket: `socly-media`
6. Zkopíruj **Access Key ID** a **Secret Access Key** (zobrazí se jen jednou!)
7. Endpoint najdeš v detailu bucketu (formát: `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`)
8. Do `.env` na VPS:
```env
FILESYSTEM_DISK=r2
R2_ACCESS_KEY_ID=xxxxxxxxxx
R2_SECRET_ACCESS_KEY=xxxxxxxxxx
R2_BUCKET=socly-media
R2_ENDPOINT=https://ACCOUNT_ID.r2.cloudflarestorage.com
```

### 3. Sentry — error tracking (FREE: 5 000 chyb/měsíc)
1. Registruj se na https://sentry.io/signup/ (GitHub/Google/email)
2. Vytvoř organizaci (např. `socly`)
3. **Create Project** → platforma: **Laravel**, název: `socly-production`
4. Sentry ti rovnou ukáže DSN (formát: `https://xxx@oXXX.ingest.sentry.io/XXX`)
5. Nebo: **Settings** → **Projects** → `socly-production` → **Client Keys (DSN)**
6. Do `.env` na VPS:
```env
SENTRY_LARAVEL_DSN=https://xxx@oXXX.ingest.sentry.io/XXX
```

### 4. LiveKit — live streaming (FREE: 50 GB přenos/měsíc)
1. Registruj se na https://cloud.livekit.io (GitHub/Google)
2. Vytvoř projekt (např. `socly`)
3. Na dashboardu uvidíš **API Key** a **API Secret**
4. URL projektu je ve formátu `wss://socly-xxxxx.livekit.cloud`
5. Do `.env` na VPS:
```env
LIVEKIT_API_KEY=APIxxxxxxxxxx
LIVEKIT_API_SECRET=xxxxxxxxxx
LIVEKIT_URL=wss://socly-xxxxx.livekit.cloud
```

### 5. OpenAI — AI moderace (FREE: moderation endpoint je zdarma)
1. Registruj se na https://platform.openai.com/signup (Google/email)
2. **API Keys** → **Create new secret key**, pojmenuj `socly`
3. Zkopíruj klíč (začíná `sk-...`)
4. **Moderation endpoint** (`/v1/moderations`) je **zdarma** a nepočítá se do limitu
5. Do `.env` na VPS:
```env
OPENAI_API_KEY=sk-xxxxxxxxxx
```
> Pozn: Registrace vyžaduje ověření telefonním číslem. Moderace je free, ale pokud bys chtěl i generování textu/obrázků, to už je placené.

---

## Testy (post-deploy)

### PHPUnit feature testy
- Registrace/login/logout, CRUD postů, follow, subscribe, like, zprávy, admin.

### Playwright E2E testy
- Registrační flow, vytvoření postu, profil → follow → zpráva.

---

*Tento soubor obsahuje pouze to, co JEŠTĚ NEBYLO implementováno.*
