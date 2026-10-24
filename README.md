# QA Practice Lab

Always-on practice applications for UI and API test automation (Selenium, Playwright, Cypress, RestAssured, Postman…).
Built with Laravel 13 + MySQL, deployable to Vercel.

| Module | URL | What learners practise |
|---|---|---|
| Automation Practice | `/practice` | radio, auto-suggest, dropdown, checkbox, windows, tabs, alerts/confirm, web tables, show/hide, hover menu, iframe |
| ProtoShop | `/protoshop` | form validation, two-way binding, disabled controls, product grid, cart table, delayed country suggestions, T&C checkbox |
| ShopSphere | `/client` | register / login / reset password, product filters, cart, coupons, checkout typeahead, order history, CSV download, auth errors |
| TicketTrail | `/tickets` | event booking: search/category/city filters, pagination, seat stepper, sold-out states, confirm dialogs, a 4 s async refund check, event CRUD with read-only featured rows, booking table with event filter |
| API reference | `/docs/api`, `/tickets/api-docs` | ShopSphere's 17 endpoints under `/api/ecom`; TicketTrail's 16 under `/api/tickets` (Swagger UI, `openapi.json` imports into Postman) |

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
- **TicketTrail sandbox.** It shares the users table (and the demo login) with ShopSphere, but stores its session as
  `tt_token` / `tt_user` in `localStorage`. Featured events are shared and read-only (`isStatic: true`); seats are counted
  per user, so one learner selling out an event never affects another. Each account keeps at most 6 custom events and
  9 bookings, and the oldest is replaced. Errors use `{success: false, error, details?}`, with validation failures as 400.
  The API accepts any 6+ character password while the register page demands a stronger one: a deliberate UI-vs-API gap.
- **Self-cleaning.** `practice:prune` deletes orders, bookings and carts older than 7 days, expired tokens, and every learner
  account 30 days after it was registered (however active it is), so learners register again. The demo account shown
  on the login pages is never deleted, and its password cannot be reset.
- **Abuse limits.** 300 API requests/min per user, and login is limited to 30/min per IP and 10/min per email.
- **ProtoShop and Practice are stateless.** They make no database queries, so they cost almost nothing to serve.
- **Challenge mode** (`?chaos=1`, `?chaos=0`, `?seed=N`): random reveal delays, randomised element ids, shuffled table
  rows, and random API latency (`X-Chaos-Mode: 1` header or `practice_chaos` cookie). A seed replays the same run, so a
  learner can reproduce a flaky failure.

## Code map for new developers

Start with `routes/web.php` (pages) and `routes/api.php` (API). Every route leads to one of the folders below.

```
app/
├── Actions/Tickets/BookTickets.php   Business rules for booking seats (locking, sold out, sandbox cap)
├── Console/Commands/                 practice:prune, the daily clean-up (scheduled in routes/console.php)
├── Enums/BookingStatus.php           Allowed booking statuses
├── Exceptions/
│   ├── TicketsApiException.php       Throw this for an expected TicketTrail error (404, 403, sold out...)
│   └── TicketsApiErrorRenderer.php   Turns any error on /api/tickets/* into {success:false, error}
├── Http/
│   ├── Controllers/Api/Ecom/         ShopSphere API  (/api/ecom/*)
│   ├── Controllers/Api/Tickets/      TicketTrail API (/api/tickets/*); all extend TicketsController
│   ├── Controllers/Api/CronController.php   Vercel Cron + post-deploy migrate hook
│   ├── Middleware/                   Bare-token Authorization header, challenge-mode latency
│   └── Requests/Tickets/             Validation rules for the bigger TicketTrail request bodies
├── Models/                           Eloquent models; each has toApi() = its exact public JSON shape
└── Support/
    ├── AccountPurger.php             Deletes expired learner accounts and all of their data
    ├── VersionedAsset.php            Cache-busting ?v= URLs for CSS, JS and images
    ├── TicketsOpenApi.php            Swagger spec served at /api/tickets/openapi.json
    └── PracticeData.php, Countries.php   Fixed data for the stateless modules
resources/views/<module>/            Blade page shells (one folder per module)
public/assets/js/<module>.js         Each module's front end: calls the API and renders the page
config/practice.php                  Every practice-specific setting (demo login, limits, retention)
tests/Feature/                       One test file per module; read these to learn the expected behaviour
```

### Conventions

- **The public API is a contract.** Learners' test suites depend on every URL, field name, status code and error
  message. Never rename or reshape them; add new fields instead. Each model's `toApi()` is the single place a
  JSON shape is defined.
