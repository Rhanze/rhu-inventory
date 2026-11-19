<p class="disease-muted"><strong>{{ $selectedMedicine->medicine_name }}{{ $selectedMedicine->dosage ? ' '.$selectedMedicine->dosage : '' }}</strong>
    @if($selectedMedicine->trashed() || ! $selectedMedicine->is_dispensable) <span class="disease-badge disease-badge-neutral">Archived medicine history</span> @endif
    &middot; {{ $usageIsArea ? 'Dispensing for '.$selectedBarangay.' within your RHU.' : ($usageIsPersonal ? 'Only dispensing recorded by your account.' : 'Dispensing across your RHU.') }}</p>
<div class="disease-stats">
    <div class="disease-stat disease-stat-success"><span class="disease-muted">Dispensed in selected dates</span><strong>{{ number_format($usage['total_quantity']) }} {{ $usageUnit }}</strong>{{ number_format($usage['total_transactions']) }} transactions</div>
</div>
@if($usage['window']['from'] !== null)
<p class="disease-muted">Quantities use actual dispensing dates from {{ $usage['window']['from'] }} through {{ $usage['window']['to'] }}. Boundary weeks or months include only the selected days. Later transactions, forecasts and today's stock are excluded. A zero means no saved dispensing in that period after recording began; it does not prove there was no demand.</p>
@endif
@if($usage['status'] === 'no_history')<p class="disease-muted" role="status">No dispensing transactions were saved for this medicine in the selected dates and scope.</p>@endif
<div class="disease-chart"><canvas id="historicalMedicineUsageChart" role="img" aria-label="Historical medicine dispensing within the selected dates"></canvas></div>
<div class="disease-scroll" tabindex="0" role="region" aria-label="Historical medicine dispensing quantities">
    <table class="disease-table" style="min-width:560px;"><thead><tr><th>Included dates</th><th>Dispensed ({{ $usageUnit }})</th><th>Transactions</th></tr></thead><tbody>
    @forelse($usage['history'] as $period)
        <tr><td>{{ $period['included_start'] }} to {{ $period['included_end'] }}</td><td>{{ $period['quantity'] === null ? 'Before first recorded dispensing' : number_format($period['quantity']) }}</td><td>{{ number_format($period['transactions']) }}</td></tr>
    @empty<tr><td colspan="3">No historical dates are available in this range.</td></tr>@endforelse
    </tbody></table>
</div>
<script>
(() => {
    if (typeof Chart === 'undefined') return;
    const history = @json($usage['history']);
    new Chart(document.getElementById('historicalMedicineUsageChart'), {type:'line',data:{labels:history.map(period => period.included_start),datasets:[{label:'Recorded dispensing',data:history.map(period => period.quantity),borderColor:'#0f766e',spanGaps:false,tension:0}]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,title:{display:true,text:@json('Dispensed quantity ('.$usageUnit.')')}}}}});
})();
</script>
