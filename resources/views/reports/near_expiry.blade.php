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

    <div class="no-print" style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
        display:flex; align-items:flex-end; justify-content:space-between; flex-wrap:wrap; gap:16px;
    ">
        <div style="display:flex; align-items:flex-end; gap:24px; flex-wrap:wrap;">
            <div>
                <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Reports</p>
                <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0; line-height:1.2;">Near Expiry Report</h2>
            </div>
            @include('reports._tabs')
        </div>

        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            @if(is_null(session('health_unit_id')))
                <form method="GET" action="{{ route('reports.near_expiry') }}" style="display: flex; gap: 8px; align-items: center;">
                    @foreach(request()->except('rhu_filter', 'page') as $k => $v)
                        @if(!is_array($v)) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
                    @endforeach
                    <select name="rhu_filter" style="
                        height: 40px; padding: 0 12px; font-size: 14px;
                        border: 2px solid #bec6cf; border-radius: 10px; outline: none;
                        background: white; color: #0f172a; appearance: auto;
                    " onchange="this.form.submit()">
                        <option value="">All RHUs</option>
                        @foreach($healthUnits as $rhu)
                            <option value="{{ $rhu->id }}" {{ request('rhu_filter') == $rhu->id ? 'selected' : '' }}>{{ $rhu->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            @include('reports._batch_filter', [
                'batches'    => $inventories->pluck('batch_no')->filter()->unique()->sort()->values(),
                'formAction' => route('reports.near_expiry'),
            ])

            @include('reports._export_menu', [
                'csvRoute'  => 'reports.near_expiry.csv',
                'csvParams' => request()->except('page'),
            ])
        </div>
    </div>

    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius:20px; padding:0; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06); overflow:hidden;
    ">
        <div class="table-scroll" style="width:100%; overflow-x:auto; overflow-y:hidden; -webkit-overflow-scrolling:touch;">
            <table style="width:100%; min-width:1000px; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <th class="no-print" style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">#</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Batch No.</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Medicine</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Available Stock</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Expiration Date</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Status</th>
                        @if(is_null(session('health_unit_id')))
                            <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">RHU</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @php $today = now()->startOfDay(); @endphp
                    @forelse($inventories as $inventory)
                        @php
                            $status = \Carbon\Carbon::parse($inventory->expiration_date)->lt($today) ? 'Expired' : 'Near Expiry';
                        @endphp
                        <tr style="border-bottom:1px solid #f1f5f9; transition: background 0.2s;"
                            onmouseover="this.style.background='#f8fafc';"
                            onmouseout="this.style.background='transparent';">
                            <td class="no-print" style="padding:14px 16px; color:#334155; text-align:center;">{{ $loop->iteration }}</td>
                            <td style="padding:14px 16px; color:#334155; font-weight:600;">{{ $inventory->batch_no ?? 'N/A' }}</td>
                            <td style="padding:14px 16px; font-weight:600; color:#0f172a;">{{ $inventory->medicine->medicine_name ?? 'N/A' }}</td>
                            <td style="padding:14px 16px; color:#334155;">{{ $inventory->available_stock }}</td>
                            <td style="padding:14px 16px; color:#334155; white-space:nowrap;">{{ $inventory->expiration_date }}</td>
                            <td style="padding:14px 16px;">
                                <span style="display:inline-block; font-size:12px; font-weight:600; padding:3px 10px; border-radius:999px;
                                    {{ $status === 'Expired' ? 'background:#FECACA; color:#991B1B; border:1px solid #FCA5A5;' : 'background:#FFE4CC; color:#B85C00; border:1px solid #FFB870;' }}">
                                    {{ $status }}
                                </span>
                            </td>
                            @if(is_null(session('health_unit_id')))
                                <td style="padding:14px 16px; color:#334155;">{{ $inventory->healthUnit->name ?? 'N/A' }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="@php $cols = 6; if (is_null(session('health_unit_id'))) $cols += 1; echo $cols; @endphp"
                                style="padding:40px 20px; text-align:center; color:#64748b; border-bottom:1px solid #f1f5f9;">
                                No near-expiry records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection