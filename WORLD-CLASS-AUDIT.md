# SOCLY v3 — Stav projektu

> Poslední aktualizace: 10. 5. 2026

---

## ✅ Implementováno

### Bezpečnost
- ✅ CSP — odstraněn `unsafe-eval` ze `SecurityHeaders.php`
- ✅ Brute-force login — `RateLimiter::for('login')` s klíčem `email|ip`
- ✅ Admin deleteUser — dynamický disk (public/R2) + `forceDelete()`

### Backend
- ✅ Změna hesla — `PUT /settings/password` + formulář v `Settings.vue`
- ✅ Změna emailu — `PUT /settings/email` + migrace `pending_email`
- ✅ GDPR export — `GET /settings/export` (ZIP s profil, posty, zprávy)
- ✅ Tipy — `POST /users/{user}/tip` s 80/20 split + notifikace
- ✅ Story views — `story_views` tabulka + `POST /stories/{story}/view`
- ✅ Cron: `subscriptions:expire`, `stories:cleanup`, `posts:sync-counts`
- ✅ Queue async — `ShouldBroadcast` (kromě NewMessage)

### API integrace (hotovo 10. 5. 2026)
- ✅ **Resend** — mail driver, domain verified, forgot password + email verification funguje
- ✅ **Cloudflare R2** — všechny uploady (avatary, posty, stories, zprávy) na S3/R2 disku
- ✅ **OpenAI Moderation** — `ModerationService` kontroluje posty + komentáře (fail-open)
- ✅ **LiveKit** — `LiveStreamController` JWT token, room listing, `LiveStream.vue` + `useLiveStream.js`
- ✅ **Stories** — reálná data z DB, `StoryViewer.vue`, `CreateStoryModal.vue`, progress bars, autoplay
- ✅ **Forgot password** — `Password::sendResetLink()` přes Resend, ForgotPassword + ResetPassword Vue stránky

### Logické chyby (opraveno)
- ✅ WallController N+1 konverzace — single SQL s JOIN + subquery
- ✅ ProfileController likes — `(int)` místo `K` formátu
- ✅ Profile modal likes — odstraněn hardcoded `K` suffix
- ✅ PostInteraction — per-post channel `post.{id}`
- ✅ Whisper typing — server-side broadcasting
- ✅ FeedScreen stories — reálná data místo fake `$creators`

### Frontend
- ✅ Infinite scroll (IntersectionObserver)
- ✅ Pull-to-refresh — `usePullToRefresh` composable
- ✅ Image lazy loading — `loading="lazy"`
- ✅ Video přehrávač v modalu
- ✅ Swipe gesta pro tab switching
- ✅ a11y — `aria-label`, `role="dialog"`, `aria-modal`
- ✅ EmptyState + ConfirmModal komponenty
- ✅ `useAction` composable
- ✅ StoryViewer — fullscreen, progress bars, tap navigation, autoplay, captions
- ✅ CreateStoryModal — upload, preview, caption, locked toggle
- ✅ LiveStream.vue — camera/mic controls, viewer count
- ✅ Settings.vue — profil, zabezpečení, notifikace, soukromí, vzhled, GDPR export

### Databáze
- ✅ Performance indexy — messages, posts, likes, follows, subscriptions

---

## Zbývá (nice-to-have)

### Admin 2FA (TOTP)
- Balíček: `composer require pragmarx/google2fa-laravel`
- Přidat `google2fa_secret` sloupec, middleware `Verify2FA`, Vue stránku.

### i18n
- `npm install vue-i18n`, `locales/cs.json`, `createI18n()` v `app.js`

### CDN subdoména
- `cdn.socly.eu` → R2 custom domain pro média

---

## Infrastruktura (VPS)

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

## Testy (post-deploy)

### PHPUnit feature testy
- Registrace/login/logout, CRUD postů, follow, subscribe, like, zprávy, admin.

### Playwright E2E testy
- Registrační flow, vytvoření postu, profil → follow → zpráva.

---

*Tento soubor obsahuje stav celého projektu.*
