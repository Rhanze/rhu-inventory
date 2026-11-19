@php
    $consumptionLabels = ['elevated' => 'Elevated consumption signal', 'new' => 'New consumption to review',
        'insufficient' => 'Building comparison history', 'typical' => 'Within usual consumption'];
@endphp
<section class="forecast-panel" style="padding:24px 26px;box-sizing:border-box;width:100%;min-width:0;max-width:100%;" aria-labelledby="forecast-diseases">
    <div style="margin-bottom:18px;">
        <h2 style="--forecast-text-size:1.4375rem;font-weight:700;color:#0f172a;margin:0 0 8px;" id="forecast-diseases">Possible Disease Trends from Medicine Consumption</h2>
        <p style="--forecast-text-size:1.0625rem;color:#475569;margin:0;">Linked medicines are compared with their own usual consumption. The latest completed week is compared with the previous four completed weeks; a rise of at least 50% creates an elevated signal.</p>
    </div>
    @if(count($unplanned['items']) > 0)
        @include('forecast._disease_consumption_chart')
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:16px;">
            @foreach($unplanned['items'] as $disease)
                <article style="background:#fff;border:1px solid #e2e8f0;border-left:4px solid {{ $disease->color }};border-radius:14px;padding:18px 20px;min-width:0;">
                    <p style="--forecast-text-size:1.0625rem;color:#0f766e;font-weight:700;margin:0 0 6px;">{{ $consumptionLabels[$disease->status] }}</p>
                    <h3 style="--forecast-text-size:1.3125rem;font-weight:700;color:#0f172a;margin:0 0 6px;">{{ $disease->disease_name }}</h3>
                    <p style="--forecast-text-size:1.0625rem;color:#475569;margin:0 0 14px;">{{ $disease->health_unit_name }}<br>{{ $disease->period_start }} to {{ $disease->period_end }}</p>
                    <p style="--forecast-text-size:1.0625rem;font-weight:700;color:#334155;margin:0 0 10px;">Associated medicine consumption</p>
                    @foreach($disease->medicines as $medicine)
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px;margin-top:8px;">
                            <strong style="--forecast-text-size:1.0625rem;color:#0f172a;">{{ $medicine->medicine_name }}{{ $medicine->dosage ? ' '.$medicine->dosage : '' }}{{ $medicine->dosage_form ? ' ('.$medicine->dosage_form.')' : '' }}</strong>
                            <p style="--forecast-text-size:1.0625rem;color:#475569;margin:6px 0 0;">Latest week: {{ number_format($medicine->current_quantity) }} {{ $medicine->unit }}<br>Usual weekly amount: {{ $medicine->baseline_average === null ? 'Building history' : number_format($medicine->baseline_average, 1).' '.$medicine->unit }}</p>
                            @if($medicine->change_percent !== null)
                                <p style="--forecast-text-size:1.0625rem;font-weight:600;color:#334155;margin:6px 0 0;">{{ $medicine->change_percent > 0 ? '+' : '' }}{{ number_format($medicine->change_percent, 1) }}% consumption change</p>
                            @endif
                        </div>
                    @endforeach
                </article>
            @endforeach
        </div>
        <p style="--forecast-text-size:1.0625rem;color:#475569;margin:16px 0 0;">Review elevated signals alongside stock availability and recording consistency. Evidence stays separate for each medicine and RHU; a consumption increase suggests a possible disease-related trend.</p>
    @else
        <div style="padding:28px 20px;background:#f8fafc;border-radius:12px;border:1px dashed #cbd5e1;">
            <h3 style="--forecast-text-size:1.3125rem;font-weight:700;color:#0f172a;margin:0 0 8px;">No mapped medicine consumption yet</h3>
            <p style="--forecast-text-size:1.0625rem;color:#475569;margin:0;">Record dispensing for medicines linked to diseases to build automatic trend signals.</p>
        </div>
    @endif
</section>
