@extends('layouts.app')

@section('content')
@php
    // ═══════════════════════════════════════════════════════════════
    // Returns the list of barangays that have an EPI session on
    // $dateStr, based on the $epiSchedules collection.
    // Returns NULL if no EPI schedules exist at all (→ don't filter).
    // ═══════════════════════════════════════════════════════════════
    $epiBarangaysOn = function (string $dateStr) use ($epiSchedules) {
        if (!$epiSchedules || $epiSchedules->isEmpty()) return null;

        $d   = \Carbon\Carbon::parse($dateStr);
        $dow = (int) $d->dayOfWeek;   // 0=Sun .. 6=Sat
        $matches = [];

        foreach ($epiSchedules as $s) {
            if ((int) $s->weekday !== $dow) continue;

            $ok = match ($s->pattern) {
                'weekly'        => true,
                'monthly_first' => $d->day <= 7,
                'monthly_last'  => $d->day > ($d->copy()->endOfMonth()->day - 7),
                'monthly_nth'   => (int) ceil($d->day / 7) === (int) ($s->week_number ?? 1),
                default         => false,
            };

            if ($ok) $matches[] = $s->area_label;
        }

        return array_values(array_unique($matches));
    };
@endphp

<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    <style>
        .cal-nav-btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 10px 14px; border-radius: 10px;
            background: #ffffff; color: #334155; border: 1px solid #e2e8f0;
            text-decoration: none; font-weight: 600; font-size: 16px;
            transition: all 0.2s; min-width: 44px;
        }
        .cal-nav-btn:hover { background: #f8fafc; border-color: #42ad8a; color: #0f5c4b; }

        .cal-select {
            height: 44px; padding: 0 14px; font-size: 16px; font-weight: 600;
            border: 2px solid #bec6cf; border-radius: 10px;
            background: white; color: #0f172a; outline: none; appearance: auto;
            cursor: pointer; min-width: 140px;
        }
        .cal-select:focus { border-color: #42ad8a; box-shadow: 0 0 0 3px rgba(66,173,138,0.1); }

        .cal-grid-wrap {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            min-width: 0;
        }
        .cal-grid-wrap > * { min-width: 0; }

        .cal-cell {
            display: block; padding: 8px 6px; min-height: 110px;
            border-right: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
            text-decoration: none;
            transition: background 0.15s;
            position: relative;
            min-width: 0;
            overflow: hidden;
        }
        .cal-cell:hover { background: #f8fafc; }
        .cal-cell.is-out { background: #f8fafc; opacity: 0.55; }
        .cal-cell.is-today { background: #EFF6FF; }
        .cal-cell.is-selected { background: #E8F7F4; box-shadow: inset 0 0 0 2px #42ad8a; }

        .cal-day-num { font-size: 15px; font-weight: 600; color: #0f172a; line-height: 1; }
        .cal-day-num.is-today { color: #1e40af; font-weight: 700; }
        .cal-day-num.is-out { color: #94a3b8; }

        .cal-chip {
            display: block; margin-top: 3px; padding: 2px 6px;
            font-size: 11px; font-weight: 700; line-height: 1.3;
            border-radius: 4px; white-space: nowrap;
            overflow: hidden; text-overflow: ellipsis;
            max-width: 100%;
        }
        .cal-chip.pending    { background: #FEF3C7; color: #92400E; }
        .cal-chip.overdue    { background: #FCEBEB; color: #991b1b; }
        .cal-chip.given      { background: #E8F7F4; color: #0f5c4b; }
        .cal-chip.fp         { background: #FCE7F3; color: #9D174D; }
        .cal-chip.prenatal   { background: #E0E7FF; color: #3730A3; }
        .cal-chip.postpartum { background: #FFEDD5; color: #9A3412; }
        .cal-chip.event { background: #F3E8FF; color: #6B21A8; border-left: 3px solid #8B5CF6; padding-left: 5px; }
        .cal-chip.event.high { background: #FEF3C7; color: #92400E; border-left-color: #F59E0B; }
        .cal-chip.event.emergency { background: #FCEBEB; color: #991b1b; border-left-color: #DC2626; font-weight: 800; }
        .cal-chip.doh { background: #DBEAFE; color: #1E40AF; border-left: 3px solid #3B82F6; padding-left: 5px; }

        .cal-more { font-size: 11px; font-weight: 700; color: #64748b; padding: 2px 6px; }

        .cal-badges { display: flex; gap: 3px; align-items: center; flex-wrap: wrap; }
        .cal-badge {
            font-size: 10px; font-weight: 700;
            padding: 1px 5px; border-radius: 999px;
            display: inline-flex; align-items: center;
        }
        .cal-badge.doses      { background: #E8F7F4; color: #0f5c4b; border: 1px solid #42ad8a; }
        .cal-badge.fp         { background: #FCE7F3; color: #9D174D; border: 1px solid #F9A8D4; }
        .cal-badge.prenatal   { background: #E0E7FF; color: #3730A3; border: 1px solid #A5B4FC; }
        .cal-badge.postpartum { background: #FFEDD5; color: #9A3412; border: 1px solid #FDBA74; }
        .cal-badge.events     { background: #F3E8FF; color: #6B21A8; border: 1px solid #C4B5FD; }
        .cal-badge.doh        { background: #DBEAFE; color: #1E40AF; border: 1px solid #3B82F6; }

        .cal-event-area {
            display: inline-block; font-size: 10px; font-weight: 700;
            background: rgba(15,23,42,0.06); color: #475569;
            padding: 1px 6px; border-radius: 999px; margin-top: 4px;
        }

        @media (max-width: 640px) {
            .cal-cell { min-height: 78px; padding: 5px 3px; }
            .cal-day-num { font-size: 13px; }
            .cal-chip { font-size: 9px; padding: 1px 3px; }
            .cal-badge { font-size: 9px; padding: 0 4px; }
        }

        @media print { .no-print { display: none !important; } }
    </style>

    {{-- HEADER --}}
    <div class="no-print" style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius: 20px; padding: 24px 28px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 16px;">
        <div>
            <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">
                Vaccination
            </p>
            <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">
                Schedule Calendar
            </h2>
        </div>

        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <form method="POST" action="{{ route('vaccination.generate_epi_days') }}" style="display:inline;"
                  onsubmit="return confirmDialog(event, 'Generate EPI day events for all areas for the next 12 months?');">
                @csrf
                <button type="submit" style="
                    display:inline-flex; align-items:center; gap:6px;
                    padding:10px 18px; border-radius:10px;
                    background:#ffffff; color:#0f5c4b; border:1px solid #42ad8a;
                    font-size:16px; font-weight:600; cursor:pointer;">
                    🔄 Generate EPI Days
                </button>
            </form>

            <form method="POST" action="{{ route('vaccination.clear_epi_days') }}"
                  id="clear-epi-form"
                  onsubmit="return confirmClearEpi(event);"
                  style="display:inline-flex; align-items:center; gap:6px;">
                @csrf

                @if(($epiSchedules ?? collect())->count() > 0)
                    <select name="area_label" id="clear-epi-area" style="
                        height:44px; padding:0 14px; font-size:15px;
                        border:2px solid #fca5a5; border-radius:10px;
                        background:#ffffff; color:#991b1b; font-weight:700;
                        cursor:pointer; outline:none; appearance:auto;">
                        <option value="">— All areas —</option>
                        @foreach($epiSchedules as $sched)
                            <option value="{{ $sched->area_label }}">{{ $sched->area_label }}</option>
                        @endforeach
                    </select>
                @endif

                <button type="submit" style="
                    display:inline-flex; align-items:center; gap:6px;
                    padding:10px 18px; border-radius:10px;
                    background:#ffffff; color:#991b1b; border:1px solid #fca5a5;
                    font-size:16px; font-weight:600; cursor:pointer;">
                    🗑️ Clear EPI Days
                </button>
            </form>

            <button type="button" onclick="openEventModal()" style="
                display:inline-flex; align-items:center; gap:6px;
                padding:10px 18px; border-radius:10px;
                background:#42ad8a; color:#fff; border:1px solid #42ad8a;
                font-size:16px; font-weight:600; cursor:pointer;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Event
            </button>

            <a href="{{ route('vaccination.forecast') }}" style="
                display:inline-flex; align-items:center; gap:6px;
                padding:10px 18px; border-radius:10px;
                background:#ffffff; color:#334155; border:1px solid #e2e8f0;
                font-size:16px; font-weight:600; text-decoration:none;">
                📊 Forecast
            </a>
        </div>
    </div>

    {{-- AREA EPI SCHEDULES --}}
    <div class="no-print" style="
        background: rgba(255,255,255,0.95); border-radius: 20px;
        border: 1px solid rgba(66,173,138,0.15);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        padding: 24px 28px;">

        <div style="margin-bottom:16px;">
            <p style="font-size:16px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">
                Area EPI Schedules
            </p>
            <p style="font-size:15px; color:#64748b; margin:0; line-height:1.5;">
                Add one row per barangay you cover. Each area can have its own day.
            </p>
        </div>

        @if($epiSchedules->count() > 0)
            <div style="
                background:#ffffff; border-radius:12px;
                border:1px solid #e2e8f0; overflow:hidden; margin-bottom:16px;">
                <table style="width:100%; border-collapse:collapse; font-size:15px;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                            <th style="padding:10px 14px; text-align:left; font-size:12px; font-weight:700; text-transform:uppercase; color:#475569;">Area / Barangay</th>
                            <th style="padding:10px 14px; text-align:left; font-size:12px; font-weight:700; text-transform:uppercase; color:#475569;">Schedule</th>
                            <th style="padding:10px 14px; text-align:center; font-size:12px; font-weight:700; text-transform:uppercase; color:#475569; width:100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($epiSchedules as $sched)
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:12px 14px; font-weight:600; color:#0f172a;">
                                    📍 {{ $sched->area_label }}
                                </td>
                                <td style="padding:12px 14px; color:#475569;">
                                    {{ $sched->pattern_label }}
                                </td>
                                <td style="padding:12px 14px; text-align:center;">
                                    <form method="POST"
                                          action="{{ route('vaccination.epi_schedule.destroy', $sched->id) }}"
                                          onsubmit="return confirmDialog(event, 'Remove the EPI schedule for {{ addslashes($sched->area_label) }}? Existing calendar events will be kept.');"
                                          style="display:inline; margin:0;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="
                                            padding:6px 12px; border-radius:8px;
                                            background:#fee2e2; color:#991b1b;
                                            border:1px solid #fca5a5;
                                            font-size:13px; font-weight:700;
                                            cursor:pointer;">
                                            ✕ Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="
                padding:20px; background:#f8fafc;
                border:1px dashed #cbd5e1; border-radius:12px;
                color:#64748b; text-align:center; margin-bottom:16px;">
                <p style="font-size:15px; margin:0; font-weight:600; color:#334155;">
                    No area schedules yet
                </p>
                <p style="font-size:13px; margin:4px 0 0 0;">
                    Add your first area below. Without at least one, "Generate EPI Days" won't create any events.
                </p>
            </div>
        @endif

        <form method="POST" action="{{ route('vaccination.epi_schedule.store') }}"
              style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;
                     padding:16px; background:#E8F7F4;
                     border-radius:12px; border:1px solid #42ad8a;">
            @csrf

            <div style="flex:1 1 200px; min-width:180px;">
                <label style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; display:block; margin-bottom:6px;">
                    Area / Barangay
                </label>
                @if(($barangays ?? collect())->count() > 0)
                    <select name="area_label" required
                            style="width:100%; height:44px; padding:0 14px; font-size:15px;
                                   border:2px solid #bec6cf; border-radius:10px;
                                   background:#fff; box-sizing:border-box;">
                        <option value="">— Select barangay —</option>
                        @foreach($barangays as $b)
                            <option value="{{ $b }}">{{ $b }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="text" name="area_label" required maxlength="120"
                           placeholder="e.g., Brgy. Poblacion"
                           style="width:100%; height:44px; padding:0 14px; font-size:15px;
                                  border:2px solid #bec6cf; border-radius:10px;
                                  background:#fff; box-sizing:border-box;">
                @endif
            </div>

            <div style="flex:1 1 200px; min-width:180px;">
                <label style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; display:block; margin-bottom:6px;">
                    Pattern
                </label>
                <select name="pattern" id="epi-pattern" required onchange="epiToggle()"
                        style="width:100%; height:44px; padding:0 14px; font-size:15px;
                               border:2px solid #bec6cf; border-radius:10px;
                               background:#fff; box-sizing:border-box;">
                    <option value="weekly">Every week on a chosen day</option>
                    <option value="monthly_first">First [weekday] of the month</option>
                    <option value="monthly_last">Last [weekday] of the month</option>
                    <option value="monthly_nth">Nth [weekday] of the month</option>
                </select>
            </div>

            <div style="min-width:140px;">
                <label style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; display:block; margin-bottom:6px;">
                    Weekday
                </label>
                <select name="weekday" required
                        style="width:100%; height:44px; padding:0 14px; font-size:15px;
                               border:2px solid #bec6cf; border-radius:10px;
                               background:#fff; box-sizing:border-box;">
                    <option value="0">Sunday</option>
                    <option value="1">Monday</option>
                    <option value="2">Tuesday</option>
                    <option value="3">Wednesday</option>
                    <option value="4">Thursday</option>
                    <option value="5">Friday</option>
                    <option value="6">Saturday</option>
                </select>
            </div>

            <div style="min-width:100px;" id="epi-weeknum-wrap">
                <label style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; display:block; margin-bottom:6px;">
                    Week #
                </label>
                <select name="week_number"
                        style="width:100%; height:44px; padding:0 14px; font-size:15px;
                               border:2px solid #bec6cf; border-radius:10px;
                               background:#fff; box-sizing:border-box;">
                    <option value="">—</option>
                    <option value="1">1st</option>
                    <option value="2">2nd</option>
                    <option value="3">3rd</option>
                    <option value="4">4th</option>
                </select>
            </div>

            <button type="submit" style="
                height:44px; padding:0 22px; border-radius:10px;
                background:#42ad8a; color:#fff; border:none;
                font-size:15px; font-weight:700; cursor:pointer;
                white-space:nowrap;">
                + Add Schedule
            </button>
        </form>
    </div>

    {{-- CONTROLS --}}
    <div class="no-print" style="
        background: rgba(255,255,255,0.95); border-radius: 20px;
        border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        padding: 20px 24px;">
        <form method="GET" action="{{ route('vaccination.calendar') }}"
              style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between;">

            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <a href="{{ route('vaccination.calendar', ['month' => $start->copy()->subMonth()->format('Y-m')]) }}"
                   class="cal-nav-btn" title="Previous month">←</a>

                <select name="month_picker" class="cal-select" onchange="onMonthPick(this.value)" id="month-picker">
                    @php
                        $currentMonth = $start->month;
                        $currentYear  = $start->year;
                        $months = [
                            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                        ];
                    @endphp
                    @foreach($months as $num => $name)
                        <option value="{{ $num }}" {{ $num === $currentMonth ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>

                <select name="year_picker" class="cal-select" onchange="onYearPick(this.value)" id="year-picker">
                    @for($y = 2026; $y <= 2035; $y++)
                        <option value="{{ $y }}" {{ $y === $currentYear ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>

                <a href="{{ route('vaccination.calendar', ['month' => $start->copy()->addMonth()->format('Y-m')]) }}"
                   class="cal-nav-btn" title="Next month">→</a>
            </div>

            <div style="display:flex; gap:20px; flex-wrap:wrap;">
                <div style="text-align:center;">
                    <p style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.06em; margin:0 0 2px 0;">Due</p>
                    <p style="font-size:22px; font-weight:700; color:#92400E; margin:0;">{{ $monthStats['total_pending'] ?? 0 }}</p>
                </div>
                <div style="text-align:center;">
                    <p style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.06em; margin:0 0 2px 0;">Overdue</p>
                    <p style="font-size:22px; font-weight:700; color:#991b1b; margin:0;">{{ $monthStats['total_overdue'] ?? 0 }}</p>
                </div>
                <div style="text-align:center;">
                    <p style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.06em; margin:0 0 2px 0;">Given</p>
                    <p style="font-size:22px; font-weight:700; color:#0f5c4b; margin:0;">{{ $monthStats['total_given'] ?? 0 }}</p>
                </div>
            </div>
        </form>
    </div>

    {{-- CALENDAR GRID --}}
    <div style="
        background: rgba(255,255,255,0.95); border-radius: 20px;
        border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        overflow: hidden;">

        <div style="padding:16px 24px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <p style="font-size:16px; font-weight:700; color:#0f172a; margin:0;">{{ $start->format('F Y') }}</p>
            <p style="font-size:14px; color:#64748b; margin:0;">Click any day to view details</p>
        </div>

        <div class="cal-grid-wrap" style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
            @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow)
                <div style="padding:12px 6px; text-align:center; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#475569;">
                    {{ $dow }}
                </div>
            @endforeach
        </div>

        @foreach($weeks as $week)
            <div class="cal-grid-wrap">
                @foreach($week as $day)
                    @php
                        $cellDate   = $day['date']->toDateString();
                        $isSelected = $cellDate === $selectedDate;
                        $classes = 'cal-cell';
                        if (!$day['in_month']) $classes .= ' is-out';
                        if ($day['is_today'])  $classes .= ' is-today';
                        if ($isSelected)       $classes .= ' is-selected';

                        // ── Filter doses by EPI barangays on this date ──
                        $cellEpiBarangays = $epiBarangaysOn($cellDate);

                        $dayItems      = $day['items'];
                        $dayChildCount = $day['child_count'];
                        $dayDoseCount  = $day['count'];

                        if ($cellEpiBarangays !== null && count($cellEpiBarangays) > 0) {
                            $dayItems = array_values(array_filter(
                                $dayItems,
                                fn ($it) => in_array($it['barangay'] ?? '', $cellEpiBarangays, true)
                            ));
                            $dayChildCount = count(array_unique(array_column($dayItems, 'child_name')));
                            $dayDoseCount  = count($dayItems);
                        }

                        $preview = [];
                        foreach (($day['doh']        ?? []) as $dp) $preview[] = ['kind' => 'doh',        'data' => $dp];
                        foreach ($day['events']     as $ev)  $preview[] = ['kind' => 'event',      'data' => $ev];
                        foreach ($dayItems          as $it)  $preview[] = ['kind' => 'dose',       'data' => $it];
                        foreach ($day['fp']         as $f)   $preview[] = ['kind' => 'fp',         'data' => $f];
                        foreach ($day['prenatal']   as $p)   $preview[] = ['kind' => 'prenatal',   'data' => $p];
                        foreach ($day['postpartum'] as $p)   $preview[] = ['kind' => 'postpartum', 'data' => $p];

                        $totalItems = count($preview);
                    @endphp
                    <a href="{{ route('vaccination.calendar', ['month' => $month, 'day' => $cellDate]) }}"
                       class="{{ $classes }}">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:4px;">
                            <span class="cal-day-num {{ $day['is_today'] ? 'is-today' : '' }} {{ !$day['in_month'] ? 'is-out' : '' }}">
                                {{ $day['date']->day }}
                            </span>
                        </div>

                        <div class="cal-badges" style="margin-top:4px;">
                            @if($dayChildCount > 0)
                                <span class="cal-badge doses" title="{{ $dayChildCount }} children · {{ $dayDoseCount }} doses">👶 {{ $dayChildCount }}</span>
                            @endif
                            @if($day['fp_count'] > 0)
                                <span class="cal-badge fp" title="{{ $day['fp_count'] }} FP resupplies">💊 {{ $day['fp_count'] }}</span>
                            @endif
                            @if($day['prenatal_count'] > 0)
                                <span class="cal-badge prenatal" title="{{ $day['prenatal_count'] }} prenatal visits">🤰 {{ $day['prenatal_count'] }}</span>
                            @endif
                            @if($day['postpartum_count'] > 0)
                                <span class="cal-badge postpartum" title="{{ $day['postpartum_count'] }} postpartum visits">🍼 {{ $day['postpartum_count'] }}</span>
                            @endif
                            @if($day['event_count'] > 0)
                                <span class="cal-badge events" title="{{ $day['event_count'] }} events">📌 {{ $day['event_count'] }}</span>
                            @endif
                            @if(($day['doh_count'] ?? 0) > 0)
                                <span class="cal-badge doh" title="{{ $day['doh_count'] }} DoH program(s)">🏛️ {{ $day['doh_count'] }}</span>
                            @endif
                        </div>

                        @if($totalItems > 0)
                            <div style="margin-top:4px;">
                                @foreach(array_slice($preview, 0, 2) as $p)
                                    @if($p['kind'] === 'doh')
                                        @php
                                            $dp   = $p['data'];
                                            $hex  = ltrim($dp['color'], '#');
                                            $r    = strlen($hex) === 6 ? hexdec(substr($hex, 0, 2)) : 59;
                                            $g    = strlen($hex) === 6 ? hexdec(substr($hex, 2, 2)) : 130;
                                            $b    = strlen($hex) === 6 ? hexdec(substr($hex, 4, 2)) : 246;
                                        @endphp
                                        <span class="cal-chip doh" style="background:rgba({{ $r }},{{ $g }},{{ $b }},0.13); color:{{ $dp['color'] }}; border-left:3px solid {{ $dp['color'] }};">🏛️ {{ $dp['name'] }}</span>
                                    @elseif($p['kind'] === 'event')
                                        @php $ev = $p['data']; @endphp
                                        <span class="cal-chip event {{ $ev['priority'] }}">
                                            📌 {{ $ev['title'] }}@if(!empty($ev['area_label'])) · {{ $ev['area_label'] }}@endif
                                        </span>
                                    @elseif($p['kind'] === 'dose')
                                        @php $it = $p['data']; @endphp
                                        @php $cc = $it['status'] === 'given' ? 'given' : ($it['is_overdue'] ? 'overdue' : 'pending'); @endphp
                                        <span class="cal-chip {{ $cc }}">{{ $it['vaccine'] }}</span>
                                    @elseif($p['kind'] === 'fp')
                                        <span class="cal-chip fp">💊 {{ $p['data']['method'] }}</span>
                                    @elseif($p['kind'] === 'prenatal')
                                        <span class="cal-chip prenatal">🤰 Prenatal</span>
                                    @elseif($p['kind'] === 'postpartum')
                                        <span class="cal-chip postpartum">🍼 Postpartum</span>
                                    @endif
                                @endforeach
                                @if($totalItems > 2)
                                    <span class="cal-more">+{{ $totalItems - 2 }} more</span>
                                @endif
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>
        @endforeach
    </div>

    {{-- SELECTED DAY PANEL --}}
    <div style="
        background: rgba(255,255,255,0.95); border-radius: 20px;
        border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        padding: 24px;">

        <div style="margin-bottom:20px;">
            <p style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0 0 4px 0;">
                Selected Day
            </p>
            <p style="font-size:20px; font-weight:700; color:#0f172a; margin:0;">
                {{ \Carbon\Carbon::parse($selectedDate)->format('l, F j, Y') }}
            </p>

            @php
                // ── Filter selected-day doses to EPI barangays on this date ──
                $sEpiBarangays = $epiBarangaysOn($selectedDate);
                $sSchedules    = $selectedSchedules;

                if ($sEpiBarangays !== null && count($sEpiBarangays) > 0) {
                    $sSchedules = array_values(array_filter(
                        $sSchedules,
                        fn ($s) => in_array($s['barangay'] ?? '', $sEpiBarangays, true)
                    ));
                }

                $sChildCount = count(array_unique(array_column($sSchedules, 'child_name')));
            @endphp

            @if($sEpiBarangays !== null && count($sEpiBarangays) > 0)
                <p style="font-size:13px; color:#42ad8a; margin:8px 0 0 0; font-weight:600;">
                    📍 EPI session today at:
                    <strong style="color:#0f5c4b;">{{ implode(', ', $sEpiBarangays) }}</strong>
                    — showing only children from these barangays.
                </p>
            @endif

            <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:12px;">
                @if($sChildCount > 0)
                    <div style="padding:8px 16px; border-radius:12px; background:#E8F7F4; border:1px solid #42ad8a;">
                        <span style="font-size:20px; font-weight:700; color:#0f5c4b;">{{ $sChildCount }}</span>
                        <span style="font-size:12px; font-weight:700; color:#0f5c4b; text-transform:uppercase; margin-left:6px;">
                            {{ $sChildCount === 1 ? 'child' : 'children' }}
                        </span>
                    </div>
                @endif
                @if(count($selectedFP) > 0)
                    <div style="padding:8px 16px; border-radius:12px; background:#FCE7F3; border:1px solid #F9A8D4;">
                        <span style="font-size:20px; font-weight:700; color:#9D174D;">{{ count($selectedFP) }}</span>
                        <span style="font-size:12px; font-weight:700; color:#9D174D; text-transform:uppercase; margin-left:6px;">FP</span>
                    </div>
                @endif
                @if(count($selectedPrenatal) > 0)
                    <div style="padding:8px 16px; border-radius:12px; background:#E0E7FF; border:1px solid #A5B4FC;">
                        <span style="font-size:20px; font-weight:700; color:#3730A3;">{{ count($selectedPrenatal) }}</span>
                        <span style="font-size:12px; font-weight:700; color:#3730A3; text-transform:uppercase; margin-left:6px;">prenatal</span>
                    </div>
                @endif
                @if(count($selectedPostpartum) > 0)
                    <div style="padding:8px 16px; border-radius:12px; background:#FFEDD5; border:1px solid #FDBA74;">
                        <span style="font-size:20px; font-weight:700; color:#9A3412;">{{ count($selectedPostpartum) }}</span>
                        <span style="font-size:12px; font-weight:700; color:#9A3412; text-transform:uppercase; margin-left:6px;">postpartum</span>
                    </div>
                @endif
                @if(count($selectedEvents) > 0)
                    <div style="padding:8px 16px; border-radius:12px; background:#F3E8FF; border:1px solid #C4B5FD;">
                        <span style="font-size:20px; font-weight:700; color:#6B21A8;">{{ count($selectedEvents) }}</span>
                        <span style="font-size:12px; font-weight:700; color:#6B21A8; text-transform:uppercase; margin-left:6px;">
                            {{ count($selectedEvents) === 1 ? 'event' : 'events' }}
                        </span>
                    </div>
                @endif
                @if(count($sDoh ?? []) > 0)
                    <div style="padding:8px 16px; border-radius:12px; background:#DBEAFE; border:1px solid #3B82F6;">
                        <span style="font-size:20px; font-weight:700; color:#1E40AF;">{{ count($sDoh) }}</span>
                        <span style="font-size:12px; font-weight:700; color:#1E40AF; text-transform:uppercase; margin-left:6px;">
                            DoH {{ count($sDoh) === 1 ? 'program' : 'programs' }}
                        </span>
                    </div>
                @endif
            </div>
        </div>

        @if(count($sDoh ?? []) > 0)
            <p style="font-size:12px; font-weight:700; text-transform:uppercase; color:#1E40AF; margin:0 0 10px 0;">🏛️ National Health Programs</p>
            @foreach($sDoh as $prog)
                @php
                    $pHex = ltrim($prog['color'], '#');
                    $pR   = strlen($pHex) === 6 ? hexdec(substr($pHex, 0, 2)) : 59;
                    $pG   = strlen($pHex) === 6 ? hexdec(substr($pHex, 2, 2)) : 130;
                    $pB   = strlen($pHex) === 6 ? hexdec(substr($pHex, 4, 2)) : 246;
                    $isVitA = str_contains($prog['name'], 'Garantisadong Pambata');
                @endphp
                <div style="padding:12px 16px; border-left:4px solid {{ $prog['color'] }}; background:rgba({{ $pR }},{{ $pG }},{{ $pB }},0.07); border-radius:12px; margin-bottom:8px;">
                    <p style="font-size:15px; font-weight:700; color:#0f172a; margin:0;">{{ $prog['name'] }}</p>
                    @if($prog['description'])
                        <p style="font-size:13px; color:#475569; margin:4px 0 0 0; white-space: pre-line;">{{ $prog['description'] }}</p>
                    @endif
                </div>

                @if($isVitA && !empty($vitaminAEligibility))
                    @php $v = $vitaminAEligibility; @endphp
                    <div style="
                        margin: 4px 0 16px 0; padding: 18px 20px;
                        background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%);
                        border: 1px solid #F97316; border-radius: 14px;">

                        <p style="font-size:12px; font-weight:800; color:#9A3412; text-transform:uppercase; letter-spacing:.08em; margin:0 0 12px 0;">
                            🧡 Eligible for Vitamin A today
                        </p>

                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px; margin-bottom:14px;">
                            <div style="padding:12px 14px; background:#ffffff; border:1px solid #FDBA74; border-radius:10px;">
                                <p style="font-size:11px; font-weight:700; color:#9A3412; text-transform:uppercase; margin:0 0 4px 0;">Infants 6–11 months</p>
                                <p style="font-size:24px; font-weight:800; color:#9A3412; margin:0; line-height:1;">{{ $v['infants_6_11m'] }}</p>
                                <p style="font-size:12px; color:#9A3412; margin:4px 0 0 0;">→ 100,000 IU each</p>
                            </div>
                            <div style="padding:12px 14px; background:#ffffff; border:1px solid #FDBA74; border-radius:10px;">
                                <p style="font-size:11px; font-weight:700; color:#9A3412; text-transform:uppercase; margin:0 0 4px 0;">Children 12–59 months</p>
                                <p style="font-size:24px; font-weight:800; color:#9A3412; margin:0; line-height:1;">{{ $v['children_12_59m'] }}</p>
                                <p style="font-size:12px; color:#9A3412; margin:4px 0 0 0;">→ 200,000 IU each</p>
                            </div>
                            <div style="padding:12px 14px; background:#ffffff; border:1px solid #FDBA74; border-radius:10px;">
                                <p style="font-size:11px; font-weight:700; color:#9A3412; text-transform:uppercase; margin:0 0 4px 0;">Postpartum ≤4 weeks</p>
                                <p style="font-size:24px; font-weight:800; color:#9A3412; margin:0; line-height:1;">{{ $v['postpartum_4w'] }}</p>
                                <p style="font-size:12px; color:#9A3412; margin:4px 0 0 0;">→ 200,000 IU each</p>
                            </div>
                        </div>

                        <div style="
                            padding:12px 16px; background:#FEF3C7;
                            border:1px solid #FAC775; border-radius:10px;
                            display:flex; align-items:center; justify-content:space-between;
                            gap:12px; flex-wrap:wrap;">
                            <div>
                                <p style="font-size:11px; font-weight:800; color:#92400E; text-transform:uppercase; letter-spacing:.06em; margin:0 0 4px 0;">
                                    Estimated capsules needed
                                </p>
                                <p style="font-size:24px; font-weight:800; color:#92400E; margin:0; line-height:1;">
                                    {{ $v['total_capsules'] }}
                                    <span style="font-size:13px; font-weight:700;">
                                        ({{ $v['capsules_100k'] }} × 100k &nbsp;+&nbsp; {{ $v['capsules_200k'] }} × 200k)
                                    </span>
                                </p>
                            </div>
                            <a href="{{ route('supply_requests.create') }}"
                               style="
                                   padding:10px 18px; border-radius:10px;
                                   background:#F97316; color:#fff;
                                   text-decoration:none; font-size:14px; font-weight:700;
                                   white-space:nowrap;">
                                Request Stock →
                            </a>
                        </div>

                        <p style="font-size:11px; color:#9A3412; margin:10px 0 0 0; font-style:italic;">
                            Counts are based on children registered at your RHU. Actual turnout may vary.
                        </p>
                    </div>
                @endif
            @endforeach
        @endif

        @foreach($selectedEvents as $ev)
            @php
                $prioStyle = match($ev['priority']) {
                    'emergency' => ['bg' => '#FCEBEB', 'text' => '#991b1b', 'border' => '#DC2626', 'label' => '🚨 EMERGENCY'],
                    'high'      => ['bg' => '#FEF3C7', 'text' => '#92400E', 'border' => '#F59E0B', 'label' => '⚠ High'],
                    default     => ['bg' => '#F3E8FF', 'text' => '#6B21A8', 'border' => '#8B5CF6', 'label' => 'Event'],
                };
            @endphp
            <div style="padding:14px 16px; background:{{ $prioStyle['bg'] }}; border:1px solid {{ $prioStyle['border'] }}; border-radius:12px; margin-bottom:10px;">
                <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:0;">
                        <p style="font-size:11px; font-weight:800; color:{{ $prioStyle['text'] }}; text-transform:uppercase; margin:0 0 4px 0;">
                            {{ $prioStyle['label'] }} · {{ $ev['type_label'] }}
                        </p>
                        <p style="font-size:16px; font-weight:700; color:#0f172a; margin:0;">{{ $ev['title'] }}</p>

                        @if(!empty($ev['area_label']))
                            <span class="cal-event-area">📍 {{ $ev['area_label'] }}</span>
                        @endif

                        @if($ev['start_time'] || $ev['end_time'])
                            <p style="font-size:14px; color:#64748b; margin:4px 0 0 0;">🕐 {{ $ev['start_time'] }}{{ $ev['end_time'] ? ' – '.$ev['end_time'] : '' }}</p>
                        @endif
                        @if($ev['description'])
                            <p style="font-size:14px; color:#475569; margin:6px 0 0 0;">{{ $ev['description'] }}</p>
                        @endif
                    </div>
                    <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:flex-start;">
                        <button type="button"
                                class="js-edit-event"
                                data-event="{{ json_encode($ev) }}"
                                style="padding:8px 14px; border-radius:8px; background:#fff; color:#334155; border:1px solid #e2e8f0; font-size:13px; font-weight:700; cursor:pointer;">
                            ✎ Edit
                        </button>

                        <form method="POST"
                              action="{{ route('calendar_events.destroy', $ev['id']) }}"
                              onsubmit="return confirmDialog(event, 'Delete this event?');"
                              style="margin:0; display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="padding:8px 14px; border-radius:8px; background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; font-size:13px; font-weight:700; cursor:pointer;">
                                ✕
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach

        @if(count($selectedFP) > 0)
            <p style="font-size:12px; font-weight:700; text-transform:uppercase; color:#9D174D; margin:16px 0 10px 0;">💊 Family Planning</p>
            @foreach($selectedFP as $f)
                <div style="padding:12px 16px; background:#FCE7F3; border:1px solid #F9A8D4; border-radius:12px; margin-bottom:8px;">
                    <p style="font-size:15px; font-weight:700; color:#0f172a; margin:0;">{{ $f['patient_name'] }}</p>
                    <p style="font-size:13px; color:#9D174D; margin:4px 0 0 0;">{{ $f['barangay'] }} · {{ $f['method'] }}</p>
                </div>
            @endforeach
        @endif

        @if(count($selectedPrenatal) > 0)
            <p style="font-size:12px; font-weight:700; text-transform:uppercase; color:#3730A3; margin:16px 0 10px 0;">🤰 Prenatal Visits</p>
            @foreach($selectedPrenatal as $p)
                <div style="padding:12px 16px; background:#E0E7FF; border:1px solid #A5B4FC; border-radius:12px; margin-bottom:8px;">
                    <p style="font-size:15px; font-weight:700; color:#0f172a; margin:0;">{{ $p['patient_name'] }}</p>
                    <p style="font-size:13px; color:#3730A3; margin:4px 0 0 0;">{{ $p['barangay'] }} · {{ $p['trimester'] ?? '—' }} trimester</p>
                </div>
            @endforeach
        @endif

        @if(count($selectedPostpartum) > 0)
            <p style="font-size:12px; font-weight:700; text-transform:uppercase; color:#9A3412; margin:16px 0 10px 0;">🍼 Postpartum Visits</p>
            @foreach($selectedPostpartum as $p)
                <div style="padding:12px 16px; background:#FFEDD5; border:1px solid #FDBA74; border-radius:12px; margin-bottom:8px;">
                    <p style="font-size:15px; font-weight:700; color:#0f172a; margin:0;">{{ $p['patient_name'] }}</p>
                    <p style="font-size:13px; color:#9A3412; margin:4px 0 0 0;">{{ $p['barangay'] }} · Visit #{{ $p['visit_number'] }}</p>
                </div>
            @endforeach
        @endif

        @if(count($sSchedules) > 0)
            <p style="font-size:12px; font-weight:700; text-transform:uppercase; color:#42ad8a; margin:16px 0 10px 0;">💉 Vaccination Doses</p>
        @endif

        @foreach($sSchedules as $item)
            @php
                $statusColor = match (true) {
                    $item['status'] === 'given' => ['bg' => '#E8F7F4', 'text' => '#0f5c4b', 'border' => '#42ad8a', 'label' => 'Given'],
                    $item['is_overdue']         => ['bg' => '#FCEBEB', 'text' => '#991b1b', 'border' => '#F7C1C1', 'label' => 'Overdue'],
                    default                     => ['bg' => '#FEF3C7', 'text' => '#92400E', 'border' => '#FAC775', 'label' => 'Pending'],
                };
            @endphp
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; margin-bottom:10px; flex-wrap:wrap;">
                <div style="flex:1; min-width:0;">
                    <p style="font-size:16px; font-weight:700; color:#0f172a; margin:0;">{{ $item['child_name'] }}</p>
                    <p style="font-size:14px; color:#64748b; margin:4px 0 0 0;">{{ $item['barangay'] }} · {{ $item['vaccine'] }} (Dose {{ $item['dose'] }})</p>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <span style="font-size:13px; font-weight:700; padding:4px 12px; border-radius:999px; background:{{ $statusColor['bg'] }}; color:{{ $statusColor['text'] }}; border:1px solid {{ $statusColor['border'] }};">
                        {{ $statusColor['label'] }}
                    </span>
                    @if($item['status'] !== 'given')
                        <a href="{{ route('immunization.tcl', ['search' => $item['child_name']]) }}"
                           style="background:#42ad8a; color:#fff; padding:8px 16px; border-radius:10px; font-size:14px; font-weight:700; text-decoration:none;">
                            Record Dose
                        </a>
                    @endif
                </div>
            </div>
        @endforeach

        @if(count($sSchedules) === 0 && count($selectedEvents) === 0 && count($selectedFP) === 0 && count($selectedPrenatal) === 0 && count($selectedPostpartum) === 0 && count($sDoh ?? []) === 0)
            <div style="padding:40px 20px; text-align:center; color:#64748b; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:12px;">
                <p style="font-size:16px; margin:0;">Nothing scheduled on this day</p>
                <p style="font-size:14px; margin:6px 0 0 0;">Click <strong>+ Add Event</strong> to create a calendar entry.</p>
            </div>
        @endif
    </div>
</div>

{{-- ADD / EDIT EVENT MODAL --}}
<div id="event-modal" onclick="if(event.target === this) closeEventModal()" style="
    display:none; position:fixed; inset:0; z-index:99999;
    background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);
    align-items:center; justify-content:center; padding:20px;">
    <div style="
        background:#ffffff; border-radius:20px;
        width:100%; max-width:520px;
        max-height:90vh; overflow-y:auto;
        box-shadow: 0 24px 60px rgba(0,0,0,0.25);">
        <div style="padding:22px 26px 14px; border-bottom:1px solid #f1f5f9;">
            <h3 id="event-modal-title" style="font-size:19px; font-weight:700; color:#0f172a; margin:0 0 4px 0;">Add Calendar Event</h3>
            <p style="font-size:13px; color:#64748b; margin:0;">Schedule immunization drives, outbreak responses, trainings, or stock deliveries.</p>
        </div>

        <form id="event-form" method="POST" action="{{ route('calendar_events.store') }}" style="padding:20px 26px;">
            @csrf
            <input type="hidden" name="_method" id="event-method" value="POST">

            <div style="margin-bottom:16px;">
                <label style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; display:block; margin-bottom:6px;">Title *</label>
                <input type="text" id="ev-title" name="title" required maxlength="200" style="width:100%; height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div>
                    <label style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; display:block; margin-bottom:6px;">Type *</label>
                    <select id="ev-type" name="type" required style="width:100%; height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; background:#fff; box-sizing:border-box;">
                        <option value="immunization_drive">Immunization Drive</option>
                        <option value="outbreak_response">Outbreak Response</option>
                        <option value="training">Training / Meeting</option>
                        <option value="stock_delivery">Stock Delivery</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; display:block; margin-bottom:6px;">Priority *</label>
                    <select id="ev-priority" name="priority" required style="width:100%; height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; background:#fff; box-sizing:border-box;">
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="emergency">Emergency</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; display:block; margin-bottom:6px;">
                    Area / Barangay <span style="color:#64748b; font-weight:400; text-transform:none;">(optional — for per-area sessions)</span>
                </label>
                <input type="text" id="ev-area" name="area_label" maxlength="120" placeholder="e.g., Barangay Poblacion"
                       style="width:100%; height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
            </div>

            <div style="margin-bottom:16px;">
                <label style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; display:block; margin-bottom:6px;">Date *</label>
                <input type="date" id="ev-date" name="event_date" required value="{{ $selectedDate }}" style="width:100%; height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div>
                    <label style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; display:block; margin-bottom:6px;">Start Time</label>
                    <input type="time" id="ev-start" name="start_time" style="width:100%; height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; display:block; margin-bottom:6px;">End Time</label>
                    <input type="time" id="ev-end" name="end_time" style="width:100%; height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
                </div>
            </div>

            <div>
                <label style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; display:block; margin-bottom:6px;">Description</label>
                <textarea id="ev-description" name="description" rows="3" maxlength="2000" style="width:100%; padding:12px 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box; resize:vertical;"></textarea>
            </div>
        </form>

        <div style="padding:16px 26px 20px; border-top:1px solid #f1f5f9; display:flex; gap:10px; justify-content:flex-end; background:#fafbfc; border-radius: 0 0 20px 20px;">
            <button type="button" onclick="closeEventModal()" style="font-size:14px; font-weight:600; padding:10px 18px; border-radius:10px; background:#fff; color:#334155; border:1px solid #e2e8f0; cursor:pointer;">Cancel</button>
            <button type="submit" form="event-form" style="font-size:14px; font-weight:600; padding:10px 18px; border-radius:10px; background:#42ad8a; color:#fff; border:none; cursor:pointer;">Save Event</button>
        </div>
    </div>
</div>

<script>
const CALENDAR_EVENTS_BASE = '{{ url("/calendar-events") }}';

function onMonthPick(m){const y=document.getElementById('year-picker').value;const mm=String(m).padStart(2,'0');window.location.href='{{ route('vaccination.calendar') }}?month='+y+'-'+mm;}
function onYearPick(y){const m=document.getElementById('month-picker').value;const mm=String(m).padStart(2,'0');window.location.href='{{ route('vaccination.calendar') }}?month='+y+'-'+mm;}

function timeInputValue(v) {
    if (!v) return '';
    const m = String(v).match(/(\d{2}):(\d{2})/);
    return m ? (m[1] + ':' + m[2]) : '';
}

function openEventModal(){
    document.getElementById('event-modal-title').textContent='Add Calendar Event';
    document.getElementById('event-form').action='{{ route('calendar_events.store') }}';
    document.getElementById('event-method').value='POST';
    document.getElementById('event-form').reset();
    document.getElementById('ev-date').value='{{ $selectedDate }}';
    document.getElementById('ev-area').value='';
    document.getElementById('event-modal').style.display='flex';
    document.body.style.overflow='hidden';
}

function openEditEventModal(id, data){
    document.getElementById('event-modal-title').textContent='Edit Calendar Event';
    document.getElementById('event-form').action = CALENDAR_EVENTS_BASE + '/' + id;
    document.getElementById('event-method').value = 'PUT';

    document.getElementById('ev-title').value = data.title || '';

    const typeSel = document.getElementById('ev-type');
    const typeVal = data.type || 'other';
    if (!Array.from(typeSel.options).some(o => o.value === typeVal)) {
        const opt = document.createElement('option');
        opt.value = typeVal;
        opt.textContent = typeVal.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        typeSel.appendChild(opt);
    }
    typeSel.value = typeVal;

    document.getElementById('ev-priority').value    = data.priority || 'normal';
    document.getElementById('ev-area').value        = data.area_label || '';
    document.getElementById('ev-date').value        = data.event_date || '{{ $selectedDate }}';
    document.getElementById('ev-start').value       = timeInputValue(data.start_time);
    document.getElementById('ev-end').value         = timeInputValue(data.end_time);
    document.getElementById('ev-description').value = data.description || '';

    document.getElementById('event-modal').style.display='flex';
    document.body.style.overflow='hidden';
}

function closeEventModal(){
    document.getElementById('event-modal').style.display='none';
    document.body.style.overflow='';
}

function epiToggle(){
    const p  = document.getElementById('epi-pattern');
    const wn = document.getElementById('epi-weeknum-wrap');
    if (!p || !wn) return;
    wn.style.display = (p.value === 'monthly_nth') ? '' : 'none';
}

function confirmClearEpi(event) {
    const sel  = document.getElementById('clear-epi-area');
    const area = sel ? sel.value : '';

    const msg = area
        ? 'Remove all UPCOMING EPI day events for "' + area + '"? Past EPI days and other event types will be kept.'
        : 'Remove ALL upcoming EPI day events for ALL areas? Past EPI days and other event types will be kept.';

    return confirmDialog(event, msg);
}

document.addEventListener('DOMContentLoaded', function () {
    epiToggle();

    document.querySelectorAll('.js-edit-event').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try {
                const raw  = this.getAttribute('data-event');
                const data = JSON.parse(raw);
                openEditEventModal(data.id, data);
            } catch (e) {
                console.error('Failed to parse event data:', e, this.getAttribute('data-event'));
            }
        });
    });
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeEventModal();
});
</script>
@endsection