<section aria-labelledby="stock-trend-heading" class="forecast-panel forecast-trend">
    <h2 id="stock-trend-heading" style="--forecast-text-size:1.4375rem;font-weight:700;margin:0 0 8px;">Weekly release trend</h2>
    <p style="color:#64748b;margin:0 0 16px;">Recorded medicine releases and the projected trend for each RHU. Incoming stock, inter-facility transfers, and stock corrections do not count as usage.</p>
    @if(count($forecast['all_items'] ?? []) > 0)
        <label for="stock-trend-item" style="display:block;font-weight:600;margin-bottom:6px;">Medicine, strength, and category</label>
        <select id="stock-trend-item" class="forecast-select" data-search-placeholder="Search medicine, strength, or category">
            @foreach($forecast['all_items'] as $trendIndex => $trendItem)
                @php($trendMedicine = $trendItem['inventory']->medicine)
                <option value="{{ $trendIndex }}">{{ $trendItem['medicine_name'] }}{{ filled($trendMedicine->dosage) ? ' '.trim($trendMedicine->dosage) : '' }}@if(filled($trendMedicine->category)) — {{ $trendMedicine->category_label }}@endif</option>
            @endforeach
        </select>
        <p id="stock-trend-note" role="status" style="margin:12px 0;color:#475569;"></p>
        <div style="height:300px;position:relative;"><canvas id="stockTrendChart" aria-label="Historical and forecast medicine release quantities" role="img"></canvas></div>
    @else
        <p style="color:#64748b;">Stock or release records are needed to display a medicine trend.</p>
    @endif

</section>
