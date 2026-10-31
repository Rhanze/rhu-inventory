@extends('layouts.app')

@section('content')

@php
    // Fixed window: October 1 → November 30 of the current year
    $trendStart = \Carbon\Carbon::now()->startOfYear()->addMonths(9)->startOfMonth()->format('Y-m-d');  // Oct 1
    $trendEnd   = \Carbon\Carbon::now()->startOfYear()->addMonths(10)->endOfMonth()->format('Y-m-d');   // Nov 30
    $trendLabel = 'October – November ' . \Carbon\Carbon::now()->year;
@endphp

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- MOBILE-FIRST STYLES                                            --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<style>
    /* Base = mobile. Desktop enhancements come in media queries. */

    .mw-wrap {
        display: flex;
        flex-direction: column;
        gap: 14px;
        padding-bottom: 90px; /* space for sticky bottom bar */
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
    }
    .mw-action:active {
        transform: scale(0.97);
        background: #f8fafc;
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

    /* Sticky bottom action bar (mobile only) */
    .mw-bottom-bar {
        position: fixed;
        left: 0; right: 0; bottom: 0;
        background: #ffffff;
        border-top: 1px solid #e2e8f0;
        box-shadow: 0 -4px 12px rgba(0,0,0,0.06);
        display: flex; justify-content: space-around;
        padding: 8px 4px calc(8px + env(safe-area-inset-bottom));
        z-index: 50;
    }
    .mw-bottom-btn {
        flex: 1;
        display: flex; flex-direction: column; align-items: center; gap: 3px;
        padding: 8px 4px;
        text-decoration: none;
        color: #475569;
        border-radius: 10px;
        min-height: 52px;
        -webkit-tap-highlight-color: transparent;
        transition: background 0.15s;
    }
    .mw-bottom-btn:active { background: #f1f5f9; }
    .mw-bottom-btn.active { color: #42ad8a; }
    .mw-bottom-btn svg { width: 22px; height: 22px; }
    .mw-bottom-btn span { font-size: 12px; font-weight: 600; }

    /* Chart box */
    .mw-chart-box {
        position: relative;
        height: 220px;
    }

    /* Outbreak pills */
    .mw-outbreak-list {
        display: flex; flex-wrap: wrap; gap: 6px;
    }
    .mw-outbreak-pill {
        display: inline-flex; align-items: center; gap: 5px;
        background: #fff; border: 1px solid #fca5a5; border-radius: 8px;
        padding: 4px 10px; font-size: 13px; font-weight: 600; color: #991b1b;
    }

    /* Transfer row */
    .mw-transfer-row {
        display: flex; flex-direction: column; gap: 10px;
        padding: 12px; background: #f8fafc;
        border: 1px solid #e2e8f0; border-radius: 12px;
        margin-bottom: 8px;
    }
    .mw-transfer-row:last-child { margin-bottom: 0; }
    .mw-transfer-btn {
        width: 100%; padding: 12px;
        background: #0f5c4b; color: #fff;
        border: none; border-radius: 10px;
        font-size: 15px; font-weight: 700; cursor: pointer;
        min-height: 44px;
    }
    .mw-transfer-btn:active { background: #0b4638; }

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

    /* Hide bottom bar on desktop */
    @media (min-width: 768px) {
        .mw-bottom-bar { display: none; }
        .mw-wrap { padding-bottom: 20px; }
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
        .mw-transfer-row {
            flex-direction: row; align-items: center; justify-content: space-between;
        }
        .mw-transfer-btn { width: auto; padding: 9px 18px; }
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

        <a href="#report-form" onclick="document.getElementById('report-form')?.scrollIntoView({behavior:'smooth'}); return false;" class="mw-action">
            <div class="mw-action-icon" style="background:#FCEBEB;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#991b1b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="12" y1="18" x2="12" y2="12"/>
                    <line x1="9" y1="15" x2="15" y2="15"/>
                </svg>
            </div>
            <div>
                <p class="mw-action-label">Report</p>
                <p class="mw-action-hint">Disease case</p>
            </div>
        </a>
    </div>

    {{-- ═══════ TODAY'S SNAPSHOT ═══════ --}}
    <div class="mw-stats">
        <div class="mw-stat tone-green">
            <p class="mw-stat-label">Dispensed Today</p>
            <p class="mw-stat-value">{{ $dispensedToday }}</p>
            <p class="mw-stat-sub">{{ $dispensedThisMonth }} this month</p>
        </div>
        <div class="mw-stat">
            <p class="mw-stat-label">Registered</p>
            <p class="mw-stat-value">{{ number_format($activePatients) }}</p>
            <p class="mw-stat-sub">patients in your RHU</p>
        </div>
        <div class="mw-stat {{ $pendingRequests > 0 ? 'tone-amber' : '' }}">
            <p class="mw-stat-label">Pending Requests</p>
            <p class="mw-stat-value">{{ $pendingRequests }}</p>
            <p class="mw-stat-sub">{{ $pendingRequests > 0 ? 'awaiting approval' : 'no open requests' }}</p>
        </div>
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

    {{-- ═══════ INCOMING TRANSFERS ═══════ --}}
    @if($incomingTransfers->count() > 0)
        <div class="mw-section">
            <p class="mw-section-title">📦 Incoming Transfers</p>
            <p class="mw-section-sub">Confirm receipt once items arrive</p>

            @foreach($incomingTransfers as $t)
                <div class="mw-transfer-row">
                    <div style="min-width:0;">
                        <p style="font-size:16px; font-weight:600; color:#0f172a; margin:0;">
                            {{ $t->medicine->medicine_name ?? 'Medicine' }}
                            @if($t->dosage) · {{ $t->dosage }} @endif
                        </p>
                        <p style="font-size:14px; color:#64748b; margin:4px 0 0 0;">
                            From <strong>{{ $t->fromHealthUnit->name ?? 'N/A' }}</strong>
                            · Qty <strong>{{ $t->quantity }}</strong>
                            @if($t->batch_no) · Batch {{ $t->batch_no }} @endif
                        </p>
                    </div>
                    <form method="POST" action="{{ route('transfers.receive', $t->id) }}"
                          onsubmit="return confirm('Confirm you have physically received these items?');">
                        @csrf
                        <button type="submit" class="mw-transfer-btn">📦 Confirm Receipt</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ═══════ DISEASE TRENDS ═══════ --}}
    <div class="mw-section">
        <div class="mw-collapse-head" aria-expanded="true" onclick="toggleCollapse(this)">
            <div>
                <p class="mw-section-title">📈 Disease Trends</p>
                <p class="mw-section-sub">{{ $trendLabel }}</p>
            </div>
            <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
        <div class="mw-collapse-body">

            @if($outbreakAlerts->count() > 0)
                <div style="background:#FCEBEB; border:1px solid #F7C1C1; border-radius:12px; padding:12px 14px; margin-bottom:14px;">
                    <p style="font-size:14px; font-weight:700; color:#A32D2D; margin:0 0 8px 0;">🚨 Outbreak Alerts (30 days)</p>
                    <div class="mw-outbreak-list">
                        @foreach($outbreakAlerts as $alert)
                            <span class="mw-outbreak-pill">
                                {{ $alert->disease->name ?? 'Unknown' }} · {{ $alert->case_count }} cases
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mw-stats" style="grid-template-columns: repeat(3, minmax(0,1fr)); margin-bottom:16px;">
                <div class="mw-stat">
                    <p class="mw-stat-label">Diseases</p>
                    <p class="mw-stat-value">{{ $diseases->count() }}</p>
                </div>
                <div class="mw-stat">
                    <p class="mw-stat-label">Cases / Month</p>
                    <p class="mw-stat-value" style="color:#0f5c4b;">{{ number_format($totalCasesThisMonth) }}</p>
                </div>
                <div class="mw-stat {{ $outbreakAlerts->count() > 0 ? 'tone-red' : '' }}">
                    <p class="mw-stat-label">Outbreaks</p>
                    <p class="mw-stat-value">{{ $outbreakAlerts->count() }}</p>
                </div>
            </div>

            @if($diseases->count() > 0)
                <div class="mw-chart-box">
                    <canvas id="diseaseTrendChart"></canvas>
                </div>
            @else
                <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:12px; padding:24px 16px; text-align:center;">
                    <p style="font-size:15px; color:#64748b; margin:0;">No disease reports yet</p>
                </div>
            @endif
        </div>
    </div>

    {{-- ═══════ QUICK REPORT FORM ═══════ --}}
    <div class="mw-section" id="report-form">
        <p class="mw-section-title">📝 Quick Report</p>
        <p class="mw-section-sub">Submit a disease case in seconds</p>

        @if($errors->any())
            <div style="background:#FCEBEB; border:1px solid #F7C1C1; border-radius:10px; padding:10px 14px; margin-bottom:12px;">
                @foreach($errors->all() as $err)
                    <p style="font-size:15px; color:#A32D2D; margin:0;">{{ $err }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('disease_reports.store') }}" class="mw-form-grid">
            @csrf

            <div class="full">
                <label>Disease</label>
                <select name="disease_id" required>
                    <option value="">Select disease</option>
                    @foreach($allDiseases as $d)
                        <option value="{{ $d->id }}" {{ old('disease_id') == $d->id ? 'selected' : '' }}>
                            {{ $d->name }}{{ $d->category ? ' · ' . $d->category : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Cases</label>
                <input type="number" name="case_count" min="0" required value="{{ old('case_count', 1) }}">
            </div>

            <div>
                <label>Date</label>
                <input type="date" name="report_date" required value="{{ old('report_date', now()->startOfWeek()->format('Y-m-d')) }}">
            </div>

            <div>
                <label>&nbsp;</label>
                <button type="submit" class="mw-submit-btn">Submit</button>
            </div>

            <input type="hidden" name="period_type" value="weekly">

            <div class="full">
                <label style="display:inline-flex; align-items:center; gap:8px; font-weight:600; color:#334155; cursor:pointer;">
                    <input type="checkbox" name="is_outbreak" value="1" {{ old('is_outbreak') ? 'checked' : '' }} style="width:18px; height:18px; min-height:18px; accent-color:#dc2626;">
                    Flag as outbreak
                </label>
            </div>

            <div class="full">
                <label>Remarks (optional)</label>
                <textarea name="remarks" maxlength="500" placeholder="e.g., Cluster of 5 cases in Barangay Poblacion">{{ old('remarks') }}</textarea>
            </div>
        </form>
    </div>

    {{-- ═══════ RECENT ACTIVITY (2-col on desktop, stacked on mobile) ═══════ --}}
    <div class="mw-2col">

        <div class="mw-section">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                <p class="mw-section-title" style="margin:0;">💊 Recent Dispensing</p>
                <a href="{{ route('dispensing.index') }}" style="font-size:14px; font-weight:600; color:#42ad8a; text-decoration:none;">View all →</a>
            </div>
            @forelse($recentDispensing as $r)
                <div class="mw-alert">
                    <div class="mw-alert-text">
                        <p class="mw-alert-title">{{ $r->medicine->medicine_name ?? 'N/A' }}</p>
                        <p class="mw-alert-sub">{{ $r->date_dispensed }} · {{ $r->doctor_name ?: 'Walk-in' }}</p>
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
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                <p class="mw-section-title" style="margin:0;">📋 Recent Requests</p>
                <a href="{{ route('allocation.index') }}" style="font-size:14px; font-weight:600; color:#42ad8a; text-decoration:none;">View all →</a>
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

{{-- ═══════ STICKY MOBILE BOTTOM BAR ═══════ --}}
<nav class="mw-bottom-bar" aria-label="Quick navigation">
    <a href="{{ route('dashboard.midwife') }}" class="mw-bottom-btn active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
        <span>Home</span>
    </a>
    <a href="{{ route('dispensing.index') }}" class="mw-bottom-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 20.5a7 7 0 0 1-10-10l6-6a7 7 0 0 1 10 10z"/><line x1="8.5" y1="8.5" x2="15.5" y2="15.5"/></svg>
        <span>Dispense</span>
    </a>
    <a href="{{ route('immunization.tcl') }}" class="mw-bottom-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="3"/></svg>
        <span>Immunize</span>
    </a>
    <a href="{{ route('public_users.index') }}" class="mw-bottom-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        <span>Patients</span>
    </a>
    <a href="{{ route('notifications.index') }}" class="mw-bottom-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span>Alerts</span>
    </a>
</nav>

{{-- ═══════ SCRIPTS ═══════ --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
function toggleCollapse(head) {
    const isOpen = head.getAttribute('aria-expanded') === 'true';
    head.setAttribute('aria-expanded', !isOpen);
}

@if($diseases->count() > 0)
document.addEventListener('DOMContentLoaded', function () {
    fetch('{{ route('disease_reports.chart_data') }}?start_date={{ $trendStart }}&end_date={{ $trendEnd }}', {
        headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        const ctx = document.getElementById('diseaseTrendChart');
        if (!ctx) return;

        const palette = ['#dc2626', '#eab308', '#3b82f6', '#42ad8a', '#8b5cf6', '#ec4899', '#f97316', '#14b8a6'];

        new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: data.datasets.map((ds, i) => ({
                    label: ds.label,
                    data: ds.data,
                    borderColor: palette[i % palette.length],
                    backgroundColor: palette[i % palette.length] + '15',
                    borderWidth: 2,
                    tension: 0.35,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                })),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            font: { size: 13 },
                            padding: 8,
                        }
                    },
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0, font: { size: 12 } } },
                    x: { ticks: { font: { size: 12 } } }
                }
            }
        });
    })
    .catch(err => console.error('Failed to load chart:', err));
});
@endif
</script>
@endsection