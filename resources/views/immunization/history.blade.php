@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- ═══════ HEADER ═══════ --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius: 20px; padding: 24px 28px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 16px;">
        <div>
            <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">
                Immunization History
            </p>
            <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">
                {{ $child->full_name }}
            </h2>
            <p style="font-size:15px; color:#64748b; margin:6px 0 0 0;">
                {{ $child->age_display }} · {{ $child->barangay ?? '—' }}
                @if($child->birth_date)
                    · Born {{ \Carbon\Carbon::parse($child->birth_date)->format('M d, Y') }}
                @endif
            </p>
        </div>

        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('vaccination.child', $child->id) }}" style="
                display:inline-flex; align-items:center; gap:6px;
                padding:10px 18px; border-radius:10px;
                background:#ffffff; color:#334155; border:1px solid #e2e8f0;
                font-size:16px; font-weight:600; text-decoration:none;">
                📋 Full Schedule
            </a>
            <a href="{{ route('immunization.tcl') }}" style="
                display:inline-flex; align-items:center; gap:6px;
                padding:10px 18px; border-radius:10px;
                background:#42ad8a; color:#fff; border:1px solid #42ad8a;
                font-size:16px; font-weight:600; text-decoration:none;">
                ← Back to TCL
            </a>
        </div>
    </div>

    {{-- ═══════ SUMMARY STATS ═══════ --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px;">
        <div style="background:#E8F7F4; border:1px solid #42ad8a; border-radius:16px; padding:20px; text-align:center;">
            <p style="font-size:13px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#0f5c4b; margin:0 0 4px 0;">
                Total Doses Given
            </p>
            <p style="font-size:32px; font-weight:700; color:#0f5c4b; margin:0; line-height:1.1;">
                {{ $records->count() }}
            </p>
        </div>

        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:20px; text-align:center;">
            <p style="font-size:13px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#64748b; margin:0 0 4px 0;">
                Unique Vaccines
            </p>
            <p style="font-size:32px; font-weight:700; color:#0f172a; margin:0; line-height:1.1;">
                {{ $records->pluck('antigen')->unique()->count() }}
            </p>
        </div>

        @if($records->count() > 0)
            <div style="background:#FEF3C7; border:1px solid #FAC775; border-radius:16px; padding:20px; text-align:center;">
                <p style="font-size:13px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#854F0B; margin:0 0 4px 0;">
                    Latest Dose
                </p>
                <p style="font-size:18px; font-weight:700; color:#633806; margin:0; line-height:1.3;">
                    {{ \Carbon\Carbon::parse($records->first()->date_given)->format('M d, Y') }}
                </p>
            </div>
        @endif
    </div>

    {{-- ═══════ HISTORY TABLE ═══════ --}}
    <div style="
        background: rgba(255,255,255,0.95); border-radius: 20px;
        border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        overflow: hidden;">

        <div style="padding:20px 24px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <div>
                <p style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0 0 4px 0;">
                    Records
                </p>
                <p style="font-size:16px; font-weight:700; color:#0f172a; margin:0;">
                    All Doses Administered
                </p>
            </div>
            @if($records->count() > 0)
                <span style="
                    font-size:13px; font-weight:700;
                    background:#E8F7F4; color:#0f5c4b;
                    border:1px solid #42ad8a;
                    padding:4px 12px; border-radius:999px;">
                    {{ $records->count() }} dose{{ $records->count() === 1 ? '' : 's' }}
                </span>
            @endif
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%; min-width:900px; border-collapse:collapse; font-size:15px;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <th style="padding:16px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569;">#</th>
                        <th style="padding:16px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569;">Vaccine</th>
                        <th style="padding:16px; text-align:center; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569;">Dose</th>
                        <th style="padding:16px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569;">Date Given</th>
                        <th style="padding:16px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569;">Batch No.</th>
                        <th style="padding:16px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569;">Administered By</th>
                        <th style="padding:16px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $r)
                        <tr style="border-bottom:1px solid #f1f5f9; transition:background 0.15s;"
                            onmouseover="this.style.background='#f8fafc';"
                            onmouseout="this.style.background='transparent';">

                            <td style="padding:16px; color:#94a3b8; font-weight:600;">
                                {{ $loop->iteration }}
                            </td>

                            <td style="padding:16px; font-weight:700; color:#0f172a;">
                                {{ $r->antigen }}
                            </td>

                            <td style="padding:16px; text-align:center;">
                                <span style="
                                    display:inline-block;
                                    min-width:28px; padding:3px 10px;
                                    background:#E8F7F4; color:#0f5c4b;
                                    border:1px solid #42ad8a; border-radius:999px;
                                    font-size:13px; font-weight:700;">
                                    {{ $r->dose_number }}
                                </span>
                            </td>

                            <td style="padding:16px; color:#334155; white-space:nowrap;">
                                {{ \Carbon\Carbon::parse($r->date_given)->format('M d, Y') }}
                            </td>

                            <td style="padding:16px; color:#334155; font-family:'Courier New', monospace; font-size:14px;">
                                {{ $r->batch_no ?? '—' }}
                            </td>

                            <td style="padding:16px; color:#334155;">
                                {{ $r->administeredBy?->name ?? '—' }}
                            </td>

                            <td style="padding:16px; color:#64748b; font-size:14px;">
                                {{ $r->remarks ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding:60px 20px; text-align:center;">
                                <p style="font-size:40px; margin:0 0 12px 0;">💉</p>
                                <p style="font-size:17px; font-weight:700; color:#0f172a; margin:0;">
                                    No doses recorded yet
                                </p>
                                <p style="font-size:15px; color:#64748b; margin:8px 0 16px 0;">
                                    This patient hasn't received any vaccines through this RHU.
                                </p>
                                <a href="{{ route('immunization.tcl') }}" style="
                                    display:inline-block;
                                    padding:10px 20px; border-radius:10px;
                                    background:#42ad8a; color:#fff;
                                    font-size:15px; font-weight:700; text-decoration:none;">
                                    Go to TCL to Record a Dose →
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══════ FOOTER ═══════ --}}
    <div style="display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap;">
        <a href="{{ route('vaccination.child', $child->id) }}" style="
            padding:10px 20px; border-radius:10px;
            background:#ffffff; color:#334155; border:1px solid #e2e8f0;
            font-size:16px; font-weight:600; text-decoration:none;">
            📋 View Full Schedule
        </a>
        <a href="{{ route('dashboard.midwife') }}" style="
            padding:10px 20px; border-radius:10px;
            background:#ffffff; color:#334155; border:1px solid #e2e8f0;
            font-size:16px; font-weight:600; text-decoration:none;">
            ← Back to Dashboard
        </a>
    </div>

</div>
@endsection