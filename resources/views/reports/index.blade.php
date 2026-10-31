@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header Card with inline text nav --}}
    <div class="no-print" style="
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(12px);
        border-radius: 20px;
        padding: 24px 28px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    ">
        {{-- Title + inline text nav --}}
        <div style="display:flex; align-items:baseline; gap:16px; flex-wrap:wrap;">
            <div>
                <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Reports</p>
                <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">
                    @if(is_null(session('health_unit_id')))
                        Provincial Reports
                    @else
                        RHU Reports
                    @endif
                </h2>
            </div>

            {{-- Inline text nav --}}
            <nav style="display:flex; align-items:baseline; gap:20px;">
                <a href="{{ route('reports.index') }}" style="
                    font-size:14px; font-weight:700; text-decoration:none;
                    color:#0f5c4b; padding-bottom:4px;
                    border-bottom:2px solid #42ad8a;
                ">
                    Reports
                </a>

                <a href="{{ route('elmis.export') }}" style="
                    font-size:14px; font-weight:600; text-decoration:none;
                    color:#94a3b8; transition: color 0.2s;
                " onmouseover="this.style.color='#0f5c4b'" onmouseout="this.style.color='#94a3b8'">
                    ELMIS Export
                </a>
            </nav>
        </div>
    </div>

    {{-- Reports Table --}}
    <div style="
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(8px);
        border-radius: 20px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        overflow: hidden;
    ">
        <div style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch;">
            <table style="width:100%; min-width:760px; border-collapse: collapse; font-size:14px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap; width:60px;">#</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Report Name</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Description</th>
                        <th style="padding: 14px 20px; text-align: right; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $reports = [
                            ['reports.inventory',  'Inventory Report',   'View all current stock records.',          'Inventory'],
                            ['reports.stock',      'Stock Logs Report',  'View all stock additions and releases.',   'Logs'],
                            ['reports.allocation', 'Allocation Report',  'View all BHS allocations.',                'Allocation'],
                            ['reports.near_expiry','Near Expiry Report', 'View items expiring within 30 days.',      'Expiry'],
                        ];
                        if (session('health_unit_id')) {
                            array_splice($reports, 3, 0, [
                                ['reports.dispensing', 'Dispensing Report', 'View all direct dispensing records.', 'Dispensing'],
                            ]);
                        }

                        $badgeColors = [
                            'Inventory'  => ['bg' => '#DBEAFE', 'color' => '#1E40AF', 'border' => '#93C5FD'],
                            'Logs'       => ['bg' => '#FEF3C7', 'color' => '#92400E', 'border' => '#FCD34D'],
                            'Allocation' => ['bg' => '#F3E8FF', 'color' => '#6B21A8', 'border' => '#D8B4FE'],
                            'Dispensing' => ['bg' => '#DCFCE7', 'color' => '#166534', 'border' => '#86EFAC'],
                            'Expiry'     => ['bg' => '#FEE2E2', 'color' => '#991B1B', 'border' => '#FCA5A5'],
                        ];
                    @endphp

                    @foreach($reports as $i => $r)
                        @php $badge = $badgeColors[$r[3]] ?? ['bg' => '#F1F5F9', 'color' => '#475569', 'border' => '#CBD5E1']; @endphp
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;"
                            onmouseover="this.style.background='#f8fafc';"
                            onmouseout="this.style.background='transparent';">
                            <td style="padding: 14px 20px; color: #334155;">{{ $i + 1 }}</td>

                            <td style="padding: 14px 20px;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <span style="
                                        display:inline-block; padding:3px 10px; border-radius:999px;
                                        font-size:12px; font-weight:600; white-space:nowrap;
                                        background:{{ $badge['bg'] }}; color:{{ $badge['color'] }};
                                        border:1px solid {{ $badge['border'] }};
                                    ">
                                        {{ $r[3] }}
                                    </span>
                                    <span style="font-weight:600; color:#0f172a;">{{ $r[1] }}</span>
                                </div>
                            </td>

                            <td style="padding: 14px 20px; color: #475569;">{{ $r[2] }}</td>

                            <td style="padding: 14px 20px; text-align: right;">
                                <a href="{{ route($r[0]) }}" style="
                                    display:inline-flex; align-items:center; gap:6px;
                                    font-size:13px; font-weight:600; padding:6px 14px;
                                    border-radius:8px; background:#42ad8a; color:#ffffff;
                                    text-decoration:none; transition: background 0.2s;
                                " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                                    View
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="5" y1="12" x2="19" y2="12"/>
                                        <polyline points="12 5 19 12 12 19"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection