<section aria-labelledby="stock-trend-heading" style="background:white;border-radius:12px;padding:24px;border:1px solid #dce5e2;">
    <h2 id="stock-trend-heading" style="font-size:20px;font-weight:700;margin:0 0 8px;">Weekly release trend</h2>
    <p style="color:#64748b;margin:0 0 16px;">Recorded medicine releases and the projected trend for each RHU. Incoming stock, inter-facility transfers, and stock corrections do not count as usage.</p>
    @if(count($forecast['all_items'] ?? []) > 0)
        <label for="stock-trend-item" style="display:block;font-weight:600;margin-bottom:6px;">Medicine and facility</label>
        <select id="stock-trend-item" style="max-width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
            @foreach($forecast['all_items'] as $trendIndex => $trendItem)
                <option value="{{ $trendIndex }}">{{ $trendItem['medicine_name'] }} — {{ $trendItem['health_unit_name'] }}</option>
            @endforeach
        </select>
        <p id="stock-trend-note" role="status" style="margin:12px 0;color:#475569;"></p>
        <div style="height:300px;position:relative;"><canvas id="stockTrendChart" aria-label="Historical and forecast medicine release quantities" role="img"></canvas></div>
    @else
        <p style="color:#64748b;">Stock or release records are needed to display a medicine trend.</p>
    @endif
    <details style="margin-top:16px;color:#475569;">
        <summary style="cursor:pointer;font-weight:600;">How the forecast is computed</summary>
        <p>Up to {{ config('forecasting.history_weeks') }} completed seven-day periods are fitted with <strong>y = a + bt</strong>, where y is released units and t is the period number. The slope b indicates rising or falling usage. Missing periods after the first release count as zero.</p>
        <p>Fewer than {{ config('forecasting.minimum_trend_weeks') }} periods use a weekly-average fallback marked “Limited history”. Negative predictions are limited to zero. The {{ config('forecasting.forecast_days') }}-day need sums daily projections; the final partial week is prorated. Stock coverage and reorder quantities use this same forecast.</p>
        <p>R² measures the historical fit, not future accuracy. Seasonality, unrecorded demand, and demand prevented by stockouts are not estimated.</p>
    </details>
</section>
