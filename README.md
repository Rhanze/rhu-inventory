# RHU Inventory

Laravel 12 application for PHO and RHU stock, dispensing, transfers, patient registration, maternal care, immunization, disease reports, and exports.

## Requirements

- PHP 8.2 or newer with PDO MySQL (PDO SQLite for the fast test suite).
- Composer, Node.js, and npm.
- MariaDB/MySQL for an existing installation. A clean SQLite database is supported by migrations and regression tests.
- The web server document root must point to `public`, not the repository root.

## New installation

1. Run `composer install` and `npm install`.
2. Copy `.env.example` to `.env`, configure the database, and run `php artisan key:generate`.
3. Run `php artisan migrate` on the empty database. The May 1 baseline creates the core tables previously supplied by a manual database import.
4. Run `php artisan db:seed` to install the application's reference immunization schedules, programs, and disease mappings.
5. Provision the initial PHO administrator through your trusted administration process. A PHO account has `role=admin`, `health_unit_id=NULL`, an active flag, and a password hashed with Laravel Hash. RHU administrators and staff require a facility; midwives require an RHU and patient assignments.
6. Run `npm run build` and serve the `public` directory. `php artisan serve` is available for local development.

The default application timezone is Asia/Manila; override it with `APP_TIMEZONE` if necessary. Keep `.env`, credentials, uploaded prescriptions, and session files private.

## Upgrade an existing imported database

The existing installation has several legacy tables whose original creation migrations are not recorded. Use these explicit upgrade paths for the October 31 repair release:

```powershell
php artisan migrate --path=database/migrations/2026_10_31_000000_complete_legacy_columns.php --path=database/migrations/2026_10_31_000001_create_stock_movements.php --force
npm run build
```

These two upgrades were applied to the local installation during this repair. They add the exact stock movement ledger, transfer reservation flag, stock-log history fields, and missing legacy columns (including medicine `unit`, copied from `base_unit` when necessary). They preserve existing patient and stock rows. Do not replay unrecorded legacy creation migrations against existing tables. Reconcile that installation's historical migration records before switching it to unrestricted future `migrate` commands.

The guarded baseline and legacy-column migrations intentionally retain imported tables/columns on rollback. Reverting application code after recording new ledger movements requires a coordinated schema/data rollback; do not drop the ledger as a routine recovery action.

## Stock workflows and historical records

For varied, reconciled stock demonstration records, use the dedicated [sample stock history generator](docs/SAMPLE_DATA.md). It creates clearly labeled demo batches and linked receipts, dispensing, and stock movements without deleting existing transactions.

For the disease reporting and analysis upgrade, apply only its additional migration to an existing imported database:

```powershell
php artisan migrate --path=database/migrations/2026_10_31_000002_add_disease_reporting_periods.php --force
```

Existing disease reports require review before inclusion in analysis. Confirm the reporting period and complete case total through Review/edit; missing reports remain gaps and confirmed zeros remain actual observations. See [DISEASE_ANALYSIS.md](docs/DISEASE_ANALYSIS.md) for the reporting rules, OLS trend equations, Holt forecasting equations, and validation method.

- Dispensing and allocations deduct unexpired batches in FEFO order and record each batch's exact deltas.
- Edits/deletions reverse those deltas transactionally. Original stock-log entries remain available with `voided_at`; demand calculations exclude superseded entries.
- Pending/rejected supply requests have no stock movement to reverse.
- Transfer acceptance moves quantity from available to reserved stock. Completion releases the reservation and credits the matching destination batch once. Cancellation returns a reservation to availability.
- Transfers accepted before this release reserve their stock under a row lock when received. Insufficient or expired historical stock requires cancellation/reconciliation.
- Historical dispensing/allocation reversals resolve their per-batch stock logs only when the original batch can be identified uniquely and quantities agree. Ambiguous/missing history is rejected with a reconciliation message. The repair does not invent balances or automatically correct prior corruption.
- Source batch IDs and quantities are kept in `stock_movements`; backup this table with the inventory and transaction tables.

## Authorization and attachments

For Vercel, use the Container preset and follow [VERCEL_DEPLOYMENT.md](docs/VERCEL_DEPLOYMENT.md). The repository includes a PHP/FrankenPHP Docker build, separate public/management environment templates, durable session/storage integration and an authenticated scheduler endpoint. Hosted MySQL, shared Redis and private object storage must be provisioned before deployment.

Public and management access use distinct, configurable hostnames by default. The public root opens medicine search; the management root redirects to login or the user's role dashboard. Private routes are unavailable on the public host. See [DOMAIN_SPLIT.md](docs/DOMAIN_SPLIT.md) for the required XAMPP hosts/virtual-host setup and separate production deployments. Public views use `layouts.public`, while management uses the internal layout. The public RHU map and its `/map/*` endpoints were removed; the protected administrative GIS remains available. Legacy single-host mode is available only outside production.

Protected requests reload the active user and current role/facility. RHU exports are facility-scoped; midwife clinical access is limited to assigned patients. Account creation and password changes share a password policy; login verifies existing credentials without imposing a new registration pattern.

Prescription uploads use the configured private `PRESCRIPTION_DISK` (`local` by default, `s3` on Vercel). Downloads stream from that same disk after authorization. Replacements delete the old file only after the database update succeeds. Existing attachments must be copied to the chosen remote disk before changing production storage.

## Scheduled jobs

Run the Laravel scheduler once per minute through Windows Task Scheduler or the deployment host's scheduler:

```powershell
php artisan schedule:run
```

Use `php artisan schedule:list` to inspect the four registered tasks: daily log pruning, hourly inventory alerts, a daily notification digest scan, and daily transfer processing. Schedules live in `routes/console.php`; the old `app/Console/kernel.php` is not the active schedule registration point. Use a shared cache and run a single scheduler instance, or configure a suitable distributed scheduler lock before running multiple hosts.

## Verification

Medicine forecasting now uses a mathematical weekly stock-release trend model. See [FORECAST_MODEL.md](docs/FORECAST_MODEL.md) for the regression equations, accepted movement types, short-history fallback, and stock/reorder calculations. The forecasting page shows the observed and projected weekly trend for each medicine/facility; the dashboard uses the same forecast service.

```powershell
php artisan test
php tools/verify-database.php
npm run build
```

`php artisan test` uses SQLite in memory. `phpunit.xml.dist` is the standard fallback test configuration. During repair verification the original `phpunit.xml` became unavailable and the OS denied restoring that filename, including with elevated access; the equivalent `.dist` configuration keeps tests runnable.

The database verification script creates a uniquely named `rhu_repair_test_*` database using the configured MySQL credentials, runs the same regression suite, and starts two competing receipt processes. It drops only the database it created in a `finally` block. It requires database create/drop privileges. The test base rejects MySQL databases without that disposable prefix before migrations run.

Compiled test views go to `storage/framework/testing-views`, keeping tests separate from the application's compiled views. Read `REPAIR_NOTES.md` for verification results and `CODE_REVIEW.md` for the original findings.

## Source maintenance

The executable inventory template is `resources/views/inventory/index.blade.php`; `.bak` copies are backups. Existing backup files and tracked compiled views were retained. No Git operations were performed during these repairs, as required by `AGENTS.md`.
