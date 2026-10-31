# Consistent stock demonstration data

Use the dedicated stock-history seeder on a local demonstration database:

```powershell
php artisan db:seed --class=SampleStockHistorySeeder
```

It targets the RHU containing the older `STK-INIT-` or `BT-` sample batches, falling back to the first RHU. Set `SEED_HEALTH_UNIT_ID` to target another RHU. The RHU needs an active staff/admin account and the catalog needs dispensable entries in the medicine category. The generator is restricted to local/testing environments and is not called by the default database seeder.

The dataset uses up to 12 different medicines, each with its own `DEMO-V2-<facility>-<medicine>` batch. Every batch has opening and supplementary receipts, up to 12 completed weeks of dispensing, matching dispensing records, and exact stock movements. Four repeatable usage patterns cycle across the medicines: rising, declining, approximately steady, and intermittent. Counts are demonstration quantities, not prescribing examples. Fictional visitors and clinicians are explicitly labeled `[DEMO]`.

Each batch's chronological balance stays nonnegative. Receipts minus dispensing equals available inventory, and the movement ledger reconciles to that same balance. Allocated stock is zero because these scenarios do not fabricate allocations or pending transfers. Remaining quantities and expiry dates vary so the examples support stock, expiry, and trend views.

Rerunning skips existing demonstration batches, including soft-deleted ones, so it neither doubles stock nor overwrites subsequent activity. It does not remove or repair older sample transactions. The older seeders insert records independently and should not be used to regenerate this movement history. Use this dedicated generator for stock-log and forecasting demonstrations.

Verification: `tests/Feature/SampleStockHistoryTest.php` checks variety, chronological balances, record references, ledger reconciliation, reversibility, and idempotency.