- **Every element learners automate has an `id` and a `data-testid`.** Keep both when changing a page.
- **Controllers stay thin.** They validate input, call a model or Action, and return JSON. Rules with real logic
  (locking, money, limits) go in `app/Actions`.
- **TicketTrail errors are thrown, not returned:** `throw TicketsApiException::notFound('Event with id 5 not found')`.
  ShopSphere keeps its own error format, `{message}`, because it copies a different public API.
- **Never change the demo login** (`config/practice.php`, the demo user in `DatabaseSeeder`, the `is_demo` checks).
  It is printed on the login pages.
- **Test every behaviour change** in `tests/Feature`, then run `php artisan test --compact` and `vendor/bin/pint --dirty`.

## Local development

Uses the existing MySQL server on port 3306, with its own database and a dedicated user that can only access this
project's database:

```bash
composer install
cp .env.example .env && php artisan key:generate
mysql -u root -p <<'SQL'
CREATE DATABASE qa_practice_lab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'qa_lab'@'localhost' IDENTIFIED BY '<password>';
CREATE USER 'qa_lab'@'127.0.0.1' IDENTIFIED BY '<password>';
GRANT ALL PRIVILEGES ON qa_practice_lab.* TO 'qa_lab'@'localhost', 'qa_lab'@'127.0.0.1';
SQL
# put <password> in DB_PASSWORD in .env, then:
php artisan migrate --seed
php artisan serve
```

Tests run on an in-memory SQLite database, so they need no MySQL setup and never touch your dev data:

```bash
php artisan test --compact
vendor/bin/pint --dirty    # format PHP before committing
```

## Deploying to Vercel

> **Live:** https://qa-practice-lab-six.vercel.app, backed by the TiDB Cloud Starter instance `qa-practice-lab`
> (Singapore, $0 spending limit), database `qa_practice_lab`. Its credentials are Vercel environment variables
> (`DB_*`), with a local git-ignored copy in `.env.tidb`. TiDB's own Vercel integration also works: the app falls
> back to the `TIDB_*` variables it injects.

1. **Create a managed MySQL database.** Vercel does not host MySQL. Good free or cheap options are TiDB Cloud
   Serverless (MySQL compatible), Aiven for MySQL, and Railway. Note the host, port, user, password and database name.
   Pick a region close to your Vercel function region (default `iad1`, US East).
2. **Push this folder to GitHub and import it in Vercel** (Framework preset: *Other*). `vercel.json` already sets the
   PHP runtime, routing, cron and the serverless-safe settings (`/tmp` caches, cookie sessions, stderr logs).
3. **Add environment variables** in Vercel → Settings → Environment Variables:

   | Name | Value |
   |---|---|
   | `APP_KEY` | output of `php artisan key:generate --show` |
   | `APP_URL` | `https://<your-project>.vercel.app` |
   | `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | from step 1 |
   | `MYSQL_ATTR_SSL_CA` | `/etc/pki/tls/certs/ca-bundle.crt` |
   | `CRON_SECRET` | any long random string |

4. **Deploy, then create the tables** with the post-deploy hook. It runs `migrate --force` and the idempotent
   seeders on Vercel itself, so the production DB password never leaves Vercel. It is safe to re-run after every
   deploy that adds migrations:
   ```bash
   curl -X POST https://<your-project>.vercel.app/api/cron/migrate -H "Authorization: Bearer $CRON_SECRET"
   ```
5. Open `/up` (health check) and `/client/auth/login`.

### Things to know on Vercel

- The filesystem is read-only except `/tmp`; nothing here writes to `storage/`.
- Cold starts take about 1 s; warm requests are fast. Challenge-mode delays count toward function time, so they are
  capped at 2.5 s (`PRACTICE_CHAOS_MAX_MS`).
- The Hobby plan allows one cron run per day, which is all `practice:prune` needs.
- If you outgrow Vercel's PHP runtime (it is community-maintained), the same code runs unchanged on Laravel Cloud,
  Railway or any VPS. Only `vercel.json` and `api/index.php` are Vercel-specific.

## Author and licence

Built by **Rafiul Alam Antar** ([GitHub](https://github.com/rafiulalamantar) · [LinkedIn](https://www.linkedin.com/in/rafiulalamantar/) · [Buy me a coffee](https://buymeacoffee.com/rafiulalamantar)). Released under the [MIT License](LICENSE);
third-party components keep their own licences, listed at the end of `LICENSE`.

The footer on every page shows these credits. Its links come from `config/practice.php` (`author`), and any link left
empty is hidden; each can be overridden with the `PRACTICE_AUTHOR_*` environment variables.

