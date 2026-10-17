# SmartSave

A private savings and goal-tracking web app for 2–3 trusted people (e.g. a family).
Users record their savings by hand. There are **no bank connections, payment gateways or financial APIs**.

**Stack:** Laravel 13 · PHP 8.3+ · MySQL 8 (or SQLite) · Blade · Tailwind CSS 4 · Alpine.js · Chart.js
No extra Composer packages beyond the Laravel skeleton.

---

## 1. Features

| Area | What you get |
|---|---|
| Accounts | Login/logout, throttled sign-in (5 attempts/min), password reset (30-minute tokens), temporary passwords that must be changed at first sign-in, 30-minute idle logout, Admin/User roles, max 3 accounts, **no public registration** |
| Privacy | Every goal, transaction, expense, report and export is scoped to its owner. **Admins get no access to other users' money.** Foreign record IDs return 404. |
| Dashboard | Total savings, saved this month, contributed this year, allocated to active goals, overall progress, goal counts, monthly target and budget meters, activity chart with date-range filter, savings-by-goal doughnut, upcoming deadlines, recent transactions, quick actions |
| Goals | Name, description, category, icon, colour, target, deadline, opening balance, priority, planned contribution and frequency, notes. Progress bar, 25/50/75/100% milestones, needed per month, recent rate, estimated finish, suggested schedule, deadline and rate warnings. Pause, resume, complete (only once funded), archive, restore. |
| Ledger | Contributions, withdrawals, opening balances, transfers between goals (or to/from unallocated savings), audited corrections, reversals (voids), filters, pagination, CSV export |
| Expenses | Amount, category, date, note. Filters, monthly summary, category chart, CSV export. Expenses never touch savings balances. |
| Budget & plan | Monthly savings target, optional spending budget, saving frequency, target vs actual, budget usage with warnings, per-goal needed-per-month and suggested schedule |
| Reports | Yearly monthly-savings table, budget performance (✓/✗ per month), goal progress and activity, expense summary, savings trend chart, CSV export, printable summary |
| Notifications | In-app alerts for milestones, completed goals, budget 80%/100%, approaching deadlines, inactive goals and monthly target. Per-user preferences. Optional email. |
| UI | Responsive layout with a collapsible sidebar and mobile drawer, dark/light mode, toasts, confirmation dialogs, animated progress, skeleton loaders for charts, empty states, keyboard focus styles, skip link, reduced-motion support |

---

## 2. Requirements

- PHP 8.3+ with extensions: `bcmath`, `pdo_mysql` (or `pdo_sqlite`), `mbstring`, `openssl`, `fileinfo`
- Composer 2
- Node.js 20+ and npm (only to build the CSS/JS)
- MySQL 8 (recommended for production) or SQLite (fine for local use)

> **Windows note:** this PHP install ships `php_pdo_sqlite.dll` but it is not enabled.
> To use SQLite, uncomment `extension=pdo_sqlite` in `C:\Program Files\PHP\php.ini`, or use MySQL.

## 3. Installation (local)

```bash
cd SmartSave
composer install
npm install
npm run build
cp .env.example .env          # Windows PowerShell: Copy-Item .env.example .env
php artisan key:generate
```

### Database

**MySQL** (create an empty database and a dedicated user first):

```sql
CREATE DATABASE smartsave CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'smartsave'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
GRANT ALL PRIVILEGES ON smartsave.* TO 'smartsave'@'localhost';
```

