<section class="disease-card disease-signals-card" id="disease-trends" aria-labelledby="disease-trend-heading">
    <h2 id="disease-trend-heading" class="disease-section-heading">Disease signals from medicine consumption</h2>
    <p class="disease-muted">Higher use of a medicine can suggest increased activity of an associated disease. Review the medicine evidence behind each signal.</p>
    @if(session('role') === 'midwife')
        <nav class="disease-row" aria-label="Consumption scope" style="margin:20px 0;gap:10px;">
            <a class="disease-button {{ $consumptionScope === 'personal' ? '' : 'disease-secondary' }}" @if($consumptionScope === 'personal') aria-current="page" @endif href="{{ route('disease_reports.index', array_merge($viewFilters, ['consumption_scope' => 'personal', 'barangay' => '', 'medicine_id' => null, 'usage_details' => 0])) }}#disease-trends">My recorded consumption</a>
            @if($barangayOptions->isNotEmpty())
                <a class="disease-button {{ $consumptionScope === 'area' ? '' : 'disease-secondary' }}" @if($consumptionScope === 'area') aria-current="page" @endif href="{{ route('disease_reports.index', array_merge($viewFilters, ['consumption_scope' => 'area', 'barangay' => $selectedBarangay ?: $barangayOptions->first(), 'medicine_id' => null, 'usage_details' => 0])) }}#disease-trends">Assigned barangay</a>
            @else
                <span class="disease-button disease-secondary" aria-disabled="true">Assigned barangay</span>
            @endif
        </nav>
    @endif
    <form method="GET" action="{{ route('disease_reports.index') }}#disease-trends" class="disease-filters disease-trend-filters" data-empty-action="disease-trends">
        @include('disease_reports._history_parameters', ['assignedAreaForm' => true])
        @if(session('role') === 'midwife')<input type="hidden" name="consumption_scope" value="{{ $barangayOptions->isEmpty() ? 'personal' : 'area' }}">@endif
        @if(request()->filled('periods'))<input type="hidden" name="periods" value="{{ request('periods') }}">@endif
        <div><label for="demand-barangay">{{ session('role') === 'midwife' ? ($consumptionScope === 'personal' && $barangayOptions->isNotEmpty() ? 'Analyze assigned barangay' : 'Your assigned barangay') : 'Area' }}</label>
            <select id="demand-barangay" name="barangay" class="disease-control" data-native-select @disabled(session('role') === 'midwife' && $barangayOptions->isEmpty())>
                @if(session('role') !== 'midwife')<option value="">Entire RHU</option>@endif
                @forelse($barangayOptions as $area)
                    <option value="{{ $area }}" @selected($selectedBarangay === $area)>{{ $area }}</option>
                @empty
                    @if(session('role') === 'midwife')
                        <option value="">No assigned barangays</option>
                    @endif
                @endforelse
            </select>
        </div>
        <div><label for="disease-filter">Disease association</label>
            <select id="disease-filter" name="disease_id" class="disease-control" data-native-select>
                <option value="">All associated diseases</option>
                @foreach($diseases as $disease)<option value="{{ $disease->id }}" @selected($selectedDiseaseId === $disease->id)>{{ $disease->name }}</option>@endforeach
            </select>
        </div>
        <div><label for="demand-period">Compare consumption by</label><select id="demand-period" class="disease-control" name="period_type" data-native-select>
            <option value="weekly" @selected($periodType === 'weekly')>Week</option><option value="monthly" @selected($periodType === 'monthly')>Month</option>
        </select></div>
        <button type="submit" class="disease-button">Analyze trends</button>
    </form>
    @if($savedConsumptionCount > 0)
        <p class="disease-muted" id="saved-consumption-coverage">
            Saved dispensing: <strong>{{ number_format($savedConsumptionCount) }} {{ $savedConsumptionCount === 1 ? 'record' : 'records' }}</strong> &middot; {{ $savedConsumptionFrom }} to {{ $savedConsumptionTo }}.
            <a href="{{ route('disease_reports.index', array_merge($viewFilters, ['usage_details' => 1, 'year' => null, 'consumption_search' => null, 'disease_id' => $diseaseHistoryCount > 0 ? $selectedDiseaseId : null, 'date_from' => $savedConsumptionFrom, 'date_to' => $savedConsumptionTo])) }}#medicine-usage-details">View saved history</a>
        </p>
    @endif
    @include('shared.empty_result', ['resultKey' => 'disease-trends', 'recordCount' => $diseaseHistoryCount, 'emptyMessage' => $savedConsumptionCount > 0 ? 'No disease-associated consumption matches these filters. Saved dispensing is available in Medicine consumption details.' : 'No linked medicine consumption history yet.'])
    @if(! $diseaseTrends['historical'])
        <div class="disease-stats">
            <div class="disease-stat disease-stat-success" data-signal-status="elevated"><span class="disease-muted">Elevated disease signals</span><strong>{{ $diseaseTrends['overview']['elevated'] }}</strong>Associated medicine consumption is at least 50% above its usual level</div>
            <div class="disease-stat disease-stat-info" data-signal-status="typical"><span class="disease-muted">Within usual consumption</span><strong>{{ $diseaseTrends['overview']['typical'] }}</strong>Disease signals without elevated medicine consumption</div>
            <div class="disease-stat disease-stat-purple" data-signal-status="insufficient"><span class="disease-muted">Building history</span><strong>{{ $diseaseTrends['overview']['insufficient'] }}</strong>Disease signals needing recent comparison records or more completed periods</div>
            <div class="disease-stat disease-stat-info"><span class="disease-muted">New consumption signals</span><strong>{{ $diseaseTrends['overview']['new'] }}</strong>Consumption after a zero baseline needs review</div>
        </div>
        <p class="disease-muted">Barangay: <strong>{{ $selectedBarangay ?: ($consumptionScope === 'personal' ? 'My recorded consumption across saved areas' : 'Entire RHU') }}</strong>. Latest completed {{ $periodType === 'monthly' ? 'month' : 'week' }}: {{ $diseaseTrends['period_start'] }} to {{ $diseaseTrends['period_end'] }}. Usual consumption: {{ $diseaseTrends['baseline_start'] }} to {{ $diseaseTrends['baseline_end'] }} (four completed periods). The current partial period is excluded from this comparison.</p>
        @if($diseaseTrends['comparison_transactions'] === 0 && $savedConsumptionCount > 0)
            <p class="disease-muted">No qualifying records in this comparison period. <a href="{{ route('disease_reports.index', array_merge($viewFilters, ['usage_details' => 1, 'year' => null, 'disease_id' => $diseaseHistoryCount > 0 ? $selectedDiseaseId : null, 'consumption_search' => null, 'date_from' => $savedConsumptionFrom, 'date_to' => $savedConsumptionTo])) }}#recorded-consumption">View saved consumption history</a></p>
        @endif
    @else
        <p class="disease-muted">Barangay: <strong>{{ $selectedBarangay ?: ($consumptionScope === 'personal' ? 'My recorded consumption across saved areas' : 'Entire RHU') }}</strong>. Dates: {{ $diseaseTrends['period_start'] }} to {{ $diseaseTrends['period_end'] }}. Saved consumption is grouped by associated disease. Each medicine retains its own quantity and unit.</p>
    @endif
    @include('disease_reports._consumption_charts')
    <p class="disease-muted disease-signal-count" id="disease-signal-count">{{ count($diseaseTrends['signals']) }} {{ count($diseaseTrends['signals']) === 1 ? 'disease signal' : 'disease signals' }}</p>
    <div class="disease-scroll disease-signals-scroll" tabindex="0" role="region" aria-label="Disease trends and supporting medicine consumption" aria-describedby="disease-signal-count">
        <table class="disease-table mw-mobile-records" style="min-width:800px;">
            <thead><tr><th scope="col">Disease</th><th scope="col">Trend signal</th><th scope="col">Medicine consumption evidence</th></tr></thead>
            <tbody>@forelse($diseaseTrends['signals'] as $signal)
                <tr>
                    <td><strong>{{ $signal['name'] }}</strong></td>
                    <td><span class="disease-badge {{ $signal['status'] === 'elevated' ? 'disease-badge-warning' : 'disease-badge-neutral' }}">{{ $signalLabels[$signal['status']] }}</span>
                        @if($signal['status'] === 'elevated')<p class="disease-muted">{{ $signal['elevated_medicines'] }} associated {{ $signal['elevated_medicines'] === 1 ? 'medicine has' : 'medicines have' }} elevated consumption.</p>@endif
                    </td>
                    <td>
                        @foreach($signal['medicines'] as $medicine)
                            <div class="disease-medicine-evidence">
                                <strong>{{ $medicine['name'] }} {{ $medicine['dosage'] }} {{ $medicine['dosage_form'] }}</strong>
                                @if($diseaseTrends['historical'])
                                    <p class="disease-muted">Consumed: {{ number_format($medicine['total_quantity']) }} {{ $medicine['unit'] }} in {{ number_format($medicine['transactions']) }} dispensing transactions.</p>
                                @else
                                    <p class="disease-muted">Latest: {{ number_format($medicine['current_quantity']) }} {{ $medicine['unit'] }}. Usual: {{ $medicine['baseline_average'] === null ? 'Building history' : number_format($medicine['baseline_average'], 1).' '.$medicine['unit'] }}.
                                        @if($medicine['change_percent'] !== null)<strong>{{ $medicine['change_percent'] > 0 ? '+' : '' }}{{ number_format($medicine['change_percent'], 1) }}% consumption change.</strong>@endif
                                    </p>
                                @endif
                                <a class="disease-button disease-secondary" href="{{ route('disease_reports.index', array_merge(request()->only(['periods', 'year', 'date_from', 'date_to']), ['view' => 'demand', 'usage_details' => 1, 'consumption_scope' => session('role') === 'midwife' ? $consumptionScope : null, 'barangay' => $selectedBarangay ?? '', 'medicine_id' => $medicine['id'], 'disease_id' => $signal['id'], 'period_type' => $periodType])) }}#medicine-usage-details">View medicine trend</a>
                            </div>
                        @endforeach
                    </td>
                </tr>
            @empty<tr><td colspan="3">{{ $savedConsumptionCount > 0 ? 'Saved dispensing is available in Medicine consumption details. Disease signals require a configured medicine association matching these filters.' : 'No linked medicine consumption history is available in this scope. Disease signals appear automatically when associated medicines have recorded consumption.' }}</td></tr>@endforelse</tbody>
        </table>
    </div>
    <p class="disease-muted">These are possible disease-related signals, not diagnoses. A medicine can be associated with several diseases; the percentages describe consumption changes, not the probability of disease. Stockouts and missing dispensing records can also affect the pattern.</p>
</section>
