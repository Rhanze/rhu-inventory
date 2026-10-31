@extends('layouts.app')

@section('content')

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- PRINT STYLES                                                    --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<style>
    @media print {
        @page { margin: 14mm 12mm; }

        html, body {
            overflow: visible !important;
            height: auto !important;
            min-height: 0 !important;
            background: #ffffff !important;
        }

        .app-shell { display: block !important; min-height: 0 !important; overflow: visible !important; }
        .main-content { overflow: visible !important; height: auto !important; max-height: none !important; padding: 0 !important; }

        .no-print,
        .sidebar,
        .public-header,
        .public-footer,
        nav,
        button { display: none !important; }

        .print-card {
            box-shadow: none !important;
            border: 1px solid #cbd5e1 !important;
            background: #ffffff !important;
            border-radius: 6px !important;
            padding: 14px 16px !important;
            margin-bottom: 10px !important;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        table { width: 100% !important; min-width: 0 !important; }
        table td, table th { padding: 5px 6px !important; font-size: 9.5pt !important; }
        tr { page-break-inside: avoid; break-inside: avoid; }

        [style*="overflow"] { overflow: visible !important; }
    }
</style>

<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- ─────────── BACK LINK ─────────── --}}
    <a href="{{ route('vaccination.calendar') }}"
       class="no-print"
       title="Return to Schedule Calendar"
       style="display:inline-flex; align-items:center; gap:8px; align-self:flex-start;
              padding:10px 18px; border-radius:10px;
              background:#ffffff; color:#0f5c4b;
              border:1px solid #42ad8a;
              font-size:15px; font-weight:600; text-decoration:none;
              box-shadow:0 2px 6px rgba(66,173,138,0.08);
              transition:all 0.2s;"
       onmouseover="this.style.background='#E8F7F4'; this.style.borderColor='#369f7d'; this.style.transform='translateX(-3px)';"
       onmouseout="this.style.background='#ffffff'; this.style.borderColor='#42ad8a'; this.style.transform='translateX(0)';">

        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"/>
            <polyline points="12 19 5 12 12 5"/>
        </svg>

        Back to Schedule Calendar
    </a>

    {{-- ─────────── CHILD HEADER ─────────── --}}
    <div class="print-card"
         style="background: rgba(255,255,255,0.95); border-radius: 20px;
                border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
                padding: 24px 28px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:20px; flex-wrap:wrap;">
            <div style="flex:1; min-width:240px;">
                <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">
                    Immunization Timeline
                </p>
                <h2 style="font-size:28px; font-weight:700; color:#0f172a; margin:0 0 8px 0;">
                    {{ $child->full_name }}
                </h2>

                <div style="display:flex; gap:20px; flex-wrap:wrap; font-size:15px; color:#475569;">
                    @if($child->age_display)
                        <span><strong style="color:#0f172a;">Age:</strong> {{ $child->age_display }}</span>
                    @endif
                    @if(!empty($child->barangay))
                        <span><strong style="color:#0f172a;">Barangay:</strong> {{ $child->barangay }}</span>
                    @endif
                    @if($child->birth_date)
                        <span>
                            <strong style="color:#0f172a;">Born:</strong>
                            {{ \Carbon\Carbon::parse($child->birth_date)->format('M d, Y') }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ─────────── PROGRESS STATS ─────────── --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px;">

        @php
            $tiles = [
                ['label' => 'Total Doses', 'value' => $stats['total'],   'bg' => '#F1F5F9', 'text' => '#0f172a', 'border' => '#cbd5e1'],
                ['label' => 'Given',       'value' => $stats['given'],   'bg' => '#E8F7F4', 'text' => '#0f5c4b', 'border' => '#42ad8a'],
                ['label' => 'Pending',     'value' => $stats['pending'], 'bg' => '#FEF3C7', 'text' => '#92400E', 'border' => '#FAC775'],
                ['label' => 'Missed',      'value' => $stats['missed'],  'bg' => '#FCEBEB', 'text' => '#991b1b', 'border' => '#F7C1C1'],
            ];
        @endphp

        @foreach($tiles as $t)
            <div style="background:{{ $t['bg'] }}; border:1px solid {{ $t['border'] }}; border-radius:14px; padding:14px 16px; text-align:center;">
                <p style="font-size:28px; font-weight:800; color:{{ $t['text'] }}; margin:0; line-height:1;">{{ $t['value'] }}</p>
                <p style="font-size:12px; font-weight:700; color:{{ $t['text'] }}; text-transform:uppercase; letter-spacing:.04em; margin:6px 0 0 0;">
                    {{ $t['label'] }}
                </p>
            </div>
        @endforeach
    </div>

    {{-- ─────────── SCHEDULE TABLE ─────────── --}}
    <div class="print-card"
         style="background: rgba(255,255,255,0.95); border-radius: 20px;
                border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
                padding: 24px;">

        <div style="margin-bottom:16px;">
            <h3 style="font-size:19px; font-weight:700; color:#0f172a; margin:0 0 4px 0;">
                Vaccination Schedule
            </h3>
            <p style="font-size:14px; color:#64748b; margin:0;">
                Full EPI timeline per DOH NIP protocol
            </p>
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th style="padding:12px 14px; text-align:left; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569; border-bottom:1px solid #e2e8f0;">Vaccine</th>
                        <th style="padding:12px 14px; text-align:center; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569; border-bottom:1px solid #e2e8f0;">Dose</th>
                        <th style="padding:12px 14px; text-align:left; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569; border-bottom:1px solid #e2e8f0;">Due Date</th>
                        <th style="padding:12px 14px; text-align:left; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569; border-bottom:1px solid #e2e8f0;">Given</th>
                        <th style="padding:12px 14px; text-align:center; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569; border-bottom:1px solid #e2e8f0;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedules as $s)
                        @php
                            $statusColor = match ($s->status) {
                                'given'  => ['bg' => '#E8F7F4', 'text' => '#0f5c4b', 'border' => '#42ad8a'],
                                'missed' => ['bg' => '#FCEBEB', 'text' => '#991b1b', 'border' => '#F7C1C1'],
                                default  => $s->is_overdue
                                    ? ['bg' => '#FEF3C7', 'text' => '#92400E', 'border' => '#FAC775']
                                    : ['bg' => '#FEF3C7', 'text' => '#92400E', 'border' => '#FAC775'],
                            };
                            $statusLabel = match ($s->status) {
                                'given'  => 'Given',
                                'missed' => 'Missed',
                                default  => $s->is_overdue ? 'Overdue' : 'Pending',
                            };
                        @endphp
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:12px 14px; font-weight:600; color:#0f172a;">
                                {{ $s->vaccine_name }}
                            </td>
                            <td style="padding:12px 14px; text-align:center; color:#334155;">
                                {{ $s->dose_number }}
                            </td>
                            <td style="padding:12px 14px; color:#334155; white-space:nowrap;">
                                {{ $s->due_date ? \Carbon\Carbon::parse($s->due_date)->format('M d, Y') : '—' }}
                            </td>
                            <td style="padding:12px 14px; color:#334155; white-space:nowrap;">
                                {{ $s->given_date ? \Carbon\Carbon::parse($s->given_date)->format('M d, Y') : '—' }}
                            </td>
                            <td style="padding:12px 14px; text-align:center;">
                                <span style="display:inline-block; padding:4px 12px; border-radius:999px;
                                             font-size:12px; font-weight:700;
                                             background:{{ $statusColor['bg'] }}; color:{{ $statusColor['text'] }}; border:1px solid {{ $statusColor['border'] }};">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding:40px 20px; text-align:center; color:#64748b; background:#f8fafc;
                                                   border:1px dashed #cbd5e1; border-radius:12px;">
                                No schedule for this patient yet. Regenerate from the calendar page.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection