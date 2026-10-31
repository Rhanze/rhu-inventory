@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius: 20px; padding: 24px 28px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        display:flex; align-items:center; justify-content:space-between;
        flex-wrap:wrap; gap:16px;">
        <div>
            <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">
                Vaccination Forecast
            </p>
            <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">
                Monthly Stock Planning
            </h2>
            <p style="font-size:15px; color:#64748b; margin:6px 0 0 0;">
                How many children need vaccination, and which vaccines to stock
            </p>
        </div>

        <a href="{{ route('vaccination.monthly.export', ['month' => $month]) }}" style="
            display:inline-flex; align-items:center; gap:8px;
            padding:10px 20px; border-radius:10px;
            background:#42ad8a; color:#ffffff; border:1px solid #42ad8a;
            font-size:16px; font-weight:600; text-decoration:none;
            transition: background 0.2s;"
            onmouseover="this.style.background='#369f7d'; this.style.borderColor='#369f7d'"
            onmouseout="this.style.background='#42ad8a'; this.style.borderColor='#42ad8a'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Export CSV
        </a>
    </div>

    {{-- Month picker --}}
    <div style="
        background: rgba(255,255,255,0.95); border-radius: 20px;
        border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        padding: 20px 24px;">
        <form method="GET" action="{{ route('vaccination.monthly') }}"
              style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <a href="{{ route('vaccination.monthly', ['month' => \Carbon\Carbon::parse($month . '-01')->subMonth()->format('Y-m')]) }}"
               style="padding:10px 18px; border-radius:10px; background:#fff; color:#334155;
                      border:1px solid #e2e8f0; font-weight:600; font-size:16px; text-decoration:none;
                      transition: all 0.2s;"
               onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a'; this.style.color='#0f5c4b'"
               onmouseout="this.style.background='#fff'; this.style.borderColor='#e2e8f0'; this.style.color='#334155'">← Prev</a>

            <input type="month" name="month" value="{{ $month }}"
                   onchange="this.form.submit()"
                   style="height:44px; padding:0 14px; font-size:16px; font-weight:600;
                          border:2px solid #bec6cf; border-radius:10px;
                          background:#fff; color:#0f172a; outline:none;">

            <a href="{{ route('vaccination.monthly', ['month' => \Carbon\Carbon::parse($month . '-01')->addMonth()->format('Y-m')]) }}"
               style="padding:10px 18px; border-radius:10px; background:#fff; color:#334155;
                      border:1px solid #e2e8f0; font-weight:600; font-size:16px; text-decoration:none;
                      transition: all 0.2s;"
               onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a'; this.style.color='#0f5c4b'"
               onmouseout="this.style.background='#fff'; this.style.borderColor='#e2e8f0'; this.style.color='#334155'">Next →</a>
        </form>
    </div>

    {{-- Stat cards --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px;">
        <div style="background:#E8F7F4; border:1px solid #42ad8a; border-radius:16px; padding:22px;">
            <p style="font-size:13px; font-weight:700; color:#0f5c4b; text-transform:uppercase; margin:0 0 6px 0;">
                Children This Month
            </p>
            <p style="font-size:36px; font-weight:700; color:#0f5c4b; margin:0; line-height:1.1;">
                {{ $summary['total_children'] }}
            </p>
            <p style="font-size:13px; color:#0f5c4b; margin:6px 0 0 0;">
                {{ $summary['total_doses'] }} doses total
            </p>
        </div>

        <div style="background:#FEF3C7; border:1px solid #FAC775; border-radius:16px; padding:22px;">
            <p style="font-size:13px; font-weight:700; color:#854F0B; text-transform:uppercase; margin:0 0 6px 0;">
                Next Month
            </p>
            <p style="font-size:36px; font-weight:700; color:#633806; margin:0; line-height:1.1;">
                {{ $nextSummary['total_children'] }}
            </p>
            <p style="font-size:13px; color:#854F0B; margin:6px 0 0 0;">
                {{ $nextSummary['total_doses'] }} doses · {{ $nextSummary['month'] }}
            </p>
        </div>

        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:22px;">
            <p style="font-size:13px; font-weight:700; color:#64748b; text-transform:uppercase; margin:0 0 6px 0;">
                Vaccines Needed
            </p>
            <p style="font-size:36px; font-weight:700; color:#0f172a; margin:0; line-height:1.1;">
                {{ $summary['by_vaccine']->count() }}
            </p>
            <p style="font-size:13px; color:#64748b; margin:6px 0 0 0;">
                unique vaccine types
            </p>
        </div>
    </div>

    {{-- Breakdown by vaccine --}}
    <div style="
        background: rgba(255,255,255,0.95); border-radius: 20px;
        border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        overflow: hidden;">

        <div style="padding:20px 24px; border-bottom:1px solid #f1f5f9;">
            <p style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0 0 4px 0;">
                Stock Requirement
            </p>
            <p style="font-size:16px; font-weight:700; color:#0f172a; margin:0;">
                Vaccines to prepare for {{ $summary['month'] }}
            </p>
        </div>

        @if($summary['by_vaccine']->count() > 0)
            <table style="width:100%; border-collapse:collapse; font-size:15px;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <th style="padding:16px 24px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Vaccine</th>
                        <th style="padding:16px 24px; text-align:right; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Children</th>
                        <th style="padding:16px 24px; text-align:right; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Doses</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($summary['by_vaccine'] as $row)
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:16px 24px; font-weight:700; color:#0f172a;">
                                {{ $row['vaccine'] }}
                            </td>
                            <td style="padding:16px 24px; text-align:right; color:#334155; font-weight:600;">
                                {{ $row['children'] }}
                            </td>
                            <td style="padding:16px 24px; text-align:right;">
                                <span style="
                                    display:inline-block; padding:4px 14px;
                                    background:#E8F7F4; color:#0f5c4b;
                                    border:1px solid #42ad8a; border-radius:999px;
                                    font-size:14px; font-weight:700;">
                                    {{ $row['doses'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background:#f8fafc; border-top:2px solid #e2e8f0;">
                        <td style="padding:16px 24px; font-weight:700; color:#0f172a;">TOTAL</td>
                        <td style="padding:16px 24px; text-align:right; font-weight:700; color:#0f172a;">
                            {{ $summary['total_children'] }}
                        </td>
                        <td style="padding:16px 24px; text-align:right; font-weight:700; color:#0f5c4b;">
                            {{ $summary['total_doses'] }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        @else
            <div style="padding:60px 24px; text-align:center; color:#64748b;">
                <p style="font-size:32px; margin:0 0 8px 0;">📅</p>
                <p style="font-size:16px; font-weight:600; color:#0f172a; margin:0;">
                    No vaccinations scheduled
                </p>
                <p style="font-size:14px; margin:6px 0 0 0;">
                    No children are due for vaccination in {{ $summary['month'] }}.
                </p>
            </div>
        @endif
    </div>

    {{-- Children list --}}
    @if($summary['children']->count() > 0)
        <div style="
            background: rgba(255,255,255,0.95); border-radius: 20px;
            border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            overflow: hidden;">

            <div style="padding:20px 24px; border-bottom:1px solid #f1f5f9;">
                <p style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0 0 4px 0;">
                    Individual Schedules
                </p>
                <p style="font-size:16px; font-weight:700; color:#0f172a; margin:0;">
                    Children due in {{ $summary['month'] }}
                </p>
            </div>

            <table style="width:100%; border-collapse:collapse; font-size:15px;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <th style="padding:14px 20px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Child</th>
                        <th style="padding:14px 20px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Barangay</th>
                        <th style="padding:14px 20px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Vaccine</th>
                        <th style="padding:14px 20px; text-align:center; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Due</th>
                        <th style="padding:14px 20px; text-align:center; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($summary['children'] as $s)
                        @php
                            $statusClass = $s->status === 'missed' ? 'red' : 'amber';
                        @endphp
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:14px 20px; font-weight:600; color:#0f172a;">
                                {{ $s->patient?->full_name ?? 'Unknown' }}
                            </td>
                            <td style="padding:14px 20px; color:#334155;">
                                {{ $s->patient?->barangay ?? '—' }}
                            </td>
                            <td style="padding:14px 20px; color:#334155;">
                                {{ $s->vaccine_name }} (Dose {{ $s->dose_number }})
                            </td>
                            <td style="padding:14px 20px; text-align:center; color:#334155; white-space:nowrap;">
                                {{ $s->due_date->format('M d, Y') }}
                            </td>
                            <td style="padding:14px 20px; text-align:center;">
                                <span style="
                                    font-size:13px; font-weight:700;
                                    padding:3px 12px; border-radius:999px;
                                    background:{{ $statusClass === 'red' ? '#FCEBEB' : '#FEF3C7' }};
                                    color:{{ $statusClass === 'red' ? '#991b1b' : '#92400E' }};
                                    border:1px solid {{ $statusClass === 'red' ? '#F7C1C1' : '#FAC775' }};">
                                    {{ ucfirst($s->status) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
@endsection