@php
    $usageIsPersonal = ($usage['scope'] ?? null) === 'personal' || session('role') === 'midwife';
    $usageIsArea = ($usage['scope'] ?? null) === 'area';
    $usageUnit = $selectedMedicine?->unit ?: 'units';
    $usagePeriod = $periodType === 'monthly' ? 'month' : 'week';
    $usageDirectionLabels = ['rising' => 'Rising', 'declining' => 'Declining', 'stable' => 'Stable', 'unavailable' => 'Need more history'];
@endphp
<details class="disease-card disease-details" id="medicine-usage-details" @if($showUsageDetails) open @endif>
    <summary>
        <span id="medicine-usage-heading" class="disease-section-heading">Medicine consumption details</span>
        <span class="disease-view-description">Choose a non-vaccine medicine to review its history, expected demand, and RHU stock.</span>
    </summary>
    <p class="disease-scope-note">{{ $usageIsArea ? 'Barangay: '.$selectedBarangay : ($usageIsPersonal ? 'Your recorded dispensing' : 'RHU recorded dispensing') }}</p>
    <form method="GET" action="{{ route('disease_reports.index') }}#medicine-usage-details" class="disease-filters" data-empty-action="medicine-history" data-empty-message="No consumption history found for this medicine in the selected area and dates.">
        @include('disease_reports._history_parameters')
        <input type="hidden" name="usage_details" value="1">
        @if($selectedDiseaseId)<input type="hidden" name="disease_id" value="{{ $selectedDiseaseId }}">@endif
        <input type="hidden" name="barangay" value="{{ $selectedBarangay }}">
        <div>
            <label for="medicine-usage-filter">Medicine, strength and form</label>
            <select id="medicine-usage-filter" name="medicine_id" class="disease-control" @disabled($medicines->isEmpty())>
                @forelse($medicines as $medicine)
                    <option value="{{ $medicine->id }}" @selected((int) $selectedMedicineId === $medicine->id)>{{ $medicine->medicine_name }}{{ $medicine->dosage ? ' '.$medicine->dosage : '' }}{{ $medicine->dosage_form ? ' ('.$medicine->dosage_form.')' : '' }} — {{ $medicine->unit ?: 'units' }}@if($medicine->trashed() || ! $medicine->is_dispensable) [Historical]@endif</option>
                @empty
                    <option value="">No dispensable medicines available</option>
                @endforelse
            </select>
        </div>
        <div>
            <label for="medicine-usage-period">Analysis frequency</label>
            <select id="medicine-usage-period" name="period_type" class="disease-control">
                <option value="weekly" @selected($periodType === 'weekly')>Weekly (Monday to Sunday)</option>
                <option value="monthly" @selected($periodType === 'monthly')>Monthly (calendar month)</option>
            </select>
        </div>
        <div>
            <label for="medicine-usage-history">History periods</label>
            <input id="medicine-usage-history" class="disease-control" type="number" name="periods" min="4" max="52" value="{{ request('periods', config('disease_analysis.history_periods', 12)) }}">
        </div>
        <button class="disease-button" type="submit" @disabled($medicines->isEmpty())>Analyze usage</button>
    </form>
    @include('shared.empty_result', ['resultKey' => 'medicine-history', 'recordCount' => $usageHistoryCount])

    @if($usage && $selectedMedicine && ($usage['historical'] ?? false))
        @include('disease_reports._historical_usage')
    @elseif($usage && $selectedMedicine)
        <p class="disease-muted"><strong>{{ $selectedMedicine->medicine_name }}{{ $selectedMedicine->dosage ? ' '.$selectedMedicine->dosage : '' }}{{ $selectedMedicine->dosage_form ? ' ('.$selectedMedicine->dosage_form.')' : '' }}</strong> · Quantities in {{ $usageUnit }}. {{ $usageIsArea ? 'Includes dispensing recorded for '.$selectedBarangay.' within your RHU.' : ($usageIsPersonal ? 'Only dispensing recorded by your account is included.' : 'Patient dispensing recorded across your RHU is included.') }} Each medicine is analyzed separately.</p>
        <div class="disease-stats">
            <div class="disease-stat disease-stat-success">
                <span class="disease-muted">Recorded dispensing in history</span>
                <strong>{{ number_format($usage['total_quantity']) }} {{ $usageUnit }}</strong>
                {{ number_format($usage['total_transactions']) }} dispensing {{ $usage['total_transactions'] === 1 ? 'transaction' : 'transactions' }}
            </div>
            <div class="disease-stat disease-stat-info">
                <span class="disease-muted">Latest completed {{ $usagePeriod }}</span>
                <strong>{{ $usage['current_quantity'] === null ? 'No history' : number_format($usage['current_quantity']).' '.$usageUnit }}</strong>
                @if($usage['change_status'] === 'comparable' && $usage['change_percent'] !== null)
                    {{ $usage['change_percent'] > 0 ? '+' : '' }}{{ number_format($usage['change_percent'], 1) }}% from the previous {{ $usagePeriod }}
                @elseif($usage['change_status'] === 'no_baseline')
                    No percentage comparison: the previous {{ $usagePeriod }} recorded zero.
                @else
                    A previous period is needed for comparison.
                @endif
            </div>
            <div class="disease-stat disease-stat-purple">
                <span class="disease-muted">Demand direction</span>
                <strong>{{ $usageDirectionLabels[$usage['direction']] ?? 'Need more history' }}</strong>
                @if($usage['status'] === 'ready' && $usage['fit'])
                    Based on {{ $usage['observations'] }} completed {{ $usagePeriod }}s of recorded dispensing
                @else
                    {{ $usage['observations'] }} of at least 4 completed periods available
                @endif
            </div>
        </div>

        @if($usage['status'] === 'no_history')
            <p class="disease-muted" role="status">No completed-period dispensing history is available for this medicine in this scope. Record patient dispensing to begin building a trend.</p>
        @elseif($usage['status'] === 'insufficient_history')
            <p class="disease-muted" role="status">The recorded quantities are shown below. A demand projection needs at least four completed periods from the first recorded dispensing.</p>
        @endif

        <div class="disease-chart"><canvas id="medicineUsageChart" role="img" aria-label="Recorded medicine dispensing and expected demand; values are available in the tables below"></canvas></div>
        <p class="disease-muted">Training uses completed calendar periods. The current partial {{ $usagePeriod }} is excluded; its projection estimates the entire {{ $usagePeriod }}. Zero means no recorded dispensing after the first recorded transaction. Missing entries and historical stockouts cannot be identified from these quantities alone.</p>

        <h3 class="disease-subheading">Expected medicine demand</h3>
        <p class="disease-muted"><strong>{{ $selectedMedicine->medicine_name }}{{ $selectedMedicine->dosage ? ' '.$selectedMedicine->dosage : '' }}{{ $selectedMedicine->dosage_form ? ' ('.$selectedMedicine->dosage_form.')' : '' }}</strong><br>Projected quantities in {{ $usageUnit }} for each {{ $usagePeriod }}.</p>
        <div class="disease-scroll" tabindex="0" role="region" aria-label="Medicine demand projections">
            <table class="disease-table" style="min-width:520px;">
                <thead><tr><th scope="col">Projection period</th><th scope="col">Expected dispensing ({{ $usageUnit }})</th></tr></thead>
                <tbody>
                    @foreach($usage['forecast'] as $period)
                        <tr><td>{{ $period['start'] }} to {{ $period['end'] }}{{ $loop->first ? ' (current period)' : '' }}</td><td>{{ $period['quantity'] === null ? 'Need more history' : number_format($period['quantity'], 1) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="disease-row" style="margin-top:20px;align-items:stretch;">
            <div class="disease-stat" style="flex:1 1 240px;">
                <span class="disease-muted">Usable RHU stock today</span>
                <strong>{{ number_format($usage['usable_stock']) }} {{ $usageUnit }}</strong>
                @if($usage['usable_stock'] <= 0)
                    <span class="disease-badge disease-badge-warning">No usable RHU stock</span>
                @else
                    Excludes expired and deleted batches.
                @endif
            </div>
            @if(!$usageIsPersonal && $usage['suggested_replenishment'] !== null)
                <div class="disease-stat disease-stat-info" style="flex:1 1 240px;">
                    <span class="disease-muted">Estimated additional supply for 4 {{ $periodType === 'monthly' ? 'months' : 'weeks' }}</span>
                    <strong>{{ number_format($usage['suggested_replenishment']) }} {{ $usageUnit }}</strong>
                    Suggested quantity to review when preparing a supply request.
                </div>
            @endif
        </div>
        <p class="disease-muted">{{ $usageIsArea || $usageIsPersonal ? 'RHU stock is shared facility context. This local trend does not calculate a facility shortage.' : 'The additional supply estimate is a planning snapshot. Review delivery time, safety stock, future receipts and batches expiring during the forecast before making a request.' }} Lower recorded dispensing may reflect unavailable stock or missing records.</p>

        <div style="margin:20px 0;">
            <h3 class="disease-subheading">Associated conditions</h3>
            <p class="disease-muted">These are medicine associations. Dispensed quantities do not establish disease case counts or declare outbreaks.</p>
            <div class="disease-row" style="gap:8px;">
                @forelse($usage['related_diseases'] as $related)
                    <span class="disease-badge disease-badge-neutral">{{ $related['name'] }}</span>
                @empty
                    <span class="disease-muted">No condition associations have been configured for this medicine.</span>
                @endforelse
            </div>
        </div>

        <details style="margin-top:20px;">
            <summary>About these estimates</summary>
            <p class="disease-muted">Expected demand follows the recent pattern in recorded dispensing. At least four completed periods are needed. Estimates help with planning and may change when stock availability, recording practices or local needs change.</p>
        </details>
        <details style="margin-top:16px;">
            <summary>Recorded quantities by completed period</summary>
            <div class="disease-scroll" tabindex="0" role="region" aria-label="Recorded medicine dispensing history" style="margin-top:16px;">
                <table class="disease-table" style="min-width:600px;">
                    <thead><tr><th scope="col">Completed period</th><th scope="col">Dispensed ({{ $usageUnit }})</th><th scope="col">Transactions</th></tr></thead>
                    <tbody>
                        @foreach($usage['history'] as $period)
                            <tr><td>{{ $period['start'] }} to {{ $period['end'] }}</td><td>{{ $period['quantity'] === null ? 'Before first recorded dispensing' : number_format($period['quantity']) }}</td><td>{{ $period['quantity'] === null ? '—' : number_format($period['transactions']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
        <script>
        (() => {
            if (typeof Chart === 'undefined') return;
            const usage = @json($usage);
            const unit = @json($usageUnit);
            const history = usage.history;
            const future = usage.forecast;
            const fitted = history.map(() => null);
            if (usage.fit) {
                const offset = history.length - usage.fit.fitted.length;
                usage.fit.fitted.forEach((value, index) => { fitted[offset + index] = Math.max(0, value); });
            }
            const projection = history.map(() => null).concat(future.map(period => period.quantity));
            if (usage.status === 'ready' && history.length) projection[history.length - 1] = fitted[history.length - 1];
            new Chart(document.getElementById('medicineUsageChart'), {
                type: 'line',
                data: {
                    labels: history.concat(future).map(period => period.start),
                    datasets: [
                        {label: 'Recorded dispensing', data: history.map(period => period.quantity).concat(future.map(() => null)), borderColor: '#0f766e', backgroundColor: '#0f766e', spanGaps: false, tension: 0},
                        {label: 'Expected dispensing', data: projection, borderColor: '#2563eb', backgroundColor: '#2563eb', borderDash: [6, 4], spanGaps: false, tension: 0}
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: {mode: 'index', intersect: false},
                    scales: {
                        y: {beginAtZero: true, title: {display: true, text: 'Dispensed quantity (' + unit + ')'}},
                        x: {title: {display: true, text: 'Calendar period start'}}
                    }
                }
            });
        })();
        </script>
    @else
        <p class="disease-muted" role="status">No dispensable medicines are available for consumption analysis yet.</p>
    @endif
</details>
