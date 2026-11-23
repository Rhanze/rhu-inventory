@extends('layouts.app')

@section('title', 'Stock Forecasting')

@section('content')
@include('forecast._styles')
<style>
    .forecast-page { container:forecast-page / inline-size; }
    .forecast-page > *, .forecast-page .forecast-panel, .forecast-page .forecast-chart-container { min-width:0;max-width:100%;box-sizing:border-box; }
    .forecast-stock-panel { container:forecast-stock / inline-size; }
    .forecast-page .forecast-stock-table th { font-size:1rem; }
    .forecast-page .forecast-stock-table :is(td,small,.forecast-status) { font-size:1.0625rem; }
    .forecast-stock-table :is(td,th,details,summary,p) { overflow-wrap:anywhere; }
    .forecast-stock-table details { min-width:0;max-width:100%;box-sizing:border-box; }
    .forecast-row-number { display:none; }
    .forecast-chart-container { position:relative;width:100%; }
    .forecast-page canvas { display:block;max-width:100%; }
    @container forecast-page (max-width:70rem) {
        .forecast-charts-row { grid-template-columns:minmax(0,1fr); }
    }
    @container forecast-stock (max-width:78rem) {

        .forecast-stock-table { display:block;min-width:0;width:100%; }
        .forecast-stock-table colgroup { display:none; }
        .forecast-stock-table caption { display:block;max-width:100%;padding:0 20px 16px; }
        .forecast-stock-table thead { position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap; }
        .forecast-stock-table tbody { display:grid;grid-template-columns:minmax(0,1fr);gap:16px;padding:0 20px 20px; }
        .forecast-stock-table tbody tr { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0;border:1px solid #d6e5df!important;border-radius:14px;background:#fff;min-width:0;overflow:visible; }
        .forecast-stock-table td { display:flex;flex-direction:column;align-items:flex-start;gap:5px;min-width:0;max-width:100%;padding:14px 16px;text-align:left!important;border-bottom:1px solid #edf2f0;overflow-wrap:anywhere; }
        .forecast-stock-table td[data-label]::before { content:attr(data-label);display:block;color:#475569;font-size:1rem;font-weight:600;line-height:1.4; }
        .forecast-stock-table td[data-label="Item"] { display:none; }
        .forecast-stock-table td:nth-child(2) { grid-column:span 3;background:#f8fafc;border-radius:14px 0 0; }
        .forecast-stock-table td:nth-child(3) { background:#f8fafc;border-radius:0 14px 0 0; }
        .forecast-stock-table td:nth-child(2)::before { display:none; }
        .forecast-stock-table td[colspan] { grid-column:1/-1; }
        .forecast-row-number { display:inline;color:#64748b;margin-right:.4rem;font-weight:500; }
        .forecast-stock-table details { width:100%; }
    }
    @container forecast-stock (max-width:48rem) {
        .forecast-stock-table tbody tr { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .forecast-stock-table td:nth-child(2) { grid-column:1/-1;border-radius:14px 14px 0 0; }
        .forecast-stock-table td:nth-child(3) { grid-column:1/-1;border-radius:0; }
    }
    @container forecast-stock (max-width:28rem) {
        .forecast-stock-table tbody { padding:0 12px 12px; }
        .forecast-stock-table tbody tr { grid-template-columns:minmax(0,1fr); }
        .forecast-stock-table td { padding:12px 14px; }
    }
    @supports not (container-type:inline-size) {
        @media(max-width:1440px) {

        .forecast-stock-table { display:block;min-width:0;width:100%; }
        .forecast-stock-table colgroup { display:none; }
        .forecast-stock-table caption { display:block;max-width:100%;padding:0 20px 16px; }
        .forecast-stock-table thead { position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap; }
        .forecast-stock-table tbody { display:grid;grid-template-columns:minmax(0,1fr);gap:16px;padding:0 20px 20px; }
        .forecast-stock-table tbody tr { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0;border:1px solid #d6e5df!important;border-radius:14px;background:#fff;min-width:0;overflow:visible; }
        .forecast-stock-table td { display:flex;flex-direction:column;align-items:flex-start;gap:5px;min-width:0;max-width:100%;padding:14px 16px;text-align:left!important;border-bottom:1px solid #edf2f0;overflow-wrap:anywhere; }
        .forecast-stock-table td[data-label]::before { content:attr(data-label);display:block;color:#475569;font-size:1rem;font-weight:600;line-height:1.4; }
        .forecast-stock-table td[data-label="Item"] { display:none; }
        .forecast-stock-table td:nth-child(2) { grid-column:span 3;background:#f8fafc;border-radius:14px 0 0; }
        .forecast-stock-table td:nth-child(3) { background:#f8fafc;border-radius:0 14px 0 0; }
        .forecast-stock-table td:nth-child(2)::before { display:none; }
        .forecast-stock-table td[colspan] { grid-column:1/-1; }
        .forecast-row-number { display:inline;color:#64748b;margin-right:.4rem;font-weight:500; }
        .forecast-stock-table details { width:100%; }
        }
        @media(max-width:900px) {
            .forecast-stock-table tbody tr { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .forecast-stock-table td:nth-child(2),.forecast-stock-table td:nth-child(3) { grid-column:1/-1; }
        }
        @media(max-width:560px) {
            .forecast-stock-table tbody tr { grid-template-columns:minmax(0,1fr); }
        }
    }
    @media print {
        .forecast-stock-table { min-width:0;width:100%; }
        .forecast-table-scroll { overflow:visible; }
    }
</style>
<div class="forecast-page" style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem; width:100%; min-width:0; max-width:100%;">

    {{-- =================== HEADER =================== --}}
    <header class="forecast-panel forecast-header">
        <div class="forecast-heading">
            <p class="forecast-eyebrow">Demand Forecasting</p>
            <h1>Forecast Overview</h1>
            <p class="forecast-intro">Review stock priorities, projected demand, and upcoming supply needs.</p>
        </div>
    </header>

    @include('forecast._overview')

    {{-- ============================================================ --}}
    {{-- SECTION 1 - STOCK ANALYSIS TABLE                              --}}
    {{-- ============================================================ --}}
    <div class="forecast-panel forecast-stock-panel" style="
        padding:0;
        overflow:hidden;
        box-sizing:border-box; width:100%; min-width:0; max-width:100%;
    ">
        <div style="padding:20px 26px 0 26px; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:12px;">
            <div>
                <p class="forecast-eyebrow">Stock analysis</p>
                <h2 id="forecast-stock" style="--forecast-text-size:1.4375rem; font-weight:700; color:#0f172a; margin:0 0 4px 0;">Current Stock vs Consumption Analysis</h2>
                <p style="--forecast-text-size:1rem; color:#64748b; margin:0 0 18px 0;">Weekly stock-release regression drives projected demand, coverage, and reorder quantities.</p>
            </div>

            @if($forecast['items']->total() > 0)
                <p style="--forecast-text-size:1rem; color:#64748b; margin:0 0 18px 0;">
                    Showing <strong style="color:#0f172a;">{{ $forecast['items']->firstItem() ?? 0 }}</strong>–<strong style="color:#0f172a;">{{ $forecast['items']->lastItem() ?? 0 }}</strong>
                    of <strong style="color:#0f172a;">{{ $forecast['items']->total() }}</strong> items
                </p>
            @endif
        </div>

        <form method="GET" action="{{ route('forecast.index') }}#forecast-stock" style="display:flex; flex-wrap:wrap; align-items:end; gap:12px; padding:0 26px 16px;">
            <label for="forecast-stock-filter" style="min-width:0; max-width:100%; color:#334155;">Show stock analysis items
                <select id="forecast-stock-filter" name="stock_filter" data-native-select onchange="this.form.requestSubmit()"
                        style="display:block; max-width:100%; min-height:44px; padding:8px 12px; font:inherit; border:1px solid #bec6cf; border-radius:10px; background:#fff; appearance:auto;">
                    <option value="all" @selected($stockFilter === 'all')>All analysed items</option>
                    <option value="shortage" @selected($stockFilter === 'shortage')>Projected need exceeds available stock</option>
                    <option value="low" @selected($stockFilter === 'low')>Low or critical forecast coverage</option>
                    <option value="critical" @selected($stockFilter === 'critical')>Critical forecast coverage</option>
                    <option value="reorder" @selected($stockFilter === 'reorder')>At or below estimated reorder point</option>
                    <option value="unknown" @selected($stockFilter === 'unknown')>Without completed usage history</option>
                </select>
            </label>
        </form>
        <p class="forecast-scroll-note">All quantities use medicine base units. The filter applies to the stock analysis table; overview metrics and charts show the full facility scope.</p>
        {{-- Scroll container: min-width:0 lets it shrink below the table's intrinsic width --}}
        <div class="forecast-table-scroll" tabindex="0" role="region" aria-label="Stock analysis table">
            <table class="forecast-stock-table">
                <colgroup>
                    <col style="width:3%"><col style="width:16%"><col style="width:13%">
                    <col style="width:7%"><col style="width:8%"><col style="width:12%">
                    <col style="width:8%"><col style="width:7%"><col style="width:8%">
                    <col style="width:10%"><col style="width:8%">
                </colgroup>
                <caption>Forecasts use recorded releases. No usage history means demand cannot yet be estimated.</caption>
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <th scope="col" style=" text-align:left;     ">#</th>
                        <th scope="col" style=" text-align:left;     ">Medicine</th>
                        <th scope="col" style=" text-align:left;      ">RHU</th>
                        <th scope="col" style=" text-align:center;     ">Available</th>
                        <th scope="col" style=" text-align:center;     ">Average 30-day<br>Usage</th>
                        <th scope="col" style=" text-align:center;   ">Weekly trend</th>
                        <th scope="col" style=" text-align:center;   ">Next 30-day<br>Need</th>
                        <th scope="col" style=" text-align:center;     ">Days<br>Remaining</th>
                        <th scope="col" style=" text-align:center;     ">3‑mo<br>Need</th>
                        <th scope="col" style=" text-align:center;     ">Status</th>
                        <th scope="col" style=" text-align:center;     ">Reorder<br>Point</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($forecast['items'] as $index => $item)
                        @php
                            $statusLabel = $item['status'] === 'unknown'
                                ? 'No Usage Data'
                                : ucfirst($item['status']);

                            $rowNumber = ($forecast['items']->currentPage() - 1) * $forecast['items']->perPage() + $loop->iteration;
                        @endphp
                        <tr id="forecast-item-{{ $item['inventory']->health_unit_id }}-{{ $item['inventory']->medicine_id }}" style="border-bottom:1px solid #f1f5f9;">
                            <td data-label="Item" style=" color:#64748b; text-align:center;">{{ $rowNumber }}</td>
                            <td data-label="Medicine" style="font-weight:600; color:#0f172a;">
                                <span class="forecast-row-number" aria-hidden="true">#{{ $rowNumber }}</span>
                                {{ $item['medicine_name'] }} {{ $item['inventory']->medicine->dosage }}
                                <small style="display:block; color:#64748b;">{{ $item['base_unit'] }}</small>
                                <details style="margin-top:8px; font-weight:400; font-size:1.0625rem; min-width:0; max-width:100%;">
                                    <summary style="font-weight:600;">Demand basis</summary>
                                    @if($item['model']['periods'] > 0)
                                        <p style="margin:8px 0;">{{ number_format($item['history_total']) }} {{ $item['base_unit'] }} released from {{ $item['history_start'] }} to {{ $item['history_end'] }}, across {{ $item['model']['periods'] }} completed weeks.</p>
                                        <p style="margin:8px 0;">{{ $item['model']['method'] === 'linear_regression' ? 'Weekly release trend' : 'Limited-history weekly average' }} projects {{ number_format($item['forecast_full_weeks_need'], 1) }} {{ $item['base_unit'] }} over {{ $item['forecast_complete_weeks'] }} full weeks@if($item['forecast_remaining_days'] > 0), plus {{ number_format($item['forecast_partial_week_need'], 1) }} over the final {{ $item['forecast_remaining_days'] }} days@endif.</p>
                                        <p style="margin:8px 0;">Total need {{ number_format($item['forecast_need_3mo']) }} {{ $item['base_unit'] }} for {{ $item['forecast_start'] }} to {{ $item['forecast_end'] }}. Available now {{ number_format($item['available_stock']) }}. After expected daily use, {{ number_format($item['forecast_expiry_loss'] ?? 0) }} units are projected to expire unused. Additional stock needed {{ number_format($item['shortage']) }}.</p>
                                    @else
                                        <p style="margin:8px 0;">Completed release history is needed to estimate this item's future demand.</p>
                                    @endif
                                </details>
                            </td>
                            <td data-label="RHU" style=" color:#334155; ">{{ $item['health_unit_name'] }}</td>
                            <td data-label="Available" style=" text-align:center; font-weight:600; color:#0f172a;">{{ number_format($item['available_stock']) }}</td>
                            <td data-label="Average 30-day usage" style=" text-align:center; color:#64748b;">
                                {{ $item['model']['periods'] > 0 ? number_format($item['monthly_consumption'], 1) : 'No estimate' }}
                                @if($item['model']['periods'] > 0)<small>{{ number_format($item['history_total']) }} {{ $item['base_unit'] }} recorded over {{ $item['model']['periods'] }} completed weeks</small>@endif
                            </td>
                            <td data-label="Weekly trend" style="text-align:center;color:#334155;">
                                <strong>{{ ['rising' => 'Rising', 'falling' => 'Falling', 'stable' => 'Stable', 'insufficient_history' => 'Limited history', 'no_history' => 'No history'][$item['trend']] }}</strong>
                                <small style="display:block;color:#64748b;">{{ $item['model']['periods'] }} completed week(s)
                                </small>
                            </td>
                            <td data-label="Next 30-day need" style="text-align:center;color:#334155;">{{ $item['model']['periods'] > 0 ? number_format($item['forecast_next_30_days']) : 'No estimate' }}</td>
                            <td data-label="Days remaining" style=" text-align:center; font-weight:600; color:#0f172a;">
                                @if($item['days_remaining'] === '∞')
                                    <span style="color:#64748b;">∞</span>
                                @else
                                    {{ $item['days_remaining'] }}
                                @endif
                            </td>
                            <td data-label="3-month need" style="text-align:center; color:#64748b;">{{ $item['model']['periods'] > 0 ? number_format($item['forecast_need_3mo']) : 'No estimate' }}</td>
                            <td data-label="Status" style=" text-align:center;">
                                <span class="forecast-status forecast-status-{{ $item['status'] }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td data-label="Reorder point" style=" text-align:center; color:#64748b; font-weight:600;">
                                @if($item['reorder_point'] !== null)
                                    {{ number_format($item['reorder_point']) }}
                                @else
                                    <span style="color:#64748b;">N/A</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" style="padding:40px 20px; text-align:center; color:#64748b;">
                                <p style="--forecast-text-size:1.1875rem; margin:0;">{{ $stockFilter === 'all' ? 'No stock data available' : 'No items match this stock analysis filter' }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ============================================================ --}}
        {{-- PAGINATION — styled like the masterlist page                  --}}
        {{-- ============================================================ --}}
        @if ($forecast['items']->hasPages())
            <div class="no-print" style="
                padding: 16px 24px;
                border-top: 1px solid #e2e8f0;
                background: #fafbfc;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                flex-wrap: wrap;
            ">
                @if ($forecast['items']->onFirstPage())
                    <span style="--forecast-text-size:1.0625rem; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: default; user-select: none;">
                        ← Previous
                    </span>
                @else
                    <a href="{{ $forecast['items']->previousPageUrl() }}" style="
                        --forecast-text-size:1.0625rem; font-weight: 500; padding: 8px 16px; border-radius: 8px;
                        background: #ffffff; color: #334155; border: 1px solid #e2e8f0;
                        text-decoration: none; transition: all 0.2s;
                    "
                       onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';"
                       onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                        ← Previous
                    </a>
                @endif

                @foreach ($forecast['items']->getUrlRange(max(1, $forecast['items']->currentPage() - 2), min($forecast['items']->lastPage(), $forecast['items']->currentPage() + 2)) as $page => $url)
                    @if ($page == $forecast['items']->currentPage())
                        <span style="--forecast-text-size:1.0625rem; font-weight: 600; padding: 8px 14px; border-radius: 8px; background: #42ad8a; color: #ffffff; border: 1px solid #42ad8a; cursor: default;">
                            {{ $page }}
                        </span>
                    @else
                        <a href="{{ $url }}" style="
                            --forecast-text-size:1.0625rem; font-weight: 500; padding: 8px 14px; border-radius: 8px;
                            background: #ffffff; color: #334155; border: 1px solid #e2e8f0;
                            text-decoration: none; transition: all 0.2s;
                        "
                           onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';"
                           onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                            {{ $page }}
                        </a>
                    @endif
                @endforeach

                @if ($forecast['items']->hasMorePages())
                    <a href="{{ $forecast['items']->nextPageUrl() }}" style="
                        --forecast-text-size:1.0625rem; font-weight: 500; padding: 8px 16px; border-radius: 8px;
                        background: #ffffff; color: #334155; border: 1px solid #e2e8f0;
                        text-decoration: none; transition: all 0.2s;
                    "
                       onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';"
                       onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                        Next →
                    </a>
                @else
                    <span style="--forecast-text-size:1.0625rem; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: default; user-select: none;">
                        Next →
                    </span>
                @endif
            </div>
        @endif
    </div>

    @include('forecast._stock_trend')

    {{-- ============================================================ --}}
    {{-- SECTION 2 - SCHEDULED EVENTS                                  --}}
    {{-- ============================================================ --}}
    <div class="forecast-panel" style="
        padding:24px 26px;
        box-sizing:border-box; width:100%; min-width:0; max-width:100%;
    ">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
            <div>
                <h2 style="--forecast-text-size:1.4375rem; font-weight:700; color:#0f172a; margin:0 0 4px 0;" id="forecast-events">Scheduled Events &amp; Vaccine Targets</h2>
                <p style="--forecast-text-size:1rem; color:#64748b; margin:0;">
                    Calendar events, active EPI sessions, DoH programs and pending vaccine appointments for the next {{ $planned['summary']['horizon_days'] }} days.
                </p>
            </div>

            <div style="display:flex; gap:20px; flex-wrap:wrap;">
                <div style="text-align:center;">
                    <div style="--forecast-text-size:1rem; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:#64748b;">Events</div>
                    <div style="--forecast-text-size:1.5625rem; font-weight:800; color:#0f172a;">{{ $planned['summary']['total_events'] }}</div>
                </div>
                <div style="text-align:center;">
                    <div style="--forecast-text-size:1rem; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:#64748b;">Dose appointments</div>
                    <div style="--forecast-text-size:1.5625rem; font-weight:800; color:#42ad8a;">{{ number_format($planned['summary']['total_targets']) }}</div>
                </div>
                <div style="text-align:center;">
                    <div style="--forecast-text-size:1rem; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:#64748b;">Horizon</div>
                    <div style="--forecast-text-size:1.5625rem; font-weight:800; color:#0f172a;">{{ $planned['summary']['horizon_days'] }} days</div>
                </div>
            </div>
        </div>

        <p style="font-size:1rem; color:#475569; margin:16px 0;">Dose targets count pending vaccine appointments. A child can have several doses scheduled. Calendar activities with no recorded appointment targets show "Not specified".</p>
        @if($planned['summary']['total_targets'] === 0 && count($planned['items']) > 0)
            <p style="font-size:1rem; color:#92400e; margin:0 0 16px;">No pending vaccine appointments fall in this window. The chart shows the scheduled events. Register children and generate their vaccination schedules to add dose targets.</p>
        @endif
        @if(count($planned['items']) > 0)
            <div class="forecast-chart-container" style="height:260px; margin-bottom:20px;">
                <canvas id="plannedChart"></canvas>
            </div>

            <div style="width:100%; min-width:0; max-width:100%; overflow-x:auto; box-sizing:border-box; border-radius:12px; border:1px solid #e2e8f0;">
                <table style="width:100%; min-width:760px; border-collapse:collapse; --forecast-text-size:1.0625rem;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                            <th scope="col" style="padding:10px 14px; text-align:left;     ">Date</th>
                            <th scope="col" style="padding:10px 14px; text-align:left;     ">Event</th>
                            <th scope="col" style="padding:10px 14px; text-align:left;     ">Type</th>
                            <th scope="col" style="padding:10px 14px; text-align:center;     ">Dose appointments</th>
                            <th scope="col" style="padding:10px 14px; text-align:left;     ">Vaccine / facility</th>
                            <th scope="col" style="padding:10px 14px; text-align:center;     ">Basis</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($planned['items'] as $item)
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:10px 14px; color:#334155; white-space:nowrap;">
                                    {{ $item['date'] ? date('M d, Y', strtotime($item['date'])) : 'N/A' }}
                                </td>
                                <td style="padding:10px 14px; font-weight:600; color:#0f172a;">{{ $item['title'] }}</td>
                                <td style="padding:10px 14px;">
                                    <span style="display:inline-block; --forecast-text-size:1rem; font-weight:600; padding:2px 9px; border-radius:999px; background:#E8F7F4; color:#0f5c4b; border:1px solid #b2d8c9;">
                                        {{ $item['source'] }}
                                    </span>
                                </td>
                                <td style="padding:10px 14px; text-align:center; font-weight:700; color:#0f172a;">{{ $item['target_count'] === null ? 'Not specified' : number_format($item['target_count']) }}</td>
                                <td style="padding:10px 14px; color:#334155;">
                                    @if($item['medicine_name'])
                                        {{ $item['medicine_name'] }}
                                        @if($item['medicine_dosage'])<span style="color:#64748b;"> ({{ $item['medicine_dosage'] }})</span>@endif
                                    @else
                                        <span style="color:#64748b;">{{ $item['health_unit_name'] ?? 'RHU program' }}</span>
                                    @endif
                                </td>
                                <td style="padding:10px 14px; color:#475569;">{{ $item['basis'] }}@if($item['notes'] && $item['type'] === 'vaccine_targets')<small style="display:block;">{{ $item['notes'] }} at {{ $item['health_unit_name'] }}</small>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="text-align:center; padding:36px 20px; background:#f8fafc; border-radius:12px; border:1px dashed #cbd5e1;">
                <div style="--forecast-text-size:2.25rem; margin-bottom:6px;">📅</div>
                <h4 style="--forecast-text-size:1.25rem; font-weight:600; color:#0f172a; margin:0 0 4px 0;">No scheduled events or vaccine appointments in the next {{ $planned['summary']['horizon_days'] }} days</h4>
                <p style="--forecast-text-size:1.0625rem; color:#64748b; margin:0;">Add events to the RHU calendar to see scheduled forecasts here.</p>
            </div>
        @endif
    </div>

    {{-- ============================================================ --}}
    {{-- SECTION 3 - RECOMMENDED STOCK-UP                              --}}
    {{-- ============================================================ --}}
    @include('forecast._disease_consumption')

    {{-- ============================================================ --}}
    {{-- SECTION 4 - STOCK HEALTH CHARTS                               --}}
    {{-- ============================================================ --}}
    <div class="forecast-charts-row" id="forecast-charts">

        {{-- Stock Health Distribution --}}
        <div class="forecast-panel" style="
            padding:24px 26px;
            box-sizing:border-box; min-width:0;
        ">
            <div style="margin-bottom:16px;">
                <p style="--forecast-text-size:1rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0 0 4px 0;">Stock Health</p>
                <h2 style="--forecast-text-size:1.4375rem; font-weight:700; color:#0f172a; margin:0 0 4px 0;">Status Distribution</h2>
                <p style="--forecast-text-size:1rem; color:#64748b; margin:0;">Count of medicines by stock status.</p>
            </div>

            @if(count($forecast['all_items']) > 0)
                <div class="forecast-chart-container" style="height:320px;">
                    <canvas id="statusChart"></canvas>
                </div>
            @else
                <div style="text-align:center; padding:36px 20px; background:#f8fafc; border-radius:12px; border:1px dashed #cbd5e1;">
                    <div style="--forecast-text-size:2.25rem; margin-bottom:6px;">📊</div>
                    <h4 style="--forecast-text-size:1.25rem; font-weight:600; color:#0f172a; margin:0 0 4px 0;">No stock data to chart</h4>
                    <p style="--forecast-text-size:1.0625rem; color:#64748b; margin:0;">Add inventory to see the distribution.</p>
                </div>
            @endif
        </div>

        {{-- Available vs Need --}}
        <div class="forecast-panel" style="
            padding:24px 26px;
            box-sizing:border-box; min-width:0;
        ">
            <div style="margin-bottom:16px;">
                <p style="--forecast-text-size:1rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0 0 4px 0;">Demand vs Supply</p>
                <h2 style="--forecast-text-size:1.4375rem; font-weight:700; color:#0f172a; margin:0 0 4px 0;">Available Stock vs 3-Month Need</h2>
                <p style="--forecast-text-size:1rem; color:#64748b; margin:0;">Projected total use over {{ $forecast['summary']['horizon_days'] }} days is shown against available stock. Additional stock needed = max(total need &minus; available, 0). Each item retains its own unit; quantities in different units are not equivalent. Open Demand basis in an item row to see its history and calculation.</p>
            </div>

            @if(count($forecast['all_items']) > 0)
                <div class="forecast-chart-container" style="height:320px;">
                    <canvas id="needChart"></canvas>
                </div>
            @else
                <div style="text-align:center; padding:36px 20px; background:#f8fafc; border-radius:12px; border:1px dashed #cbd5e1;">
                    <div style="--forecast-text-size:2.25rem; margin-bottom:6px;">📊</div>
                    <h4 style="--forecast-text-size:1.25rem; font-weight:600; color:#0f172a; margin:0 0 4px 0;">No stock data to chart</h4>
                    <p style="--forecast-text-size:1.0625rem; color:#64748b; margin:0;">Add inventory to see the comparison.</p>
                </div>
            @endif
        </div>

    </div>



</div>



<script src="{{ asset('lib/chartjs/chart.umd.js') }}"></script>
<script src="{{ asset('js/forecast-consumption.js') }}" defer></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded');
        return;
    }

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 16;
    Chart.defaults.color = '#475569';

    const planned = @json($planned);

    // Charts use the FULL item list so they don't change per page.
    const items   = @json($forecast['all_items'] ?? []);

    // Facility/medicine time series. Quantities remain numbers; labels use textContent.
    (() => {
        const select = document.getElementById('stock-trend-item');
        const canvas = document.getElementById('stockTrendChart');
        const note = document.getElementById('stock-trend-note');
        if (!select || !canvas) return;
        let chart;
        const render = () => {
            const item = items[Number(select.value)];
            if (!item) return;
            if (chart) chart.destroy();
            const history = item.weekly_history;
            const future = item.weekly_forecast;
            const fit = item.model;
            if (!history.length) {
                note.textContent = 'No completed release history. A usage trend cannot be estimated yet.';
                return;
            }
            const method = fit.method === 'linear_regression' ? 'Linear regression' : 'Limited history: weekly-average fallback';
            note.textContent = `${method} | ${fit.periods} completed week(s) | ${item.base_unit || 'base units'}. Need covers ${item.forecast_start} to ${item.forecast_end}.`;
            const labels = history.concat(future).map(period => period.start);
            const actual = history.map(period => period.quantity).concat(future.map(() => null));
            const fitted = fit.fitted.concat(future.map(() => null));
            const forecast = history.map(() => null).concat(future.map(period => period.quantity));
            forecast[history.length - 1] = Math.max(0, fit.fitted[history.length - 1]);
            chart = new Chart(canvas, {
                type: 'line',
                data: {labels, datasets: [
                    {label: 'Recorded weekly releases', data: actual, borderColor: '#0f766e', backgroundColor: '#0f766e', tension: 0},
                    {label: 'Historical fitted line', data: fitted, borderColor: '#94a3b8', borderDash: [3, 3], pointRadius: 0, tension: 0},
                    {label: 'Projected weekly releases', data: forecast, borderColor: '#2563eb', borderDash: [6, 4], tension: 0}
                ]},
                options: {responsive: true, maintainAspectRatio: false, interaction: {mode: 'index', intersect: false},
                    scales: {y: {beginAtZero: true, title: {display: true, text: 'Base units per seven-day period'}},
                        x: {title: {display: true, text: 'Period start (forecast periods follow the last recorded period)'}}}}
            });
        };
        select.addEventListener('change', render);
        render();
    })();


    /* ============================================================
       PLANNED CHART - bar by month
       ============================================================ */
    (function () {
        const canvas = document.getElementById('plannedChart');
        if (!canvas) return;
        const byMonth = planned?.summary?.by_month || {};
        const labels  = Object.keys(byMonth).map(function (m) {
            const [y, mo] = m.split('-');
            return new Date(y, mo - 1, 1).toLocaleString('en-US', { month: 'short', year: 'numeric' });
        });
        const data    = Object.values(byMonth);

        new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Dose appointments',
                    data: data,
                    backgroundColor: 'rgba(66, 173, 138, 0.9)',
                    borderColor: '#369f7d',
                    borderWidth: 1,
                    borderRadius: 8,
                    maxBarThickness: 60,
                }, {
                    label: 'Scheduled events',
                    data: Object.values(planned?.summary?.events_by_month || {}),
                    backgroundColor: 'rgba(59,130,246,.8)',
                    borderColor: '#3b82f6', borderWidth: 1, borderRadius: 8, maxBarThickness: 60,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 600, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: true, labels: { font: { size: 15 } } },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#ffffff',
                        bodyColor: '#e2e8f0',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function (ctx) { return ' ' + ctx.dataset.label + ': ' + Number(ctx.parsed.y).toLocaleString(); }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#64748b', font: { size: 14 } },
                        grid: { display: false },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#64748b',
                            font: { size: 14 },
                            precision: 0,
                            callback: function (v) { return Number(v).toLocaleString(); }
                        },
                        grid: { color: '#eef2f6' },
                    }
                }
            }
        });
    })();

    /* ============================================================
       STATUS DISTRIBUTION - doughnut
       ============================================================ */
    (function () {
        const canvas = document.getElementById('statusChart');
        if (!canvas) return;

        const counts = { critical: 0, low: 0, adequate: 0, surplus: 0, unknown: 0 };
        items.forEach(function (i) {
            const s = (i.status || 'unknown').toLowerCase();
            if (counts[s] !== undefined) counts[s]++;
            else counts.unknown++;
        });

        const labels = ['Critical', 'Low', 'Adequate', 'Surplus', 'No Usage Data'];
        const data   = [counts.critical, counts.low, counts.adequate, counts.surplus, counts.unknown];
        const colors = ['#dc2626', '#eab308', '#42ad8a', '#3b82f6', '#94a3b8'];

        const total = data.reduce(function (a, b) { return a + b; }, 0);
        const hasData = total > 0;

        const centerTextPlugin = {
            id: 'centerText',
            afterDraw: function (chart) {
                const { ctx, chartArea } = chart;
                if (!chartArea) return;
                const cx = (chartArea.left + chartArea.right) / 2;
                const cy = (chartArea.top + chartArea.bottom) / 2;
                ctx.save();
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillStyle = '#0f172a';
                ctx.font = '800 32px Arial';
                ctx.fillText(total, cx, cy - 6);
                ctx.fillStyle = '#64748b';
                ctx.font = '600 14px Arial';
                ctx.fillText('TOTAL ITEMS', cx, cy + 22);
                ctx.restore();
            }
        };

        new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: hasData ? data : [1],
                    backgroundColor: hasData ? colors : ['#e2e8f0'],
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                animation: { duration: 700, easing: 'easeOutQuart' },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { size: 14, weight: '600' },
                            color: '#334155',
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 12,
                            boxWidth: 8,
                        }
                    },
                    tooltip: {
                        enabled: hasData,
                        backgroundColor: '#0f172a',
                        titleColor: '#ffffff',
                        bodyColor: '#e2e8f0',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function (ctx) {
                                const v = ctx.parsed;
                                const pct = total ? ((v / total) * 100).toFixed(1) : 0;
                                return ' ' + ctx.label + ': ' + v + '  (' + pct + '%)';
                            }
                        }
                    }
                }
            },
            plugins: [centerTextPlugin]
        });
    })();

    /* ============================================================
       NEED CHART - top 10 by 3-month need, grouped bar
       ============================================================ */
    (function () {
        const canvas = document.getElementById('needChart');
        if (!canvas) return;

        const sorted = items
            .slice()
            .sort(function (a, b) {
                return (Number(b.forecast_need_3mo) || 0) - (Number(a.forecast_need_3mo) || 0);
            })
            .slice(0, 10);

        const labels    = sorted.map(function (i) {
            return i.medicine_name + ' (' + (i.base_unit || 'units') + ')' + (i.health_unit_name ? ' · ' + i.health_unit_name : '');
        });
        const available = sorted.map(function (i) { return Number(i.available_stock) || 0; });
        const need      = sorted.map(function (i) { return Number(i.forecast_need_3mo) || 0; });

        new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Available Stock',
                        data: available,
                        backgroundColor: 'rgba(66, 173, 138, 0.9)',
                        borderColor: '#369f7d',
                        borderWidth: 1,
                        borderRadius: 6,
                        maxBarThickness: 22,
                    },
                    {
                        label: '3-Month Need',
                        data: need,
                        backgroundColor: 'rgba(148, 163, 184, 0.85)',
                        borderColor: '#94a3b8',
                        borderWidth: 1,
                        borderRadius: 6,
                        maxBarThickness: 22,
                    }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 700, easing: 'easeOutQuart' },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: { size: 14, weight: '600' },
                            color: '#334155',
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 14,
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#ffffff',
                        bodyColor: '#e2e8f0',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function (context) {
                                const item = sorted[context.dataIndex];
                                return context.dataset.label + ': ' + Number(context.raw).toLocaleString() + ' ' + (item?.base_unit || 'units');
                            },
                            afterBody: function (items) {
                                const idx = items[0].dataIndex;
                                const item = sorted[idx];
                                if (!item) return '';
                                const short = Math.max(0, (Number(item.forecast_need_3mo) || 0) - (Number(item.available_stock) || 0));
                                return ['Shortfall: ' + short.toLocaleString() + ' ' + (item.base_unit || 'units')];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            color: '#64748b',
                            font: { size: 14 },
                            precision: 0,
                            callback: function (v) { return Number(v).toLocaleString(); }
                        },
                        grid: { color: '#eef2f6' },
                    },
                    y: {
                        ticks: {
                            color: '#334155',
                            font: { size: 14 },
                            autoSkip: false,
                        },
                        grid: { display: false },
                    }
                }
            }
        });
    })();
});
</script>
@endsection
