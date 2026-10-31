# Disease reporting, trends, and forecasts

The disease reporting page implements improvements 1–3: consistent reporting periods, ordinary least squares (OLS) trends, and Holt's additive level/trend forecasts. Reports and models are scoped to the signed-in RHU. Select weekly or monthly analysis; the two frequencies are never mixed.

## Reporting foundation

Weekly periods run Monday–Sunday. Monthly periods are calendar months. The report date identifies a date inside the period. A report is eligible only after the entire period has ended and a user explicitly confirms that its case total is complete. Current-period reports may be saved provisionally.

There is one report per RHU, disease, frequency, and period. Legacy reports remain visible for review, with no inferred completeness or automatic conversion to zero. Review/edit them to confirm their period and total. Duplicate legacy reports make that period conflicting until reconciled. Deleting a report makes the period missing.

An explicitly confirmed zero is an observation. Missing, provisional, unverified, and conflicting periods are gaps (`null`), excluded from model fitting. They are never silently filled with zeros. The default history is 12 completed periods, adjustable from 4 to 52. Projections cover four periods, beginning with the current period, and represent full-period totals rather than cases remaining in that period.

Manual outbreak flags remain manual. Closing a flag records closure metadata on the original report and preserves its case count; it does not create an artificial zero-case report.

## OLS trend model

For observed case totals `y_i`, use their original period positions `t_i`, retaining the positions of missing periods:

```
b = sum((t_i - mean(t)) * (y_i - mean(y))) / sum((t_i - mean(t))^2)
a = mean(y) - b * mean(t)
forecast(N + h) = max(0, a + b * (N + h))
R² = 1 - sum((y_i - (a + b*t_i))^2) / sum((y_i - mean(y))^2)
```

`N` is the number of history slots, including gaps. At least four observed periods are required. Constant histories have R² = 1 by convention. R² describes historical fit, not forecast accuracy. The direction is stable when the absolute slope is at most 5% of the observed mean per period; otherwise its sign indicates rising or declining. This tolerance is a configurable display rule, not an epidemiological threshold. Projections and the current direction are unavailable when the latest completed period is missing or unverified.

## Holt's additive level/trend model

For each observed total `y_t`:

```
level_t = alpha*y_t + (1-alpha)*(level_previous + trend_previous)
trend_t = beta*(level_t - level_previous) + (1-beta)*trend_previous
forecast(t+h) = max(0, level_t + h*trend_t)
```

Only the uninterrupted sequence ending at the latest completed period is used. At least four consecutive confirmed periods are required. Initialize level with the second observation and trend with the difference between the first two observations; updates start at the third observation. Defaults are alpha = 0.4 and beta = 0.2.

With eight or more consecutive observations, reserve the last three periods for validation. Select weights using one-step mean absolute error (MAE) on the earlier training prefix, comparing the defaults with alpha in `{0.2, 0.4, 0.6, 0.8}` and beta in `{0.1, 0.2, 0.4}`. Keep the defaults on ties. Evaluate the reserved observations sequentially with fixed weights, updating the state after each actual observation. Display validation MAE alongside the previous-period naive forecast's MAE. Then refit the full sequence with the selected weights for production projections. The holdout never selects the weights.

These are non-seasonal point forecasts. Fractional expected case totals are shown to one decimal place. The models do not estimate reporting delays, seasonal effects, prediction intervals, or clinical outbreak risk. Forecast accuracy depends on the available confirmed history; no percentage improvement is asserted. Disease-to-medicine projected quantities and automatic outbreak alerts are outside these three improvements.

Configuration: `config/disease_analysis.php`. Implementation: `DiseasePeriodService`, `DiseaseRegressionModel`, `HoltDiseaseModel`, and `DiseaseAnalysisService`. Tests cover known equations, time gaps, zero cases, validation separation, frequency boundaries, authorization, and legacy review.

Model reference: Hyndman and Athanasopoulos, *Forecasting: Principles and Practice*, [exponential smoothing](https://otexts.com/fpp3/exponential-smoothing.html) and [forecasting toolbox and accuracy evaluation](https://otexts.com/fpp3/toolbox.html).