Then in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smartsave
DB_USERNAME=smartsave
DB_PASSWORD=choose-a-strong-password
```

**SQLite:** keep `DB_CONNECTION=sqlite` (the file is `database/database.sqlite`).

Run the migrations:

```bash
php artisan migrate
```

## 4. Creating the first administrator

There is deliberately **no web page** for this. Run, on the server:

```bash
php artisan smartsave:create-admin
```

It asks for name, email and password (input hidden). It **refuses to run once any admin exists**.
After that, the admin adds the other accounts from **Admin › Users** with a temporary password; each person must choose their own password at first sign-in.

## 5. Running locally

```bash
php artisan serve
```

Open http://localhost:8000. For live CSS/JS rebuilding while editing, also run `npm run dev`.

**Optional demo data (local only):** `php artisan db:seed --class=DemoSeeder` creates
`admin@smartsave.test` and `ayesha@smartsave.test` (password `DemoPass123`) with 8 months of sample data.
It refuses to run when `APP_ENV=production`. Reset with `php artisan migrate:fresh`.

## 6. Environment configuration

| Key | Default | Purpose |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` | `local` / `true` | **Production: `production` / `false`** (hides stack traces) |
| `APP_URL` | | Full public URL, `https://…` in production |
| `SESSION_LIFETIME` | `30` | Minutes before the session cookie expires |
| `SESSION_ENCRYPT` | `true` | Encrypts session payloads |
| `SESSION_SECURE_COOKIE` | *(empty)* | **Set `true` in production** (cookie only sent over HTTPS) |
| `SMARTSAVE_MAX_USERS` | `3` | Account cap (admins included, deactivated accounts count) |
| `SMARTSAVE_IDLE_TIMEOUT` | `30` | Minutes of inactivity before automatic sign-out |
| `AUTH_PASSWORD_RESET_EXPIRE` | `30` | Reset-link lifetime in minutes |
| `TRUSTED_PROXIES` | *(empty)* | Proxy IPs (comma separated) or `*` when behind a TLS-terminating proxy/load balancer |
| `MAIL_MAILER` etc. | `log` | Configure SMTP to enable password-reset emails and optional email reminders |

With `MAIL_MAILER=log`, password-reset links are written to `storage/logs/laravel.log` (development only).
Email reminders stay disabled in the UI until a real mailer is configured.

## 7. Scheduled reminders

Time-based reminders (deadlines, inactive goals, monthly target, budget) come from
`php artisan smartsave:reminders`, scheduled **daily at 08:00** (`routes/console.php`).
Milestone, completion and budget alerts also fire immediately when you save something.

The scheduler only runs if the server calls it every minute.

**Linux (cron):** `crontab -e` and add:

```cron
* * * * * cd /path/to/SmartSave && php artisan schedule:run >> /dev/null 2>&1
```

**Windows (Task Scheduler):** create a task that runs every minute:
Program `php`, arguments `artisan schedule:run`, start in `C:\path\to\SmartSave`.

Check with `php artisan schedule:list`. Run once by hand with `php artisan smartsave:reminders`
(it is idempotent: running it twice creates no duplicates).
Queues are not used (`QUEUE_CONNECTION=sync`), so no worker process is needed.

## 7b. Installing on phones and computers (PWA)

SmartSave is an installable web app, so there's no app store. After it's deployed on a public **HTTPS** address:

- **Android (Chrome/Edge/Samsung Internet):** open the site → tap **Install app** in the menu/sidebar (or the browser's *Install* prompt).
- **iPhone/iPad (Safari):** open the site → tap **Install app** for instructions, or **Share → Add to Home Screen**.
- **Windows/Mac (Chrome/Edge):** click the install icon in the address bar, or **Install app** in the sidebar.

It then opens full-screen from its own icon, with shortcuts (long-press the icon) for *Add savings*, *Record expense* and *Goals*.

Notes:
- Installing **requires HTTPS** (or `localhost`). Over plain `http://192.168…` on a home network the site works in the browser, but browsers won't install it.
- Installing doesn't create an account. People still need an account created by the admin (max `SMARTSAVE_MAX_USERS`).
- `public/sw.js` caches **only** static assets (CSS/JS/fonts/icons) and an offline page. Pages and financial data are never stored on the device.
- Files: `public/manifest.webmanifest`, `public/sw.js`, `public/offline.html`, `public/icons/*`. After changing the service worker, bump `VERSION` in `sw.js`.

## 8. Production deployment checklist

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, `SESSION_SECURE_COOKIE=true`
- Serve **only over HTTPS**. In production the app forces `https` URLs, sends HSTS and a strict Content-Security-Policy.
- Web server document root must be `SmartSave/public` (never the project root).
- `storage/` and `bootstrap/cache/` writable by the web server user. `.env` readable only by it (`chmod 600 .env`).
- Create the first admin with `smartsave:create-admin`, then add the cron entry above.
- Run `composer audit` and `npm audit` before each deployment.

## 9. Backup & restore

Everything lives in the database (no uploaded files). **Also back up `.env` separately and securely:**
`APP_KEY` is required to read encrypted sessions, and losing it signs everyone out.

**MySQL:**

```bash
# backup (run daily via cron; keep several days; store off-server, encrypted)
mysqldump --single-transaction --routines -u smartsave -p smartsave | gzip > backups/smartsave-$(date +%F).sql.gz

# restore (into an empty database)
gunzip < backups/smartsave-2026-10-15.sql.gz | mysql -u smartsave -p smartsave
```

**SQLite:** stop the app (or `php artisan down`), then copy `database/database.sqlite`
(or use `sqlite3 database/database.sqlite ".backup backups/smartsave.sqlite"`). Restore by copying the file back.

Backups contain personal financial data: encrypt them (e.g. `gpg -c`), restrict access, and test a restore periodically.
`/backups` is git-ignored.

## 10. Security checklist

| Control | Where |
|---|---|
| Auth on every protected route | `routes/web.php` (`auth` + `auth.session` group) |
| Object-level ownership (IDOR) | `app/Policies/OwnedRecordPolicy.php` (404, no admin bypass). Goal IDs re-checked inside `SavingsLedger::ownedGoal()`. All list/export queries start from the current user. |
| No public registration / admin escalation | No register route. First admin via CLI only. `role`, `is_active` are not mass-assignable. |
| Passwords | bcrypt via Laravel `hashed` cast. Min 10 chars, mixed case + number. Temporary passwords forced to change. Changing a password signs out other devices. |
| Login throttling | 5 failures per email+IP per minute (`LoginController`), plus route throttle. Reset and admin actions limited by the `sensitive` limiter. |
| Session | Encrypted, HttpOnly, SameSite=Lax, secure cookie in production, regenerated on login, invalidated on logout, 30-minute idle timeout, deactivated users kicked out immediately (`EnforceSessionRules`) |
| CSRF | Laravel's built-in CSRF middleware on all web routes; every form has `@csrf` |
| SQL injection | Eloquent/query builder with bindings only; LIKE wildcards escaped |
| XSS | Blade `{{ }}` escaping everywhere; chart data passed as escaped JSON attributes; no inline scripts |
| Mass assignment | Explicit `#[Fillable]` lists; `user_id` always set server-side |
| Validation | Server-side for every input (amount: > 0, ≤ 2 decimals, bounded; dates not in the future) |
| Headers | `SecurityHeaders` middleware: X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, no-store on authenticated pages; CSP + HSTS in production |
| CORS | No API routes. Laravel only applies CORS to `api/*`, so cross-origin calls are not enabled. |
| Errors | Custom error pages; `APP_DEBUG=false` in production hides traces |
| Audit trail | `activity_logs`: logins (incl. failures), user management, every create/edit/void of money records with old/new values. Passwords/tokens are redacted. |
| CSV injection | `App\Support\Csv::safe()` prefixes `= + - @ tab CR` text cells with `'` |
| Secrets | `.env` git-ignored; no secrets in code. Only `.env.example` is committed. |
| File uploads | None (goals use a fixed icon set), so there is no upload attack surface |
| Dependencies | `composer audit` and `npm audit` both clean at time of writing |

**Note on CSP:** Alpine.js evaluates `x-data` expressions, so the production policy allows `'unsafe-eval'` for scripts (still `'self'` only, no inline scripts).

## 11. Financial rules (read this before changing money code)

1. **Money is `DECIMAL(15,2)`**, and PHP arithmetic uses `bcmath` strings (`App\Support\Money`). Floats are never used for balances.
2. **One balance rule.** A ledger row's `amount` is always positive; its sign comes from `type`:
   `initial`, `deposit`, `transfer_in` are **+**; `withdrawal`, `transfer_out` are **−**.
   A goal's balance = signed sum of its non-voided rows (`SavingsTransaction::SIGNED_AMOUNT_SQL`). Total savings = the same over all rows.
3. **Transfers** are two rows sharing `transfer_group`, so they net to zero and never inflate total savings.
4. **Period figures:** *contributed* = deposits, *withdrawn* = withdrawals, *net saved* = contributed − withdrawn.
   Opening balances and transfers are **excluded** from "saved this month/year" (they aren't new savings).
5. **No negative buckets:** neither a goal nor unallocated savings may end below zero after any write, edit or void.
   (The check uses the resulting balance, not the running balance at each historic date.)
6. **Corrections:** amount/date/note/goal can be edited (old and new values are audited). The type can't be changed: reverse the entry and add a new one. Transfers: only date/note are editable; reverse to change the amount.
7. **Reversal (void)** keeps the row with `voided_at` and a reason; reversed rows count nowhere. Voiding one transfer leg voids both.
8. **Goal status:** money in only for active/paused goals; money out for any non-archived goal; *completed* requires balance ≥ target; *archived* goals are read-only, but their balance still counts in total savings. Goals with history can only be archived, never deleted.
9. **Concurrency:** every write locks the user's row (`SELECT … FOR UPDATE`) inside a DB transaction, so simultaneous requests from one user are serialized (MySQL). SQLite serializes all writes at the database level.
10. **Duplicate submits:** forms carry a one-time UUID (`idempotency_key`), unique per user in the database, plus a cache-based guard for goal creation. JavaScript also disables the button after the first click.
11. **Currency:** one per user, default PKR. Changing it relabels amounts; **nothing is converted**.
12. **Monthly plans** (`monthly_budgets`) apply from their month until a newer row exists, so past months keep the targets they had.

## 12. Database schema

| Table | Purpose / key relationships |
|---|---|
| `users` | Accounts: `role` (admin/user), `currency`, `preferences` JSON (reminder settings), `is_active`, `must_change_password`, `last_login_at` |
| `savings_goals` | Belongs to a user. Target (DECIMAL), deadline, category/icon/colour, priority, status, planned contribution + frequency, `completed_at`/`archived_at`. FK `restrict` (users are deactivated, never deleted). Index `(user_id, status)`. |
| `savings_transactions` | The ledger. FK to user and (nullable) goal, both `restrict`. `type`, `amount` (CHECK > 0 on MySQL), `transaction_date`, `note`, `transfer_group`, `idempotency_key` (unique per user), `voided_at`/`void_reason`. Indexed by user+date and goal. |
| `expenses` | Belongs to a user. Amount (CHECK > 0), category, date, note, idempotency key. Separate from savings. |
| `monthly_budgets` | One row per user per month (unique): `savings_target`, `expense_budget`, `savings_frequency` |
| `notifications` | Laravel's database notifications (in-app alerts); `data.key` deduplicates reminders |
| `activity_logs` | Append-only audit trail: actor, action, subject, JSON old/new values, IP |
| `sessions`, `password_reset_tokens`, `cache`, `cache_locks` | Laravel framework tables |

Per-goal contribution plans are columns on `savings_goals` rather than a separate table (simpler, one-to-one).
Shared household goals are **not** implemented (see limitations), so there is no membership table.

## 13. Code structure

```
app/
  Console/Commands/      CreateAdmin (first admin), SendReminders (daily job)
  Http/Controllers/      One focused controller per area (Goal, Transaction, Transfer, Expense,
                         Budget, Dashboard, Report, Notification, Profile, Auth/*, Admin/User)
  Http/Middleware/       SecurityHeaders, EnforceSessionRules (idle/inactive/forced password), EnsureAdmin
  Http/Requests/         GoalRequest, TransactionRequest, AmountRules (shared money/date rules)
  Models/                User, SavingsGoal, SavingsTransaction, Expense, MonthlyBudget, ActivityLog
  Notifications/         SmartSaveAlert (one generic in-app/email alert)
  Policies/              OwnedRecordPolicy + one-line subclasses per model
  Services/
    SavingsLedger.php    ← the ONLY place that writes savings rows (record, transfer, update, void)
    FinanceSummary.php   ← all read-side figures (dashboard, budget, reports)
    GoalAlerts.php       ← creates notifications (milestones, budget, daily reminders)
  Support/
    Money.php            bcmath helpers + formatting
    GoalPlanner.php      per-goal planning maths (months left, needed/month, estimate, schedule)
    Csv.php              safe CSV streaming
    Idempotency.php      double-submit guard
    Ui.php               Tailwind class maps for colours/status
config/smartsave.php     currencies, categories, icons, limits, reminder defaults
resources/views/         Blade pages + components/ (layout, icon, stat-card, progress, field, …)
resources/js/app.js      Alpine stores (theme, toasts, confirm), Chart.js setup, double-submit guard
tests/                   Feature + Unit tests (see below)
```

To add a feature, put writes that change balances in `SavingsLedger`, read-side numbers in `FinanceSummary`, and keep controllers thin.

## 14. Tests

```bash
php artisan test
```

The tests use in-memory SQLite (`phpunit.xml`), so the `pdo_sqlite` PHP extension must be enabled.
Last run: **81 tests, 429 assertions, all passing** (PHP 8.5.9, PHPUnit 12.5). Covered areas:

- **Auth:** guest redirects, no registration, login/logout, generic errors, throttling, deactivated users, idle timeout, forced password change, weak passwords, reset flow + token expiry, security headers, production CSP/HSTS
- **Admin:** access control, 3-account cap, no role mass-assignment, self-demotion blocked, first-admin command runs once
- **Goals:** opening balance recorded once, double-submit, validation, rollback when the opening balance fails, edits keep history, pause/resume, completion rules, completed/archived behaviour, delete only without history
- **Ledger:** exact balances, no negative goal/unallocated balance, invalid amounts/types/future dates, duplicate submissions, transfers (no double counting, unallocated, rollback of both legs, same-goal rejected), voids (transfer pairs, spent deposits), audited corrections, type immutability, filters + pagination
- **Privacy:** goals, transactions, transfers, expenses, notifications, dashboard, reports, admin page and filters never expose another user's data (tested with an *admin* as the intruder)
- **Expenses & budget:** CRUD without touching savings, validation, duplicates, budget maths, plan carry-forward, one-time budget warnings
- **Exports:** per-user scoping, formula-injection neutralised, numbers stay numeric, report CSV + print view
- **Summaries:** month/year/leap-day boundaries, running balance, voided rows excluded, dashboard totals vs goal balances, planner edge cases (no date, past deadline, rounding up, funded goals), estimate from recent rate, weekly schedule
- **Reminders:** milestones once each, daily deadline/inactive/monthly reminders (idempotent), preferences respected, no off-site redirects
- **PWA:** manifest valid with icons present, service worker never caches pages, layouts link the manifest

Code style: `php vendor/bin/pint`.

## 15. Limitations & not implemented

- **Shared household goals:** not implemented. Every record is strictly personal; adding it would need a membership table and its own permissions.
- **Email verification:** not implemented. Accounts are created by the admin, so self-verification adds little. Password reset and email reminders need a real SMTP configuration.
- **Goal images:** a fixed icon set is used instead of uploads (no file-upload attack surface).
- **Concurrency on SQLite:** row locks are a no-op there; SQLite's database-level write lock serializes writes instead. Use MySQL for multi-user production.
- **Negative-balance check** validates the resulting balance, not the running balance on every historic date (backdated withdrawals are allowed if the current balance stays ≥ 0).
- **Income / net worth** are not calculated (no income data is collected).
- **Currencies:** one per user, no conversion. Changing it only relabels amounts.
- Planning figures (needed per month, estimated finish) are **estimates**, not forecasts.
