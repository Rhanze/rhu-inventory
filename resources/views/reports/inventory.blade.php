@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    <style>
        .table-scroll::-webkit-scrollbar { height: 10px; }
        .table-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; border: 2px solid #f1f5f9; }
        .table-scroll::-webkit-scrollbar-thumb:hover { background: #42ad8a; }
        .table-scroll { scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9; }
        @media print { .no-print { display: none !important; } }
    </style>

    {{-- ═══════ HEADER (matches Stock Logs — compact) ═══════ --}}
    <div class="no-print" style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
        display:flex; align-items:flex-end; justify-content:space-between; flex-wrap:wrap; gap:12px;
    ">
        <div style="display:flex; align-items:flex-end; gap:20px; flex-wrap:wrap;">
            <div>
                <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Reports</p>
                <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0; line-height:1.2;">Inventory Report</h2>
            </div>
            @include('reports._tabs')
        </div>

        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">

            {{-- Program Category filter — same sizing as Stock Logs --}}
            <form method="GET" action="{{ route('reports.inventory') }}" style="display:flex; align-items:center;">
                @foreach(request()->except('category', 'page') as $k => $v)
                    @if(!is_array($v)) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
                @endforeach
                <select name="category" onchange="this.form.submit()" style="
                    height:40px; padding:0 12px; font-size:14px;
                    border:2px solid #bec6cf; border-radius:10px; outline:none;
                    background:white; color:#0f172a; appearance:auto;
                    max-width:140px;
                ">
                    <option value="">All Categories</option>
                    <option value="medicine"              {{ request('category') == 'medicine'              ? 'selected' : '' }}>Medicine</option>
                    <option value="vaccine"               {{ request('category') == 'vaccine'               ? 'selected' : '' }}>Vaccine</option>
                    <option value="family_planning"       {{ request('category') == 'family_planning'       ? 'selected' : '' }}>Family Planning</option>
                    <option value="family_health_nutrition" {{ request('category') == 'family_health_nutrition' ? 'selected' : '' }}>Maternal/Child</option>
                    <option value="emerging_reemerging"    {{ request('category') == 'emerging_reemerging'    ? 'selected' : '' }}>Disease Prev.</option>
                    <option value="non_communicable"         {{ request('category') == 'non_communicable'         ? 'selected' : '' }}>NCD</option>
                    <option value="dengue_prevention"       {{ request('category') === 'dengue_prevention' ? 'selected' : '' }}>Dengue Prevention</option>
                    <option value="tuberculosis"            {{ request('category') === 'tuberculosis' ? 'selected' : '' }}>Tuberculosis</option>
                </select>
            </form>

            {{-- RHU filter (PHO admin only) --}}
            @if(is_null(session('health_unit_id')))
                <form method="GET" action="{{ route('reports.inventory') }}" style="display:flex; align-items:center;">
                    @foreach(request()->except('rhu_filter', 'page') as $k => $v)
                        @if(!is_array($v)) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
                    @endforeach
                    <select name="rhu_filter" onchange="this.form.submit()" style="
                        height:40px; padding:0 12px; font-size:14px;
                        border:2px solid #bec6cf; border-radius:10px; outline:none;
                        background:white; color:#0f172a; appearance:auto;
                        max-width:130px;
                    ">
                        <option value="">All RHUs</option>
                        @foreach($healthUnits as $rhu)
                            <option value="{{ $rhu->id }}" {{ request('rhu_filter') == $rhu->id ? 'selected' : '' }}>{{ $rhu->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            {{-- Export button — opens modal --}}
            <button type="button" onclick="openExportModal('inventoryExportModal')" style="
                display:inline-flex; align-items:center; gap:6px;
                font-size:14px; font-weight:600;
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
        </div>
    </div>

    {{-- ═══════ TABLE ═══════ --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius:20px; padding:0; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06); overflow:hidden;
    ">
        <div class="table-scroll" style="width:100%; overflow-x:auto; overflow-y:hidden; -webkit-overflow-scrolling:touch;">
            <table style="width:100%; min-width:1000px; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">#</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Batch No.</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Medicine</th>
                        @if(is_null(session('health_unit_id')))
                            <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Health Unit</th>
                        @endif
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Dosage</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Available</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Allocated</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Dispensed</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Expiration</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inventories as $inventory)
                        @php
                            $med  = $inventory->medicine;
                            $unit = $med->unit ?? null;
                            $unitLabel = $unit ? ' ' . $unit : '';

                            $expiry = $inventory->expiration_date;
                            $expiryBadge = null;
                            $daysLeft = null;
                            if ($expiry) {
                                $daysLeft = now()->startOfDay()->diffInDays($expiry->copy()->startOfDay(), false);
                                if ($daysLeft < 0) $expiryBadge = ['label' => 'Expired', 'bg' => '#FECACA', 'color' => '#991B1B', 'border' => '#FCA5A5'];
                                elseif ($daysLeft <= 90) $expiryBadge = ['label' => 'Near Expiry', 'bg' => '#FFE4CC', 'color' => '#B85C00', 'border' => '#FFB870'];
                                elseif ($daysLeft <= 180) $expiryBadge = ['label' => 'Monitor', 'bg' => '#FEF3C7', 'color' => '#92400E', 'border' => '#FCD34D'];
                                else $expiryBadge = ['label' => 'Good', 'bg' => '#DCFCE7', 'color' => '#166534', 'border' => '#86EFAC'];
                            }
                        @endphp
                        <tr style="border-bottom:1px solid #f1f5f9;"
                            onmouseover="this.style.background='#f8fafc';"
                            onmouseout="this.style.background='transparent';">
                            <td style="padding:14px 16px; color:#334155; text-align:center;">{{ $loop->iteration }}</td>
                            <td style="padding:14px 16px; color:#334155; font-weight:600;">{{ $inventory->batch_no ?? 'N/A' }}</td>
                            <td style="padding:14px 16px; font-weight:600; color:#0f172a;">{{ $med->medicine_name ?? 'N/A' }}</td>
                            @if(is_null(session('health_unit_id')))
                                <td style="padding:14px 16px; color:#334155;">{{ $inventory->healthUnit->name ?? 'N/A' }}</td>
                            @endif
                            <td style="padding:14px 16px; color:#334155;">
                                <div>{{ $med->dosage ?? '—' }}</div>
                                @if($med && $med->dosage_form)
                                    <div style="font-size:12px; color:#64748b; margin-top:2px;">{{ $med->dosage_form }}</div>
                                @endif
                            </td>
                            <td style="padding:14px 16px; color:#334155; font-weight:600;">{{ $inventory->available_stock }}{{ $unitLabel }}</td>
                            <td style="padding:14px 16px; color:#334155;">{{ $inventory->allocated_stock }}{{ $unitLabel }}</td>
                            <td style="padding:14px 16px; color:#334155;">{{ $inventory->total_dispensed ?? 0 }}{{ $unitLabel }}</td>
                            <td style="padding:14px 16px; color:#334155; white-space:nowrap;">
                                @if($expiry)
                                    <div style="font-weight:600;">{{ $expiry->format('m/d/Y') }}</div>
                                    @if($expiryBadge)
                                        <span style="display:inline-block; margin-top:4px; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:700;
                                            background:{{ $expiryBadge['bg'] }}; color:{{ $expiryBadge['color'] }}; border:1px solid {{ $expiryBadge['border'] }};">
                                            {{ $expiryBadge['label'] }}
                                        </span>
                                    @endif
                                @else
                                    <span style="color:#94a3b8;">N/A</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="@php $cols = 8; if (is_null(session('health_unit_id'))) $cols += 1; echo $cols; @endphp"
                                style="padding:40px 20px; text-align:center; color:#64748b;">
                                No inventory records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- EXPORT MODAL — RHU + Program Category + Batch Number(s) + Expiry --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div id="inventoryExportModal"
     onclick="if(event.target === this) closeExportModal('inventoryExportModal')"
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
        {{-- Header --}}
        <div style="padding:22px 26px 14px; border-bottom:1px solid #f1f5f9;">
            <h3 style="font-size:19px; font-weight:700; color:#0f172a; margin:0 0 4px 0;">
                Export Inventory Report
            </h3>
            <p style="font-size:13px; color:#64748b; margin:0;">
                Choose what to include, then pick an action.
            </p>
        </div>

        {{-- Body --}}
        <div style="padding:20px 26px;">

            {{-- RHU (PHO admin only) --}}
            @if(is_null(session('health_unit_id')))
                <div style="margin-bottom:22px;">
                    <label for="rhu-inventoryExportModal"
                           style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                        Health Unit (RHU)
                    </label>
                    <select id="rhu-inventoryExportModal"
                            style="
                                width:100%; height:44px;
                                padding:0 14px; font-size:14px;
                                border:2px solid #bec6cf; border-radius:10px;
                                outline:none; box-sizing:border-box;
                                background:#ffffff; color:#0f172a; appearance:auto; cursor:pointer;
                            "
                            onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                            onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                        <option value="" {{ !request('rhu_filter') ? 'selected' : '' }}>All RHUs</option>
                        @foreach($healthUnits as $rhu)
                            <option value="{{ $rhu->id }}" {{ request('rhu_filter') == $rhu->id ? 'selected' : '' }}>
                                {{ $rhu->name }}
                            </option>
                        @endforeach
                    </select>
                    <p style="font-size:12px; color:#64748b; margin:6px 0 0 0;">
                        Choose a specific RHU, or leave as "All RHUs" for province-wide.
                    </p>
                </div>
            @endif

            {{-- PROGRAM CATEGORY --}}
            <div style="margin-bottom:22px;">
                <label for="category-inventoryExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Program Category
                </label>
                <select id="category-inventoryExportModal"
                        style="
                            width:100%; height:44px;
                            padding:0 14px; font-size:14px;
                            border:2px solid #bec6cf; border-radius:10px;
                            outline:none; box-sizing:border-box;
                            background:#ffffff; color:#0f172a; appearance:auto; cursor:pointer;
                        "
                        onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                        onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <option value="" {{ !request('category') ? 'selected' : '' }}>All Categories</option>
                    <option value="medicine"              {{ request('category') == 'medicine'              ? 'selected' : '' }}>Medicine</option>
                    <option value="vaccine"               {{ request('category') == 'vaccine'               ? 'selected' : '' }}>Vaccine</option>
                    <option value="family_planning"       {{ request('category') == 'family_planning'       ? 'selected' : '' }}>Family Planning</option>
                    <option value="family_health_nutrition" {{ request('category') == 'family_health_nutrition' ? 'selected' : '' }}>Family Health &amp; Nutrition</option>
                    <option value="emerging_reemerging"    {{ request('category') == 'emerging_reemerging'    ? 'selected' : '' }}>Emerging &amp; Re-emerging</option>
                    <option value="non_communicable"         {{ request('category') == 'non_communicable'         ? 'selected' : '' }}>Non-Communicable Disease</option>
                    <option value="dengue_prevention"       {{ request('category') === 'dengue_prevention' ? 'selected' : '' }}>Dengue Prevention</option>
                    <option value="tuberculosis"            {{ request('category') === 'tuberculosis' ? 'selected' : '' }}>Tuberculosis</option>
                </select>
            </div>

            {{-- BATCH NUMBER(S) --}}
            <div style="margin-bottom:22px;">
                <label for="batchInput-inventoryExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Batch Number(s)
                </label>
                <input type="text"
                       id="batchInput-inventoryExportModal"
                       placeholder="e.g. BT-001, BT-002, 1-8"
                       value="{{ request('batch_input', '') }}"
                       style="
                           width:100%; height:44px;
                           padding:0 14px; font-size:14px;
                           border:2px solid #bec6cf; border-radius:10px;
                           outline:none; box-sizing:border-box;
                           background:#ffffff; color:#0f172a;
                       "
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                <p style="font-size:12px; color:#64748b; margin:6px 0 0 0;">
                    Separate multiple batch numbers with commas, or use a range like <code>1-8</code>. Leave blank to include all batches.
                </p>
            </div>

            {{-- EXPIRY STATUS --}}
            <div>
                <label style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:10px;">
                    Expiry Status
                </label>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    @foreach([
                        ''            => 'All Expiry',
                        'near_expiry' => 'Near Expiry (≤180 days)',
                        'expired'     => 'Expired Only',
                        'not_expired' => 'Not Expiring Soon',
                    ] as $val => $label)
                        <label style="
                            display:flex; align-items:center; gap:10px;
                            padding:9px 12px; border:1px solid #e2e8f0; border-radius:10px;
                            cursor:pointer; font-size:14px; color:#0f172a;
                            transition: all .15s;
                        "
                               onmouseover="this.style.borderColor='#42ad8a'; this.style.background='#f0fdf9';"
                               onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#ffffff';">
                            <input type="radio"
                                   name="expiry_filter_inventoryExportModal"
                                   value="{{ $val }}"
                                   {{ $val === request('expiry_filter', '') ? 'checked' : '' }}
                                   style="width:16px; height:16px; accent-color:#42ad8a; cursor:pointer;">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div style="
            padding:16px 26px 20px;
            border-top:1px solid #f1f5f9;
            display:flex; gap:10px; justify-content:flex-end;
            background:#fafbfc;
            border-radius: 0 0 20px 20px;
        ">
            <button type="button"
                    onclick="closeExportModal('inventoryExportModal')"
                    style="
                        font-size:14px; font-weight:600;
                        padding:10px 18px; border-radius:10px;
                        background:#ffffff; color:#334155;
                        border:1px solid #e2e8f0; cursor:pointer;
                    "
                    onmouseover="this.style.background='#f1f5f9'"
                    onmouseout="this.style.background='#ffffff'">
                Cancel
            </button>

            <button type="button"
                    onclick="runReportExport('inventoryExportModal', '{{ route('reports.inventory') }}', 'print')"
                    style="
                        display:inline-flex; align-items:center; gap:6px;
                        font-size:14px; font-weight:600;
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
                    onclick="runReportExport('inventoryExportModal', '{{ route('reports.inventory.csv') }}', 'csv')"
                    style="
                        display:inline-flex; align-items:center; gap:6px;
                        font-size:14px; font-weight:600;
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
    function openExportModal(id) {
        document.getElementById(id).style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function closeExportModal(id) {
        document.getElementById(id).style.display = 'none';
        document.body.style.overflow = '';
    }
    function runReportExport(modalId, baseUrl, mode) {
        const modal = document.getElementById(modalId);

        const rhuSelect      = document.getElementById('rhu-' + modalId);
        const categorySelect = document.getElementById('category-' + modalId);
        const batchInput     = document.getElementById('batchInput-' + modalId);
        const expiryRadio    = modal.querySelector('input[name="expiry_filter_' + modalId + '"]:checked');

        const rhuVal      = rhuSelect ? rhuSelect.value : '';
        const categoryVal = categorySelect ? categorySelect.value : '';
        const rawBatch    = batchInput ? batchInput.value.trim() : '';
        const expiryVal   = expiryRadio ? expiryRadio.value : '';

        const url = new URL(baseUrl, window.location.origin);
        const params = new URLSearchParams(window.location.search);
        params.delete('page');
        params.delete('rhu_filter');
        params.delete('category');
        params.delete('batch_input');
        params.delete('expiry_filter');
        params.delete('print');

        if (rhuVal)      params.set('rhu_filter', rhuVal);
        if (categoryVal) params.set('category', categoryVal);
        if (rawBatch !== '') params.set('batch_input', rawBatch);
        if (expiryVal)   params.set('expiry_filter', expiryVal);
        if (mode === 'print') params.set('print', '1');

        url.search = params.toString();
        window.location.href = url.toString();
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('inventoryExportModal');
            if (modal && modal.style.display === 'flex') {
                closeExportModal('inventoryExportModal');
            }
        }
    });
</script>
@endsection