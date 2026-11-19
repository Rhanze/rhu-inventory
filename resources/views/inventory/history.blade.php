@extends('layouts.app')

@section('content')
<div class="inventory-history">
    <style>
        .inventory-history{display:flex;flex-direction:column;gap:20px;min-width:0;font-size:1.0625rem;line-height:1.5;color:#334155;padding-bottom:2rem}
        .inventory-history-card{background:rgba(255,255,255,.95);border:1px solid #d6e5df;border-radius:18px;padding:24px;min-width:0;max-width:100%;box-sizing:border-box}
        .inventory-history h1{margin:0;color:#0f172a;font-size:1.75rem;line-height:1.3}
        .inventory-history h2{margin:0 0 8px;color:#0f172a;font-size:1.35rem;line-height:1.35}
        .inventory-history p{margin:6px 0;overflow-wrap:break-word}
        .inventory-history-nav{display:flex;flex-wrap:wrap;gap:12px;margin-top:16px}
        .inventory-history a{color:#0f766e;font-weight:600}
        .history-filter-fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;align-items:end}
        .history-filter-fields.is-facility-scoped{grid-template-columns:repeat(2,minmax(0,1fr))}
        .history-date-fields{margin-top:16px}
        .history-more-filters{margin-top:18px;border-top:1px solid #e2e8f0;padding-top:12px}
        .history-more-filters>summary,.history-facilities>summary,.history-reading-help>summary{cursor:pointer;min-height:44px;box-sizing:border-box;padding:10px 0;font-weight:600;color:#0f5c4b}
        .history-more-filters .history-filter-fields{margin-top:12px}
        .history-preview-filters{margin-top:18px;padding-top:14px;border-top:1px solid #e2e8f0}
        .history-preview-filters h3{margin:0;font-size:1.0625rem;color:#334155}
        .history-period-summary,.history-metric-groups{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
        .history-period-summary{margin:16px 0}
        .history-period-summary>div,.history-metric-group{padding:12px 16px;border-radius:10px}
        .history-period-summary dt{font-weight:650;color:#334155}
        .history-period-summary dd,.history-metric-list,.history-metric-list dd{margin:0}
        .history-balance{background:#edf6f1}
        .history-activity{background:#eef3fa}
        .history-summary-tools{display:flex;flex-wrap:wrap;align-items:baseline;justify-content:space-between;column-gap:16px}
        .history-summary-tools p{margin:0}
        .history-medicine,.history-facility{border:1px solid #d6e5df;border-radius:12px;padding:16px;overflow-wrap:anywhere}
        .history-medicine+.history-medicine,.history-facility+.history-facility{margin-top:12px}
        .history-medicines{margin-top:16px}
        .history-medicine-heading{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:baseline;gap:2px 12px;margin-bottom:10px}
        .history-medicine-heading h3,.history-facility h4,.inventory-history .history-metric-heading{margin:0;color:#0f172a;font-size:1.0625rem;font-weight:650}
        .history-facility>h4{margin-bottom:8px}
        .history-metric-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 12px;margin-top:6px}
        .history-metric-list dt,.history-record-count{font-size:.9375rem;color:#475569}
        .history-metric-list dd{font-size:1.25rem;line-height:1.35}
        .history-metric-list dd a,.history-record-count a{font-variant-numeric:tabular-nums}
        .history-record-count{display:block}
        .history-metric-group .history-warning{margin:6px 0 0}
        .history-facilities{margin-top:8px}
        .history-facilities[open]>summary{margin-bottom:8px}
        .history-reading-help{margin-top:12px}
        .history-preview-count{font-weight:400;color:#64748b;margin-left:8px}
        .inventory-history label{display:flex;flex-direction:column;gap:8px;min-width:0;font-weight:600}
        .inventory-history-form label>input,.inventory-history-form label>select{width:100%}
        .inventory-history-form select{padding-right:2rem;text-overflow:ellipsis}
        .inventory-history input,.inventory-history select,.inventory-history button,.inventory-history-clear{font:inherit;box-sizing:border-box;max-width:100%;min-width:0;min-height:48px;padding:10px 12px;border:1px solid #a6b7af;border-radius:10px;background:#fff;color:#0f172a}
        .inventory-history button{background:#258663;color:#fff;border-color:#258663;font-weight:600;cursor:pointer}
        .inventory-history-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding-top:18px}
        .inventory-history-clear{display:inline-flex;align-items:center;text-decoration:none}
        .inventory-history-scroll{overflow-x:auto;max-width:100%;min-width:0;overscroll-behavior-x:contain;margin-top:16px}
        .inventory-history table{width:100%;min-width:68rem;border-collapse:collapse;font:inherit}
        .inventory-history th,.inventory-history td{padding:14px;vertical-align:top;text-align:left;border-bottom:1px solid #e2e8f0;overflow-wrap:break-word}
        .inventory-history th{font-size:1rem;background:#f8fafc;color:#334155}
        .inventory-history td{min-width:8rem;max-width:22rem}
        .inventory-history td small{display:block;margin-top:6px;font-size:1rem;color:#475569}
        .inventory-history .history-count{font-weight:600;font-variant-numeric:tabular-nums}
        .inventory-history .history-medicines a,.inventory-history .history-note a{color:#0f5c4b;text-decoration:underline;text-underline-offset:3px}
        .inventory-history-status{display:inline-block;padding:3px 8px;border:1px solid #cbd5e1;border-radius:8px;font-size:1rem;color:#475569}
        .inventory-history .history-warning{color:#92400e;font-size:1rem}
        .inventory-history .history-note{font-size:1rem;color:#475569}
        .inventory-history .history-period-field[hidden]{display:none}
        .inventory-history :is(a,input,select,button,summary,[tabindex]):focus-visible{outline:3px solid #0f766e;outline-offset:3px}
        @media(max-width:1100px){.history-filter-fields{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:700px){.history-filter-fields,.history-filter-fields.is-facility-scoped{grid-template-columns:minmax(0,1fr)}}
        @media(max-width:600px){.inventory-history-card{padding:18px}.inventory-history-actions>*{flex:1 1 10rem;justify-content:center}.history-period-summary,.history-metric-groups{grid-template-columns:minmax(0,1fr);gap:8px}.history-medicine,.history-facility,.history-period-summary>div,.history-metric-group{padding:10px 12px}}
        @media print{
            .history-preview-filters,.history-movement-preview:not([open]),.history-facilities>summary,.history-reading-help>summary{display:none}
            .inventory-history-card{padding:12px;background:#fff;box-shadow:none}
            .inventory-history-scroll{overflow:visible}
            .inventory-history table{min-width:0;table-layout:fixed;font-size:.8rem}
            .inventory-history th,.inventory-history td{min-width:0;max-width:none;padding:6px;font-size:inherit}
            .inventory-history td small{font-size:inherit}
            .history-period-summary>div,.history-metric-group{padding:6px;background:#fff}
            .history-facility,.history-metric-groups{break-inside:avoid}
        }
    </style>

    <section class="inventory-history-card page-heading" aria-labelledby="inventory-history-title">
        <div class="page-heading-main">
            <div class="page-heading-title">
                <h1 id="inventory-history-title">Inventory history</h1>
            </div>
            @include('inventory._navigation', ['activeInventoryTab' => 'history', 'inventoryNavigationClass' => 'inventory-history-nav'])
        </div>
        <p class="history-note">Review stock balances and activity by medicine and facility for a period.</p>
    </section>

    <section class="inventory-history-card no-print" aria-label="Filter inventory history">
        @php
            $historyExtraFiltersActive = request()->filled('batch_no') || request()->filled('year');
            $movementFiltersActive = request()->filled('search') || request()->filled('action')
                || request()->filled('status') || request()->filled('type');
        @endphp
        <form method="GET" action="{{ route('inventory.index') }}" class="inventory-history-form" id="inventory-history-filters">
            <input type="hidden" name="view" value="history">
            @foreach(request()->only('search', 'action', 'status', 'type') as $name => $value)
                @if(is_scalar($value) && $value !== '')<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
            @endforeach
            <div class="history-filter-fields {{ session('health_unit_id') !== null ? 'is-facility-scoped' : '' }}">
                <label>Period
                    <select name="history_period" id="inventory-history-period">
                        @foreach(['all' => 'All saved dates', 'today' => 'Today', 'this_month' => 'This month', 'last_month' => 'Last month', 'day' => 'Single date', 'range' => 'Custom dates / year'] as $value => $label)
                            <option value="{{ $value }}" @selected($historyPeriod === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Medicine
                    @php
                        $selectedHistoryMedicine = $historyMedicines->firstWhere('id', (int) request('medicine_id'));
                    @endphp
                    <select name="medicine_id" title="{{ $selectedHistoryMedicine ? $selectedHistoryMedicine->medicine_name.($selectedHistoryMedicine->dosage ? ' - '.$selectedHistoryMedicine->dosage : '') : 'All medicines' }}">
                        <option value="">All medicines</option>
                        @foreach($historyMedicines as $medicine)
                            <option value="{{ $medicine->id }}" @selected((string) request('medicine_id') === (string) $medicine->id)>{{ $medicine->medicine_name }}{{ $medicine->dosage ? ' - '.$medicine->dosage : '' }}</option>
                        @endforeach
                    </select>
                </label>
                @if(is_null(session('health_unit_id')))
                    <label>Facility
                        <select name="rhu_filter">
                            <option value="" @selected(!request('rhu_filter') || request('rhu_filter') === 'all')>All facilities</option>
                            <option value="pho" @selected(request('rhu_filter') === 'pho')>Provincial Health Office</option>
                            @foreach($healthUnits as $unit)
                                <option value="{{ $unit->id }}" @selected((string) request('rhu_filter') === (string) $unit->id)>{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            </div>
            <div class="history-filter-fields history-date-fields history-period-field" data-history-period="day" @if($historyPeriod !== 'day') hidden @endif>
                <label>Date
                    <input type="date" name="on_date" min="1900-01-01" max="{{ today()->toDateString() }}" value="{{ request('on_date', today()->toDateString()) }}" @disabled($historyPeriod !== 'day')>
                </label>
            </div>
            <div class="history-filter-fields history-date-fields history-period-field" data-history-period="range" @if($historyPeriod !== 'range') hidden @endif>
                <label>Start date
                    <input type="date" name="date_from" min="1900-01-01" max="{{ today()->toDateString() }}" value="{{ request('date_from') }}" @disabled($historyPeriod !== 'range')>
                </label>
                <label>End date
                    <input type="date" name="date_to" min="1900-01-01" max="{{ today()->toDateString() }}" value="{{ request('date_to') }}" @disabled($historyPeriod !== 'range')>
                </label>
            </div>
            <details class="history-more-filters" id="inventory-history-more-filters" @if($historyExtraFiltersActive) open @endif>
                <summary>More filters</summary>
                <div class="history-filter-fields">
                    <label>Exact batch
                        <input type="text" name="batch_no" maxlength="100" value="{{ request('batch_no') }}" placeholder="Batch number">
                    </label>
                    <label class="history-period-field" data-history-period="range" @if($historyPeriod !== 'range') hidden @endif>Year (optional)
                        <input type="number" name="year" min="1900" max="{{ today()->year }}" step="1" value="{{ request('year') }}" placeholder="All years" @disabled($historyPeriod !== 'range')>
                    </label>
                </div>
                <p class="history-note">Batch applies to totals and individual movements. Choose Custom dates / year to filter by year.</p>
            </details>
            <div class="inventory-history-actions">
                <button type="submit">Apply filters</button>
                <a class="inventory-history-clear" href="{{ route('inventory.index', ['view' => 'history']) }}">Clear filters</a>
            </div>
        </form>
        @foreach(['history_period','on_date','year','date_from','date_to','medicine_id','search','batch_no','type','action','status','rhu_filter'] as $field)
            @error($field)<p role="alert" style="color:#b91c1c;">{{ $message }}</p>@enderror
        @endforeach
    </section>

    <section class="inventory-history-card" aria-labelledby="stock-history-summary-title">
        @php
            $stockLogScope = array_filter([
                'rhu_filter' => session('health_unit_id') === null ? (request('rhu_filter') ?: 'all') : null,
                'batch_no' => request('batch_no'),
                'date_to' => $stockHistory['as_of'],
                'status' => 'active',
                'evidence' => 1,
            ], fn ($value) => $value !== null && $value !== '');
            $periodStockLogScope = $stockLogScope + array_filter([
                'date_from' => $historyRange->from,
                'medicine_id' => request('medicine_id'),
            ], fn ($value) => $value !== null && $value !== '');
            $closingDate = \Carbon\Carbon::parse($stockHistory['as_of'])->format('M j, Y');
            $activityDates = match (true) {
                $historyRange->from === null => 'All saved dates through '.$closingDate,
                $historyRange->from === $stockHistory['as_of'] => $closingDate,
                default => \Carbon\Carbon::parse($historyRange->from)->format('M j, Y').' to '.$closingDate,
            };
        @endphp
        <h2 id="stock-history-summary-title">Stock balances and recorded activity</h2>
        <div class="history-summary-tools history-note">
            <p><strong>{{ $unitId === null ? 'Combined totals across all facilities' : ($historyFacility?->name ?? 'Selected facility') }}</strong></p>
            <a href="{{ route('stock_logs.index', $periodStockLogScope) }}">View period in Stock Logs</a>
        </div>
        <dl class="history-period-summary">
            <div class="history-balance">
                <dt>Stock on closing date</dt>
                <dd><time datetime="{{ $stockHistory['as_of'] }}">{{ $closingDate }}</time></dd>
            </div>
            <div class="history-activity">
                <dt>Recorded activity dates</dt>
                <dd>{{ $activityDates }}</dd>
            </div>
        </dl>
        <div class="history-medicines">
            @forelse($stockSummary as $item)
                <article class="history-medicine" aria-labelledby="history-medicine-{{ $item->medicine_id }}">
                    <header class="history-medicine-heading">
                        <h3 id="history-medicine-{{ $item->medicine_id }}">{{ $item->medicine_name }}{{ $item->dosage ? ' · '.$item->dosage : '' }}</h3>
                        <span class="history-note">Unit of measure &middot; {{ $item->unit ?: 'units' }}</span>
                    </header>
                    @include('inventory._history_metrics', [
                        'metrics' => (array) $item,
                        'metricScope' => ['medicine_id' => $item->medicine_id],
                        'metricScopeLabel' => $item->medicine_name,
                    ])
                    @if($unitId === null && $item->facilities)
                        <details class="history-facilities">
                            <summary>Totals by facility ({{ count($item->facilities) }})</summary>
                            @foreach($item->facilities as $facility)
                                <div class="history-facility">
                                    <h4>{{ $facility['facility_name'] }}</h4>
                                    @include('inventory._history_metrics', [
                                        'metrics' => $facility,
                                        'metricScope' => ['rhu_filter' => $facility['health_unit_id'], 'medicine_id' => $item->medicine_id],
                                        'metricScopeLabel' => $item->medicine_name.' at '.$facility['facility_name'],
                                    ])
                                </div>
                            @endforeach
                        </details>
                    @endif
                </article>
            @empty
                <p class="history-note">No medicines match the selected facility, medicine or batch.</p>
            @endforelse
        </div>
        <details class="history-reading-help">
            <summary>How to read these totals</summary>
            <p class="history-note">Available stock excludes batches that expired before the closing date. Reserved stock is set aside for allocations.</p>
            <p class="history-note">Activity counts saved allocations and dispensing records within the displayed dates. A zero means there are no matching saved records. One record can use several batches, and older records may have missing movements.</p>
            <p class="history-note">Select a stock balance to see stock movements through the closing date, or select an activity total to see entries within the displayed dates. Totals exclude cancelled and reversed activity.</p>
        </details>
        <div style="margin-top:20px;">{{ $stockSummary->links() }}</div>
    </section>

    <details class="inventory-history-card history-movement-preview" @if($movementFiltersActive || request()->filled('page')) open @endif>
        <summary id="inventory-transactions-title" style="cursor:pointer;font-weight:700;">Individual movements <span class="history-preview-count">{{ number_format($movements->total()) }} entries</span></summary>
        <p class="history-note">Batch activity by date and staff. <a href="{{ route('stock_logs.index', array_replace($periodStockLogScope, request()->only('search', 'action', 'type', 'status'))) }}">Open Stock Logs</a></p>
        <form method="GET" action="{{ route('inventory.index') }}#inventory-transactions-title" class="inventory-history-form history-preview-filters">
            <input type="hidden" name="view" value="history">
            <input type="hidden" name="history_period" value="{{ $historyPeriod }}">
            @foreach(request()->only('rhu_filter', 'medicine_id', 'batch_no') as $name => $value)
                @if(is_scalar($value) && $value !== '')<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
            @endforeach
            @if($historyPeriod === 'day')
                <input type="hidden" name="on_date" value="{{ request('on_date', today()->toDateString()) }}">
            @elseif($historyPeriod === 'range')
                @foreach(request()->only('date_from', 'date_to', 'year') as $name => $value)
                    @if(is_scalar($value) && $value !== '')<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
                @endforeach
            @endif
            <div class="history-filter-fields">
                <label>Search movements
                    <input type="search" name="search" maxlength="200" value="{{ request('search') }}" placeholder="Staff, medicine or reference">
                </label>
                <label>Transaction
                    <select name="action">
                        <option value="">All transactions</option>
                        @foreach(['addition' => 'Stock receipts', 'dispensing' => 'Dispensing', 'allocation' => 'Allocations', 'transfer' => 'Transfers', 'adjustment' => 'Adjustments', 'reservation' => 'Reservations', 'reversal' => 'Reversals'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('action') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Preview status
                    <select name="status">
                        @foreach(['all' => 'All statuses', 'active' => 'Active', 'cancelled' => 'Cancellations', 'reversed' => 'Reversed originals', 'reversal' => 'Reversal entries'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status', request('type') === 'reversal' ? 'reversal' : 'all') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            @if(request()->filled('type'))
                <input type="hidden" name="type" value="{{ request('type') }}">
                <p class="history-note">Movement direction: {{ ['addition' => 'Receipts', 'release' => 'Releases', 'reservation' => 'Reservations', 'reversal' => 'Reversals'][request('type')] ?? request('type') }}. <a href="{{ request()->fullUrlWithoutQuery(['type', 'page']) }}#inventory-transactions-title">Clear direction</a></p>
            @endif
            <div class="inventory-history-actions">
                <button type="submit">Filter movements</button>
                @if($movementFiltersActive)<a class="inventory-history-clear" href="{{ request()->fullUrlWithoutQuery(['search', 'action', 'status', 'type', 'page']) }}#inventory-transactions-title">Clear movement filters</a>@endif
            </div>
            <p class="history-note">Movement filters apply only to these entries. Summary totals above stay the same.</p>
        </form>
        @include('stock_logs._movement_table', ['movements' => $movements])
        <div style="margin-top:20px;">{{ $movements->links() }}</div>
    </details>
</div>
<script>
(() => {
    const form = document.getElementById('inventory-history-filters');
    const period = document.getElementById('inventory-history-period');
    if (!form || !period) return;
    const updateFields = () => {
        form.querySelectorAll('[data-history-period]').forEach(field => {
            const active = field.dataset.historyPeriod === period.value;
            field.hidden = !active;
            field.querySelectorAll('input').forEach(input => { input.disabled = !active; });
        });
    };
    period.addEventListener('change', updateFields);
    form.addEventListener('reset', () => requestAnimationFrame(updateFields));
    updateFields();
    const printDetails = new Map();
    window.addEventListener('beforeprint', () => {
        document.querySelectorAll('.inventory-history .history-facilities, .inventory-history .history-reading-help').forEach(details => {
            if (!printDetails.has(details)) printDetails.set(details, details.open);
            details.open = true;
        });
    });
    window.addEventListener('afterprint', () => {
        printDetails.forEach((wasOpen, details) => { details.open = wasOpen; });
        printDetails.clear();
    });
})();
</script>
@endsection
