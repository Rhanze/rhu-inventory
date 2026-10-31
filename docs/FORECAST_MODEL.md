# Medicine stock-movement trend model

The stock forecast and dashboard shortage summary use ordinary least squares linear regression of weekly medicine releases. The calculation is independent for each medicine and health unit. It is an explanatory trend model, without trained external weights or an AI service.

## Inputs

The source is the dated stock movement history in `stock_logs`, in base units. New dispensing and local allocations write explicit `movement_type` values. `StockLog::demandReleases()` selects:

- Active, positive `dispensing` and local `allocation` releases.
- Older logs linked to a dispensing record or a local allocation, using the existing foreign keys.
- Unlinked historical logs explicitly identified as dispensing/allocation releases in their remarks.

Voided records, negative quantities, incoming stock, inter-facility transfers, provincial distributions, and generic corrections/deletions are excluded. Supply receipts are not patient usage; counting transfers as consumption at both source and destination would double-count the same supply chain. Pending supply requests have no demand movement until approved. Local approved allocations represent recorded program issues, so they are included even if patient administration occurs later.

The exact batch ledger continues to protect stock balances. Forecasting uses the effective dated release logs rather than counting every ledger reversal and replacement as new demand.

## Observation periods

Defaults are in `config/forecasting.php`:

- Look back 12 completed seven-day periods (84 days), ending yesterday in the application timezone.
- Today's partial day and future-dated entries are excluded from fitting.
- Start the series at the first qualifying release period in that window. Earlier periods are not assumed to be observed zero demand.
- Fill subsequent periods without releases with zero; preserve the passage of time.
- Sum releases over all batches for the medicine/facility before fitting.

Periods are consecutive seven-day intervals relative to the forecast date, not ISO calendar weeks. Consequently, a newly generated forecast can change as the daily observation window advances.

## Equations

For n observed periods, let t = 1,...,n and y(t) be recorded released units in period t. Fit:

```text
y_hat(t) = a + b*t
b = sum((t - mean(t)) * (y(t) - mean(y))) / sum((t - mean(t))^2)
a = mean(y) - b*mean(t)
```

At future period k (starting at k=1):

```text
weekly_forecast(k) = max(0, a + b*(n+k))
daily_forecast(d) = weekly_forecast(1 + floor((d-1)/7)) / 7
```

Historical fit quality is:

```text
R_squared = 1 - sum((y(t) - y_hat(t))^2) / sum((y(t) - mean(y))^2)
```

A constant perfectly fitted series has R²=1 by convention. R² describes historical fit and is not a percentage prediction accuracy, confidence level, or causal explanation.

The slope is the change in weekly released units per period. A slope within ±5% of the historical weekly mean is labelled stable; otherwise its sign labels rising/falling. This label does not replace the underlying fitted coefficient.

## Sparse history

At least four completed observed periods are required to use the linear trend. One to three periods use their weekly mean with zero slope, labelled **Limited history** and with no R². No qualifying history produces a **No history** label, zero numerical projection, and unavailable coverage/reorder values for positive stock. A zero projection without history does not establish that demand is zero.

## Stock decisions

```text
90-day need = ceil(sum(daily_forecast(d), d=1..90))
30-day need = ceil(sum(daily_forecast(d), d=1..30))
reorder point = ceil(sum(daily_forecast(d), d=1..(14+7)))
shortage = max(0, 90-day need - currently usable available stock)
```

The reorder window comprises a 14-day lead time and seven safety-stock days. It uses the projected trend over that window, not the old flat historical daily average. Final partial weeks are prorated; a 90-day horizon uses 12 full weeks and six days of week 13. Floating-point noise is rounded before the final whole-unit ceiling.

Coverage consumes the same daily forecast cumulatively until available stock runs out. The display shows full covered days. `365+` indicates that stock is not exhausted during the 365-day calculation window; the infinity display indicates no projected release in that window. Stock-status thresholds remain critical below seven days, low below 30, adequate below 90, and surplus thereafter. Zero available stock is critical; positive stock without history is unknown.

The historical “Average 30-day Usage” column is retained for comparison and equals historical weekly average ×30/7. Forecast needs, reorder points, and coverage are derived from the regression/fallback projections, not that display average.

## Display and diagnostics

The forecasting page displays the direction, completed period count, historical R² when applicable, next 30-day need, 90-day need, coverage, and reorder point. A medicine/facility selector plots historical weekly totals, their fitted line, and future weekly estimates. The graph shows complete future weekly estimates; the numeric 90-day need prorates its final week.

`StockTrendModel` contains the deterministic mathematics. `StockTrendService` groups dated movements and prepares the observations/projections. `ForecastService` applies the model to current stock and exposes the results to the forecast page and dashboard. No schema migration is required for this change.

## Assumptions and limits

Observed issues/releases are a proxy for demand. Unrecorded activity and unmet demand during stockouts cannot be reconstructed from those records. This model does not estimate seasonality, outbreak-driven changes, future receipts, or future batch expiry. Coverage starts with currently usable, unreserved stock and assumes that balance remains usable. Check expiry and planned supply separately.

No measured production prediction accuracy is claimed. Equation tests verify known lines, noisy data, zero/negative boundaries, sparse history, and partial weeks. Integration tests verify facility/medicine separation, input exclusions, zero periods, and actual forecast/reorder/coverage outputs. Retrospective out-of-sample validation is needed before asserting accuracy on this installation's real demand.

Method reference: [NIST — Least Squares](https://www.itl.nist.gov/div898/handbook/pmd/section4/pmd431.htm).

## Verification of this implementation

On 31 October 2026, all 41 tests passed (320 assertions) on both SQLite and MariaDB. The disposable MariaDB concurrency check also passed. Production asset build, forecasting JavaScript syntax, Blade compilation, and 157 source/compiled PHP syntax checks passed. These checks establish implementation correctness for the tested cases; they do not establish production prediction accuracy.
