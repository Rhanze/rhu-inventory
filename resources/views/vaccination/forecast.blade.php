@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    <style>
        .fc-stat {
            border-radius: 16px; padding: 22px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
            background: #fff;
        }
        .fc-stat-label {
            font-size: 13px; font-weight: 700; letter-spacing: .06em;
            text-transform: uppercase; color: #64748b;
            margin: 0 0 6px 0;
        }
        .fc-stat-value {
            font-size: 36px; font-weight: 700; color: #0f172a;
            margin: 0; line-height: 1.1;
        }
        .fc-stat-hint {
            font-size: 13px; color: #64748b; margin: 6px 0 0 0;
        }
        .fc-stat.tone-red   { background: #FCEBEB; border-color: #F7C1C1; }
        .fc-stat.tone-red   .fc-stat-label,
        .fc-stat.tone-red   .fc-stat-hint   { color: #A32D2D; }
        .fc-stat.tone-red   .fc-stat-value  { color: #791F1F; }

        .fc-stat.tone-amber { background: #FEF3C7; border-color: #FAC775; }
        .fc-stat.tone-amber .fc-stat-label,
        .fc-stat.tone-amber .fc-stat-hint   { color: #854F0B; }
        .fc-stat.tone-amber .fc-stat-value  { color: #633806; }

        .fc-stat.tone-green { background: #E8F7F4; border-color: #42ad8a; }
        .fc-stat.tone-green .fc-stat-label,
        .fc-stat.tone-green .fc-stat-hint   { color: #0f5c4b; }
        .fc-stat.tone-green .fc-stat-value  { color: #0f5c4b; }

        .fc-row {
            padding: 16px 24px;
            border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; flex-wrap: wrap;
            transition: background 0.15s;
        }
        .fc-row:last-child { border-bottom: none; }
        .fc-row:hover { background: #f8fafc; }

        .fc-row-title {
            font-size: 16px; font-weight: 600; color: #0f172a;
            margin: 0 0 4px 0;
        }
        .fc-row-meta {
            font-size: 14px; color: #64748b; margin: 0;
        }

        .fc-badge {
            display: inline-block; padding: 3px 10px;
            border-radius: 999px; font-size: 12px; font-weight: 700;
            letter-spacing: .02em;
        }

        .fc-btn {
            padding: 9px 18px; border-radius: 10px;
            font-size: 14px; font-weight: 700;
            text-decoration: none; white-space: nowrap;
            transition: all 0.2s;
        }
        .fc-btn:hover { transform: translateY(-1px); }

        .fc-btn-green {
            background: #42ad8a; color: #fff; border: 1px solid #42ad8a;
        }
        .fc-btn-green:hover { background: #369f7d; }

        .fc-btn-white {
            background: #fff; color: #334155; border: 1px solid #e2e8f0;
        }
        .fc-btn-white:hover { background: #f8fafc; border-color: #42ad8a; color: #0f5c4b; }

        .fc-btn-red {
            background: #FCEBEB; color: #991b1b; border: 1px solid #F7C1C1;
        }
        .fc-btn-red:hover { background: #f8d7d7; }
    </style>

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
                Vaccination
            </p>
            <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">
                Immunization Forecast
            </h2>
            <p style="font-size:15px; color:#64748b; margin:6px 0 0 0;">
                Overview of upcoming, overdue, and completed doses across your catchment
            </p>
        </div>

        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('vaccination.calendar') }}"
               style="
                   display:inline-flex; align-items:center; gap:6px;
                   padding:10px 18px; border-radius:10px;
                   background:#42ad8a; color:#fff; border:1px solid #42ad8a;
                   font-size:16px; font-weight:600; text-decoration:none;">
                📅 Calendar View
            </a>
        </div>
    </div>

    {{-- ═══════ STAT CARDS ═══════ --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px;">

        <div class="fc-stat tone-amber">
            <p class="fc-stat-label">Due This Month</p>
            <p class="fc-stat-value">{{ $monthStats['due_this_month'] }}</p>
            <p class="fc-stat-hint">Scheduled for {{ now()->format('F Y') }}</p>
        </div>

        <div class="fc-stat {{ $monthStats['overdue'] > 0 ? 'tone-red' : '' }}">
            <p class="fc-stat-label">Overdue</p>
            <p class="fc-stat-value">{{ $monthStats['overdue'] }}</p>
            <p class="fc-stat-hint">{{ $monthStats['overdue'] > 0 ? 'Needs follow-up' : 'All clear' }}</p>
        </div>

        <div class="fc-stat tone-green">
            <p class="fc-stat-label">Given This Month</p>
            <p class="fc-stat-value">{{ $monthStats['given_month'] }}</p>
            <p class="fc-stat-hint">Doses recorded in {{ now()->format('F') }}</p>
        </div>

    </div>

    {{-- ═══════ OVERDUE LIST ═══════ --}}
    <div style="
        background: rgba(255,255,255,0.95); border-radius: 20px;
        border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        overflow: hidden;">

        <div style="padding:20px 24px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <div>
                <p style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#dc2626; margin:0 0 4px 0;">
                    ⚠️ Attention Required
                </p>
                <p style="font-size:16px; font-weight:700; color:#0f172a; margin:0;">
                    Overdue Doses
                </p>
                <p style="font-size:14px; color:#64748b; margin:4px 0 0 0;">
                    Children whose vaccine is past the recommended date
                </p>
            </div>
            @if($overdue->count() > 0)
                <span class="fc-badge" style="background:#FCEBEB; color:#991b1b; border:1px solid #F7C1C1;">
                    {{ $overdue->count() }} overdue
                </span>
            @endif
        </div>

        @forelse($overdue as $s)
            @php
                $daysLate = \Carbon\Carbon::parse($s->due_date)->diffInDays(now());
            @endphp
            <div class="fc-row">
                <div style="min-width:0; flex:1;">
                    <p class="fc-row-title">
                        {{ $s->patient?->full_name ?? 'Unknown' }}
                    </p>
                    <p class="fc-row-meta">
                        {{ $s->vaccine_name }} (Dose {{ $s->dose_number }})
                        · Due {{ $s->due_date->format('M d, Y') }}
                        · {{ $s->patient?->barangay ?? '—' }}
                    </p>
                </div>

                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <span class="fc-badge" style="background:#FCEBEB; color:#991b1b; border:1px solid #F7C1C1;">
                        {{ $daysLate }} day{{ $daysLate === 1 ? '' : 's' }} late
                    </span>
                    <a href="{{ route('vaccination.child', $s->public_user_id) }}" class="fc-btn fc-btn-red">
                        View Child
                    </a>
                </div>
            </div>
        @empty
            <div style="padding:48px 24px; text-align:center; color:#64748b;">
                <p style="font-size:32px; margin:0 0 8px 0;">🎉</p>
                <p style="font-size:16px; font-weight:600; color:#0f172a; margin:0;">No overdue doses</p>
                <p style="font-size:14px; margin:6px 0 0 0;">All children in your catchment are up to date.</p>
            </div>
        @endforelse
    </div>

    {{-- ═══════ UPCOMING LIST ═══════ --}}
    <div style="
        background: rgba(255,255,255,0.95); border-radius: 20px;
        border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        overflow: hidden;">

        <div style="padding:20px 24px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <div>
                <p style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0 0 4px 0;">
                    📅 Next 30 Days
                </p>
                <p style="font-size:16px; font-weight:700; color:#0f172a; margin:0;">
                    Upcoming Doses
                </p>
                <p style="font-size:14px; color:#64748b; margin:4px 0 0 0;">
                    Children due for vaccination soon
                </p>
            </div>
            @if($upcoming->count() > 0)
                <span class="fc-badge" style="background:#FEF3C7; color:#92400E; border:1px solid #FAC775;">
                    {{ $upcoming->count() }} upcoming
                </span>
            @endif
        </div>

        @forelse($upcoming as $s)
            @php
                $daysUntil = now()->diffInDays(\Carbon\Carbon::parse($s->due_date), false);
            @endphp
            <div class="fc-row">
                <div style="min-width:0; flex:1;">
                    <p class="fc-row-title">
                        {{ $s->patient?->full_name ?? 'Unknown' }}
                    </p>
                    <p class="fc-row-meta">
                        {{ $s->vaccine_name }} (Dose {{ $s->dose_number }})
                        · Due {{ $s->due_date->format('M d, Y') }}
                        · {{ $s->patient?->barangay ?? '—' }}
                    </p>
                </div>

                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <span class="fc-badge" style="background:#FEF3C7; color:#92400E; border:1px solid #FAC775;">
                        {{ $daysUntil === 0 ? 'Today' : 'in ' . $daysUntil . ' day' . ($daysUntil === 1 ? '' : 's') }}
                    </span>
                    <a href="{{ route('vaccination.child', $s->public_user_id) }}" class="fc-btn fc-btn-green">
                        View Child
                    </a>
                </div>
            </div>
        @empty
            <div style="padding:48px 24px; text-align:center; color:#64748b;">
                <p style="font-size:32px; margin:0 0 8px 0;">📅</p>
                <p style="font-size:16px; font-weight:600; color:#0f172a; margin:0;">No upcoming doses</p>
                <p style="font-size:14px; margin:6px 0 0 0;">Nothing scheduled in the next 30 days.</p>
            </div>
        @endforelse
    </div>

    {{-- ═══════ FOOTER ═══════ --}}
    <div style="display:flex; justify-content:flex-end;">
        <a href="{{ route('dashboard.midwife') }}" class="fc-btn fc-btn-white"
           style="padding:10px 20px; font-size:16px;">
            ← Back to Dashboard
        </a>
    </div>

</div>
@endsection 