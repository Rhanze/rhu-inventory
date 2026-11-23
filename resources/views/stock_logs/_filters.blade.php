@php
    $stockLogStatus = request('status', request('type') === 'reversal' ? 'reversal' : 'active');
    $additionalFilters = ['date_from', 'date_to', 'year', 'type', 'medicine_id', 'batch_no', 'evidence'];
    $showStockLogOptions = request()->anyFilled($additionalFilters) || $stockLogStatus !== 'active'
        || $errors->hasAny([...$additionalFilters, 'status']);
    $movementRecordsRequired = request()->anyFilled(['action', 'record_kind'])
        || in_array(request('type'), ['reservation', 'reversal'], true)
        || $stockLogStatus !== 'active';
@endphp
<section class="stock-log-filters no-print" aria-label="Stock log filters">
    <p style="margin:0 0 14px;color:#475569;">Find individual receipts, issues, transfers, and corrections.</p>
    <form method="GET" action="{{ route('stock_logs.index') }}" data-empty-action="history">
        @foreach(request()->only('source', 'action', 'record_kind', 'movement_type', 'category', 'batch_input', 'inventory_id') as $name => $value)
            @if(is_scalar($value) && (string) $value !== '')
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endif
        @endforeach
        <div class="stock-log-fields">
            <label class="stock-log-search">Search
                <input type="search" name="search" maxlength="200" value="{{ request('search') }}" placeholder="Medicine, batch, or staff name">
            </label>
            @if(is_null(session('health_unit_id')))
                <label>Facility
                    <select name="rhu_filter">
                        <option value="">All RHUs</option>
                        <option value="all" @selected($stockLogFacility === 'all')>All facilities including PHO</option>
                        <option value="pho" @selected($stockLogFacility === 'pho')>Provincial Health Office</option>
                        @if(!in_array($stockLogFacility, ['', 'all', 'pho'], true) && !$healthUnits->contains('id', $stockLogFacility))
                            <option value="{{ $stockLogFacility }}" selected>{{ $stockLogFacilityLabel }}</option>
                        @endif
                        @foreach($healthUnits as $unit)
                            <option value="{{ $unit->id }}" @selected($stockLogFacility === (string) $unit->id)>{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>
        <details class="stock-log-options" @if($showStockLogOptions) open @endif>
            <summary>More filters</summary>
            <div class="stock-log-fields">
                <label>Start date
                    <input type="date" name="date_from" min="1900-01-01" value="{{ request('date_from') }}">
                </label>
                <label>End date
                    <input type="date" name="date_to" min="1900-01-01" value="{{ request('date_to') }}">
                </label>
                <label>Year (optional)
                    <input type="number" name="year" min="1900" max="9999" step="1" value="{{ request('year') }}" placeholder="All years">
                </label>
                <label>Movement
                    <select name="type">
                        <option value="">All movements</option>
                        @foreach(['addition' => 'Stock in', 'release' => 'Stock out', 'reservation' => 'Stock reserved'] as $type => $label)
                            <option value="{{ $type }}" @selected(request('type') === $type)>{{ $label }}</option>
                        @endforeach
                        @if(request('type') === 'reversal')
                            <option value="reversal" selected>Reversal entries</option>
                        @endif
                    </select>
                </label>
                <label>Status
                    <select name="status">
                        @foreach(['active' => 'Active only', 'all' => 'All statuses', 'cancelled' => 'Cancelled transfers', 'reversed' => 'Reversed originals', 'reversal' => 'Reversal entries'] as $status => $label)
                            <option value="{{ $status }}" @selected($stockLogStatus === $status)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Medicine
                    <select name="medicine_id">
                        <option value="">All medicines</option>
                        @foreach($medicines as $medicine)
                            <option value="{{ $medicine->id }}" @selected((string) request('medicine_id') === (string) $medicine->id)>{{ $medicine->medicine_name }}{{ $medicine->dosage ? ' · '.$medicine->dosage : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Exact batch number
                    <input name="batch_no" maxlength="100" value="{{ request('batch_no') }}">
                </label>
                @if(request('source') !== 'stock_logs' && !$movementRecordsRequired)
                    <label>Records to include
                        <select name="evidence">
                            <option value="">Standard stock logs</option>
                            <option value="1" @selected(request()->boolean('evidence'))>Include additional movement records</option>
                        </select>
                    </label>
                @elseif(request()->boolean('evidence'))
                    <input type="hidden" name="evidence" value="1">
                @endif
            </div>
        </details>
        <div class="stock-log-actions">
            <button type="submit">Apply filters</button>
            @if(request()->anyFilled(['search', 'rhu_filter', 'status', 'source', 'action', 'record_kind', 'movement_type', 'category', 'batch_input', 'inventory_id', ...$additionalFilters]))
                <a href="{{ route('stock_logs.index') }}">Clear filters</a>
            @endif
        </div>
    </form>
    @foreach(['search', 'rhu_filter', 'status', ...$additionalFilters] as $field)
        @error($field)<p role="alert" style="margin:8px 0 0;color:#b91c1c;">{{ $message }}</p>@enderror
    @endforeach
</section>
