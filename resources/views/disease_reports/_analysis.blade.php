@if($analysis)
    <div class="disease-row" style="margin:20px 0;align-items:stretch;">
        <div class="disease-stat"><span class="disease-muted">Reporting completeness</span><strong>{{ $analysis['reported_periods'] }} / {{ count($analysis['periods']) }}</strong>confirmed completed periods</div>
        <div class="disease-stat"><span class="disease-muted">OLS trend</span><strong>{{ ucfirst(str_replace('_', ' ', $analysis['direction'])) }}</strong>
            @if($analysis['regression']['slope'] !== null){{ number_format($analysis['regression']['slope'], 2) }} change in cases per period<br>Historical R&sup2; {{ number_format($analysis['regression']['r_squared'], 3) }}@endif
        </div>
        <div class="disease-stat"><span class="disease-muted">Holt adaptive forecast</span><strong>{{ $statusLabels[$analysis['holt']['status']] }}</strong>{{ $analysis['holt']['observations'] }} consecutive confirmed periods</div>
    </div>
    <p class="disease-muted">Missing = {{ $analysis['quality_counts']['missing'] ?? 0 }}; provisional = {{ $analysis['quality_counts']['provisional'] ?? 0 }}; needs review = {{ $analysis['quality_counts']['unverified'] ?? 0 }}; conflicting = {{ $analysis['quality_counts']['conflict'] ?? 0 }}. Missing counts stay unknown; a confirmed zero remains zero.</p>
    <div style="position:relative;height:320px;"><canvas id="diseaseTrendChart" role="img" aria-label="Confirmed disease cases with regression and Holt projections"></canvas></div>
    <p class="disease-muted">The first projection is for the current {{ $periodType === 'weekly' ? 'week' : 'month' }}, as a full-period estimate. Current partial reports are excluded from training. Gaps in observed data are not connected.</p>
    <div class="disease-scroll" style="margin-top:20px;">
        <table class="disease-table" style="min-width:600px;"><thead><tr><th>Projection period</th><th>OLS expected cases</th><th>Holt expected cases</th></tr></thead><tbody>
        @foreach($analysis['forecast'] as $period)
            <tr><td>{{ $period['start'] }} to {{ $period['end'] }} {{ $period['is_current_period'] ? '(current period)' : '' }}</td><td>{{ $period['regression'] === null ? 'Unavailable' : number_format($period['regression'], 1) }}</td><td>{{ $period['holt'] === null ? 'Unavailable' : number_format($period['holt'], 1) }}</td></tr>
        @endforeach
        </tbody></table>
    </div>
    <details style="margin-top:20px;"><summary style="font-weight:600;cursor:pointer;">Model assumptions and validation</summary>
        <p class="disease-muted">OLS fits cases = intercept + slope &times; period number using confirmed observations and their actual time positions. At least four observations and a confirmed latest completed period are required for projection.</p>
        <p class="disease-muted">Holt updates its level and trend as each count arrives. It requires at least four consecutive confirmed periods ending at the latest completed period. Negative predictions are limited to zero. Missing reports are never imputed.</p>
        <p class="disease-muted">Holt weights: &alpha;={{ number_format($analysis['holt']['alpha'], 2) }}, &beta;={{ number_format($analysis['holt']['beta'], 2) }}.
        @if($analysis['holt']['validation_mae'] !== null)
            Last {{ $analysis['holt']['validation_periods'] }} periods, evaluated one step at a time: Holt MAE {{ number_format($analysis['holt']['validation_mae'], 2) }} cases; previous-period baseline MAE {{ number_format($analysis['holt']['naive_mae'], 2) }} cases. Weights were selected using the earlier training data.
        @else
            Holdout validation is unavailable until at least eight consecutive confirmed periods are available; configured weights are used.
        @endif
        </p>
        <p class="disease-muted">R&sup2; measures historical fit. Expected cases are estimates, not diagnoses or outbreak declarations. These models do not account for seasonality, changes in testing, or reporting delays.</p>
    </details>
    <details style="margin-top:16px;"><summary style="font-weight:600;cursor:pointer;">Observed periods and data quality</summary><div class="disease-scroll">
        <table class="disease-table" style="min-width:600px;"><thead><tr><th>Period</th><th>Reported cases</th><th>Data status</th></tr></thead><tbody>@foreach($analysis['periods'] as $period)<tr><td>{{ $period['start'] }} to {{ $period['end'] }}</td><td>{{ $period['cases'] === null ? 'Unknown' : $period['cases'] }}</td><td>{{ ucfirst($period['status']) }}</td></tr>@endforeach</tbody></table>
    </div></details>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof Chart === 'undefined') return;
        const analysis = @json($analysis);
        const history = analysis.periods;
        const future = analysis.forecast;
        const labels = history.concat(future).map(period => period.start);
        const regression = history.map(() => null).concat(future.map(period => period.regression));
        const holt = history.map(() => null).concat(future.map(period => period.holt));
        if (analysis.regression.status === 'ready') regression[history.length - 1] = Math.max(0, analysis.regression.fitted[history.length - 1]);
        if (analysis.holt.status === 'ready') holt[history.length - 1] = Math.max(0, analysis.holt.level);
        new Chart(document.getElementById('diseaseTrendChart'), {type:'line', data:{labels, datasets:[
            {label:'Confirmed reported cases', data:history.map(period => period.cases).concat(future.map(() => null)), borderColor:'#0f766e', spanGaps:false, tension:0},
            {label:'OLS historical fit', data:analysis.regression.fitted.concat(future.map(() => null)), borderColor:'#94a3b8', borderDash:[3,3], spanGaps:false, tension:0},
            {label:'OLS projection', data:regression, borderColor:'#2563eb', borderDash:[5,4], spanGaps:false, tension:0},
            {label:'Holt projection', data:holt, borderColor:'#9333ea', borderDash:[5,4], spanGaps:false, tension:0}
        ]}, options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},scales:{y:{beginAtZero:true,title:{display:true,text:'Cases per reporting period'}},x:{title:{display:true,text:'Reporting period start'}}}}});
    });
    </script>
@else
    <p class="disease-muted">Add a disease to begin reporting. Forecasts become available after sufficient confirmed periods are recorded.</p>
@endif
