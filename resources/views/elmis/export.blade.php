@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    <div class="no-print" style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
        display:flex; align-items:flex-end; justify-content:space-between; flex-wrap:wrap; gap:16px;
    ">
        <div style="display:flex; align-items:flex-end; gap:24px; flex-wrap:wrap;">
            <div>
                <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Reports</p>
                <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0; line-height:1.2;">ELMIS Export</h2>
            </div>
            @include('reports._tabs')
        </div>
    </div>

    {{-- ELMIS intro --}}
    <div style="
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(8px);
        border-radius:16px; padding:16px 20px;
        border:1px solid rgba(66,173,138,0.15);
        box-shadow:0 4px 12px rgba(0,0,0,0.04);
    ">
        <p style="font-size:13px; color:#64748b; margin:0;">
            <strong>ELMIS Format:</strong> Export inventory and stock movement data in DOH ELMIS format (CSV).
            The exported file can be opened in Excel and used for PHO/DOH reporting requirements.
        </p>
    </div>

    {{-- Export Cards --}}
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">

        {{-- Inventory Export --}}
        <div style="
            background: rgba(255,255,255,0.95);
            border-radius:20px;
            padding:24px;
            border:1px solid rgba(66,173,138,0.15);
            box-shadow:0 8px 20px rgba(0,0,0,0.06);
        ">
            <h3 style="font-size:16px; font-weight:700; color:#0f172a; margin:0 0 4px 0;">Inventory Report</h3>
            <p style="font-size:13px; color:#64748b; margin:0 0 16px 0;">Export current inventory levels with batch and expiry details.</p>

            <form action="{{ route('elmis.export.inventory') }}" method="GET">
                <div style="display:flex; flex-direction:column; gap:12px;">
                    @if(is_null(session('health_unit_id')))
                    <div>
                        <label style="font-size:13px; font-weight:600; color:#0f172a; display:block; margin-bottom:4px;">Facility</label>
                        <select name="health_unit_id" style="
                            width:100%; padding:8px 12px; font-size:14px;
                            border:2px solid #bec6cf; border-radius:10px; outline:none;
                            background:white; color:#0f172a;
                        ">
                            <option value="">All Facilities</option>
                            @foreach($healthUnits as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div>
                            <label style="font-size:13px; font-weight:600; color:#0f172a; display:block; margin-bottom:4px;">Date From</label>
                            <input type="date" name="date_from" style="
                                width:100%; padding:8px 12px; font-size:14px;
                                border:2px solid #bec6cf; border-radius:10px; outline:none;
                            ">
                        </div>
                        <div>
                            <label style="font-size:13px; font-weight:600; color:#0f172a; display:block; margin-bottom:4px;">Date To</label>
                            <input type="date" name="date_to" style="
                                width:100%; padding:8px 12px; font-size:14px;
                                border:2px solid #bec6cf; border-radius:10px; outline:none;
                            ">
                        </div>
                    </div>

                    <button type="submit" style="
                        font-size:14px; font-weight:600; padding:10px 20px; border-radius:10px;
                        background:#42ad8a; color:#ffffff; border:none; cursor:pointer;
                        transition: background 0.2s;
                    " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                        Export Inventory CSV
                    </button>
                </div>
            </form>
        </div>

        {{-- Stock Movement Export --}}
        <div style="
            background: rgba(255,255,255,0.95);
            border-radius:20px;
            padding:24px;
            border:1px solid rgba(66,173,138,0.15);
            box-shadow:0 8px 20px rgba(0,0,0,0.06);
        ">
            <h3 style="font-size:16px; font-weight:700; color:#0f172a; margin:0 0 4px 0;">Stock Movement Report</h3>
            <p style="font-size:13px; color:#64748b; margin:0 0 16px 0;">Export additions and releases with transaction details.</p>

            <form action="{{ route('elmis.export.movement') }}" method="GET">
                <div style="display:flex; flex-direction:column; gap:12px;">
                    @if(is_null(session('health_unit_id')))
                    <div>
                        <label style="font-size:13px; font-weight:600; color:#0f172a; display:block; margin-bottom:4px;">Facility</label>
                        <select name="health_unit_id" style="
                            width:100%; padding:8px 12px; font-size:14px;
                            border:2px solid #bec6cf; border-radius:10px; outline:none;
                            background:white; color:#0f172a;
                        ">
                            <option value="">All Facilities</option>
                            @foreach($healthUnits as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div>
                            <label style="font-size:13px; font-weight:600; color:#0f172a; display:block; margin-bottom:4px;">Date From</label>
                            <input type="date" name="date_from" style="
                                width:100%; padding:8px 12px; font-size:14px;
                                border:2px solid #bec6cf; border-radius:10px; outline:none;
                            ">
                        </div>
                        <div>
                            <label style="font-size:13px; font-weight:600; color:#0f172a; display:block; margin-bottom:4px;">Date To</label>
                            <input type="date" name="date_to" style="
                                width:100%; padding:8px 12px; font-size:14px;
                                border:2px solid #bec6cf; border-radius:10px; outline:none;
                            ">
                        </div>
                    </div>

                    <button type="submit" style="
                        font-size:14px; font-weight:600; padding:10px 20px; border-radius:10px;
                        background:#42ad8a; color:#ffffff; border:none; cursor:pointer;
                        transition: background 0.2s;
                    " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                        Export Movement CSV
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection