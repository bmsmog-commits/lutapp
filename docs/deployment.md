# Lutapp: CI, deployment readiness & production checklist

Status at Phase 27.11 (2026-10-16): **CI and a production container are defined and verified locally. No hosting target is configured, and no deploy workflow exists.** Nothing has been pushed or deployed.

## 1. Continuous integration (`.github/workflows/ci.yml`)

Runs on pushes to `main`, on every pull request and on manual dispatch. It uses **no repository secrets** and has `contents: read` only, so fork PRs are safe.

| Job | What it proves |
|---|---|
| **Tests & frontend build** (PHP 8.3, SQLite) | `composer install` from `composer.lock`, `npm ci` from `package-lock.json`, `npm run build`, then `php artisan test` with `phpunit.xml`'s in-memory SQLite. The build runs first because the Blade layout uses `@vite`. |
| **Production container** | Builds the `Dockerfile` (not pushed), checks the image has no secrets/data/tests and runs as `www-data`, checks it refuses to start without `APP_KEY`, then boots it and requires `/up` to be healthy. |
| **Migrations & seeders** (PostgreSQL 16) | Fresh `migrate`, `db:seed` twice (fails if any translation code is duplicated or the sample isn't exactly 31 verses), `migrate:reset` (every `down()`), then `migrate` again. |

Guarantees the suite never touches live services:
- `phpunit.xml` blanks `API_BIBLE_API_KEY`.
- `tests/TestCase.php` calls `Http::preventStrayRequests()`, so any un-faked outbound request fails the test.
- The workflow sets no API key.

Verified locally before push:
- The test job's exact steps passed, **787/787**, in a clean copy of the commit-candidate files (no local `.env`, `vendor`, `node_modules` or built assets).
- The migration cycle passed on fresh **MySQL 8** and **SQLite**.
- The PostgreSQL job's sequence passed **locally against a postgres:16 container** using the production image (Phase 27.11). The GitHub Actions run itself is **not yet verified**: nothing has been pushed.

Not in CI (deliberate): live API.Bible calls, deployment, and `npm audit` gating. `npm audit` reports **0 vulnerabilities** since Phase 27.11 (lockfile-only updates: `concurrently` 9.2.4→9.2.5, `shell-quote` 1.9.0→1.12.0, `source-map-js` 1.2.1→1.2.2).

## 2. Production container (Phase 27.11)

**Hosting target: still not established.** The image is platform-neutral; no deploy workflow exists.

### Image (`Dockerfile`, `docker/`)

| Stage | Purpose |
|---|---|
| `base` | `dunglas/frankenphp:1-php8.3`: PHP **8.3** (same as CI) + `pdo_pgsql`, `pgsql`, `bcmath`, `intl`, `zip`, `opcache` |
| `vendor` | `composer install --no-dev` from `composer.lock` on that exact runtime. A **release gate** fails the build if any `.env`, SQLite, SQL, dump, key or PEM file reached the build context. |
| `assets` | `node:22-alpine` (same Node major as CI); `npm ci` from `package-lock.json`, then `npm run build` |
| `runtime` | FrankenPHP (Caddy) serving `/app/public` on **:8080**. Runs as **`www-data`**; code is root-owned and read-only, and only `storage/` and `bootstrap/cache/` are writable. `php.ini-production` plus `docker/php/conf.d/zz-production.ini` (OPcache with immutable code, 55M uploads, `expose_php=Off`). `HEALTHCHECK` on `/up`. |

**Runtime decisions:**
- **Server:** FrankenPHP's Caddy server, not `php artisan serve`. HTTP only on :8080; **TLS is expected at the platform/load balancer**. Running Caddy with its own certificates would mean `SERVER_NAME=your.domain` and publishing 80/443, which is a hosting decision.
- **HTTPS awareness: NOT yet configured (deployment blocker).** Caddy's `trusted_proxies private_ranges` only affects how Caddy reads client IPs. **Laravel itself does not trust any proxy** (`bootstrap/app.php` has no `trustProxies(...)`), so behind a TLS-terminating load balancer it would see plain HTTP and generate `http://` URLs and redirects. Once the host is known, add `$middleware->trustProxies(at: …)` with that platform's proxy addresses (or `'*'` only if the container is reachable solely through the platform proxy), and verify `https://` URLs and secure cookies.
- **Persistent storage volume** must be writable by uid/gid **33** (`www-data`). The entrypoint creates missing `storage/` subdirectories and exits on failure rather than starting with an unwritable volume.
- **Entrypoint (`docker/entrypoint.sh`):** refuses to start without `APP_KEY`, creates `storage/` subdirectories (for an empty volume), and runs `php artisan optimize` (config/route/view/event caches) at **start**, because cached config captures runtime environment variables. Set `LARAVEL_SKIP_OPTIMIZE=true` to skip.
- **Logs:** `LOG_CHANNEL=stderr` by default, so container logs reach the platform.
- **No secrets in the image:** `.dockerignore` excludes `.env*` (except `.env.example`), databases/dumps, uploads, logs, caches, tests, docs, `.git`. Patterns use `**/` because bare Docker patterns only match at the context root (`*.sqlite` previously let `database/database.sqlite` in).

**Verified locally on 2026-10-16** (Docker 29.5.3, image `lutapp:phase-27.11`, 938 MB):
- **Build:** succeeds. Contents: no `.env`/SQLite/tests/dev packages; Vite manifest present; runs as `www-data`. The leak guard fails on planted files.
- **Startup:** without `APP_KEY` the container exits 1. With a key and **no database**, `/up` returns 200 and the container is healthy.
- **Against PostgreSQL 16:** fresh migrate → seed twice (12 translations, no duplicates, 31 sample verses) → full rollback → migrate again: **all pass**.
- **Web, over HTTP:**
  - `/up` 200; login form with CSRF, session stored in PostgreSQL; KJV Genesis 1 renders; Study 200; search works; reading history recorded; the scheduler lists `bible:prune-reading-history` hourly.
  - Storage writes are served through `/storage`.
  - `/.env` and path traversal return 404; no `X-Powered-By`; debug pages off; Vite assets served `immutable` and zstd-compressed.

### Release process (any container platform)

1. **Build** the image in CI from a tagged commit. Never build from a developer machine with local files.
2. **Back up** the database (`pg_dump -Fc`) and the `storage` volume.
3. **Migrate once**, as a one-off container from the *new* image, before switching traffic. It is never run automatically on start, so replicas can't race:
   `docker run --rm --env-file <secrets> IMAGE php artisan migrate --force`
4. **First install only:** `php artisan db:seed --force` (insert-only), then `php artisan bible:import-kjv` and `php artisan bible:verify KJV` as one-off containers.
5. **Start/replace the web container(s)**: `IMAGE` with the default command, `/app/storage` on a **persistent volume**, port 8080 behind TLS. Wait for `/up` to be healthy.
6. **Run exactly one scheduler process** from the same image: `IMAGE php artisan schedule:work`, with the same environment and volume. Alternatively, run `php artisan schedule:run` every minute from the platform's cron. It is required for the hourly 24-hour reading-history prune.
7. **Queue:** nothing is queued today. If queued work is added, run `IMAGE php artisan queue:work` as another process.
8. **Rollback:** redeploy the previous image tag. If its schema differs, restore the backup from step 2 (see section 4).

### Information needed to build a deploy workflow
- Hosting provider and deploy mechanism (container registry + platform, SSH/rsync to a VPS, PaaS Git integration…)
- Production domain (`APP_URL`) and TLS termination point
- PostgreSQL host/version and backup capability
- Where `storage/` lives persistently (volume, or S3-compatible bucket)
- How cron/scheduler and (future) queue workers run on that host
- A health-check URL the platform can probe (Laravel serves `/up`)
- GitHub environment `production` with required reviewers, holding deploy secrets (never in the repo):
  - `APP_KEY`
  - DB credentials
  - `API_BIBLE_API_KEY`
  - mail credentials
  - registry/SSH credentials

A deploy job should `needs: [tests, migrations-postgres]`, run only on `main`, use that protected environment, and never run on `pull_request`.

## 3. Production environment checklist

| Area | Setting / action |
|---|---|
| App | `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, `APP_KEY` (generate once and keep stable: rotating it logs everyone out and breaks encrypted data) |
| Database | `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| API.Bible | `API_BIBLE_API_KEY`; keep `API_BIBLE_ALLOW_COPYRIGHTED=false` and `API_BIBLE_PENDING_VERIFICATION=ASV` until the plan/use is confirmed (see `bible-translation-licensing.md`) |
| Cache / sessions | `CACHE_STORE=database` (or `redis`) and `SESSION_DRIVER=database` (both need the migrated tables). `SESSION_SECURE_COOKIE=true` when on HTTPS. API.Bible usage counters need an atomic store (`database` or `redis`, not `file`). |
| Mail | `MAIL_MAILER` + provider credentials. The default `log` sends **nothing**. |
| Filesystem | `FILESYSTEM_DISK`; persistent `storage/app` (uploads: profile photos, logos, media, audio); `php artisan storage:link` |
| Permissions | `storage/` and `bootstrap/cache/` writable by the web user |
| Assets | Built inside the image (`assets` stage); nothing to do at deploy time |
| Release steps | One-off `php artisan migrate --force` per release (section 2). Caches are rebuilt by the entrypoint on every container start. |
| Scheduler | Cron `* * * * * php /path/artisan schedule:run`. Needed for the hourly `bible:prune-reading-history`. Without it, expired history is still hidden (queries filter on `expires_at`), but rows are never deleted. |
| Queue | No queued jobs exist today. `QUEUE_CONNECTION=database` is harmless, but run `php artisan queue:work` if queued work is added. |
| Logging | The image defaults to `LOG_CHANNEL=stderr` (collected by the platform); set `LOG_LEVEL=warning` or `error`. API.Bible threshold alerts arrive as `warning`/`error` log entries. |
| Health | Laravel's `/up` route |
| HTTPS | Terminate TLS, set `APP_URL` to https, and **add Laravel proxy trust** (`trustProxies` in `bootstrap/app.php`, not yet present; see section 2). Set `SESSION_SECURE_COOKIE=true`. |

### First-install data steps
1. `php artisan migrate --force`
2. `php artisan db:seed --force`: languages, translation catalog, books, and a 31-verse Genesis 1 sample. **Insert-only since Phase 27.10**: it never overwrites existing verses.
3. `php artisan bible:import-kjv`: fetches the public-domain KJV from `raw.githubusercontent.com/aruljohn/Bible-kjv` (needs outbound HTTPS). Then run `php artisan bible:verify KJV`; expect 31,102 verses, 66 books, 1,189 chapters.
4. `php artisan bible:api-bible-catalog --language=eng --check-linked` (uses a few API calls) to confirm remote translations.

## 4. Backup, migration & rollback

- **Before every release:** take a database snapshot (`pg_dump -Fc`) and back up `storage/app`. Test restores periodically (`pg_restore` into a scratch database).
- **Rollback = redeploy the previous release + restore the snapshot** if the schema changed. Do not rely on `migrate:rollback` for production data:
  - `2026_09_17_100001_fix_bible_verses_unique_constraint` intentionally has a no-op `down()`.
  - Rolling back past `2026_08_31_000004_create_bible_translations_table` maps verses back to legacy codes, but re-migrating then **refuses to continue** until the translation catalog is re-seeded. That guard prevents orphaned verses.
- **Migration safety reviewed in Phase 27.10.** Fixed before any deployment:
  - `2026_08_31_000004`: failed on fresh MySQL (error 1553), and its `down()` called a non-existent method.
  - `2026_09_17_100001`: MySQL-only `SHOW INDEX`; would have failed on PostgreSQL.
  - `2026_09_21_000003`: MySQL-only `DATE_ADD`; would have failed on PostgreSQL.
  - `2026_09_20_800001`: `down()` failed on SQLite (index dropped after its column).

  These edits do not change the schema on databases that already ran them.
- Never run `migrate:fresh`, `migrate:reset` or `db:wipe` against production.

## 5. Features intentionally not operational

| Feature | State | What enables it |
|---|---|---|
| Giving / payments | **Simulated** (`PAYMENT_PROVIDER=fake`). No real money moves. | Implement and configure a real provider (Paystack config is stubbed, not wired) |
| Email | `MAIL_MAILER=log` writes to the log only | Mail provider credentials |
| SMS phone verification | `NullSmsVerificationProvider`; nothing is sent | An SMS provider implementation |
| WhatsApp | `NullWhatsAppProvider`; nothing is sent | A WhatsApp provider implementation |
| AI assistant | `NullAiAssistant` answers "not configured" | An AI provider implementation |
| NLT / ASV | Licence verification required | See `bible-translation-licensing.md` |
| NIV, ESV, NASB, CSB, Segond 21 | Not available to this API.Bible account | A licence and catalog access |
