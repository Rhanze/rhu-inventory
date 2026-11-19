@php
    $consumptionSeries = [];
    foreach ($unplanned['items'] as $condition) {
        foreach ($condition->medicines as $medicine) {
            $consumptionSeries[] = [
                'label' => $condition->disease_name.' / '.$medicine->medicine_name
                    .($medicine->dosage ? ' '.$medicine->dosage : '')
                    .($medicine->dosage_form ? ' ('.$medicine->dosage_form.')' : '')
                    .' / '.$condition->health_unit_name,
                'unit' => $medicine->unit,
                'baseline' => $medicine->baseline_average,
                'history' => $medicine->history,
            ];
        }
    }
@endphp
<link rel="stylesheet" href="{{ asset('css/consumption-search.css') }}">
<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:18px;margin-bottom:20px;min-width:0;">
    <h3 style="--forecast-text-size:1.25rem;color:#0f172a;margin:0 0 8px;">Consumption trend graph</h3>
    <p style="--forecast-text-size:1rem;color:#475569;margin:0 0 14px;">Search for a condition, medicine, or RHU to compare five completed weeks. The dashed line shows the previous four weeks' average.</p>
    <label for="forecast-consumption-series" style="--forecast-text-size:1rem;display:block;font-weight:600;color:#334155;margin-bottom:6px;">Condition, medicine and RHU</label>
    <select id="forecast-consumption-series" data-consumption-search data-search-placeholder="Search condition, medicine, or RHU…" style="--forecast-text-size:1rem;box-sizing:border-box;width:100%;max-width:100%;min-width:0;border:1px solid #cbd5e1;border-radius:8px;background:#fff;padding:10px;color:#0f172a;">
        @foreach($consumptionSeries as $series)
            <option value="{{ $loop->index }}">{{ $series['label'] }} ({{ $series['unit'] }})</option>
        @endforeach
    </select>
    <p id="forecast-consumption-note" aria-live="polite" style="--forecast-text-size:1rem;color:#475569;margin:12px 0;">{{ $consumptionSeries[0]['baseline'] === null ? 'More completed consumption history is needed to calculate the usual weekly amount.' : 'Usual weekly amount: '.number_format($consumptionSeries[0]['baseline'], 1).' '.$consumptionSeries[0]['unit'].'.' }}</p>
    <div style="position:relative;height:270px;min-width:0;">
        <canvas id="forecastConsumptionChart" role="img" aria-label="Weekly consumption of {{ $consumptionSeries[0]['label'] }}, in {{ $consumptionSeries[0]['unit'] }}"></canvas>
    </div>
    <details style="margin-top:12px;">
        <summary style="--forecast-text-size:1rem;color:#334155;cursor:pointer;">View graph values</summary>
        <div style="overflow-x:auto;">
            <table style="--forecast-text-size:1rem;width:100%;border-collapse:collapse;text-align:left;margin-top:10px;">
                <thead><tr><th scope="col" style="padding:8px;">Completed week</th><th scope="col" style="padding:8px;">Quantity consumed</th></tr></thead>
                <tbody id="forecast-consumption-values">
                    @foreach($consumptionSeries[0]['history'] as $week)
                        <tr><td style="padding:8px;">{{ $week['start'] }} to {{ $week['end'] }}</td><td style="padding:8px;">{{ $week['quantity'] === null ? 'No earlier consumption history' : number_format($week['quantity']).' '.$consumptionSeries[0]['unit'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
    <p style="--forecast-text-size:1rem;color:#64748b;margin:12px 0 0;">Medicine quantities are consumption evidence for a possible associated disease trend. They are not disease case counts or a calculated probability.</p>
</div>
<script id="forecast-consumption-data" type="application/json">@json($consumptionSeries)</script>
<script src="{{ asset('js/consumption-search.js') }}" defer></script>
