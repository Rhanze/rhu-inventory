# Repair implementation — 31 October 2026

The five repair stages from CODE_REVIEW.md have been implemented. The original review remains an audit record; this file describes the resulting behavior and deployment state.

## Implemented changes

| Original finding | Repair |
| --- | --- |
| 1: Pending request deletion creates stock | Reversal skips unissued pending/rejected requests. Regression verifies stock remains unchanged. |
| 2: Cross-RHU exports | Export authorization rejects another facility's ID for RHU users; PHO selection requires an administrator. |
| 3: Autocomplete stored HTML | Header suggestions create text nodes with textContent. |
| 4: Incorrect batch reversals | stock_movements records each original source/destination batch delta and its reversal. Dispense/allocation edits and deletions lock their owner and reverse the exact movements. Historical stock logs are retained with voided_at. |
| 5: Duplicate transfer completion | Manual receipt and automatic completion share TransferService and lock/recheck the transfer and batches. Repeated completion is idempotent. Shipment reversals check stock inside their locked transaction. |
| 6: Unreserved accepted transfers | Acceptance reserves available stock; receipt consumes the reservation; cancellation releases it. Historical accepted transfers reserve under the completion lock. |
| 7: Expired transfer batches | Selection excludes expired batches; completion rechecks expiry. |
| 8: Stale account permissions | Protected requests resolve the active account and refresh its current role/facility. Deleted/deactivated accounts lose their session. |
| 9: Midwife patient access | Shared patient viewer scope is applied to clinical record lookups, listings, calendars, and monthly summaries/exports. Midwife dispensing/search/dashboard results are creator-scoped. Explicit patient reassignment requires an active same-RHU midwife. |
| 10: Credential mismatch | Creation and password changes share CredentialRules. Login accepts existing credential strings and verifies their hash. |
| 11: Missing baseline schema | A guarded legacy-core baseline and supplemental columns make clean SQLite and MariaDB installations reproducible. Legacy raw SQL and duplicate indexes were repaired where needed for those migrations. |
| 12: Missing schedules | Four schedules are registered in routes/console.php. Timezone defaults to Asia/Manila. |
| 13: Prescription download path | Download paths resolve through the same private local disk used for uploads. |
| 14: Prescription loss on failed edit | The old attachment survives failed transactions; unsuccessful replacements are cleaned up; successful edits delete the old file after commit. |
| 15: Batch merging | Stock entry matches medicine, facility, batch number, and expiry and serializes creation for the facility. |
| 16: Empty disease forecasts | Queries use diseases.name and disease_reports.case_count, retaining the aliases expected by views. Unexpected query failures are logged and raised. |
| 17: Forecast demand duplication | StockSummaryService aggregates usable stock per medicine/facility, includes demand-only stockouts, and ignores expired/deleted stock. Redistribution shares these totals, reaches its critical state, and limits the combined donor/recipient recommendation budget. |
| 18: Broken route/view wiring | Restored dashboard refresh, corrected the shipment export action, and added public map/redistribution pages. Unused residency routes with no implementation or view references were removed. |
| 19: Repeated supply approvals | Approval, rejection, and cancellation lock/recheck the request state. |
| 20: Missing PHO audit | Null-facility PHO events are recorded. |
| 21: Session fixation | Successful login regenerates the session identifier. |
| 22: Invalid lookup totals | Stock lookup excludes deleted and expired inventory. |
| 23: Inadequate tests | Added business regression coverage, fixed the root redirect assertion, and verified the same suite on SQLite and MariaDB. |

Shared services now cover exact stock movements, transfer transitions, usable stock aggregation, password policy, and patient visibility. Inventory adjustment and deletion keep balances and their audit writes in one transaction. Reserved batches cannot be deleted. Notification deduplication permits warning-to-critical escalation, and admin/staff recipients can see their own personal notifications as well as shared facility notifications. Vaccine entry validates an active configured dose and synchronizes its calendar label.

## Database state

The two October 31 upgrade migrations were applied successfully to the configured local database by explicit path:

- 2026_10_31_000000_complete_legacy_columns
- 2026_10_31_000001_create_stock_movements

The existing installation reports some older creation migrations as pending even though their tables already exist. Those older migrations were not replayed and historical migration rows were not fabricated. New-installation migrations were tested separately on empty disposable databases. README.md gives distinct new-installation and imported-database upgrade instructions.

Historical balances have not been recalculated or guessed. Old records can be reversed only when their logs uniquely identify the original batches and agree with the transaction quantity. Ambiguous or incomplete histories require reconciliation. Existing accepted transfers remain unreserved until their next receipt/cancellation transition; future acceptance reserves immediately.

## Verification

- SQLite regression suite: 28 passed (272 assertions), including clean migrations, scope enforcement, batch reversals, prescription lifecycle, historical ambiguity, reservations, state transitions, stock lookup, forecasts, dose/calendar synchronization, and page rendering.
- MariaDB/MySQL verification: tools/verify-database.php passed the same 28 tests (272 assertions) in a uniquely named disposable database and checks two competing receipt processes against an actual row lock. Only the disposable database is dropped.
- Production asset build: npm run build passed.
- All 356 PHP/source and compiled Blade files passed syntax checks. Blade compilation used a separate test view directory to preserve application compiled views.
- The four scheduler definitions are inspectable with php artisan schedule:list. The deployment host must still invoke schedule:run once per minute as described in README.md.

During the first MariaDB test attempt phpunit.xml and its initial helper became unavailable. The OS denied restoring phpunit.xml, including an elevated attempt. Equivalent configuration is retained in the supported phpunit.xml.dist fallback; php artisan test and the disposable MariaDB runner use it successfully. The replacement helper is tools/verify-database.php.

## Disease analysis improvements 1–3

Added separate ISO-week/calendar-month reporting periods, explicit completeness review, OLS trends, and Holt additive level/trend forecasts. Legacy reports remain unverified until reviewed; missing observations stay unknown and confirmed zeros remain observations. Manual outbreak closure now preserves the case report instead of recording an artificial zero. The disease-period upgrade (`2026_10_31_000002_add_disease_reporting_periods`) was applied to the local database by explicit path.

Verification after this addition: 57 tests, 391 assertions passed on both SQLite and disposable MariaDB; the competing transfer receipt check also passed. The production asset build passed using `npm.cmd run build` because PowerShell execution policy blocks the unsigned `npm.ps1` wrapper. Model equations, reporting requirements, and validation are documented in `docs/DISEASE_ANALYSIS.md`.

## Retained maintenance items

The repair preserves existing .bak files, tracked compiled application views, and unused legacy kernel files. It does not perform repository cleanup or broad rewrites of search pagination and unused immunization summary methods. Those performance/cleanup observations remain future maintenance work. No Git operations, staging, commits, or pushes were performed, following AGENTS.md.
