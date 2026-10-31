@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    <style>
        .hidden { display: none !important; }
        .filter-card input, .filter-card select {
            height: 44px; padding: 0 14px; font-size: 16px;
            border: 2px solid #bec6cf; border-radius: 10px; outline: none;
            background: white; color: #0f172a; width: 100%; box-sizing: border-box;
        }
        .filter-card input:focus, .filter-card select:focus {
            border-color: #42ad8a; box-shadow: 0 0 0 3px rgba(66,173,138,0.1);
        }
        .filter-card label {
            display: block; margin-bottom: 6px; font-size: 15px;
            font-weight: 600; color: #0f172a;
        }
        .filter-card .btn-filter {
            font-size: 15px; font-weight: 600; padding: 8px 22px; border-radius: 10px;
            background: #42ad8a; color: white; border: none; cursor: pointer;
            transition: background 0.2s; height: 44px;
        }
        .filter-card .btn-filter:hover { background: #369f7d; }
        .filter-card .btn-reset {
            font-size: 15px; font-weight: 600; padding: 8px 22px; border-radius: 10px;
            background: #ffffff; color: #334155; border: 1px solid #e2e8f0;
            text-decoration: none; height: 44px;
            display: inline-flex; align-items: center;
            transition: all 0.2s;
        }
        .filter-card .btn-reset:hover { background: #f8fafc; border-color: #42ad8a; }
        .table-scroll::-webkit-scrollbar { height: 10px; }
        .table-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1; border-radius: 10px; border: 2px solid #f1f5f9;
        }
        .table-scroll::-webkit-scrollbar-thumb:hover { background: #42ad8a; }
        .table-scroll { scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9; }
        @media print { .no-print { display: none !important; } }
    </style>

    {{-- Header Card --}}
    <div class="no-print" style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius: 20px; padding: 24px 28px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 16px;">
        <div>
            <p style="font-size: 13px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #42ad8a; margin: 0 0 6px 0;">
                Provincial Health Office
            </p>
            <h2 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0;">
                DOH Receive Records
            </h2>
        </div>

        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <button type="button" onclick="openShipExportModal()" style="
                display:inline-flex; align-items:center; gap:6px;
                font-size:14px; font-weight:600; padding:9px 18px; border-radius:10px;
                background:#42ad8a; color:#ffffff; border:1px solid #42ad8a;
                cursor:pointer; transition: background 0.2s, border-color 0.2s;"
                onmouseover="this.style.background='#369f7d'; this.style.borderColor='#369f7d'"
                onmouseout="this.style.background='#42ad8a'; this.style.borderColor='#42ad8a'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Export
            </button>

            <a href="{{ route('inventory.pho_receive.create') }}" style="
                display:inline-flex; align-items:center; gap:6px;
                font-size:14px; font-weight:600; padding:9px 18px; border-radius:10px;
                background:#42ad8a; color:#ffffff; text-decoration:none;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Receive Record
            </a>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="no-print filter-card" style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius: 16px; padding: 22px 26px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
        <form method="GET" action="{{ route('inventory.pho_receive') }}"
              style="display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end;">
            <div style="flex: 2 1 260px; min-width: 200px;">
                <label for="search">Shipment / Reference / Supplier</label>
                <input type="text" name="search" id="search"
                       placeholder="Search shipment..." value="{{ $search }}">
            </div>
            <div style="flex: 1 1 160px; min-width: 140px;">
                <label for="date_from">Date From</label>
                <input type="date" name="date_from" id="date_from" value="{{ $dateFrom }}">
            </div>
            <div style="flex: 1 1 160px; min-width: 140px;">
                <label for="date_to">Date To</label>
                <input type="date" name="date_to" id="date_to" value="{{ $dateTo }}">
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="submit" class="btn-filter">Apply Filters</button>
                <a href="{{ route('inventory.pho_receive') }}" class="btn-reset">Reset</a>
            </div>
        </form>
    </div>

    {{-- Table Card --}}
    <div style="
        background: rgba(255,255,255,0.9); border-radius: 20px; padding: 0;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06); overflow: hidden;">
        <div class="table-scroll" style="width:100%; overflow-x:auto; overflow-y:hidden;">
            <table style="width:100%; min-width: 800px; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">#</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Shipment No.</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Reference No.</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Supplier</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Received By</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Date Received</th>
                        <th style="padding:14px 16px; text-align:center; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shipments as $ship)
                        <tr id="shipment-{{ $ship->id }}"
                            style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;"
                            onmouseover="this.style.background='#f8fafc';"
                            onmouseout="this.style.background='transparent';">
                            <td style="padding:14px 16px; color:#334155; text-align:center;">
                                {{ $loop->iteration + ($shipments->currentPage() - 1) * $shipments->perPage() }}
                            </td>
                            <td style="padding:14px 16px; font-weight:600; color:#0f172a; font-family:monospace;">
                                {{ $ship->shipment_no ?? '—' }}
                            </td>
                            <td style="padding:14px 16px; color:#334155; font-family:monospace;">
                                {{ $ship->reference_no ?? '—' }}
                            </td>
                            <td style="padding:14px 16px; color:#334155;">
                                {{ $ship->supplier ?? '—' }}
                            </td>
                            <td style="padding:14px 16px; color:#334155;">
                                {{ $ship->receivedByUser->name ?? '—' }}
                            </td>
                            <td style="padding:14px 16px; color:#334155; white-space:nowrap;">
                                {{ \Carbon\Carbon::parse($ship->received_date)->format('M d, Y') }}
                            </td>
                            <td style="padding:14px 16px;">
                                <div style="display:flex; align-items:center; justify-content:center; gap:6px;">
                                    <a href="{{ route('inventory.pho_receive.show', $ship->id) }}" title="View"
                                       style="
                                           width:34px; height:34px;
                                           display:inline-flex; align-items:center; justify-content:center;
                                           border-radius:8px;
                                           background:#EFF6FF; color:#1e40af; border:1px solid #93c5fd;
                                           text-decoration:none; transition:all 0.2s;"
                                       onmouseover="this.style.background='#DBEAFE'" onmouseout="this.style.background='#EFF6FF'">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </a>

                                    <form action="{{ route('inventory.pho_receive.destroy', $ship->id) }}"
                                          method="POST" style="margin:0;"
                                          onsubmit="return confirmDialog(event, 'Delete this shipment and reverse its inventory? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Delete"
                                                style="
                                                    width:34px; height:34px;
                                                    display:inline-flex; align-items:center; justify-content:center;
                                                    border-radius:8px;
                                                    background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;
                                                    cursor:pointer; transition:all 0.2s;"
                                                onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"/>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                <line x1="10" y1="11" x2="10" y2="17"/>
                                                <line x1="14" y1="11" x2="14" y2="17"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding:40px 20px; text-align:center; color:#64748b; border-bottom:1px solid #f1f5f9;">
                                No DOH receive records yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @if ($shipments->hasPages())
        <div class="no-print" style="
            background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
            border-radius: 16px; padding: 16px 24px;
            border: 1px solid rgba(255,255,255,0.4);
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            display: flex; align-items: center; justify-content: center; gap: 10px; flex-wrap: wrap;">
            @if ($shipments->onFirstPage())
                <span style="font-size:14px; font-weight:500; padding:8px 16px; border-radius:8px; background:#f1f5f9; color:#94a3b8; border:1px solid #e2e8f0; user-select:none;">← Previous</span>
            @else
                <a href="{{ $shipments->previousPageUrl() }}" style="font-size:14px; font-weight:500; padding:8px 16px; border-radius:8px; background:#ffffff; color:#334155; border:1px solid #e2e8f0; text-decoration:none;">← Previous</a>
            @endif

            @foreach ($shipments->getUrlRange(1, $shipments->lastPage()) as $page => $url)
                @if ($page == $shipments->currentPage())
                    <span style="font-size:14px; font-weight:600; padding:8px 14px; border-radius:8px; background:#42ad8a; color:#ffffff; border:1px solid #42ad8a;">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" style="font-size:14px; font-weight:500; padding:8px 14px; border-radius:8px; background:#ffffff; color:#334155; border:1px solid #e2e8f0; text-decoration:none;">{{ $page }}</a>
                @endif
            @endforeach

            @if ($shipments->hasMorePages())
                <a href="{{ $shipments->nextPageUrl() }}" style="font-size:14px; font-weight:500; padding:8px 16px; border-radius:8px; background:#ffffff; color:#334155; border:1px solid #e2e8f0; text-decoration:none;">Next →</a>
            @else
                <span style="font-size:14px; font-weight:500; padding:8px 16px; border-radius:8px; background:#f1f5f9; color:#94a3b8; border:1px solid #e2e8f0; user-select:none;">Next →</span>
            @endif
        </div>
    @endif

</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- EXPORT MODAL --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div id="shipExportModal"
     onclick="if(event.target === this) closeShipExportModal()"
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
                Export DOH Receive Records
            </h3>
            <p style="font-size:13px; color:#64748b; margin:0;">
                Filter what to include, then export as CSV.
            </p>
        </div>

        {{-- Body --}}
        <div style="padding:20px 26px;">

            {{-- SHIPMENT NUMBER(S) --}}
            <div style="margin-bottom:22px;">
                <label for="shipmentNo-shipExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Shipment Number(s)
                </label>
                <input type="text"
                       id="shipmentNo-shipExportModal"
                       placeholder="e.g. DOH-2026-0001, DOH-2026-0002"
                       value="{{ request('shipment_no', '') }}"
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
                    Separate multiple shipment numbers with commas. Leave blank to include all.
                </p>
            </div>

            {{-- DATE FROM --}}
            <div style="margin-bottom:22px;">
                <label for="dateFrom-shipExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Date From
                </label>
                <input type="date"
                       id="dateFrom-shipExportModal"
                       value="{{ request('date_from', '') }}"
                       style="
                           width:100%; height:44px;
                           padding:0 14px; font-size:14px;
                           border:2px solid #bec6cf; border-radius:10px;
                           outline:none; box-sizing:border-box;
                           background:#ffffff; color:#0f172a;
                       "
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
            </div>

            {{-- DATE TO --}}
            <div>
                <label for="dateTo-shipExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Date To
                </label>
                <input type="date"
                       id="dateTo-shipExportModal"
                       value="{{ request('date_to', '') }}"
                       style="
                           width:100%; height:44px;
                           padding:0 14px; font-size:14px;
                           border:2px solid #bec6cf; border-radius:10px;
                           outline:none; box-sizing:border-box;
                           background:#ffffff; color:#0f172a;
                       "
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
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
                    onclick="closeShipExportModal()"
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
                    onclick="runShipExport()"
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
function openShipExportModal() {
    document.getElementById('shipExportModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeShipExportModal() {
    document.getElementById('shipExportModal').style.display = 'none';
    document.body.style.overflow = '';
}
function runShipExport() {
    const shipmentNo = document.getElementById('shipmentNo-shipExportModal').value.trim();
    const dateFrom   = document.getElementById('dateFrom-shipExportModal').value;
    const dateTo     = document.getElementById('dateTo-shipExportModal').value;

    const baseUrl = '{{ route('inventory.pho_receive.export') }}';
    const url = new URL(baseUrl, window.location.origin);
    const params = new URLSearchParams();

    if (shipmentNo) params.set('shipment_no', shipmentNo);
    if (dateFrom)   params.set('date_from',   dateFrom);
    if (dateTo)     params.set('date_to',     dateTo);

    url.search = params.toString();
    window.location.href = url.toString();
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('shipExportModal');
        if (modal && modal.style.display === 'flex') {
            closeShipExportModal();
        }
    }
});
</script>
@endsection