@extends('layouts.app')

@section('content')

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- PRINT STYLES                                                    --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<style>
    .print-header { display: none; }

    @media print {
        @page { margin: 14mm 12mm; }

        html, body {
            overflow: visible !important;
            height: auto !important;
            min-height: 0 !important;
            width: auto !important;
            background: #ffffff !important;
            font-size: 11pt !important;
            color: #000 !important;
        }

        .app-shell {
            display: block !important;
            min-height: 0 !important;
            height: auto !important;
            overflow: visible !important;
        }

        .main-content {
            overflow: visible !important;
            height: auto !important;
            max-height: none !important;
            padding: 0 !important;
            margin: 0 !important;
            flex: none !important;
        }

        .print-wrapper {
            overflow: visible !important;
            display: block !important;
            height: auto !important;
        }

        [style*="overflow"] {
            overflow: visible !important;
            overflow-x: visible !important;
            overflow-y: visible !important;
        }

        .no-print,
        .sidebar,
        .public-header,
        .public-footer,
        nav,
        button,
        .pagination { display: none !important; }

        .print-header {
            display: block !important;
            border-bottom: 2px solid #0f5c4b;
            padding-bottom: 10px;
            margin-bottom: 18px;
        }

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

        h3 { page-break-after: avoid; break-after: avoid; font-size: 14pt !important; margin: 0 0 8px 0 !important; }
        table { page-break-inside: auto; }
        tr { page-break-inside: avoid; break-inside: avoid; }

        table {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            table-layout: auto !important;
            border-collapse: collapse !important;
        }

        table td, table th {
            padding: 5px 6px !important;
            font-size: 9.5pt !important;
            word-break: break-word !important;
            overflow-wrap: anywhere !important;
        }

        .print-stats {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 6px !important;
            margin-bottom: 12px !important;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .print-stat-tile {
            padding: 8px 10px !important;
            border-radius: 6px !important;
        }
        .print-stat-tile p:first-child {
            font-size: 18pt !important;
            line-height: 1.1 !important;
        }
        .print-stat-tile p:last-child {
            font-size: 9pt !important;
            margin-top: 2px !important;
        }

        .print-patient-header {
            padding: 12px 16px !important;
            margin-bottom: 8px !important;
        }
        .print-patient-header h2 { font-size: 18pt !important; }

        * { transition: none !important; }
    }
</style>

<div class="print-wrapper" style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- PRINT-ONLY HEADER                                              --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="print-header">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="vertical-align:bottom; padding:0;">
                    <p style="font-size:9pt; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#0f5c4b; margin:0 0 2px 0;">
                        Provincial Health Office — Patient Profile
                    </p>
                    <p style="font-size:16pt; font-weight:700; color:#000; margin:0;">
                        {{ $patient->full_name }}
                    </p>
                    @if($patient->birth_date)
                        <p style="font-size:10pt; color:#334155; margin:2px 0 0 0;">
                            DOB: {{ \Carbon\Carbon::parse($patient->birth_date)->format('M d, Y') }}
                            @if($age !== null) · {{ $age }} yr @endif
                            @if(!empty($patient->barangay)) · Brgy. {{ $patient->barangay }} @endif
                        </p>
                    @endif
                </td>
                <td style="vertical-align:bottom; text-align:right; padding:0;">
                    <p style="font-size:9pt; color:#64748b; margin:0;">
                        Printed: {{ now()->format('M d, Y · g:i A') }}
                    </p>
                    @if(session('user_name'))
                        <p style="font-size:9pt; color:#64748b; margin:2px 0 0 0;">
                            By: {{ session('user_name') }}
                        </p>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ─────────── BACK LINK (screen only) ─────────── --}}
    <a href="{{ route('public_users.index') }}"
       class="no-print"
       title="Return to Patient Registry"
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

        Back to Patient Registry
    </a>

    {{-- ─────────── PATIENT HEADER ─────────── --}}
    <div class="print-card print-patient-header"
         style="background: rgba(255,255,255,0.95); border-radius: 20px;
                border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
                padding: 24px 28px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:20px; flex-wrap:wrap;">
            <div style="flex:1; min-width:240px;">
                <p class="no-print" style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">
                    Patient Profile
                </p>
                <h2 style="font-size:28px; font-weight:700; color:#0f172a; margin:0 0 8px 0;">
                    {{ $patient->full_name }}
                </h2>

                <div style="display:flex; gap:20px; flex-wrap:wrap; font-size:15px; color:#475569;">
                    @if($patient->birth_date)
                        <span>
                            <strong style="color:#0f172a;">DOB:</strong>
                            {{ \Carbon\Carbon::parse($patient->birth_date)->format('M d, Y') }}
                            @if($age !== null)
                                · <strong style="color:#0f172a;">{{ $age }}</strong> yr
                                @if($ageMonths < 24 && $ageMonths >= 0)
                                    ({{ $ageMonths }} mo)
                                @endif
                            @endif
                        </span>
                    @endif
                    @if(!empty($patient->sex))
                        <span><strong style="color:#0f172a;">Sex:</strong> {{ ucfirst($patient->sex) }}</span>
                    @endif
                    @if(!empty($patient->barangay))
                        <span><strong style="color:#0f172a;">Barangay:</strong> {{ $patient->barangay }}</span>
                    @endif
                    @if(!empty($patient->contact_number))
                        <span><strong style="color:#0f172a;">Contact:</strong> {{ $patient->contact_number }}</span>
                    @endif
                </div>
            </div>

            {{-- ─────────── HEADER ACTIONS ─────────── --}}
            {{-- Only show the Vaccine Schedule button when the patient actually
                 has EPI records. EPI is a pediatric program; adults and seniors
                 have zero rows in vaccination_schedules, so they never see it. --}}
            @php
                $showVaccineSchedule = ($stats['total_doses'] ?? 0) > 0;
            @endphp

            <div class="no-print" style="display:flex; gap:8px; flex-wrap:wrap;">
                @if($showVaccineSchedule)
                    <a href="{{ route('vaccination.child', $patient->id) }}"
                       title="View the full EPI vaccine schedule for this patient"
                       style="padding:10px 18px; border-radius:10px; background:#42ad8a; color:#fff;
                              border:1px solid #42ad8a; font-size:15px; font-weight:600; text-decoration:none;
                              display:inline-flex; align-items:center; gap:6px;
                              transition:background 0.2s;"
                       onmouseover="this.style.background='#369f7d'; this.style.borderColor='#369f7d';"
                       onmouseout="this.style.background='#42ad8a'; this.style.borderColor='#42ad8a';">
                        Vaccine Schedule
                    </a>
                @endif

                <button type="button" onclick="window.print()"
                        style="padding:10px 18px; border-radius:10px; background:#fff; color:#334155;
                               border:1px solid #e2e8f0; font-size:15px; font-weight:600; cursor:pointer;">
                    🖨 Print
                </button>
            </div>
        </div>
    </div>

    {{-- ─────────── QUICK STATS ─────────── --}}
    <div class="print-stats"
         style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px;">

        @php
            $tiles = [
                ['label' => 'Doses Given',   'value' => $stats['given_doses'],      'bg' => '#E8F7F4', 'text' => '#0f5c4b', 'border' => '#42ad8a'],
                ['label' => 'Pending',       'value' => $stats['pending_doses'],    'bg' => '#FEF3C7', 'text' => '#92400E', 'border' => '#FAC775'],
                ['label' => 'Overdue',       'value' => $stats['missed_doses'],     'bg' => '#FCEBEB', 'text' => '#991b1b', 'border' => '#F7C1C1'],
                ['label' => 'Prenatal',      'value' => $stats['prenatal_count'],   'bg' => '#E0E7FF', 'text' => '#3730A3', 'border' => '#A5B4FC'],
                ['label' => 'Postpartum',    'value' => $stats['postpartum_count'], 'bg' => '#FFEDD5', 'text' => '#9A3412', 'border' => '#FDBA74'],
                ['label' => 'Family Plan.',  'value' => $stats['fp_count'],         'bg' => '#FCE7F3', 'text' => '#9D174D', 'border' => '#F9A8D4'],
                ['label' => 'Dispensing',    'value' => $stats['dispensing_count'], 'bg' => '#F3E8FF', 'text' => '#6B21A8', 'border' => '#C4B5FD'],
            ];
        @endphp

        @foreach($tiles as $t)
            <div class="print-stat-tile"
                 style="background:{{ $t['bg'] }}; border:1px solid {{ $t['border'] }}; border-radius:14px; padding:14px 16px; text-align:center;">
                <p style="font-size:28px; font-weight:800; color:{{ $t['text'] }}; margin:0; line-height:1;">{{ $t['value'] }}</p>
                <p style="font-size:12px; font-weight:700; color:{{ $t['text'] }}; text-transform:uppercase; letter-spacing:.04em; margin:6px 0 0 0;">
                    {{ $t['label'] }}
                </p>
            </div>
        @endforeach
    </div>

    {{-- ─────────── SECTION JUMP LINKS (screen only) ─────────── --}}
    <div class="no-print" style="display:flex; gap:8px; flex-wrap:wrap;">
        @foreach([
            ['id' => 'immunization', 'label' => '💉 Immunization'],
            ['id' => 'maternal',     'label' => '🤰 Maternal Care'],
            ['id' => 'fp',           'label' => '💊 Family Planning'],
            ['id' => 'dispensing',   'label' => '📦 Dispensing'],
        ] as $jump)
            <a href="#{{ $jump['id'] }}"
               style="padding:8px 16px; border-radius:999px; background:#fff; color:#334155;
                      border:1px solid #e2e8f0; font-size:14px; font-weight:600; text-decoration:none;">
                {{ $jump['label'] }}
            </a>
        @endforeach
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- IMMUNIZATION                                                 --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div id="immunization" class="print-card"
         style="background: rgba(255,255,255,0.95); border-radius: 20px;
                border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
                padding: 24px;">

        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
            <h3 style="font-size:19px; font-weight:700; color:#0f172a; margin:0;">💉 Immunization</h3>
            <p style="font-size:14px; color:#64748b; margin:0;">
                {{ $stats['given_doses'] }} / {{ $stats['total_doses'] }} doses given
            </p>
        </div>

        @if($vaccinations->isEmpty())
            <div style="padding:30px; text-align:center; color:#64748b; background:#f8fafc;
                        border:1px dashed #cbd5e1; border-radius:12px;">
                No vaccine schedule on file.
            </div>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:14px;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="padding:10px 12px; text-align:left; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569; border-bottom:1px solid #e2e8f0;">Vaccine</th>
                            <th style="padding:10px 12px; text-align:center; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569; border-bottom:1px solid #e2e8f0;">Dose</th>
                            <th style="padding:10px 12px; text-align:left; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569; border-bottom:1px solid #e2e8f0;">Due</th>
                            <th style="padding:10px 12px; text-align:left; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569; border-bottom:1px solid #e2e8f0;">Given</th>
                            <th style="padding:10px 12px; text-align:left; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569; border-bottom:1px solid #e2e8f0;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vaccinations as $v)
                            @php
                                $sc = match(true) {
                                    $v->status === 'given' => ['bg' => '#E8F7F4', 'text' => '#0f5c4b', 'border' => '#42ad8a', 'label' => 'Given'],
                                    $v->status === 'missed' => ['bg' => '#FCEBEB', 'text' => '#991b1b', 'border' => '#F7C1C1', 'label' => 'Missed'],
                                    default                 => ['bg' => '#FEF3C7', 'text' => '#92400E', 'border' => '#FAC775', 'label' => 'Pending'],
                                };
                            @endphp
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:10px 12px; color:#0f172a; font-weight:600;">{{ $v->vaccine_name }}</td>
                                <td style="padding:10px 12px; text-align:center; color:#475569;">{{ $v->dose_number }}</td>
                                <td style="padding:10px 12px; color:#475569;">
                                    {{ $v->due_date ? \Carbon\Carbon::parse($v->due_date)->format('M d, Y') : '—' }}
                                </td>
                                <td style="padding:10px 12px; color:#475569;">
                                    {{ $v->given_date ? \Carbon\Carbon::parse($v->given_date)->format('M d, Y') : '—' }}
                                </td>
                                <td style="padding:10px 12px;">
                                    <span style="display:inline-block; padding:4px 12px; border-radius:999px;
                                                 font-size:12px; font-weight:700;
                                                 background:{{ $sc['bg'] }}; color:{{ $sc['text'] }}; border:1px solid {{ $sc['border'] }};">
                                        {{ $sc['label'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- MATERNAL CARE                                                --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div id="maternal" class="print-card"
         style="background: rgba(255,255,255,0.95); border-radius: 20px;
                border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
                padding: 24px;">

        <h3 style="font-size:19px; font-weight:700; color:#0f172a; margin:0 0 16px 0;">🤰 Maternal Care</h3>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:16px;">

            {{-- PRENATAL --}}
            <div>
                <p style="font-size:12px; font-weight:700; text-transform:uppercase; color:#3730A3; margin:0 0 10px 0;">
                    Prenatal Visits ({{ $stats['prenatal_count'] }})
                </p>
                @forelse($prenatalVisits as $p)
                    <div style="padding:12px 14px; background:#E0E7FF; border:1px solid #A5B4FC;
                                border-radius:12px; margin-bottom:8px;">
                        <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                            <p style="font-size:14px; font-weight:700; color:#0f172a; margin:0;">
                                {{ $p->visit_date ? \Carbon\Carbon::parse($p->visit_date)->format('M d, Y') : '—' }}
                            </p>
                            @if(!empty($p->trimester))
                                <span style="font-size:12px; font-weight:700; color:#3730A3;">
                                    {{ $p->trimester }} trimester
                                </span>
                            @endif
                        </div>

                        @php
                            $vitals = array_filter([
                                !empty($p->blood_pressure)   ? 'BP: '.$p->blood_pressure           : null,
                                !empty($p->weight)           ? 'Wt: '.$p->weight.' kg'             : null,
                                !empty($p->fundal_height)    ? 'FH: '.$p->fundal_height.' cm'      : null,
                                !empty($p->fetal_heart_tone) ? 'FHT: '.$p->fetal_heart_tone        : null,
                                !empty($p->tt_dose)          ? 'TT: '.$p->tt_dose                  : null,
                            ]);
                        @endphp
                        @if(count($vitals))
                            <p style="font-size:13px; color:#3730A3; margin:4px 0 0 0;">
                                {{ implode(' · ', $vitals) }}
                            </p>
                        @endif

                        @if(!empty($p->next_visit_date))
                            <p style="font-size:12px; color:#3730A3; margin:4px 0 0 0;">
                                Next visit: <strong>{{ \Carbon\Carbon::parse($p->next_visit_date)->format('M d, Y') }}</strong>
                            </p>
                        @endif

                        @if(!empty($p->remarks))
                            <p style="font-size:13px; color:#475569; margin:6px 0 0 0;">{{ $p->remarks }}</p>
                        @endif
                    </div>
                @empty
                    <div style="padding:20px; text-align:center; color:#64748b; background:#f8fafc;
                                border:1px dashed #cbd5e1; border-radius:12px; font-size:14px;">
                        No prenatal visits recorded.
                    </div>
                @endforelse
            </div>

            {{-- POSTPARTUM --}}
            <div>
                <p style="font-size:12px; font-weight:700; text-transform:uppercase; color:#9A3412; margin:0 0 10px 0;">
                    Postpartum Visits ({{ $stats['postpartum_count'] }})
                </p>
                @forelse($postpartumVisits as $p)
                    <div style="padding:12px 14px; background:#FFEDD5; border:1px solid #FDBA74;
                                border-radius:12px; margin-bottom:8px;">
                        <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                            <p style="font-size:14px; font-weight:700; color:#0f172a; margin:0;">
                                {{ $p->visit_date ? \Carbon\Carbon::parse($p->visit_date)->format('M d, Y') : '—' }}
                            </p>
                            @if(!empty($p->visit_number))
                                <span style="font-size:12px; font-weight:700; color:#9A3412;">
                                    Visit #{{ $p->visit_number }}
                                </span>
                            @endif
                        </div>
                        @if(!empty($p->place_of_delivery) || !empty($p->attended_by))
                            <p style="font-size:13px; color:#9A3412; margin:4px 0 0 0;">
                                @if(!empty($p->place_of_delivery)) Delivered at: {{ $p->place_of_delivery }} @endif
                                @if(!empty($p->attended_by)) · By: {{ $p->attended_by }} @endif
                            </p>
                        @endif
                        @if(!empty($p->remarks))
                            <p style="font-size:13px; color:#475569; margin:6px 0 0 0;">{{ $p->remarks }}</p>
                        @endif
                    </div>
                @empty
                    <div style="padding:20px; text-align:center; color:#64748b; background:#f8fafc;
                                border:1px dashed #cbd5e1; border-radius:12px; font-size:14px;">
                        No postpartum visits recorded.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- FAMILY PLANNING                                              --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div id="fp" class="print-card"
         style="background: rgba(255,255,255,0.95); border-radius: 20px;
                border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
                padding: 24px;">

        <h3 style="font-size:19px; font-weight:700; color:#0f172a; margin:0 0 16px 0;">
            💊 Family Planning ({{ $stats['fp_count'] }})
        </h3>

        @forelse($fpRecords as $f)
            <div style="padding:14px 16px; background:#FCE7F3; border:1px solid #F9A8D4;
                        border-radius:12px; margin-bottom:8px;">
                <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                    <div>
                        <p style="font-size:15px; font-weight:700; color:#0f172a; margin:0;">
                            {{ $f->method ?? '—' }}
                        </p>
                        @if(!empty($f->date_accepted))
                            <p style="font-size:13px; color:#9D174D; margin:4px 0 0 0;">
                                Accepted: {{ \Carbon\Carbon::parse($f->date_accepted)->format('M d, Y') }}
                            </p>
                        @endif
                        @if(!empty($f->next_supply_date))
                            <p style="font-size:13px; color:#9D174D; margin:4px 0 0 0;">
                                Next supply: <strong>{{ \Carbon\Carbon::parse($f->next_supply_date)->format('M d, Y') }}</strong>
                            </p>
                        @endif
                    </div>
                    @if(!empty($f->remarks))
                        <p style="font-size:13px; color:#475569; margin:0; max-width:50%;">{{ $f->remarks }}</p>
                    @endif
                </div>
            </div>
        @empty
            <div style="padding:30px; text-align:center; color:#64748b; background:#f8fafc;
                        border:1px dashed #cbd5e1; border-radius:12px;">
                No family planning records.
            </div>
        @endforelse
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- DISPENSING                                                   --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div id="dispensing" class="print-card"
         style="background: rgba(255,255,255,0.95); border-radius: 20px;
                border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
                padding: 24px;">

        <h3 style="font-size:19px; font-weight:700; color:#0f172a; margin:0 0 6px 0;">
            📦 Dispensing History
            <span style="font-size:14px; color:#64748b; font-weight:500;">(last 30)</span>
        </h3>
        <p class="no-print" style="font-size:12px; color:#94a3b8; margin:0 0 16px 0;">
            Matched by patient name + RHU.
        </p>

        @if($dispensingRecords->isEmpty())
            <div style="padding:30px; text-align:center; color:#64748b; background:#f8fafc;
                        border:1px dashed #cbd5e1; border-radius:12px;">
                No dispensing records.
            </div>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:14px;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="padding:10px 12px; text-align:left; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569; border-bottom:1px solid #e2e8f0;">Date</th>
                            <th style="padding:10px 12px; text-align:left; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569; border-bottom:1px solid #e2e8f0;">Batch</th>
                            <th style="padding:10px 12px; text-align:center; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569; border-bottom:1px solid #e2e8f0;">Qty</th>
                            <th style="padding:10px 12px; text-align:left; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569; border-bottom:1px solid #e2e8f0;">Prescribed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dispensingRecords as $d)
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:10px 12px; color:#475569;">
                                    {{ !empty($d->date_dispensed) ? \Carbon\Carbon::parse($d->date_dispensed)->format('M d, Y') : '—' }}
                                </td>
                                <td style="padding:10px 12px; color:#334155; font-family:monospace;">
                                    {{ $d->batch_no ?? '—' }}
                                </td>
                                <td style="padding:10px 12px; text-align:center; color:#0f172a; font-weight:600;">
                                    {{ $d->quantity_dispensed ?? '—' }}
                                </td>
                                <td style="padding:10px 12px; color:#475569;">
                                    {{ $d->doctor_name ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
@endsection