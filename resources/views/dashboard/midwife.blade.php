@extends('layouts.app')

@section('title', 'Midwife Dashboard')

@section('content')


{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- MOBILE-FIRST STYLES                                            --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<style>
    /* Base = mobile. Desktop enhancements come in media queries. */

    .mw-wrap {
        display: flex;
        flex-direction: column;
        gap: 14px;
        min-width: 0;
        overflow-wrap: anywhere;
    }

    /* Welcome header */
    .mw-hero {
        background: linear-gradient(135deg, #42ad8a 0%, #369f7d 100%);
        border-radius: 18px;
        padding: 20px;
        box-shadow: 0 6px 16px rgba(66,173,138,0.22);
    }
    .mw-hero-label {
        font-size: 13px; font-weight: 700; letter-spacing: .1em;
        text-transform: uppercase; color: rgba(255,255,255,0.85);
        margin: 0 0 4px 0;
    }
    .mw-hero-title {
        font-size: 26px; font-weight: 700; color: #fff; margin: 0;
        line-height: 1.25;
    }
    .mw-hero-sub {
        font-size: 15px; color: rgba(255,255,255,0.9); margin: 6px 0 0 0;
        line-height: 1.4;
    }

    /* Section wrapper */
    .mw-section {
        background: #ffffff;
        border-radius: 18px;
        padding: 18px 18px;
        border: 1px solid rgba(66,173,138,0.2);
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        min-width: 0;
    }
    .mw-section-title {
        font-size: 18px; font-weight: 700; color: #0f172a;
        margin: 0 0 4px 0; display: flex; align-items: center; gap: 8px;
    }
    .mw-section-sub {
        font-size: 14px; color: #64748b; margin: 0 0 14px 0;
    }

    /* Stats grid — 2 cols on mobile, 3 on desktop */
    .mw-stats {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }
    .mw-stat {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 14px;
        min-width: 0;
    }
    .mw-stat-label {
        font-size: 13px; font-weight: 700; letter-spacing: .06em;
        text-transform: uppercase; color: #64748b; margin: 0 0 4px 0;
    }
    .mw-stat-value {
        font-size: 30px; font-weight: 700; color: #0f172a; margin: 0;
        line-height: 1.1;
    }
    .mw-stat-sub {
        font-size: 13px; color: #64748b; margin: 3px 0 0 0;
    }
    .mw-kpi-link { color:inherit;text-decoration:none;display:block; }
    .mw-kpi-link:hover .mw-stat-label,.mw-kpi-link:hover .mw-stat-value { text-decoration:underline; }
    .mw-kpi-link:focus-visible { outline:3px solid #0f766e;outline-offset:3px;border-radius:8px; }
    a.mw-stat-sub { display:inline-block;color:inherit; }
    .mw-stat.tone-green   { background:#E8F7F4; border-color:#42ad8a; }
    .mw-stat.tone-green   .mw-stat-label,
    .mw-stat.tone-green   .mw-stat-sub   { color:#0f5c4b; }
    .mw-stat.tone-green   .mw-stat-value { color:#0f5c4b; }
    .mw-stat.tone-amber   { background:#FAEEDA; border-color:#FAC775; }
    .mw-stat.tone-amber   .mw-stat-label,
    .mw-stat.tone-amber   .mw-stat-sub   { color:#854F0B; }
    .mw-stat.tone-amber   .mw-stat-value { color:#633806; }
    .mw-stat.tone-red     { background:#FCEBEB; border-color:#F7C1C1; }
    .mw-stat.tone-red     .mw-stat-label,
    .mw-stat.tone-red     .mw-stat-sub   { color:#A32D2D; }
    .mw-stat.tone-red     .mw-stat-value { color:#791F1F; }

    /* Quick action cards */
    .mw-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }
    .mw-action {
        background: #ffffff;
        border: 1px solid rgba(66,173,138,0.25);
        border-radius: 14px;
        padding: 14px;
        text-decoration: none;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        transition: all 0.15s;
        min-height: 96px;
        min-width: 0;
    }
    .mw-action:active {
        transform: scale(0.97);
        background: #f8fafc;
    }
    .mw-action:focus-visible {
        outline: 3px solid #0f766e;
        outline-offset: 3px;
    }
    .mw-action-icon {
        width: 38px; height: 38px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .mw-action-label {
        font-size: 16px; font-weight: 700; color: #0f172a; margin: 0;
        line-height: 1.25;
    }
    .mw-action-hint {
        font-size: 13px; color: #64748b; margin: 0; line-height: 1.3;
    }

    /* Alert list */
    .mw-alert {
        display: flex; align-items: center; justify-content: space-between;
        gap: 10px; padding: 12px;
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;
        margin-bottom: 8px;
        text-decoration: none;
    }
    .mw-alert:last-child { margin-bottom: 0; }
    .mw-alert-text { min-width: 0; flex: 1; }
    .mw-alert-title {
        font-size: 15px; font-weight: 600; color: #0f172a; margin: 0;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .mw-alert-sub {
        font-size: 13px; color: #64748b; margin: 3px 0 0 0;
    }
    .mw-badge {
        font-size: 13px; font-weight: 700; padding: 3px 9px;
        border-radius: 6px; white-space: nowrap;
    }

    /* Collapsible sections on mobile */
    .mw-collapse-head {
        display: flex; align-items: center; justify-content: space-between;
        cursor: pointer; user-select: none;
        -webkit-tap-highlight-color: transparent;
    }
    .mw-collapse-head .chev {
        transition: transform 0.2s;
        width: 16px; height: 16px;
        color: #94a3b8;
    }
    .mw-collapse-head[aria-expanded="true"] .chev { transform: rotate(180deg); }
    .mw-collapse-body {
        display: none;
        margin-top: 14px;
    }
    .mw-collapse-head[aria-expanded="true"] + .mw-collapse-body {
        display: block;
    }

    /* Two-column grid — stacks on mobile */
    .mw-2col {
        display: grid;
        grid-template-columns: 1fr;
        gap: 14px;
    }

    /* Chart box */
    .mw-chart-box {
        position: relative;
        height: 220px;
    }

    .mw-demand-signals {
        max-height: 420px;
        overflow-y: auto;
        overscroll-behavior-y: contain;
        scrollbar-gutter: stable;
        scrollbar-color: #94a3b8 #f1f5f9;
        padding-right: 8px;
    }
    .mw-demand-signals:focus-visible { outline: 3px solid #0f766e;outline-offset: 3px;border-radius: 8px; }
    @media print { .mw-demand-signals { max-height: none;overflow: visible;scrollbar-gutter: auto; } }

    /* Outbreak pills */
    .mw-outbreak-list {
        display: flex; flex-wrap: wrap; gap: 6px;
    }
    .mw-outbreak-pill {
        display: inline-flex; align-items: center; gap: 5px;
        background: #fff; border: 1px solid #fca5a5; border-radius: 8px;
        padding: 4px 10px; font-size: 13px; font-weight: 600; color: #991b1b;
    }

    /* Quick report form — stacks on mobile */
    .mw-form-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 10px;
    }
    .mw-form-grid input,
    .mw-form-grid select,
    .mw-form-grid textarea {
        width: 100%; box-sizing: border-box;
        min-height: 44px;
        padding: 10px 12px;
        font-size: 17px; /* prevents iOS zoom on focus */
        border: 2px solid #bec6cf; border-radius: 10px;
        background: #fff; color: #0f172a;
    }
    .mw-form-grid textarea { min-height: 72px; resize: vertical; }
    .mw-form-grid label {
        display: block; font-size: 15px; font-weight: 700;
        color: #475569; margin-bottom: 4px;
    }
    .mw-submit-btn {
        min-height: 44px; padding: 0 22px;
        background: #42ad8a; color: #fff;
        border: none; border-radius: 10px;
        font-weight: 700; font-size: 17px; cursor: pointer;
        width: 100%;
    }
    .mw-submit-btn:active { background: #369f7d; }

    @media (max-width: 767px) {
        .mw-demand-signals { max-height: 320px; }
        .mw-hero { padding: 18px; }
        .mw-hero-title { font-size: clamp(22px, 6vw, 26px); }
        .mw-section { padding: 16px; }
        .mw-section-title { line-height: 1.35; }
        .mw-section-heading { flex-wrap: wrap; gap: 8px; }
        .mw-section-link { display: inline-flex; align-items: center; min-height: 44px; }
        .mw-alert { flex-wrap: wrap; }
        .mw-alert-text { flex: 1 1 160px; }
        .mw-alert-title { white-space: normal; overflow-wrap: anywhere; }
        .mw-stat-value { font-size: clamp(24px, 7vw, 30px); }
        .mw-outbreak-pill { max-width: 100%; overflow-wrap: anywhere; }
    }

    /* Tablet+ enhancements */
    @media (min-width: 640px) {
        .mw-hero { padding: 26px 28px; }
        .mw-hero-title { font-size: 30px; }
        .mw-section { padding: 22px 24px; }
        .mw-actions { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .mw-action { align-items: center; text-align: center; min-height: 120px; }
        .mw-action-label { font-size: 17px; }
        .mw-form-grid { grid-template-columns: 2fr 1fr 1fr auto; align-items: end; }
        .mw-form-grid > .full { grid-column: 1 / -1; }
        .mw-submit-btn { width: auto; }
    }

    @media (min-width: 1024px) {
        .mw-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .mw-2col { grid-template-columns: 1fr 1fr; }
        .mw-chart-box { height: 280px; }
    }
</style>

<div class="mw-wrap">

    {{-- ═══════ HERO ═══════ --}}
    <div class="mw-hero">
        <p class="mw-hero-label">Midwife Portal</p>
        <h1 class="mw-hero-title">Welcome back, {{ $user->name }}</h1>
        <p class="mw-hero-sub">
            {{ $healthUnit->name ?? 'N/A' }}
            @if($healthUnit && $healthUnit->municipality)
                · {{ $healthUnit->municipality }}
            @endif
            · {{ now()->format('D, M j') }}
        </p>
    </div>

    {{-- ═══════ QUICK ACTIONS ═══════ --}}
    <div class="mw-actions">
        <a href="{{ route('dispensing.create') }}" class="mw-action">
            <div class="mw-action-icon" style="background:#E8F7F4;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0f5c4b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 11.5 5c-.57.55-1.08 1.15-1.5 1.79M5 10c-1.5 1.46-3 3.21-3 5.5a5.5 5.5 0 0 0 10.95 1"/>
                </svg>
            </div>
            <div>
                <p class="mw-action-label">Dispense</p>
                <p class="mw-action-hint">Record a dispense</p>
            </div>
        </a>

        <a href="{{ route('immunization.tcl') }}" class="mw-action">
            <div class="mw-action-icon" style="background:#FEF3C7;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#92400E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
            </div>
            <div>
                <p class="mw-action-label">Immunize</p>
                <p class="mw-action-hint">Record a vaccine</p>
            </div>
        </a>

        <a href="{{ route('public_users.create') }}" class="mw-action">
            <div class="mw-action-icon" style="background:#F3E8FF;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6B21A8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                </svg>
            </div>
            <div>
                <p class="mw-action-label">Register</p>
                <p class="mw-action-hint">New patient</p>
            </div>
        </a>

        <a href="{{ route('disease_reports.index', ['barangay' => $demandBarangay]) }}" class="mw-action">
            <div class="mw-action-icon" style="background:#FCEBEB;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#991b1b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="12" y1="18" x2="12" y2="12"/>
                    <line x1="9" y1="15" x2="15" y2="15"/>
                </svg>
            </div>
            <div>
                <p class="mw-action-label">Disease trends</p>
                <p class="mw-action-hint">Signals from medicine consumption</p>
            </div>
        </a>
    </div>

    {{-- ═══════ TODAY'S SNAPSHOT ═══════ --}}
    <div class="mw-stats">
        <div class="mw-stat tone-green">
            <a id="midwife-kpi-dispensed-today" class="mw-kpi-link" href="{{ route('dispensing.index', ['dispensing_scope' => 'personal', 'date_from' => $snapshotDate, 'date_to' => $snapshotDate]) }}">
                <p class="mw-stat-label">Dispensed Today</p>
                <p class="mw-stat-value">{{ $dispensedToday }}</p>
            </a>
            <a id="midwife-kpi-dispensed-month" class="mw-stat-sub" href="{{ route('dispensing.index', ['dispensing_scope' => 'personal', 'date_from' => $snapshotMonthStart, 'date_to' => $snapshotDate]) }}">{{ $dispensedThisMonth }} this month through {{ $snapshotDate }}</a>
            <p class="mw-stat-sub">Your saved dispensing records at this RHU. Today: {{ $snapshotDate }}.</p>
        </div>
        <a id="midwife-kpi-registered" class="mw-stat mw-kpi-link" href="{{ route('public_users.index') }}">
            <p class="mw-stat-label">Registered</p>
            <p class="mw-stat-value">{{ number_format($activePatients) }}</p>
            <p class="mw-stat-sub">As of {{ $snapshotDate }}: patients currently assigned to you at this RHU, across all saved registrations.</p>
        </a>
        <a id="midwife-kpi-pending-requests" class="mw-stat mw-kpi-link {{ $pendingRequests > 0 ? 'tone-amber' : '' }}" href="{{ route('supply_requests.index', ['status' => 'pending']) }}">
            <p class="mw-stat-label">Pending Requests</p>
            <p class="mw-stat-value">{{ $pendingRequests }}</p>
            <p class="mw-stat-sub">As of {{ $snapshotDate }}: your pending supply requests at this RHU.</p>
        </a>
    </div>

    {{-- ═══════ IMMUNIZATION DUE TODAY — placeholder until Track 2 ═══════ --}}
    {{--
    <div class="mw-section">
        <div class="mw-collapse-head" aria-expanded="true" onclick="toggleCollapse(this)">
            <div>
                <p class="mw-section-title">💉 Immunization Due Today</p>
                <p class="mw-section-sub">{{ $dueTodayCount ?? 0 }} children in your catchment</p>
            </div>
            <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
        <div class="mw-collapse-body">
            @forelse($dueToday ?? [] as $child)
                <div class="mw-alert">
                    <div class="mw-alert-text">
                        <p class="mw-alert-title">{{ $child->full_name }}</p>
                        <p class="mw-alert-sub">{{ $child->barangay }} · {{ $child->age_display }} · Due: {{ $child->vaccine }}</p>
                    </div>
                    <a href="{{ route('immunization.tcl', ['patient' => $child->id]) }}" class="mw-badge" style="background:#42ad8a; color:#fff; text-decoration:none;">Record</a>
                </div>
            @empty
                <p style="font-size:14px; color:#94a3b8; text-align:center; padding:16px 0; margin:0;">No children due today.</p>
            @endforelse
        </div>
    </div>
    --}}

    {{-- AUTOMATIC AREA MEDICINE DEMAND --}}
    <section class="mw-section" aria-labelledby="midwife-area-demand-heading">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <h2 id="midwife-area-demand-heading" class="mw-section-title">Disease trend signals</h2>
            <a class="mw-section-link" href="{{ route('disease_reports.index', ['consumption_scope' => $demandBarangay ? 'area' : 'personal', 'barangay' => $demandBarangay, 'period_type' => 'weekly']) }}" style="font-size:14px;font-weight:600;color:#0f766e;">View all trends</a>
        </div>
        <p class="mw-section-sub">Possible disease trends from medicine consumption in your assigned barangay.</p>
        @if($assignedDemandBarangays->isNotEmpty())
            <p style="font-size:14px;color:#475569;margin:12px 0;">Assigned barangays: <strong>{{ $assignedDemandBarangays->join(', ') }}</strong></p>
            <p style="font-size:14px;color:#475569;margin:12px 0;">Barangay: <strong>{{ $demandBarangay }}</strong>. Latest completed week: {{ $areaDemand['period_start'] }} to {{ $areaDemand['period_end'] }}. Usual consumption: {{ $areaDemand['baseline_start'] }} to {{ $areaDemand['baseline_end'] }} (four completed weeks). Choose another assigned barangay on the full trends page.</p>
            @if($areaDemand['comparison_transactions'] === 0 && $areaSavedConsumption->record_count > 0)
                <p style="font-size:14px;color:#475569;margin:12px 0;">No qualifying records in this comparison period. <a href="{{ route('disease_reports.index', ['consumption_scope' => 'area', 'barangay' => $demandBarangay, 'period_type' => 'weekly', 'date_from' => $areaSavedConsumption->first_date, 'date_to' => $areaSavedConsumption->last_date, 'usage_details' => 1]) }}#recorded-consumption">View saved consumption history</a></p>
            @endif
            <div class="mw-stats" style="margin:16px 0;">
                <div class="mw-stat {{ $areaDemand['overview']['elevated'] > 0 ? 'tone-amber' : '' }}" data-signal-status="elevated">
                    <p class="mw-stat-label">Elevated disease signals</p>
                    <p class="mw-stat-value">{{ $areaDemand['overview']['elevated'] }}</p>
                    <p class="mw-stat-sub">Higher demand in associated medicines</p>
                </div>
                <div class="mw-stat" data-signal-status="typical">
                    <p class="mw-stat-label">Within usual consumption</p>
                    <p class="mw-stat-value">{{ $areaDemand['overview']['typical'] }}</p>
                    <p class="mw-stat-sub">disease signals without elevated consumption</p>
                </div>
                <div class="mw-stat" data-signal-status="insufficient">
                    <p class="mw-stat-label">Building history</p>
                    <p class="mw-stat-value">{{ $areaDemand['overview']['insufficient'] }}</p>
                    <p class="mw-stat-sub">disease signals needing recent records or more periods</p>
                </div>
                <div class="mw-stat">
                    <p class="mw-stat-label">New consumption signals</p>
                    <p class="mw-stat-value">{{ $areaDemand['overview']['new'] }}</p>
                    <p class="mw-stat-sub">disease signals after a zero baseline</p>
                </div>
            </div>
            <div class="mw-demand-signals" id="midwife-demand-signals" tabindex="0" role="region" aria-labelledby="midwife-area-demand-heading">
            @forelse($areaDemand['signals'] as $signal)
                @php
                    $signalLabels = ['elevated' => 'Higher demand', 'new' => 'New consumption signal', 'typical' => 'Within usual consumption', 'insufficient' => 'Building history'];
                @endphp
                <div class="mw-alert" style="align-items:flex-start;gap:12px;flex-wrap:wrap;">
                    <div class="mw-alert-text">
                        <p class="mw-alert-title">{{ $signal['name'] }}</p>
                        @foreach($signal['medicines'] as $medicine)
                            <p class="mw-alert-sub">{{ $medicine['name'] }} {{ $medicine['dosage'] }} {{ $medicine['dosage_form'] }}: {{ number_format($medicine['current_quantity']) }} {{ $medicine['unit'] }} in the latest completed week; usual {{ $medicine['baseline_average'] === null ? 'Building history' : number_format($medicine['baseline_average'], 1).' '.$medicine['unit'] }}.</p>
                        @endforeach
                    </div>
                    <span class="mw-badge" style="background:{{ $signal['status'] === 'elevated' ? '#FAEEDA' : '#E8F7F4' }};color:#334155;">{{ $signalLabels[$signal['status']] }}</span>
                </div>
            @empty
                <p style="font-size:15px;color:#64748b;margin:16px 0;">No area medicine demand history yet. Record dispensing quantities with the barangay to begin building automatic trends.</p>
            @endforelse
            </div>
            <p style="font-size:14px;color:#64748b;margin:16px 0;">Higher consumption of medicines linked to a condition highlights that condition for follow-up. Each signal uses the latest completed week and the previous four weeks of consumption.</p>
        @else
            <p style="font-size:15px;color:#64748b;margin:16px 0;">No barangay is assigned to your account. Ask your RHU administrator to assign your barangay so automatic local trends can be shown.</p>
        @endif
        <a class="mw-section-link" href="{{ route('dispensing.create') }}" style="font-size:14px;font-weight:600;color:#0f766e;">Record medicine quantities</a>
    </section>

    {{-- ═══════ RECENT ACTIVITY (2-col on desktop, stacked on mobile) ═══════ --}}
    <div class="mw-2col">

        <div class="mw-section">
            <div class="mw-section-heading" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                <p class="mw-section-title" style="margin:0;">💊 Recent Dispensing</p>
                <a class="mw-section-link" href="{{ route('dispensing.index') }}" data-empty-action="history" style="font-size:14px; font-weight:600; color:#42ad8a; text-decoration:none;">View all →</a>
            </div>
            @forelse($recentDispensing as $r)
                <div class="mw-alert">
                    <div class="mw-alert-text">
                        <p class="mw-alert-title">{{ $r->medicine->medicine_name ?? 'N/A' }}</p>
                        <p class="mw-alert-sub">{{ $r->date_dispensed }} · {{ $r->prescribingDoctorDisplay() }}</p>
                    </div>
                    <span class="mw-badge" style="background:#EAF3DE; color:#3B6D11; border:1px solid #C0DD97;">
                        {{ $r->quantity_dispensed }}
                    </span>
                </div>
            @empty
                <p style="font-size:14px; color:#94a3b8; text-align:center; padding:16px 0; margin:0;">No dispensing yet.</p>
            @endforelse
        </div>

        <div class="mw-section">
            <div class="mw-section-heading" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                <p class="mw-section-title" style="margin:0;">📋 Recent Requests</p>
                <a class="mw-section-link" href="{{ route('supply_requests.index') }}" aria-label="View all supply requests" data-empty-guard="true" data-empty-count="{{ $recentRequests->count() }}" data-empty-message="No supply requests yet." style="font-size:14px; font-weight:600; color:#42ad8a; text-decoration:none;">View all →</a>
            </div>
            @forelse($recentRequests as $r)
                <div class="mw-alert">
                    <div class="mw-alert-text">
                        <p class="mw-alert-title">{{ $r->medicine->medicine_name ?? 'N/A' }}</p>
                        <p class="mw-alert-sub">{{ $r->date_allocated?->format('M d') ?? '—' }} · {{ $r->destination_display ?? 'N/A' }}</p>
                    </div>
                    <span class="mw-badge" style="background:#EFF6FF; color:#1e40af; border:1px solid #93c5fd;">
                        {{ $r->quantity_allocated }}
                    </span>
                </div>
            @empty
                <p style="font-size:14px; color:#94a3b8; text-align:center; padding:16px 0; margin:0;">No requests yet.</p>
            @endforelse
        </div>

    </div>

</div>

{{-- ═══════ SCRIPTS ═══════ --}}

<script>
function toggleCollapse(head) {
    const isOpen = head.getAttribute('aria-expanded') === 'true';
    head.setAttribute('aria-expanded', !isOpen);
}

</script>
@endsection
