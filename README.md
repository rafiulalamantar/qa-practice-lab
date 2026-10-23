# QA Practice Lab

Always-on practice applications for UI and API test automation (Selenium, Playwright, Cypress, RestAssured, Postman…).
Built with Laravel 13 + MySQL, deployable to Vercel.

| Module | URL | What learners practise |
|---|---|---|
| Automation Practice | `/practice` | radio, auto-suggest, dropdown, checkbox, windows, tabs, alerts/confirm, web tables, show/hide, hover menu, iframe |
| ProtoShop | `/protoshop` | form validation, two-way binding, disabled controls, product grid, cart table, delayed country suggestions, T&C checkbox |
| ShopSphere | `/client` | register / login / reset password, product filters, cart, coupons, checkout typeahead, order history, CSV download, auth errors |
| API reference | `/docs/api` | 17 REST endpoints under `/api/ecom` for API tests and UI+API hybrid tests |

**Demo login:** `demo@practice.test` / `Demo@1234`. Coupons: `PRACTICE10`, `QA25`, `FREESHIP`, and `EXPIRED50` (inactive).

## Architecture

```
Browser ──► Vercel edge ──► /public/assets, /public/images      (static, CDN cached)
                     └────► api/index.php (vercel-php, PHP 8.4)
                               └── Laravel 13
                                     ├── Blade pages (no build step; Bootstrap 5 via CDN)
                                     ├── /api/ecom/*  Sanctum token API
                                     └── MySQL (managed, TLS) ◄── php artisan migrate (run from your machine/CI)
Vercel Cron (03:00 daily) ──► /api/cron/prune  (Bearer CRON_SECRET)
```

Key decisions:

- **ShopSphere's UI is a thin JS client over the public API.** Pages are static Blade shells; all data comes from
  `/api/ecom` using a token in `localStorage` (`token`, `userId`, `userEmail`). Learners can log in through the API and
  inject the token, intercept or mock network calls, and test the API on its own.
- **Isolation for many concurrent learners.** Carts and orders are scoped to the token's user. Reading or deleting
  another user's order returns 403. Order IDs are random 24-hex strings, not sequential, so they can't be guessed.
- **Self-cleaning.** `practice:prune` deletes orders and carts older than 7 days, expired tokens, and accounts unused for
  30 days. The demo account is never deleted, and its password cannot be reset.
- **Abuse limits.** 300 API requests/min per user, and login is limited to 30/min per IP and 10/min per email.
- **ProtoShop and Practice are stateless.** They make no database queries, so they cost almost nothing to serve.
- **Challenge mode** (`?chaos=1`, `?chaos=0`, `?seed=N`): random reveal delays, randomised element ids, shuffled table
  rows, and random API latency (`X-Chaos-Mode: 1` header or `practice_chaos` cookie). A seed replays the same run, so a
  learner can reproduce a flaky failure.

## Local development

Uses the existing MySQL server on port 3306, with its own database and a dedicated user that can only access this
project's databases:

```bash
composer install
cp .env.example .env && php artisan key:generate
mysql -u root -p <<'SQL'
CREATE DATABASE qa_practice_lab      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE qa_practice_lab_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'qa_lab'@'localhost' IDENTIFIED BY '<password>';
CREATE USER 'qa_lab'@'127.0.0.1' IDENTIFIED BY '<password>';
GRANT ALL PRIVILEGES ON qa_practice_lab.*      TO 'qa_lab'@'localhost', 'qa_lab'@'127.0.0.1';
GRANT ALL PRIVILEGES ON qa_practice_lab_test.* TO 'qa_lab'@'localhost', 'qa_lab'@'127.0.0.1';
SQL
# put <password> in DB_PASSWORD in .env, then:
php artisan migrate --seed
php artisan serve
```

Tests:

```bash
php artisan test                                   # fast, in-memory SQLite
DB_CONNECTION=mysql DB_DATABASE=qa_practice_lab_test php artisan test   # same suite on MySQL
```

The MySQL run wipes and rebuilds `qa_practice_lab_test` only, never the dev database.

## Deploying to Vercel

1. **Create a managed MySQL database.** Vercel does not host MySQL. Good free or cheap options are TiDB Cloud
   Serverless (MySQL compatible), Aiven for MySQL, and Railway. Note the host, port, user, password and database name.
   Pick a region close to your Vercel function region (default `iad1`, US East).
2. **Run migrations from your machine** against that database (never during the Vercel build):
   ```bash
   DB_HOST=… DB_PORT=… DB_DATABASE=… DB_USERNAME=… DB_PASSWORD=… \
   MYSQL_ATTR_SSL_CA=/etc/ssl/cert.pem php artisan migrate --seed --force
   ```
   (`/etc/ssl/cert.pem` is the macOS CA bundle.)
3. **Push this folder to GitHub and import it in Vercel** (Framework preset: *Other*). `vercel.json` already sets the
   PHP runtime, routing, cron and the serverless-safe settings (`/tmp` caches, cookie sessions, stderr logs).
4. **Add environment variables** in Vercel → Settings → Environment Variables:

   | Name | Value |
   |---|---|
   | `APP_KEY` | output of `php artisan key:generate --show` |
   | `APP_URL` | `https://<your-project>.vercel.app` |
   | `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | from step 1 |
   | `MYSQL_ATTR_SSL_CA` | `/etc/pki/tls/certs/ca-bundle.crt` |
   | `CRON_SECRET` | any long random string |

5. Deploy, then open `/up` (health check) and `/client/auth/login`.

### Things to know on Vercel

- The filesystem is read-only except `/tmp`; nothing here writes to `storage/`.
- Cold starts take about 1 s; warm requests are fast. Challenge-mode delays count toward function time, so they are
  capped at 2.5 s (`PRACTICE_CHAOS_MAX_MS`).
- The Hobby plan allows one cron run per day, which is all `practice:prune` needs.
- If you outgrow Vercel's PHP runtime (it is community-maintained), the same code runs unchanged on Laravel Cloud,
  Railway or any VPS. Only `vercel.json` and `api/index.php` are Vercel-specific.
