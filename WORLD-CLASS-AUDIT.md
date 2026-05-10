# SOCLY v3 — Kompletní Audit

> Poslední aktualizace: 10. 5. 2026

---

## 🔴 KRITICKÉ BUGY (nefunkční funkce)

### B1. MessageController — chybí `use Storage`
- **Soubor:** `app/Http/Controllers/MessageController.php`
- **Problém:** Třída používá `Storage::disk()` na řádku 83 a 87, ale nemá import `use Illuminate\Support\Facades\Storage;`
- **Dopad:** Upload souborů ve zprávách **CRASHNE** s fatal error
- **Fix:** Přidat import

### B2. Home route `/` nemá `verified` middleware
- **Soubor:** `routes/web.php` řádek 25
- **Problém:** `Route::get('/', ...)->middleware('auth')` — chybí `verified`
- **Dopad:** Neověření uživatelé se dostanou na feed místo přesměrování na `/email/verify`
- **Fix:** Změnit na `->middleware(['auth', 'verified'])`

### B3. User model — `cover_image` není v `$fillable`
- **Soubor:** `app/Models/User.php`
- **Problém:** `ProfileController::update` ukládá `cover_image` přes `$user->update($validated)`, ale pole není ve fillable
- **Dopad:** Cover image se **NIKDY neuloží** do DB
- **Fix:** Přidat `'cover_image'` do `$fillable`

### B4. `postsApi` — ambiguous column `likes_count` (trending sort)
- **Soubor:** `app/Http/Controllers/WallController.php` řádek 191
- **Problém:** Query používá `.withCount(['likes'])` což vytvoří alias `likes_count`, ale tabulka `posts` má **také sloupec** `likes_count`. Při `orderByDesc('likes_count')` PostgreSQL vrací error 42702.
- **Dopad:** Feed s trending řazením **CRASHNE**
- **Fix:** Použít `orderByDesc('posts.likes_count')` nebo odstranit withCount pro likes

### B5. StoryController::index — `orWhere` scope leak
- **Soubor:** `app/Http/Controllers/StoryController.php` řádek 26
- **Problém:** `->orWhere('user_id', $user->id)` je na top-level query, takže bypass `active()` scope — vlastní expirované stories se stále zobrazí
- **Dopad:** Uživatel vidí své stories i po expiraci (24h bypass)
- **Fix:** Zabalit do nested where

### B6. `is_banned` není v User `$casts`
- **Soubor:** `app/Models/User.php`
- **Problém:** Sloupec `is_banned` existuje v DB ale není v `$casts`
- **Dopad:** Může vracet string `"0"` místo `false`
- **Fix:** Přidat `'is_banned' => 'boolean'` do `$casts`

---

## 🟡 UI/UX PROBLÉMY

### U1. Settings — `cover_image` v shared Inertia data chybí
- **Soubor:** `app/Http/Middleware/HandleInertiaRequests.php`
- **Problém:** Settings.vue čte `user.cover_image` ale HandleInertiaRequests to nepředává
- **Dopad:** Cover preview v nastavení je vždy `undefined`
- **Fix:** Přidat `'cover_image'` do shared auth user data

### U2. Sitemap generuje `/@username` ale route neexistuje
- **Soubor:** `routes/web.php` řádek 155
- **Problém:** Sitemap odkazuje na `https://socly.eu/@username` — taková route neexistuje (profily jsou na `/profile/{id}`)
- **Dopad:** Google indexuje mrtvé linky (404)
- **Fix:** Přidat route `/@{username}` nebo změnit sitemap na `/profile/{id}`

### U3. Wallet.vue — `balance` reaktivita
- **Problém:** `balance` je `ref(props.balance)` — při Inertia navigaci zpět se nereaktivuje
- **Dopad:** Po návratu na wallet stránku se může zobrazit starý zůstatek
- **Fix:** Použít `computed` nebo `watch` na props

### U4. Žádný veřejný profil pro nepřihlášené
- **Problém:** `/profile/{id}` vyžaduje auth+verified — sdílené linky nefungují
- **Dopad:** Sdílení profilu na sociální sítě vede na login stránku

