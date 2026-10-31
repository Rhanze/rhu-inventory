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
                <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0; line-height:1.2;">Allocation Report</h2>
            </div>
            @include('reports._tabs')
        </div>

        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            @if(is_null(session('health_unit_id')))
                <form method="GET" action="{{ route('reports.allocation') }}" style="display: flex; gap: 8px; align-items: center;">
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

            @include('reports._export_menu', [
                'csvRoute'  => 'reports.allocation.csv',
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
            <table style="width:100%; min-width:1080px; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <th class="no-print" style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">#</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Medicine</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Destination</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Quantity</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Date</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Recipient</th>
                        <th style="padding:14px 16px; text-align:center; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Status</th>
                        <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569; white-space:nowrap;">Allocated By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allocations as $allocation)
                        @php
                            $destination = $allocation->destination
                                        ?? $allocation->destinationHealthUnit?->name
                                        ?? $allocation->destination_health_unit_name
                                        ?? null;

                            $allocatedBy = $allocation->allocatedBy?->name
                                        ?? $allocation->allocatedByUser?->name
                                        ?? $allocation->user?->name
                                        ?? null;

                            $recipient = $allocation->recipient?->name;

                            $statusStyle = match($allocation->status ?? null) {
                                'pending'          => ['bg'=>'#FEF3C7','text'=>'#92400E','border'=>'#FAC775','label'=>'⏳ Pending'],
                                'approved'         => ['bg'=>'#EFF6FF','text'=>'#1E40AF','border'=>'#93C5FD','label'=>'✓ Approved'],
                                'received'         => ['bg'=>'#E8F7F4','text'=>'#0f5c4b','border'=>'#42ad8a','label'=>'📦 Received'],
                                'rejected'         => ['bg'=>'#FCEBEB','text'=>'#991b1b','border'=>'#F7C1C1','label'=>'✗ Rejected'],
                                'awaiting_receipt' => ['bg'=>'#FEF3C7','text'=>'#92400E','border'=>'#FAC775','label'=>'⏳ Awaiting Receipt'],
                                'completed'        => ['bg'=>'#E8F7F4','text'=>'#0f5c4b','border'=>'#42ad8a','label'=>'✓ Completed'],
                                default            => ['bg'=>'#f1f5f9','text'=>'#475569','border'=>'#cbd5e1','label'=>'—'],
                            };
                        @endphp
                        <tr style="border-bottom:1px solid #f1f5f9; transition: background 0.2s;"
                            onmouseover="this.style.background='#f8fafc';"
                            onmouseout="this.style.background='transparent';">
                            <td class="no-print" style="padding:14px 16px; color:#334155; text-align:center;">{{ $loop->iteration }}</td>
                            <td style="padding:14px 16px; font-weight:600; color:#0f172a;">{{ $allocation->medicine->medicine_name ?? 'N/A' }}</td>
                            <td style="padding:14px 16px; color:#334155;">
                                @if($destination)
                                    {{ $destination }}
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>
                            <td style="padding:14px 16px; color:#334155; font-weight:600;">{{ $allocation->quantity_allocated }}</td>
                            <td style="padding:14px 16px; color:#334155; white-space:nowrap;">
                                @if($allocation->date_allocated)
                                    {{ \Carbon\Carbon::parse($allocation->date_allocated)->format('M d, Y') }}
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>

                            <td style="padding:14px 16px; color:#334155;">
                                @if($recipient)
                                    <div style="font-weight:600;">{{ $recipient }}</div>
                                    @if($allocation->recipient?->role)
                                        <div style="font-size:12px; color:#94a3b8; margin-top:2px;">
                                            {{ ucfirst($allocation->recipient->role) }}
                                        </div>
                                    @endif
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>

                            <td style="padding:14px 16px; text-align:center;">
                                <span style="
                                    display:inline-block; padding:4px 12px; border-radius:999px;
                                    font-size:12px; font-weight:700; white-space:nowrap;
                                    background:{{ $statusStyle['bg'] }};
                                    color:{{ $statusStyle['text'] }};
                                    border:1px solid {{ $statusStyle['border'] }};">
                                    {{ $statusStyle['label'] }}
                                </span>
                            </td>

                            <td style="padding:14px 16px; color:#334155;">
                                @if($allocatedBy)
                                    {{ $allocatedBy }}
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8"
                                style="padding:40px 20px; text-align:center; color:#64748b; border-bottom:1px solid #f1f5f9;">
                                No allocation records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection