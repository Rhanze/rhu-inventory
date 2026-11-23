@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    <style>
        .stock-log-filters { min-width: 0; background: #fff; border: 1px solid #d6e5df; border-radius: 16px; padding: 18px 20px; }
        .stock-log-fields { display: flex; flex-wrap: wrap; gap: 12px; }
        .stock-log-fields label { display: grid; gap: 6px; flex: 1 1 12rem; min-width: 0; color: #334155; font-weight: 600; }
        .stock-log-fields .stock-log-search { flex: 2 1 18rem; }
        .stock-log-fields :is(input, select) {
            box-sizing: border-box; width: 100%; min-width: 0; min-height: 44px;
            padding: 8px 12px; font: inherit; background: #fff; color: #0f172a;
            border: 1px solid #a6b7af; border-radius: 8px; text-overflow: ellipsis;
        }
        .stock-log-options { margin-top: 12px; }
        .stock-log-options summary { width: fit-content; padding: 6px 0; cursor: pointer; font-weight: 600; color: #0f5c4b; }
        .stock-log-options .stock-log-fields { padding: 8px 0 4px; }
        .stock-log-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 10px; }
        .stock-log-actions button { min-height: 44px; padding: 8px 16px; border: 0; border-radius: 8px; background: #258663; color: #fff; cursor: pointer; font: inherit; font-weight: 600; }
        .stock-log-filters a, .stock-log-results a { color: #0f5c4b; text-decoration: underline; }
        .stock-log-filters :is(input, select, button, a, summary):focus-visible { outline: 3px solid #0f766e; outline-offset: 2px; }
        .stock-log-results { margin: 0; color: #334155; }
        .stock-log-movements { min-width: 0; }
        .stock-log-movements > p:not(.stock-movement-empty) { margin: 0 0 12px; color: #475569; }
    </style>

    {{-- Header Card --}}
    <div class="no-print page-heading" style="
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(12px);
        border-radius: 20px;
        padding: 24px 28px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    ">
        <div class="page-heading-main" style="display:flex; align-items:center; gap:24px; flex-wrap:wrap; min-width:0; max-width:100%;">
            <div class="page-heading-title">
                <h1 style="font-size:1.5rem; font-weight:700; color:#0f172a; margin:0;">Stock Logs</h1>
            </div>

            @include('inventory._navigation', ['activeInventoryTab' => 'logs'])

        </div>

        {{-- Actions --}}
        <div class="page-heading-actions" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">

            {{-- EXPORT BUTTON — opens inline modal --}}
            <button type="button" onclick="openStockLogsExportModal()" style="
                display:inline-flex; align-items:center; gap:6px;
                font-size:1.0625rem; font-weight:600;
                padding:9px 18px; border-radius:10px;
                background:#42ad8a; color:#ffffff;
                border:none; cursor:pointer; transition: background 0.2s;
                white-space:nowrap;
            " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Export
            </button>
            @include('imports._link', ['section' => session('health_unit_id') ? 'stock_logs' : 'shipments', 'fallback' => true])
        </div>
    </div>

    @php
        $stockLogFacility = (string) request('rhu_filter');
        $stockLogFacilityLabel = session('health_unit_id') !== null ? 'Your RHU' : match ($stockLogFacility) {
            '' => 'All RHUs',
            'all' => 'All facilities including PHO',
            'pho' => 'Provincial Health Office',
            default => $healthUnits->firstWhere('id', $stockLogFacility)?->name ?? 'Selected facility',
        };
    @endphp
    @include('stock_logs._filters')
    @include('shared.empty_result', ['resultKey' => 'history', 'recordCount' => $stockLogs->total()])
    <p class="stock-log-results">
        <strong>{{ number_format($stockLogs->total()) }} {{ Str::plural('movement', $stockLogs->total()) }}</strong>
        &middot; {{ $stockLogFacilityLabel }} &middot; {{ $historyRange->label() }}
        @if(request()->filled('inventory_id'))
            &middot; Inventory batch #{{ request('inventory_id') }}
        @endif
        @if(request('source') === 'stock_logs')
            &middot; <a href="{{ route('stock_logs.index', ['evidence' => 1] + request()->except('source', 'page')) }}">Include additional movement records</a>
        @endif
    </p>

    <section class="stock-log-movements" aria-label="Stock movements">
        @if($showEvidence && request()->filled('record_kind'))
            <p>{{ request('record_kind') === 'allocation' ? 'Stock issued for allocations' : 'Stock issued through dispensing' }}. A single record can include several batches.</p>
        @endif
        @include('stock_logs._movement_table', ['movements' => $stockLogs])
    </section>

    {{-- Pagination --}}
    @if ($stockLogs->hasPages())
        <div class="no-print" style="
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(8px);
            border-radius: 16px;
            padding: 16px 24px;
            border: 1px solid rgba(255,255,255,0.4);
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        ">
            @if ($stockLogs->onFirstPage())
                <span style="font-size:1.0625rem; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: default; user-select: none;">← Previous</span>
            @else
                <a href="{{ $stockLogs->previousPageUrl() }}" style="font-size:1.0625rem; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                    ← Previous
                </a>
            @endif

            @foreach ($stockLogs->getUrlRange(1, $stockLogs->lastPage()) as $page => $url)
                @if ($page == $stockLogs->currentPage())
                    <span style="font-size:1.0625rem; font-weight: 600; padding: 8px 14px; border-radius: 8px; background: #42ad8a; color: #ffffff; border: 1px solid #42ad8a; cursor: default;">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" style="font-size:1.0625rem; font-weight: 500; padding: 8px 14px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                       onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                        {{ $page }}
                    </a>
                @endif
            @endforeach

            @if ($stockLogs->hasMorePages())
                <a href="{{ $stockLogs->nextPageUrl() }}" style="font-size:1.0625rem; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                    Next →
                </a>
            @else
                <span style="font-size:1.0625rem; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: default; user-select: none;">Next →</span>
            @endif
        </div>
    @endif

</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- INLINE EXPORT MODAL --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div id="stockLogsExportModal"
     onclick="if(event.target === this) closeStockLogsExportModal()"
     style="
        display:none; position:fixed; inset:0; z-index:99999;
        background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);
        align-items:center; justify-content:center; padding:20px;
     ">
    <div style="
        background:#ffffff; border-radius:20px;
        width:100%; max-width:520px;
        display:flex; flex-direction:column;
        box-shadow: 0 24px 60px rgba(0,0,0,0.25);
        max-height:90vh; overflow-y:auto;
    ">
        <div style="padding:22px 26px 14px; border-bottom:1px solid #f1f5f9;">
            <h3 style="font-size:1.1875rem; font-weight:700; color:#0f172a; margin:0 0 4px 0;">
                Export Stock Logs
            </h3>
            <p style="font-size:1.0625rem; color:#64748b; margin:0;">
                Choose what to include, then pick an action.
            </p>
        </div>

        <div style="padding:20px 26px;">

            {{-- 1. PROGRAM CATEGORY --}}
            <div style="margin-bottom:22px;">
                <label for="category-stockLogsExportModal"
                       style="font-size:1.0625rem; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Program Category
                </label>
                <select id="category-stockLogsExportModal"
                        style="
                            width:100%; min-height:2.75rem;height:auto;
                            padding:0 14px; font-size:1.0625rem;
                            border:2px solid #bec6cf; border-radius:10px;
                            outline:none; box-sizing:border-box;
                            background:#ffffff; color:#0f172a; appearance:auto; cursor:pointer;
                        "
                        onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                        onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <option value="" {{ !request('category') ? 'selected' : '' }}>All Categories</option>
                    <option value="medicine"                 {{ request('category') == 'medicine'                 ? 'selected' : '' }}>Medicine</option>
                    <option value="vaccine"                  {{ request('category') == 'vaccine'                  ? 'selected' : '' }}>Vaccine</option>
                    <option value="family_planning"          {{ request('category') == 'family_planning'          ? 'selected' : '' }}>Family Planning</option>
                    <option value="family_health_nutrition"  {{ request('category') == 'family_health_nutrition'  ? 'selected' : '' }}>Family Health &amp; Nutrition</option>
                    <option value="emerging_reemerging"      {{ request('category') == 'emerging_reemerging'      ? 'selected' : '' }}>Emerging &amp; Re-emerging</option>
                    <option value="non_communicable"         {{ request('category') == 'non_communicable'         ? 'selected' : '' }}>Non-Communicable Disease</option>
                    <option value="dengue_prevention"        {{ request('category') === 'dengue_prevention'       ? 'selected' : '' }}>Dengue Prevention</option>
                    <option value="tuberculosis"             {{ request('category') === 'tuberculosis'            ? 'selected' : '' }}>Tuberculosis</option>
                </select>
            </div>

            {{-- 2. RHU (PHO admin only) --}}
            @if(is_null(session('health_unit_id')))
                <div style="margin-bottom:22px;">
                    <label for="rhu-stockLogsExportModal"
                           style="font-size:1.0625rem; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                        Health Unit (RHU)
                    </label>
                    <select id="rhu-stockLogsExportModal"
                            style="
                                width:100%; min-height:2.75rem;height:auto;
                                padding:0 14px; font-size:1.0625rem;
                                border:2px solid #bec6cf; border-radius:10px;
                                outline:none; box-sizing:border-box;
                                background:#ffffff; color:#0f172a; appearance:auto; cursor:pointer;
                            "
                            onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                            onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                        <option value="" {{ !request('rhu_filter') ? 'selected' : '' }}>All Health Units</option>
                        <option value="all" @selected(request('rhu_filter') === 'all')>All facilities including PHO</option>
                        <option value="pho" {{ request('rhu_filter') === 'pho' ? 'selected' : '' }}>Your PHO Stock Logs</option>
                        @foreach($healthUnits as $rhu)
                            <option value="{{ $rhu->id }}" {{ request('rhu_filter') == $rhu->id ? 'selected' : '' }}>
                                {{ $rhu->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- 3. MOVEMENT TYPE --}}
            <div style="margin-bottom:22px;">
                <label for="type-stockLogsExportModal"
                       style="font-size:1.0625rem; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Movement Type
                </label>
                <select id="type-stockLogsExportModal"
                        style="
                            width:100%; min-height:2.75rem;height:auto;
                            padding:0 14px; font-size:1.0625rem;
                            border:2px solid #bec6cf; border-radius:10px;
                            outline:none; box-sizing:border-box;
                            background:#ffffff; color:#0f172a; appearance:auto; cursor:pointer;
                        "
                        onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                        onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <option value=""          {{ !request('type')              ? 'selected' : '' }}>All Movements</option>
                    <option value="addition"  {{ request('type') == 'addition'  ? 'selected' : '' }}>Stock In only</option>
                    <option value="release"   {{ request('type') == 'release'   ? 'selected' : '' }}>Stock Out only</option>
                    <option value="reservation" @selected(request('type') === 'reservation')>Reservations only</option>
                    <option value="reversal" @selected(request('type') === 'reversal')>Reversals only</option>
                </select>
            </div>

            {{-- 4. DATE RANGE --}}
            <div style="margin-bottom:22px;">
                <label style="font-size:1.0625rem; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Date Range
                </label>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div>
                        <label for="dateFrom-stockLogsExportModal"
                               style="font-size:1.0625rem; font-weight:600; color:#475569; display:block; margin-bottom:4px;">
                            From
                        </label>
                        <input type="date"
                               id="dateFrom-stockLogsExportModal"
                               value="{{ request('date_from', '') }}"
                               style="
                                   width:100%; min-height:2.75rem;height:auto;
                                   padding:0 12px; font-size:1.0625rem;
                                   border:2px solid #bec6cf; border-radius:10px;
                                   outline:none; box-sizing:border-box;
                                   background:#ffffff; color:#0f172a;
                               "
                               onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                               onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    </div>
                    <div>
                        <label for="dateTo-stockLogsExportModal"
                               style="font-size:1.0625rem; font-weight:600; color:#475569; display:block; margin-bottom:4px;">
                            To
                        </label>
                        <input type="date"
                               id="dateTo-stockLogsExportModal"
                               value="{{ request('date_to', '') }}"
                               style="
                                   width:100%; min-height:2.75rem;height:auto;
                                   padding:0 12px; font-size:1.0625rem;
                                   border:2px solid #bec6cf; border-radius:10px;
                                   outline:none; box-sizing:border-box;
                                   background:#ffffff; color:#0f172a;
                               "
                               onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                               onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    </div>
                </div>
                <p style="font-size:1.0625rem; color:#64748b; margin:6px 0 0 0;">
                    Leave both blank to include all dates.
                </p>
            </div>

            {{-- 5. BATCH NUMBER(S) --}}
            <div>
                <label for="batchInput-stockLogsExportModal"
                       style="font-size:1.0625rem; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Batch Number(s)
                </label>
                <input type="text"
                       id="batchInput-stockLogsExportModal"
                       placeholder="e.g. BT-001, BT-002, 1-8"
                       value="{{ request('batch_input', '') }}"
                       style="
                           width:100%; min-height:2.75rem;height:auto;
                           padding:0 14px; font-size:1.0625rem;
                           border:2px solid #bec6cf; border-radius:10px;
                           outline:none; box-sizing:border-box;
                           background:#ffffff; color:#0f172a;
                       "
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                <p style="font-size:1.0625rem; color:#64748b; margin:6px 0 0 0;">
                    Separate multiple batch numbers with commas. Leave blank to include all batches.
                </p>
            </div>
        </div>

        <div style="
            padding:16px 26px 20px;
            border-top:1px solid #f1f5f9;
            display:flex; gap:10px; justify-content:flex-end;
            background:#fafbfc;
            border-radius: 0 0 20px 20px;
        ">
            <button type="button"
                    onclick="closeStockLogsExportModal()"
                    style="
                        font-size:1.0625rem; font-weight:600;
                        padding:10px 18px; border-radius:10px;
                        background:#ffffff; color:#334155;
                        border:1px solid #e2e8f0; cursor:pointer;
                    "
                    onmouseover="this.style.background='#f1f5f9'"
                    onmouseout="this.style.background='#ffffff'">
                Cancel
            </button>

            <button type="button"
                    onclick="runStockLogsExport('print')"
                    style="
                        display:inline-flex; align-items:center; gap:6px;
                        font-size:1.0625rem; font-weight:600;
                        padding:10px 18px; border-radius:10px;
                        background:#ffffff; color:#334155;
                        border:1px solid #e2e8f0; cursor:pointer;
                    "
                    onmouseover="this.style.background='#f1f5f9'"
                    onmouseout="this.style.background='#ffffff'">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                Print
            </button>

            <button type="button"
                    onclick="runStockLogsExport('csv')"
                    style="
                        display:inline-flex; align-items:center; gap:6px;
                        font-size:1.0625rem; font-weight:600;
                        padding:10px 18px; border-radius:10px;
                        background:#42ad8a; color:#ffffff;
                        border:none; cursor:pointer;
                    "
                    onmouseover="this.style.background='#369f7d'"
                    onmouseout="this.style.background='#42ad8a'">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Export CSV
            </button>
        </div>
    </div>
</div>

<script>
function openStockLogsExportModal() {
    document.getElementById('stockLogsExportModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeStockLogsExportModal() {
    document.getElementById('stockLogsExportModal').style.display = 'none';
    document.body.style.overflow = '';
}
function runStockLogsExport(mode) {
    const categorySelect = document.getElementById('category-stockLogsExportModal');
    const rhuSelect      = document.getElementById('rhu-stockLogsExportModal');
    const typeSelect     = document.getElementById('type-stockLogsExportModal');
    const dateFrom       = document.getElementById('dateFrom-stockLogsExportModal');
    const dateTo         = document.getElementById('dateTo-stockLogsExportModal');
    const batchInput     = document.getElementById('batchInput-stockLogsExportModal');

    const categoryVal = categorySelect ? categorySelect.value : '';
    const rhuVal      = rhuSelect      ? rhuSelect.value      : '';
    const typeVal     = typeSelect     ? typeSelect.value     : '';
    const dateFromVal = dateFrom       ? dateFrom.value       : '';
    const dateToVal   = dateTo         ? dateTo.value         : '';
    const batchVal    = batchInput     ? batchInput.value.trim() : '';

    const baseUrl = mode === 'csv'
        ? '{{ route('stock_logs.export') }}'
        : '{{ route('stock_logs.index') }}';

    const url = new URL(baseUrl, window.location.origin);
    const params = new URLSearchParams(window.location.search);
    params.delete('page');
    params.delete('print');
    params.delete('category');
    params.delete('rhu_filter');
    params.delete('type');
    params.delete('date_from');
    params.delete('date_to');
    params.delete('batch_input');

    if (categoryVal)     params.set('category', categoryVal);
    if (rhuVal)          params.set('rhu_filter', rhuVal);
    if (typeVal)         params.set('type', typeVal);
    if (dateFromVal)     params.set('date_from', dateFromVal);
    if (dateToVal)       params.set('date_to', dateToVal);
    if (batchVal !== '') params.set('batch_input', batchVal);
    if (mode === 'print') params.set('print', '1');

    url.search = params.toString();
    window.location.href = url.toString();
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('stockLogsExportModal');
        if (modal && modal.style.display === 'flex') {
            closeStockLogsExportModal();
        }
    }
});
</script>
@endsection