### U5. Registrace — opraveno (10.5.)
- ✅ `onError` přepne na step 1 kde se zobrazí validační chyby
- ✅ Hesla se resetují jen při chybě

---

## 🟢 FUNKČNÍ (ověřeno)

- ✅ **Email verifikace** — Registered event, verified middleware, VerifyEmail page
- ✅ **Case-insensitive unique** email+username (normalize to lowercase)
- ✅ **Admin panel** — search, reporty, ban/unban, revenue stats, flash messages
- ✅ **Password reset** přes Resend
- ✅ **Stories** — vytvoření, zobrazení, 24h expirace, VIP/locked
- ✅ **OpenAI moderation** — posty, komentáře
- ✅ **LiveKit streaming** — token generation, room listing
- ✅ **R2 Storage** — posty, avatary, covers, stories
- ✅ **Wallet** — crypto deposit (NOWPayments), withdraw, tip, unlock
- ✅ **Messages** — text, media, voice, reactions, edit/delete, read receipts
- ✅ **Follow/Subscribe** — toggle, auto-follow on subscribe
- ✅ **Like/Comment/Bookmark** — real-time broadcast
- ✅ **Notifications** — DB + WebSocket broadcast
- ✅ **Block/Report** — users & posts, follow cleanup
- ✅ **Account deletion** — soft-delete + anonymize (GDPR)
- ✅ **Data export** — ZIP (profil, posty, zprávy)
- ✅ **Security headers** — CSP, HSTS, X-Frame-Options
- ✅ **Login ban check** — zabanovaní se nepřihlásí
- ✅ **Button UI system** — btn-premium, btn-cta, btn-ghost, btn-danger, btn-icon
- ✅ **Legal pages** — Terms, Privacy, Content Policy
- ✅ **Sitemap** — dynamic XML (ale s nesprávnými URL — viz U2)
- ✅ **Search** — ilike, SQL injection safe
- ✅ **Settings** — heslo, email, profil, notifikace, vzhled

---

## 📋 GLOBÁLNÍ OPRAVY (7 fixů)

| # | Oprava | Soubor | Priorita |
|---|--------|--------|----------|
| 1 | Přidat `use Storage` import | `MessageController.php` | KRITICKÁ |
| 2 | Přidat `cover_image` do `$fillable` | `User.php` | KRITICKÁ |
| 3 | Přidat `is_banned` do `$casts` | `User.php` | VYSOKÁ |
| 4 | Home route — přidat `verified` middleware | `web.php` | VYSOKÁ |
| 5 | Trending feed — opravit ambiguous `likes_count` | `WallController.php` | VYSOKÁ |
| 6 | Story index — opravit `orWhere` scope leak | `StoryController.php` | STŘEDNÍ |
| 7 | Shared data — přidat `cover_image` | `HandleInertiaRequests.php` | STŘEDNÍ |

---

## 🔵 CHYBĚJÍCÍ FUNKCE (TODO)

| # | Funkce | Priorita |
|---|--------|----------|
| F1 | Subscription auto-renewal (cron) | VYSOKÁ |
| F2 | Admin — schvalování výběrů (payout queue) | VYSOKÁ |
| F3 | Push notifikace (service worker) | STŘEDNÍ |
| F4 | PWA manifest + offline shell | STŘEDNÍ |
| F5 | Creator analytics (earnings, views) | STŘEDNÍ |
| F6 | Veřejné profily (bez auth) | STŘEDNÍ |
| F7 | 2FA (TOTP) | NÍZKÁ |
| F8 | Content watermarking | NÍZKÁ |
| F9 | IP rate-limit + auto-ban | NÍZKÁ |

---

## 🏗 Infrastruktura (VPS)

### Redis (doporučeno)
```
apt install redis-server php-redis
```
V `.env`: `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`

### Zálohy databáze
```
0 3 * * * pg_dump socly > /backups/socly_$(date +\%F).sql && find /backups -mtime +30 -delete
```

### Firewall + SSL
```
ufw default deny incoming && ufw allow 22,80,443,8080/tcp && ufw enable
certbot renew --dry-run
```

---

*Tento soubor obsahuje kompletní audit SOCLY v3.*
