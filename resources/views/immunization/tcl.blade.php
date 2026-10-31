@extends('layouts.app')

@section('content')

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- PRINT STYLES                                                    --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<style>
    .print-header { display: none; }

    @media print {
        @page { margin: 12mm 10mm; }

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

        .print-header {
            display: block !important;
            border-bottom: 2px solid #0f5c4b;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        [style*="overflow"] { overflow: visible !important; }

        table { font-size: 10pt !important; }
        th, td { padding: 4px 6px !important; }
        .dose-cell { pointer-events: none; box-shadow: none !important; transform: none !important; }
    }
</style>

@php
    $groups = $schedule->groupBy('recommended_age_label');

    $shortLabel = [
        'bcg'   => 'BCG',
        'hepb'  => 'HepB',
        'penta' => 'Penta',
        'opv'   => 'OPV',
        'ipv'   => 'IPV',
        'pcv'   => 'PCV',
        'mmr'   => 'MMR',
        'rota'  => 'Rota',
    ];

    $formatAge = function (?int $days): string {
        if ($days === null) return '—';
        if ($days < 30)   return $days . 'd';
        $months = intdiv($days, 30);
        if ($months < 24) return $months . 'mo';
        return intdiv($months, 12) . 'y ' . ($months % 12) . 'mo';
    };

    $statusStyle = [
        'completed'     => ['bg' => '#DCFCE7', 'color' => '#166534', 'border' => '#86EFAC', 'label' => 'Done'],
        'due'           => ['bg' => '#FEF3C7', 'color' => '#92400E', 'border' => '#FCD34D', 'label' => 'Due'],
        'overdue'       => ['bg' => '#FFE4CC', 'color' => '#B85C00', 'border' => '#FFB870', 'label' => 'Overdue'],
        'out_of_window' => ['bg' => '#FEE2E2', 'color' => '#991B1B', 'border' => '#FCA5A5', 'label' => 'Missed'],
        'not_due'       => ['bg' => '#F1F5F9', 'color' => '#94A3B8', 'border' => '#E2E8F0', 'label' => '—'],
    ];
@endphp

<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- PRINT-ONLY HEADER                                              --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="print-header">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="vertical-align:bottom; padding:0;">
                    <p style="font-size:9pt; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#0f5c4b; margin:0 0 2px 0;">
                        Provincial Health Office — Immunization Tracking
                    </p>
                    <p style="font-size:14pt; font-weight:700; color:#000; margin:0;">
                        Target Client List
                    </p>
                    <p style="font-size:10pt; color:#334155; margin:2px 0 0 0;">
                        {{ $children->count() }} child{{ $children->count() === 1 ? '' : 'ren' }} shown
                        @if(request('search')) · Search: "{{ request('search') }}" @endif
                        @if($filter !== 'all') · Filter: {{ ucfirst($filter) }} @endif
                    </p>
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

    {{-- ─────────── HEADER ─────────── --}}
    <div class="no-print" style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius: 20px; padding: 24px 28px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 16px;
    ">
        <div>
            <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">
                National Immunization Program
            </p>
            <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">
                Immunization Tracking
            </h2>
        </div>

        <button type="button" onclick="window.print()" style="
            display:inline-flex; align-items:center; gap:8px;
            font-size:15px; font-weight:600; padding:10px 18px;
            border-radius:10px; background:#ffffff; color:#334155;
            border:1px solid #e2e8f0; cursor:pointer;
            box-shadow:0 2px 6px rgba(0,0,0,0.04);
        ">
            🖨 Print
        </button>
    </div>

    {{-- ─────────── FILTERS + LEGEND ─────────── --}}
    <div class="no-print" style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius: 16px; padding: 18px 22px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 14px;
    ">
        <form method="GET" action="{{ route('immunization.tcl') }}" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search child name..."
                   style="height:42px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; outline:none; width:230px;">
            <select name="filter" onchange="this.form.submit()"
                    style="height:42px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; outline:none; background:white;">
                <option value="all"     {{ $filter === 'all'     ? 'selected' : '' }}>All Children</option>
                <option value="due"     {{ $filter === 'due'     ? 'selected' : '' }}>With Due Doses</option>
                <option value="overdue" {{ $filter === 'overdue' ? 'selected' : '' }}>With Overdue Doses</option>
            </select>
            <button type="submit" style="height:42px; padding:0 20px; background:#42ad8a; color:white; border:none; border-radius:10px; font-weight:600; font-size:15px; cursor:pointer;">
                Apply
            </button>
            @if(request('search') || $filter !== 'all')
                <a href="{{ route('immunization.tcl') }}" style="font-size:15px; font-weight:600; color:#64748b; text-decoration:none; padding:0 8px;">Reset</a>
            @endif
        </form>

        <div style="display:flex; gap:14px; flex-wrap:wrap; align-items:center;">
            @foreach($statusStyle as $key => $s)
                @if($key === 'not_due') @continue @endif
                <span style="display:inline-flex; align-items:center; gap:6px; font-size:14px; color:#475569;">
                    <span style="display:inline-block; width:14px; height:14px; border-radius:4px; background:{{ $s['bg'] }}; border:1px solid {{ $s['border'] }};"></span>
                    {{ $s['label'] }}
                </span>
            @endforeach
        </div>
    </div>

    {{-- ─────────── THE GRID ─────────── --}}
    <div style="
        background: rgba(255,255,255,0.95);
        border-radius: 20px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        overflow: hidden;
    ">
        @if($children->count() > 0)
            <div style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch;">
                <table style="border-collapse: separate; border-spacing: 0; font-size: 14px; min-width: 100%;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th rowspan="2" style="
                                position: sticky; left: 0; z-index: 20;
                                background: #f8fafc;
                                padding: 14px 16px; text-align: left;
                                font-size: 13px; font-weight: 700; text-transform: uppercase;
                                letter-spacing: 0.05em; color: #475569;
                                border-bottom: 1px solid #e2e8f0; border-right: 2px solid #cbd5e1;
                                min-width: 260px;
                            ">Child</th>

                            @foreach($groups as $ageLabel => $rows)
                                <th colspan="{{ $rows->count() }}" style="
                                    padding: 10px 8px; text-align: center;
                                    font-size: 13px; font-weight: 700; text-transform: uppercase;
                                    letter-spacing: 0.05em; color: #0f5c4b;
                                    background: #E8F7F4;
                                    border-bottom: 1px solid #e2e8f0;
                                    border-left: 1px solid #d1e7e0;
                                    white-space: nowrap;
                                ">{{ $ageLabel }}</th>
                            @endforeach
                        </tr>

                        <tr style="background:#f8fafc;">
                            @foreach($groups as $ageLabel => $rows)
                                @foreach($rows as $row)
                                    <th title="{{ $row->antigen_label }} — Dose {{ $row->dose_number }} ({{ $row->recommended_age_label }})"
                                        style="
                                            padding: 8px 6px; text-align: center;
                                            font-size: 12px; font-weight: 700;
                                            letter-spacing: 0.03em; color: #475569;
                                            border-bottom: 1px solid #e2e8f0; border-left: 1px solid #eef2f6;
                                            min-width: 52px; max-width: 52px;
                                            white-space: nowrap;
                                        ">
                                        {{ $shortLabel[$row->antigen] ?? strtoupper($row->antigen) }}-{{ $row->dose_number }}
                                    </th>
                                @endforeach
                            @endforeach
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($children as $child)
                            @php
                                $info  = $bulk[$child->id] ?? ['overall' => 'unknown', 'age_days' => null, 'doses' => []];
                                $childDoses = $info['doses'];
                            @endphp
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="
                                    position: sticky; left: 0; z-index: 10;
                                    background: #ffffff;
                                    padding: 12px 16px;
                                    border-right: 2px solid #cbd5e1;
                                    vertical-align: top;
                                ">
                                    <p style="font-size: 15px; font-weight: 600; color: #0f172a; margin: 0 0 3px 0; white-space: nowrap;">
                                        {{ $child->full_name }}
                                    </p>
                                    <p style="font-size: 12px; color: #64748b; margin: 0;">
                                        {{ $child->birth_date ? \Carbon\Carbon::parse($child->birth_date)->format('M d, Y') : 'No DOB' }}
                                        &bull; {{ $formatAge($info['age_days']) }}
                                        @if($info['overall'] === 'has_overdue')
                                            <span style="color:#B85C00; font-weight:700;"> · OVERDUE</span>
                                        @elseif($info['overall'] === 'has_due')
                                            <span style="color:#92400E; font-weight:700;"> · DUE</span>
                                        @elseif($info['overall'] === 'up_to_date')
                                            <span style="color:#166534; font-weight:700;"> · COMPLETE</span>
                                        @endif
                                    </p>

                                    {{-- Per-child drill-downs: Schedule + History --}}
                                    <div class="no-print" style="display:flex; gap:6px; flex-wrap:wrap; margin-top:6px;">
                                        <a href="{{ route('vaccination.child', $child->id) }}"
                                           title="View full vaccine schedule for {{ $child->full_name }}"
                                           style="display:inline-flex; align-items:center; gap:4px;
                                                  font-size:12px; font-weight:600; padding:4px 10px;
                                                  border-radius:6px; background:#E8F7F4; color:#0f5c4b;
                                                  border:1px solid #42ad8a; text-decoration:none; white-space:nowrap;
                                                  transition: background 0.15s;"
                                           onmouseover="this.style.background='#d2efe6';"
                                           onmouseout="this.style.background='#E8F7F4';">
                                            📋 Schedule
                                        </a>
                                        <a href="{{ route('immunization.history', $child->id) }}"
                                           title="View recorded immunization history for {{ $child->full_name }}"
                                           style="display:inline-flex; align-items:center; gap:4px;
                                                  font-size:12px; font-weight:600; padding:4px 10px;
                                                  border-radius:6px; background:#EFF6FF; color:#1e40af;
                                                  border:1px solid #93c5fd; text-decoration:none; white-space:nowrap;
                                                  transition: background 0.15s;"
                                           onmouseover="this.style.background='#DBEAFE';"
                                           onmouseout="this.style.background='#EFF6FF';">
                                            📜 History
                                        </a>
                                    </div>
                                </td>

                                @foreach($groups as $ageLabel => $rows)
                                    @foreach($rows as $row)
                                        @php
                                            $key  = $row->antigen . ':' . $row->dose_number;
                                            $cell = $childDoses[$key] ?? ['status' => 'not_due', 'date_given' => null];
                                            $style = $statusStyle[$cell['status']] ?? $statusStyle['not_due'];

                                            $isClickable = in_array($cell['status'], ['due', 'overdue', 'out_of_window', 'not_due']);
                                        @endphp
                                        <td style="padding: 5px 3px; text-align: center; vertical-align: middle; border-left: 1px solid #eef2f6;">
                                            <span
                                                @if($isClickable)
                                                    role="button"
                                                    tabindex="0"
                                                    class="dose-cell"
                                                    data-child-id="{{ $child->id }}"
                                                    data-child-name="{{ $child->full_name }}"
                                                    data-vaccine="{{ $row->antigen }}"
                                                    data-vaccine-label="{{ $row->antigen_label }}"
                                                    data-dose-number="{{ $row->dose_number }}"
                                                    data-age-label="{{ $row->recommended_age_label }}"
                                                    data-status="{{ $cell['status'] }}"
                                                @endif
                                                title="{{ $child->full_name }} — {{ $row->antigen_label }} Dose {{ $row->dose_number }} — {{ $style['label'] }}{{ $cell['date_given'] ? ' on ' . $cell['date_given'] : '' }}{{ $isClickable ? ' · Click to record' : '' }}"
                                                style="
                                                    display: inline-flex; flex-direction: column;
                                                    align-items: center; justify-content: center;
                                                    min-width: 46px; min-height: 40px;
                                                    padding: 4px 6px;
                                                    border-radius: 6px;
                                                    background: {{ $style['bg'] }};
                                                    color: {{ $style['color'] }};
                                                    border: 1px solid {{ $style['border'] }};
                                                    font-size: 12px; font-weight: 700;
                                                    line-height: 1.1;
                                                    {{ $isClickable ? 'cursor: pointer; transition: transform 0.15s, box-shadow 0.15s;' : '' }}
                                                "
                                                @if($isClickable)
                                                    onmouseover="this.style.transform='scale(1.08)'; this.style.boxShadow='0 4px 10px rgba(0,0,0,0.12)';"
                                                    onmouseout="this.style.transform='none'; this.style.boxShadow='none';"
                                                    onclick="openDoseModal(this)"
                                                @endif
                                            >
                                                @if($cell['date_given'])
                                                    {{ \Carbon\Carbon::parse($cell['date_given'])->format('m/d') }}
                                                @elseif($cell['status'] === 'overdue')
                                                    !
                                                @elseif($cell['status'] === 'due')
                                                    •
                                                @else
                                                    &nbsp;
                                                @endif
                                            </span>
                                        </td>
                                    @endforeach
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="padding: 60px 24px; text-align: center;">
                <p style="font-size: 17px; font-weight: 600; color: #334155; margin: 0 0 8px 0;">No children found</p>
                <p style="font-size: 15px; color: #64748b; margin: 0 0 18px 0;">
                    @if(request('search') || $filter !== 'all')
                        Try clearing your filters.
                    @else
                        Register children in the Patient Registry first, then come back to track their immunizations.
                    @endif
                </p>
                @if(!request('search') && $filter === 'all')
                    <a href="{{ route('public_users.index') }}" style="
                        display:inline-flex; align-items:center; gap:6px;
                        font-size:15px; font-weight:600; padding:10px 20px;
                        border-radius:10px; background:#42ad8a; color:white;
                        text-decoration:none;
                    ">Go to Patient Registry →</a>
                @endif
            </div>
        @endif
    </div>

    @if($children->count() > 0)
        <p class="no-print" style="text-align:center; font-size:14px; color:#64748b; margin:0;">
            <strong>{{ $children->count() }}</strong> child{{ $children->count() === 1 ? '' : 'ren' }} shown
            &bull; Cell legend: a date means done &bull; <strong>!</strong> = overdue &bull; <strong>•</strong> = due now &bull; blank = not yet scheduled
            &bull; Click any cell to record
        </p>
    @endif
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- RECORD DOSE MODAL                                               --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div id="dose-modal" onclick="if(event.target === this) closeDoseModal()" style="
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(15,23,42,0.5); backdrop-filter: blur(2px);
    align-items: center; justify-content: center; padding: 16px;
">
    <div style="
        background: #ffffff; border-radius: 18px;
        padding: 26px; max-width: 500px; width: 100%;
        box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        max-height: 90vh; overflow-y: auto;
    ">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:20px;">
            <div>
                <p style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0 0 4px 0;">
                    Record Immunization
                </p>
                <h3 id="dm-vaccine-title" style="font-size:20px; font-weight:700; color:#0f172a; margin:0; line-height:1.25;">
                    Vaccine
                </h3>
            </div>
            <button type="button" onclick="closeDoseModal()" aria-label="Close"
                    style="background:transparent; border:none; cursor:pointer; padding:4px 10px; color:#64748b; font-size:24px; line-height:1; border-radius:8px;">
                ✕
            </button>
        </div>

        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:13px; margin-bottom:18px;">
            <p style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.05em; margin:0 0 4px 0;">Child</p>
            <p id="dm-child-name" style="font-size:15px; font-weight:600; color:#0f172a; margin:0;">—</p>
            <p id="dm-age-label" style="font-size:13px; color:#64748b; margin:4px 0 0 0;">—</p>
        </div>

        <form id="dose-form" method="POST" action="{{ route('immunization.store') }}">
            @csrf

            <input type="hidden" name="public_user_id" id="dm-child-id" value="">
            <input type="hidden" name="vaccine"        id="dm-vaccine"  value="">
            <input type="hidden" name="dose_number"    id="dm-dose"     value="">

            <div style="display:grid; gap:14px;">
                <div>
                    <label for="dm-date" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;">
                        Date Given <span style="color:#dc2626;">*</span>
                    </label>
                    <input type="date" name="date_given" id="dm-date" required
                           value="{{ now()->format('Y-m-d') }}"
                           style="width:100%; height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box; background:#fff; color:#0f172a;">
                </div>

                <div>
                    <label for="dm-batch" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;">
                        Batch / Lot Number
                    </label>
                    <input type="text" name="batch_no" id="dm-batch" maxlength="50"
                           placeholder="Optional — printed on the vial"
                           style="width:100%; height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box; background:#fff; color:#0f172a;">
                </div>

                <div>
                    <label for="dm-remarks" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;">
                        Remarks
                    </label>
                    <textarea name="remarks" id="dm-remarks" rows="2" maxlength="500"
                              placeholder="Optional — e.g., mild fever after previous dose"
                              style="width:100%; padding:11px 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box; resize:vertical; font-family:inherit; background:#fff; color:#0f172a;"></textarea>
                </div>
            </div>

            <div style="display:flex; gap:10px; margin-top:22px;">
                <button type="button" onclick="closeDoseModal()"
                        style="flex:1; height:44px; border-radius:10px; background:#ffffff; color:#334155; border:1px solid #e2e8f0; font-weight:600; font-size:15px; cursor:pointer;">
                    Cancel
                </button>
                <button type="submit"
                        style="flex:2; height:44px; border-radius:10px; background:#42ad8a; color:#ffffff; border:none; font-weight:700; font-size:15px; cursor:pointer;">
                    ✓ Record Dose
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openDoseModal(cell) {
    const d = cell.dataset;

    document.getElementById('dm-child-id').value     = d.childId;
    document.getElementById('dm-child-name').textContent = d.childName;
    document.getElementById('dm-vaccine').value      = d.vaccine;
    document.getElementById('dm-dose').value         = d.doseNumber;
    document.getElementById('dm-vaccine-title').textContent =
        d.vaccineLabel + ' — Dose ' + d.doseNumber;
    document.getElementById('dm-age-label').textContent =
        'Scheduled: ' + d.ageLabel +
        (d.status === 'overdue' ? ' · Overdue — record catch-up' : '');

    document.getElementById('dm-date').value = '{{ now()->format("Y-m-d") }}';
    document.getElementById('dm-batch').value = '';
    document.getElementById('dm-remarks').value = '';

    document.getElementById('dose-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';

    setTimeout(() => document.getElementById('dm-date').focus(), 50);
}

function closeDoseModal() {
    document.getElementById('dose-modal').style.display = 'none';
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function (e) {
    const modal = document.getElementById('dose-modal');
    if (modal && modal.style.display === 'flex' && e.key === 'Escape') {
        closeDoseModal();
    }
});

document.querySelectorAll('.dose-cell').forEach(function (el) {
    el.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openDoseModal(el);
        }
    });
});
</script>
@endsection