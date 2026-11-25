@php
    $chartMedicines = collect($consumptionChartAnalysis['medicine_signals'])->filter(fn ($medicine) => $medicine['transactions'] > 0)->map(function ($medicine) use ($consumptionChartAnalysis) {
        $medicine['diseases'] = collect($consumptionChartAnalysis['signals'])
            ->filter(fn ($signal) => in_array($medicine['id'], array_column($signal['medicines'], 'id'), true))
            ->pluck('name')->values()->all();
        return $medicine;
    })->values();
    $consumptionChartPayload = ['medicines' => $chartMedicines, 'historical' => $consumptionChartAnalysis['historical'],
        'saved_history' => $consumptionChartUsesSavedHistory, 'window' => $consumptionChartAnalysis['window'],
        'default_mode' => $consumptionChartAnalysis['default_mode'] ?? 'periods',
        'preferred_medicine_id' => $consumptionChartAnalysis['preferred_medicine_id'] ?? null,
        'period_type' => $periodType, 'threshold_percent' => $consumptionChartAnalysis['threshold_percent']];
@endphp
<div class="disease-consumption-graphs" aria-labelledby="consumption-graphs-heading">
    <h3 id="consumption-graphs-heading" class="disease-subheading">Consumption trend graphs</h3>
    <p class="disease-muted">Review recorded medicine use. Medicines without a configured disease association remain consumption records only. Each medicine keeps its own strength, form, and unit.</p>
    @if($consumptionChartUsesSavedHistory)
        <p class="disease-muted" id="consumption-chart-history-notice">No disease-linked dispensing was recorded in the current comparison dates, {{ $diseaseTrends['window']['from'] }} to {{ $diseaseTrends['window']['to'] }}. Showing saved consumption history from {{ $consumptionChartAnalysis['window']['from'] }} to {{ $consumptionChartAnalysis['window']['to'] }}.</p>
    @endif
    @if($chartMedicines->isNotEmpty())
        <div class="disease-graph-panel">
            <label for="consumption-chart-medicine" class="disease-form-label">Search medicine to graph</label>
            <select id="consumption-chart-medicine" class="disease-control" data-search-placeholder="Search medicine, strength, or form" style="width:100%;">
                @foreach($chartMedicines as $medicine)
                    @php
                        $form = trim($medicine['dosage_form'] ?? '');
                        $unit = trim($medicine['unit'] ?? '');
                        $label = trim($medicine['name'].' '.($medicine['dosage'] ?? '').' '.$form);
                        if ($unit !== '' && mb_strtolower($form) !== mb_strtolower($unit)) {
                            $label .= ' ('.$unit.')';
                        }
                    @endphp
                    <option value="{{ $medicine['id'] }}">{{ $label }}</option>
                @endforeach
            </select>
            <p id="consumption-chart-associations" class="disease-muted"></p>
            <label for="consumption-chart-mode" class="disease-form-label">Graph view</label>
            <select id="consumption-chart-mode" class="disease-control">
                <option value="transactions">Recorded transactions</option>
                <option value="periods">{{ $periodType === 'monthly' ? 'Monthly totals' : 'Weekly totals' }}</option>
            </select>
            <div class="disease-chart"><canvas id="diseaseConsumptionHistoryChart" role="img" aria-label="Medicine consumption over time; exact quantities appear in the graph data table below"></canvas></div>
            <p id="consumption-chart-summary" class="disease-muted" aria-live="polite"></p>
            <details>
                <summary>View graph data</summary>
                <div class="disease-scroll disease-graph-data-scroll" tabindex="0" role="region" aria-label="Consumption graph quantities">
                    <table class="disease-table" style="min-width:460px;">
                        <thead><tr><th scope="col" id="consumption-graph-dates">Dates included</th><th scope="col" id="consumption-graph-unit">Quantity consumed</th><th scope="col">Transactions</th></tr></thead>
                        <tbody id="consumption-graph-rows"></tbody>
                    </table>
                </div>
            </details>
        </div>
        @if(! $consumptionChartAnalysis['historical'])
            <div class="disease-graph-panel">
                <h4 class="disease-subheading">Consumption change from usual levels</h4>
                <p class="disease-muted">Latest completed {{ $periodType === 'monthly' ? 'month' : 'week' }} compared with the previous four-period average. The dashed line marks the +50% review threshold.</p>
                <div id="consumption-comparison-wrap" class="disease-chart"><canvas id="diseaseConsumptionChangeChart" role="img" aria-label="Consumption percentage change by medicine; exact changes appear in the supporting medicine evidence below"></canvas></div>
                <p id="consumption-comparison-note" class="disease-muted"></p>
            </div>
        @endif
        <p id="consumption-chart-unavailable" class="disease-muted" role="status" hidden>Graphs could not load. Supporting medicine quantities remain available in the evidence table below.</p>
        <noscript><p class="disease-muted">Enable JavaScript to view the graphs. Supporting medicine quantities are available below.</p></noscript>
        <script type="application/json" id="disease-consumption-chart-data">@json($consumptionChartPayload)</script>
        <link rel="stylesheet" href="{{ asset('css/consumption-search.css') }}">
        <script src="{{ asset('js/consumption-search.js') }}" defer></script>
        <script src="{{ asset('js/disease-consumption-charts.js') }}?v={{ filemtime(public_path('js/disease-consumption-charts.js')) }}" defer></script>
    @else
        <p class="disease-muted" role="status">Graphs appear when medicines have saved consumption in the selected scope and dates.</p>
    @endif
</div>
