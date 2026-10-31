# Installation and verification

## Requirements

- PHP 8.2 or newer, Composer, Node.js and npm.
- PDO MySQL for the application database; PDO SQLite for the in-memory test suite.
- MySQL or MariaDB for the application. SQLite is used for migration/regression testing, not as a substitute for the application's MySQL-specific reporting queries.
- A web server whose document root points to `public`, not the repository root.

The application timezone defaults to Asia/Manila. Keep `.env`, credentials, private prescriptions and session files outside source control and public web access.

## New installation on an empty database

1. Run `composer install` and `npm ci`.
2. Copy `.env.example` to `.env`, configure an empty database, and run `php artisan key:generate`.
3. Run `php artisan migrate`. The baseline migration creates the legacy core tables required by a fresh installation.
4. Run `php artisan db:seed` for the application's reference immunization schedules, programs and disease mappings. Demo stock history is a separate, optional local-only seeder.
5. Provision the initial active PHO administrator through a trusted administration process. PHO administrators have `role=admin` and `health_unit_id=NULL`. Store passwords using Laravel Hash. RHU administrators/staff need a facility assignment; midwives also need patient assignments for clinical access.
6. Run `npm run build`.
7. Configure the public and management virtual hosts described in [DOMAIN_SPLIT.md](DOMAIN_SPLIT.md), then serve the application's `public` directory. The default hostnames are `rhu-inventory.test` and `management.rhu-inventory.test`; they require hosts-file and web-server configuration.

For temporary single-host local development only, set `PORTAL_DOMAIN_SPLIT=false` before using `php artisan serve`. This mode is rejected in production. Clear configuration/route caches after changing domains or portal settings.

## Existing imported database

Back up the database first. The existing imported installation has tables whose original creation migrations are not recorded. **Do not run unrestricted migrations against those existing tables.** Apply the reviewed upgrades by explicit path:

```powershell
php artisan migrate --path=database/migrations/2026_10_31_000000_complete_legacy_columns.php --path=database/migrations/2026_10_31_000001_create_stock_movements.php --force
php artisan migrate --path=database/migrations/2026_10_31_000002_add_disease_reporting_periods.php --force
npm run build
```

These upgrades add missing legacy columns, the exact stock movement ledger, transfer reservation support, stock-log history fields and disease reporting-period metadata. They were applied to the configured local installation during development. Historical migration records must be reconciled before switching that installation to unrestricted future `migrate` commands.

Guarded baseline/legacy migrations intentionally retain imported structures on rollback. Reverting code after recording stock movements requires a coordinated schema/data plan; do not routinely drop the ledger.

Existing disease reports require review of their reporting period and complete total before inclusion in analysis. Missing reports remain unknown; confirmed zero-case reports remain observations. See [DISEASE_ANALYSIS.md](DISEASE_ANALYSIS.md).

## Stock workflows and historical reconciliation

- Dispensing and allocations deduct unexpired batches in FEFO order and record each batch's exact deltas.
- Edits/deletions reverse those deltas transactionally. Superseded stock logs retain `voided_at` and are excluded from demand calculations.
- Pending/rejected supply requests have no stock deduction to reverse.
- Transfer acceptance moves quantity from available to reserved stock. Receipt releases the reservation and credits the matching destination batch once. Cancellation reverses a reservation.
- Transfers accepted before reservation support reserve under a row lock when received. Missing, insufficient or expired historical stock requires reconciliation/cancellation.
- Legacy reversals proceed only when logs identify original batches uniquely and quantities agree. Ambiguous/incomplete history is rejected instead of guessing balances.
- Back up `stock_movements` with inventory and transaction tables.

For varied demonstration records with reconciled balances, follow [SAMPLE_DATA.md](SAMPLE_DATA.md). The generator is restricted to local/testing environments and preserves existing transactions.

## Authorization and private attachments

Protected requests reload the active account and its current role/facility. RHU exports are facility-scoped; midwife clinical access is limited to assigned patients. Account creation/password changes use the configured password policy. Existing login credentials are checked without imposing new registration rules on old usernames.

Prescription uploads use the configured `PRESCRIPTION_DISK`: private `local` storage by default, or private `s3` storage for Vercel. Authorized downloads stream from the same disk. Replacements remove the old file only after a successful database update. Copy existing files to matching keys on the destination disk before changing production storage.

## Scheduled work

On a conventional host, invoke the Laravel scheduler once per minute through Task Scheduler or the host's scheduler:

```powershell
php artisan schedule:run
php artisan schedule:list
```

The four tasks in `routes/console.php` cover daily log pruning, hourly inventory alerts, daily notification scanning and daily transfer processing. The legacy `app/Console/kernel.php` is not the active schedule registration point. Use a shared cache for distributed locking and avoid duplicate scheduler processes.

Vercel uses the authenticated management `/internal/scheduler` endpoint instead of relying on a continuously running process. Follow [VERCEL_DEPLOYMENT.md](VERCEL_DEPLOYMENT.md) for its bearer secret, schedule, shared storage and deployment settings.

## Verification

```powershell
php artisan test
php tools/verify-database.php
npm run build
```

`php artisan test` uses SQLite in memory and the `phpunit.xml.dist` configuration. `tools/verify-database.php` creates a uniquely named `rhu_repair_test_*` database using the configured MySQL credentials, runs the suite, verifies competing transfer receipts against a real row lock, and drops only its disposable database. It requires database create/drop privileges; the test base rejects other MySQL database names before running migrations.

Compiled test views use `storage/framework/testing-views`. The latest recorded suite passed on both SQLite and MariaDB: 75 tests and 1,278 assertions. This is a recorded development result, not a guarantee for a different hosting environment. Docker image execution and live Vercel connectivity remain to be verified in the deployment environment.

The executable inventory template is `resources/views/inventory/index.blade.php`; `.bak` files are local backups. Generated caches, uploads, database dumps, environment secrets and backup copies are excluded from the published source snapshot. See [REPAIR_NOTES.md](../REPAIR_NOTES.md) for historical repair details and [CODE_REVIEW.md](../CODE_REVIEW.md) for the original review.
