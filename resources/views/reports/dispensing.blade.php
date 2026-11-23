@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    <style>
        .table-scroll::-webkit-scrollbar { height: 10px; }
        .table-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1; border-radius: 10px; border: 2px solid #f1f5f9;
        }
        .table-scroll::-webkit-scrollbar-thumb:hover { background: #42ad8a; }
        .table-scroll { scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9; }
        @media print { .no-print { display: none !important; } }
    </style>

    <div class="no-print page-heading" style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
        display:flex; align-items:flex-end; justify-content:space-between; flex-wrap:wrap; gap:16px;
    ">
        <div class="page-heading-main" style="display:flex; align-items:flex-end; gap:24px; flex-wrap:wrap;">
            <div class="page-heading-title">
                <p style="font-size:1.0625rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Reports</p>
                <h2 style="font-size:1.5rem; font-weight:700; color:#0f172a; margin:0; line-height:1.2;">Dispensing Report</h2>
            </div>
            @include('reports._tabs')
        </div>

        <div class="page-heading-actions" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            @if(is_null(session('health_unit_id')))
                <form method="GET" action="{{ route('reports.dispensing') }}" style="display: flex; gap: 8px; align-items: center;">
                    @foreach(request()->except('rhu_filter', 'page') as $k => $v)
                        @if(!is_array($v)) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
                    @endforeach
                    <select name="rhu_filter" style="
                        min-height:2.75rem;height:auto; padding: 0 12px; font-size:1.0625rem;
                        border: 2px solid #bec6cf; border-radius: 10px; outline: none;
                        background: white; color: #0f172a; appearance: auto;
                    " onchange="this.form.requestSubmit()">
                        <option value="">All RHUs</option>
                        @foreach($healthUnits as $rhu)
                            <option value="{{ $rhu->id }}" {{ request('rhu_filter') == $rhu->id ? 'selected' : '' }}>{{ $rhu->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            @include('imports._link', ['section' => 'dispensing', 'fallback' => true])
            @include('dispensing._barangay_filter')
            <form method="GET" action="{{ route('reports.dispensing') }}">
                @foreach(request()->except('batch_no', 'page') as $filterName => $filterValue)
                    @if(is_scalar($filterValue))
                        <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
                    @endif
                @endforeach
                <label for="dispensing-batch-filter">Lot number</label>
                <input id="dispensing-batch-filter" type="search" name="batch_no" maxlength="100" value="{{ request('batch_no') }}">
                <button type="submit">Filter</button>
            </form>
            @include('reports._export_menu', [
                'csvRoute'  => 'reports.dispensing.csv',
                'csvParams' => request()->except('page'),
            ])
        </div>
    </div>

    @include('dispensing._medicine_filter_context')
    @include('shared.history_filters')
    @include('dispensing._barangay_breakdown')
    @include('shared.empty_result', ['resultKey' => 'history', 'recordCount' => $records->count()])
    <p style="margin:0; color:#64748b; font-size:1.0625rem;">Showing saved transactions. Cancelled or reversed transactions are excluded.</p>

    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius:20px; padding:0; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06); overflow:hidden;
    ">
        <div class="table-scroll" style="width:100%; overflow-x:auto; overflow-y:hidden; -webkit-overflow-scrolling:touch;">
            <table style="width:100%; min-width:1000px; border-collapse:collapse; font-size:1.0625rem;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <th class="no-print" style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">#</th>
                        <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Date</th>
                        <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Medicine</th>
                        <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Quantity</th>
                        <th style="padding:14px 16px; text-align:left; color:#475569;">Lots and quantities</th>
                        <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Doctor</th>
                        <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Dispensed By</th>
                        <th style="padding:14px 16px;text-align:left;color:#475569;">Barangay served</th>
                        <th style="padding:14px 16px;text-align:left;color:#475569;">Stock source</th>
                        @if(is_null(session('health_unit_id')))
                            <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">RHU</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr style="border-bottom:1px solid #f1f5f9; transition: background 0.2s;"
                            onmouseover="this.style.background='#f8fafc';"
                            onmouseout="this.style.background='transparent';">
                            <td class="no-print" style="padding:14px 16px; color:#334155; text-align:center;">{{ $loop->iteration }}</td>
                            <td style="padding:14px 16px; color:#334155; white-space:nowrap;">{{ $record->date_dispensed }}</td>
                            <td style="padding:14px 16px; font-weight:600; color:#0f172a;">{{ $record->medicine->medicine_name ?? 'N/A' }}</td>
                            <td style="padding:14px 16px; color:#334155; font-weight:600;">{{ $record->quantity_dispensed }}</td>
                            <td style="padding:14px 16px; color:#334155;">{{ $record->lotBreakdown() ?: 'Not recorded' }}</td>
                            <td style="padding:14px 16px; color:#334155;">{{ $record->prescribingDoctorDisplay() }}</td>
                            <td style="padding:14px 16px; color:#334155;">{{ $record->dispensedByUser->name ?? 'N/A' }}</td>
                            <td style="padding:14px 16px;color:#334155;">{{ $record->barangay ?: 'Not recorded' }}</td>
                            <td style="padding:14px 16px;color:#334155;">{{ $record->stock_source === 'allocation' ? 'Midwife received supplies' : 'RHU shared stock' }}</td>
                            @if(is_null(session('health_unit_id')))
                                <td style="padding:14px 16px; color:#334155;">{{ $record->healthUnit->name ?? 'N/A' }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ is_null(session('health_unit_id')) ? 10 : 9 }}"
                                style="padding:40px 20px; text-align:center; color:#64748b; border-bottom:1px solid #f1f5f9;">
                                No saved dispensing records match the selected period and filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
