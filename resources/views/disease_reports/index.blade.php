@extends('layouts.app')

@section('title', 'Disease Trend Analysis')

@section('content')
@php
    $viewFilters = array_merge(request()->only(['year', 'date_from', 'date_to', 'periods', 'consumption_search']), [
        'period_type' => $periodType, 'barangay' => $selectedBarangay ?? '',
        'medicine_id' => $selectedMedicineId, 'disease_id' => $selectedDiseaseId,
        'view' => 'demand', 'usage_details' => $showUsageDetails ? 1 : 0,
        'consumption_scope' => session('role') === 'midwife' ? $consumptionScope : null,
    ]);
    $signalLabels = ['elevated' => 'Possible rising trend', 'new' => 'New consumption signal',
        'typical' => 'No elevated signal', 'insufficient' => 'Building history', 'historical' => 'Recorded consumption'];
@endphp
@include('disease_reports._styles')
<div class="disease-stack">
    <header class="disease-card disease-header">
        <div class="disease-header-copy">
            <p class="disease-eyebrow">Automatic consumption monitoring</p>
            <h1 class="disease-heading">Disease Trend Analysis</h1>
            <p class="disease-muted">Identify possible disease trends from changes in the consumption of associated medicines.</p>
        </div>
    </header>
    @if($errors->any())
        <div class="disease-card" role="alert" style="border-color:#fca5a5;">
            <strong>Please review your input.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <details class="disease-history-dates" id="disease-history-dates" @if($historyRange->isFiltered() || $errors->hasAny(['year', 'date_from', 'date_to'])) open @endif>
        <summary>View consumption history <span class="disease-view-description">{{ $historyRange->isFiltered() ? 'Showing: '.$historyRange->label() : 'Current completed-period comparison' }}</span></summary>
        @include('shared.history_filters', ['historyRange' => $historyRange, 'preservedFilters' => $viewFilters])
    </details>
    @include('shared.empty_result', ['resultKey' => 'history', 'recordCount' => $savedConsumptionCount])
    @if($historyRange->isFiltered())
        <p class="disease-muted">Historical view: {{ $historyRange->label() }}. Quantities include only consumption dated within the selected range. Current trend alerts and demand forecasts are hidden.</p>
    @endif
    <script src="{{ asset('lib/chartjs/chart.umd.js') }}"></script>
    <script>
        if (typeof Chart !== 'undefined') {
            Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
            Chart.defaults.font.size = 17;
            Chart.defaults.color = '#475569';
        }
    </script>
    @include('disease_reports._signals')
    @include('disease_reports._records')
    @include('disease_reports._usage')
</div>
<script>
(() => {
    document.querySelectorAll('.disease-details').forEach(details => {
        details.addEventListener('toggle', () => {
            if (!details.open || typeof Chart === 'undefined') return;
            requestAnimationFrame(() => details.querySelectorAll('canvas').forEach(canvas => Chart.getChart(canvas)?.resize()));
        });
    });
})();
</script>
@endsection
