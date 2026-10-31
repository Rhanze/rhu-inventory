> Repair implementation: see [REPAIR_NOTES.md](REPAIR_NOTES.md). Original findings below are retained as the audit record.

# Repository code review — 30 October 2026

This Laravel 12 application covers PHO/RHU inventory, dispensing, allocations, transfers, patient registration, maternal care, immunization, disease reporting, notifications, maps, and exports. The main risks found are incorrect stock reversals, inconsistent authorization, incomplete deployment migrations, and broken application wiring.

Scope: repository-wide inspection of application structure, routes, controllers, models, services, commands, migrations, configuration, views, and tests. There are 33 active controller files, 32 model files, 74 migrations, and 82 Blade templates. Generated dependencies were excluded from manual review. This is a prioritized code review, not a claim that every execution path has been exercised. This section records the original review; implementation and verification status is now documented in REPAIR_NOTES.md.

## Verification performed

| Check | Result |
| --- | --- |
| PHP syntax checks | All 201 PHP files under app, bootstrap, config, database, routes, and tests passed. |
| `php artisan test` | 1 passed, 1 failed. The root route returns 302; the example feature test expects 200. |
| `npm run build` | Passed. |
| Blade compilation | `php artisan view:cache` passed. Compilation does not prove that referenced views exist or that pages render with real data. Generated cache changes were cleaned up and tracked cache files restored. |
| Route inspection | 171 routes registered; reflection identified four nonexistent controller actions. |
| Literal controller view references | Two missing views identified. |
| Scheduler | `php artisan schedule:list` reports no scheduled tasks. |
| Clean migration | Failed in an isolated SQLite in-memory database at the activity_logs alteration. No application database migrations were run. |
| Pending supply request deletion | Reproduced in isolated in-memory fixtures: deleting an unapproved request for 25 units changes available stock from 100 to 125. |
| Disease forecast | Reproduced in isolated in-memory fixtures: a valid 12-case disease report produces zero unplanned forecast items. |

Database-specific behavior in the deployed MySQL schema, browser interactions, concurrent requests, and dependency vulnerability status were not exhaustively tested. Findings below distinguish reproduced failures from conclusions drawn from code.

## High-priority findings

1. **Deleting a pending supply request creates stock that never existed.**
   Source: [AllocationController.php:525](app/Http/Controllers/AllocationController.php#L525), [AllocationController.php:993](app/Http/Controllers/AllocationController.php#L993).
   `resolveAllocationInventory()` falls back to any inventory row for the medicine when the request has no inventory_id. `destroy()` then restores the requested quantity even when the request is pending or rejected and no stock was deducted. Reproduced: 100 available + deleting a pending 25-unit request = 125 available. Branch on the workflow state before resolving stock; restore only persisted stock movements that actually occurred.

2. **RHU users can export another RHU's data.**
   Source: [ELMISExportController.php:55](app/Http/Controllers/ELMISExportController.php#L55), [ELMISExportController.php:74](app/Http/Controllers/ELMISExportController.php#L74).
   Both export methods prefer a submitted health_unit_id over the session's RHU. Validation checks existence, not ownership. An authenticated RHU staff member can submit another RHU's ID to obtain its inventory and stock movement exports. Force RHU users to their session facility; allow facility selection only for an authorized PHO administrator.

3. **Header autocomplete renders stored values as HTML.**
   Source: [app.blade.php:982](resources/views/layouts/app.blade.php#L982), [SearchController.php:335](app/Http/Controllers/SearchController.php#L335).
   `item.text` is assembled from database values including medicine names and batch numbers, then interpolated into innerHTML. Those write paths accept arbitrary strings. A stored HTML payload can execute when another user searches for it. Build the spans with textContent. The SanitizeInput middleware listed in the legacy HTTP kernel is not registered by the current bootstrap and should not be relied on as an output-escaping control.

4. **Editing/deleting a multi-batch dispense or allocation corrupts batch balances.**
   Source: [DispensingController.php:347](app/Http/Controllers/DispensingController.php#L347), [DispensingController.php:453](app/Http/Controllers/DispensingController.php#L453), [AllocationController.php:318](app/Http/Controllers/AllocationController.php#L318), [AllocationController.php:546](app/Http/Controllers/AllocationController.php#L546).
   Creation deducts through multiple FEFO batches, but reversals put the entire quantity into the primary batch or an arbitrary fallback. For a 15-unit issue using 10 from A and 5 from B, deleting it restores 15 to A. Allocation allocated_stock is also left wrong on B. Persist inventory_id and quantity for every movement and reverse each original movement exactly; do not infer the original batch from a single snapshot.

5. **Transfer receipt can apply the same movement twice under concurrency.**
   Source: [TransferController.php:221](app/Http/Controllers/TransferController.php#L221), [ProcessTransfers.php:79](app/Console/Commands/ProcessTransfers.php#L79).
   Transfer status and source quantity are read before the transaction. Neither transfer nor source inventory is locked and rechecked inside it. Two simultaneous receipts, or receipt overlapping automatic completion, can both deduct and credit the transfer. Lock the transfer and inventory inside one transaction, revalidate the transition there, and make completion idempotent. Shipment deletion similarly prechecks quantities outside its transaction at InventoryController.php:659.

6. **Accepting a transfer does not reserve its stock.**
   Source: [TransferController.php:151](app/Http/Controllers/TransferController.php#L151), [TransferController.php:496](app/Http/Controllers/TransferController.php#L496).
   Acceptance stores a batch ID and an accepted status without reducing available stock or recording a reservation. The same quantities remain dispensable and can be promised to multiple transfers. Receipt can then fail after physical dispatch. Introduce an explicit reservation movement with matching release/cancellation behavior, or define dispatch as the point at which stock leaves availability.

7. **Transfers can select expired batches.**
   Source: [TransferController.php:164](app/Http/Controllers/TransferController.php#L164), [TransferController.php:513](app/Http/Controllers/TransferController.php#L513).
   Acceptance and PHO distribution order by expiration but do not exclude expired stock. The oldest expired batch can be chosen first; receipt does not reject it either. Apply the existing unexpired FEFO condition to selection and validate expiry again when completing a movement.

8. **Active sessions retain access after deactivation or role/facility changes.**
   Source: [CheckSessionRole.php:11](app/Http/Middleware/CheckSessionRole.php#L11), [AuthController.php:85](app/Http/Controllers/AuthController.php#L85).
   Login copies role and health_unit_id into the session. Protected requests check these cached values without verifying that the user still exists, remains active, or retains that role/facility. Administrative account changes therefore do not reliably revoke existing access. Resolve the current user on each protected request or implement a session version/revocation mechanism.

9. **Patient assignment restrictions are inconsistent across clinical modules.**
   Source: [PublicUserController.php:233](app/Http/Controllers/PublicUserController.php#L233), [Patient360Controller.php:15](app/Http/Controllers/Patient360Controller.php#L15), [ImmunizationController.php:99](app/Http/Controllers/ImmunizationController.php#L99), [PrenatalController.php:69](app/Http/Controllers/PrenatalController.php#L69), [FamilyPlanningController.php:114](app/Http/Controllers/FamilyPlanningController.php#L114).
   Registration listing/editing explicitly restricts midwives to assigned_midwife_id. Patient 360 and clinical controllers generally check only RHU membership. A midwife can use another same-RHU patient ID to bypass the assignment boundary and inspect or alter clinical information; maternal/family-planning deletion is also only RHU-scoped. If the explicit registration restriction is the intended policy, apply it through shared patient authorization across all clinical endpoints.

10. **Account creation/settings accept credentials that login rejects.**
    Source: [UserController.php:117](app/Http/Controllers/UserController.php#L117), [UserController.php:355](app/Http/Controllers/UserController.php#L355), [AuthController.php:33](app/Http/Controllers/AuthController.php#L33).
    Account forms accept usernames containing digits/punctuation and passwords of six characters. Login accepts only letters/spaces in usernames and imposes a different eight-character password pattern. Accounts such as nurse1 can be created but cannot sign in; a settings password change can lock out its owner. Use shared credential rules at creation/change time and validate login credentials as input strings before checking their stored hash.

11. **The repository cannot create its core database from scratch.**
    Source: [2026_05_02_163324_modify_activity_logs_foreign_key.php:29](database/migrations/2026_05_02_163324_modify_activity_logs_foreign_key.php#L29), database/migrations, database/schema.
    Early migrations alter tables that no preceding migration creates, including activity_logs, health_units, medicines, inventory, allocations, dispensing_records, prenatal_visits, and postpartum_visits. The initial users migration also lacks username, role, and health_unit_id expected by later code/migrations. No database/schema baseline is supplied. A clean in-memory migration fails at activity_logs; this is broader than later migrations using MySQL-specific syntax. Add the missing baseline migrations or a documented schema baseline before the alteration migrations.

12. **Scheduled jobs are never registered by the active application.**
    Source: [bootstrap/app.php:7](bootstrap/app.php#L7), [kernel.php:17](app/Console/kernel.php#L17), [console.php:1](routes/console.php#L1).
    The active console kernel is Illuminate\Foundation\Console\Kernel, not App\Console\Kernel. Notification scans, log pruning, and transfer processing defined in the legacy kernel are absent from `schedule:list`. Register schedules in routes/console.php or withSchedule in bootstrap/app.php. Verify that the overdue/midwife scan commands are scheduled as intended as well.

## Additional correctness findings

13. **Prescription downloads use the wrong filesystem root.**
    Source: [DispensingController.php:157](app/Http/Controllers/DispensingController.php#L157), [DispensingController.php:526](app/Http/Controllers/DispensingController.php#L526), [filesystems.php:35](config/filesystems.php#L35).
    Uploads use the local disk rooted at storage/app/private. Downloads manually resolve storage/app/{path}, so newly uploaded files normally return 404. Resolve paths through Storage::disk('local')->path() or serve the disk response directly.

14. **A failed dispense update can permanently lose the existing prescription.**
    Source: [DispensingController.php:327](app/Http/Controllers/DispensingController.php#L327).
    Removing/replacing an attachment deletes the old file before the database transaction checks stock availability. If the update fails, the database still references a deleted attachment; a replacement can also be orphaned. Keep the old file until commit and clean up the replacement on rollback.

15. **Stock entry merges distinct batches and overwrites their identity.**
    Source: [StockLogController.php:179](app/Http/Controllers/StockLogController.php#L179), [StockLogController.php:209](app/Http/Controllers/StockLogController.php#L209).
    An existing row is matched by medicine, RHU, and expiration only. Its batch_no is then replaced by the submitted value. Adding batch B with the same expiry as A merges B's stock into A's row and renames A to B. Include batch identity in matching and enforce the intended uniqueness rules consistently in all receiving paths.

16. **Disease forecast queries use nonexistent column names and hide the error.**
    Source: [ForecastService.php:121](app/Services/ForecastService.php#L121), [ForecastService.php:182](app/Services/ForecastService.php#L182), [2026_09_27_051737_create_diseases_and_reports_tables.php:15](database/migrations/2026_09_27_051737_create_diseases_and_reports_tables.php#L15).
    The schema and reporting controller use diseases.name and disease_reports.case_count. ForecastService queries disease_name and cases, catches the SQL error, and returns empty data. Reproduced using tables matching the migration. Correct the columns and aliases, and log unexpected query errors instead of presenting them as zero disease activity.

17. **Stock forecasting duplicates demand per batch and omits stockouts.**
    Source: [ForecastService.php:233](app/Services/ForecastService.php#L233), [RedistributionService.php:40](app/Services/RedistributionService.php#L40).
    Demand is calculated per medicine/RHU, then compared separately with each inventory batch. Two 60-unit batches against a 100-unit three-month need are reported as two 40-unit shortages even though the RHU has 120 units. The initial available_stock > 0 filter also removes complete stockouts, and expiry is not excluded. Aggregate usable stock per medicine/RHU first and include medicines with demand and zero stock. Redistribution has the same batch-level comparison problem.

18. **Four routes call methods that do not exist, and two pages lack views.**
    Source: [web.php:60](routes/web.php#L60), [web.php:263](routes/web.php#L263), [web.php:372](routes/web.php#L372), [PublicController.php:235](app/Http/Controllers/PublicController.php#L235), [RedistributionController.php:42](app/Http/Controllers/RedistributionController.php#L42).
    Reflection confirmed missing actions: PublicController::verifyResidency, PublicController::clearResidency, DashboardController::refreshData, and InventoryController::exportDOH_ShipmentsCsv. The existing export method is exportDohShipmentsCsv; the underscore makes the route target different. Views public.map and redistribution.index are absent. These endpoints fail when reached, despite route registration and Blade compilation succeeding.

19. **Supply approval can deduct the same request more than once.**
    Source: [AllocationController.php:756](app/Http/Controllers/AllocationController.php#L756).
    The pending allocation is fetched before the transaction. Inventory rows are locked inside, but the allocation itself is not locked/reloaded. Concurrent approval requests can both retain the original pending model and deduct stock sequentially. Lock and validate the allocation state inside the transaction, and coordinate reject/cancel with the same state lock.

20. **PHO actions are omitted from the activity log.**
    Source: [ActivityLogger.php:14](app/Helpers/ActivityLogger.php#L14).
    PHO sessions intentionally have a null health_unit_id. The logger immediately returns when the resolved facility is falsy, dropping PHO authentication and administrative actions unless a facility is explicitly supplied. Store null-facility audit events and authorize their visibility separately.

21. **Login does not regenerate the session ID.**
    Source: [AuthController.php:82](app/Http/Controllers/AuthController.php#L82).
    Successful authentication sets session fields without calling session()->regenerate() or a framework login method that migrates the session. This preserves the anonymous session identifier across authentication and leaves a session-fixation weakness. Regenerate after successful credential/account checks.

22. **Stock lookup counts deleted and expired inventory.**
    Source: [StockLookupController.php:67](app/Http/Controllers/StockLookupController.php#L67).
    DB::table('inventory') bypasses the Inventory model's soft-delete scope and has no deleted_at or expiration filter. The UI can advertise stock that cannot be dispensed or transferred. Reuse the usable-stock conditions already present in PublicController and Inventory::availableFefo().

23. **Automated tests do not cover business behavior and the existing feature test fails.**
    Source: [ExampleTest.php:17](tests/Feature/ExampleTest.php#L17), tests/Unit/ExampleTest.php.
    The entire suite contains a true-is-true assertion and a root HTTP status assertion. The latter expects 200 although the root intentionally redirects. Update it to assert the redirect. Add regression coverage for unapproved-request deletion, multi-batch reversals, facility authorization, deactivated sessions, credential changes, prescription storage, state transitions, and clean database setup. Exercise concurrency-sensitive paths against the actual production database engine.

## Maintenance and design observations

- The active IDE file, resources/views/inventory/index.blade.php.bak, is a backup. Laravel renders index.blade.php; edits to the .bak file do not update the inventory page. Backups also duplicate controllers/services and make searches and maintenance less reliable.
- The legacy app/Http/Kernel.php and app/Console/kernel.php coexist with a Laravel 12 bootstrap that does not use them. Consolidate runtime registration after fixing scheduling; avoid assuming changes in these legacy files take effect.
- Controllers repeat role checks, RHU scoping, batch parsing, expiry filtering, and CSV formatting. Shared authorization policies and a stock movement service would make the identified correctness rules easier to enforce consistently.
- SearchController loads all matching records and calculates a page position for each result, producing repeated count queries. Immunization summary/defaulter methods also query inside per-child loops despite an existing bulk schedule method. These will scale poorly with a larger patient/history dataset.
- RedistributionService::classifyStock() tests monthsRemaining < 1 before < 0.5, so its critical branch is unreachable. Its suggestion loop does not reduce remaining surplus after each recommendation, so simultaneous recommendations can exceed the intended donor budget.
- NotificationService emits a low-stock warning before its critical-low-stock notification using the same inventory/type deduplication. The warning can suppress the danger notification. Evaluate critical stock first or include severity in the deduplication rule.
- Immunization recording validates an arbitrary vaccine string and any positive dose number without verifying a matching active schedule entry. Add referential validation against the application's configured schedule; do not silently accept misspelled/nonexistent dose identifiers.
- PublicUserController validates assigned_midwife_id only as an existing user. Restrict assignments to active midwives in the same RHU. Also verify the explicit assignment behavior when the patient currently has no assigned midwife: that branch currently ignores a submitted selection.
- Inventory adjustment and its stock log are separate writes outside a transaction. An error between them leaves an unlogged balance change. Consider the same atomic movement service used for dispensing/transfers.
- Several workflows derive transaction direction from free-text remarks. StockLogController's addition filter checks remarks IS NULL even though new additions use 'Added — Stock entry'. Introduce a structured movement type and maintain explicit reversal relationships instead of deleting the historical movement log.
- Generated compiled views are tracked in Git. Their absolute source paths and generated output should be excluded from source control in a separate cleanup change. README.md is still the Laravel template and does not describe this application's required baseline schema, roles, deployment, scheduler, or recovery process.

## Suggested repair order

1. Fix unapproved-request deletion, cross-RHU exports, stored HTML rendering, and session/user authorization.
2. Introduce exact per-batch movement records and transaction/state locking, then fix reversal and transfer workflows.
3. Restore reproducible database setup, register schedules, and repair the broken route/view wiring.
4. Fix credentials, prescription lifecycle, disease/stock forecasts, and stock lookup consistency.
5. Add targeted regression coverage, then consolidate repeated logic and improve documentation.
