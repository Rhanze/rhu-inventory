@extends('layouts.app')

@section('content')
@include('forecast._styles')
<div class="forecast-page" style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem; width:100%; min-width:0; max-width:100%;">

    {{-- =================== HEADER =================== --}}
    <div style="
        background: #ffffff;
        border-radius:12px; padding:24px 28px; border:1px solid #dce5e2;
        
        box-sizing:border-box; width:100%; min-width:0; max-width:100%;
    ">
        <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Demand Forecasting</p>
        <h1 style="font-size:26px; font-weight:700; color:#0f172a; margin:0;">Forecast Overview</h1>
        <p style="font-size:14px;color:#475569;margin:8px 0 0;">Review stock priorities, projected demand, and the reports behind your forecast.</p>
    </div>

    @include('forecast._overview')

    {{-- ============================================================ --}}
    {{-- SECTION 1 - STOCK ANALYSIS TABLE                              --}}
    {{-- ============================================================ --}}
    <div style="
        background: #ffffff;
        border-radius:12px; padding:0;
        border:1px solid #dce5e2;
        
        overflow:hidden;
        box-sizing:border-box; width:100%; min-width:0; max-width:100%;
    ">
        <div style="padding:20px 26px 0 26px; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:12px;">
            <div>
                <p style="font-size:13px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#64748b; margin:0 0 4px 0;">Reference</p>
                <h2 id="forecast-stock" style="font-size:20px; font-weight:700; color:#0f172a; margin:0 0 4px 0;">Current Stock vs Consumption Analysis</h2>
                <p style="font-size:13px; color:#64748b; margin:0 0 18px 0;">Weekly stock-release regression drives projected demand, coverage, and reorder quantities.</p>
            </div>

            @if($forecast['items']->total() > 0)
                <p style="font-size:13px; color:#64748b; margin:0 0 18px 0;">
                    Showing <strong style="color:#0f172a;">{{ $forecast['items']->firstItem() ?? 0 }}</strong>–<strong style="color:#0f172a;">{{ $forecast['items']->lastItem() ?? 0 }}</strong>
                    of <strong style="color:#0f172a;">{{ $forecast['items']->total() }}</strong> items
                </p>
            @endif
        </div>

        <p class="forecast-scroll-note">All quantities use medicine base units. Scroll horizontally to view every column.</p>
        {{-- Scroll container: min-width:0 lets it shrink below the table's intrinsic width --}}
        <div tabindex="0" role="region" aria-label="Stock analysis table" style="width:100%; min-width:0; max-width:100%; overflow-x:auto; overflow-y:hidden; box-sizing:border-box;">
            <table style="width:100%; min-width:1100px; border-collapse:collapse; font-size:14px;">
                <caption>Forecasts use recorded releases. No usage history means demand cannot yet be estimated.</caption>
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <th style="padding:12px 16px; text-align:left; font-weight:600; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:0.06em;">#</th>
                        <th style="padding:12px 16px; text-align:left; font-weight:600; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:0.06em;">Medicine</th>
                        <th style="padding:12px 16px; text-align:left; font-weight:600; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:0.06em; white-space:nowrap;">RHU</th>
                        <th style="padding:12px 16px; text-align:center; font-weight:600; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:0.06em;">Available</th>
                        <th style="padding:12px 16px; text-align:center; font-weight:600; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:0.06em;">Average 30-day<br>Usage</th>
                        <th style="padding:12px 16px; text-align:center; font-weight:600; color:#475569; font-size:12px;">Weekly trend</th>
                        <th style="padding:12px 16px; text-align:center; font-weight:600; color:#475569; font-size:12px;">Next 30-day<br>Need</th>
                        <th style="padding:12px 16px; text-align:center; font-weight:600; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:0.06em;">Days<br>Remaining</th>
                        <th style="padding:12px 16px; text-align:center; font-weight:600; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:0.06em;">3‑mo<br>Need</th>
                        <th style="padding:12px 16px; text-align:center; font-weight:600; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:0.06em;">Status</th>
                        <th style="padding:12px 16px; text-align:center; font-weight:600; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:0.06em;">Reorder<br>Point</th>
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
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:12px 16px; color:#64748b; text-align:center;">{{ $rowNumber }}</td>
                            <td style="padding:12px 16px; font-weight:600; color:#0f172a;">{{ $item['medicine_name'] }}</td>
                            <td style="padding:12px 16px; color:#334155; white-space:nowrap;">{{ $item['health_unit_name'] }}</td>
                            <td style="padding:12px 16px; text-align:center; font-weight:600; color:#0f172a;">{{ $item['available_stock'] }}</td>
                            <td style="padding:12px 16px; text-align:center; color:#64748b;">{{ number_format($item['monthly_consumption'], 1) }}</td>
                            <td style="padding:12px 16px;text-align:center;color:#334155;">
                                <strong>{{ ['rising' => 'Rising', 'falling' => 'Falling', 'stable' => 'Stable', 'insufficient_history' => 'Limited history', 'no_history' => 'No history'][$item['trend']] }}</strong>
                                <small style="display:block;color:#64748b;">{{ $item['model']['periods'] }} completed week(s)
                                    @if($item['model']['r_squared'] !== null) ? R&sup2; {{ number_format($item['model']['r_squared'], 2) }} @endif
                                </small>
                            </td>
                            <td style="padding:12px 16px;text-align:center;color:#334155;">{{ number_format($item['forecast_next_30_days']) }}</td>
                            <td style="padding:12px 16px; text-align:center; font-weight:600; color:#0f172a;">
                                @if($item['days_remaining'] === '∞')
                                    <span style="color:#64748b;">∞</span>
                                @else
                                    {{ $item['days_remaining'] }}
                                @endif
                            </td>
                            <td style="padding:12px 16px; text-align:center; color:#64748b;">{{ number_format($item['forecast_need_3mo']) }}</td>
                            <td style="padding:12px 16px; text-align:center;">
                                <span class="forecast-status forecast-status-{{ $item['status'] }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td style="padding:12px 16px; text-align:center; color:#64748b; font-weight:600;">
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
                                <p style="font-size:16px; margin:0;">No stock data available</p>
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
                    <span style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: default; user-select: none;">
                        ← Previous
                    </span>
                @else
                    <a href="{{ $forecast['items']->previousPageUrl() }}" style="
                        font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px;
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
                        <span style="font-size: 14px; font-weight: 600; padding: 8px 14px; border-radius: 8px; background: #42ad8a; color: #ffffff; border: 1px solid #42ad8a; cursor: default;">
                            {{ $page }}
                        </span>
                    @else
                        <a href="{{ $url }}" style="
                            font-size: 14px; font-weight: 500; padding: 8px 14px; border-radius: 8px;
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
                        font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px;
                        background: #ffffff; color: #334155; border: 1px solid #e2e8f0;
                        text-decoration: none; transition: all 0.2s;
                    "
                       onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';"
                       onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                        Next →
                    </a>
                @else
                    <span style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: default; user-select: none;">
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
    <div style="
        background: #ffffff;
        border-radius:12px; padding:24px 26px;
        border:1px solid #dce5e2;
        
        box-sizing:border-box; width:100%; min-width:0; max-width:100%;
    ">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
            <div>
                <h2 style="font-size:20px; font-weight:700; color:#0f172a; margin:0 0 4px 0;" id="forecast-events">Scheduled Events &amp; Vaccine Targets</h2>
                <p style="font-size:13px; color:#64748b; margin:0;">
                    Upcoming calendar events and their recorded targets help you prepare stock before each event.
                </p>
            </div>

            <div style="display:flex; gap:20px; flex-wrap:wrap;">
                <div style="text-align:center;">
                    <div style="font-size:12px; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:#64748b;">Events</div>
                    <div style="font-size:22px; font-weight:800; color:#0f172a;">{{ $planned['summary']['total_events'] }}</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:12px; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:#64748b;">Target Population</div>
                    <div style="font-size:22px; font-weight:800; color:#42ad8a;">{{ number_format($planned['summary']['total_targets']) }}</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:12px; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:#64748b;">Horizon</div>
                    <div style="font-size:22px; font-weight:800; color:#0f172a;">{{ $planned['summary']['horizon_days'] }} days</div>
                </div>
            </div>
        </div>

        @if(count($planned['items']) > 0)
            <div style="position:relative; width:100%; height:260px; margin-bottom:20px;">
                <canvas id="plannedChart"></canvas>
            </div>

            <div style="width:100%; min-width:0; max-width:100%; overflow-x:auto; box-sizing:border-box; border-radius:12px; border:1px solid #e2e8f0;">
                <table style="width:100%; min-width:760px; border-collapse:collapse; font-size:14px;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                            <th style="padding:10px 14px; text-align:left; font-weight:600; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:0.06em;">Date</th>
                            <th style="padding:10px 14px; text-align:left; font-weight:600; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:0.06em;">Event</th>
                            <th style="padding:10px 14px; text-align:left; font-weight:600; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:0.06em;">Type</th>
                            <th style="padding:10px 14px; text-align:center; font-weight:600; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:0.06em;">Target</th>
                            <th style="padding:10px 14px; text-align:left; font-weight:600; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:0.06em;">Medicine / Vaccine</th>
                            <th style="padding:10px 14px; text-align:center; font-weight:600; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:0.06em;">Planned Qty</th>
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
                                    <span style="display:inline-block; font-size:12px; font-weight:600; padding:2px 9px; border-radius:999px; background:#E8F7F4; color:#0f5c4b; border:1px solid #b2d8c9;">
                                        {{ ucfirst(str_replace('_', ' ', $item['type'])) }}
                                    </span>
                                </td>
                                <td style="padding:10px 14px; text-align:center; font-weight:700; color:#0f172a;">{{ $item['target_count'] }}</td>
                                <td style="padding:10px 14px; color:#334155;">
                                    @if($item['medicine_name'])
                                        {{ $item['medicine_name'] }}
                                        @if($item['medicine_dosage'])<span style="color:#64748b;"> ({{ $item['medicine_dosage'] }})</span>@endif
                                    @else
                                        <span style="color:#64748b;">Not linked</span>
                                    @endif
                                </td>
                                <td style="padding:10px 14px; text-align:center; font-weight:700; color:#2a9d8f;">{{ $item['target_count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="text-align:center; padding:36px 20px; background:#f8fafc; border-radius:12px; border:1px dashed #cbd5e1;">
                <div style="font-size:36px; margin-bottom:6px;">📅</div>
                <h4 style="font-size:17px; font-weight:600; color:#0f172a; margin:0 0 4px 0;">No scheduled events in the next 90 days</h4>
                <p style="font-size:14px; color:#64748b; margin:0;">Add events to the RHU calendar to see scheduled forecasts here.</p>
            </div>
        @endif
    </div>

    {{-- ============================================================ --}}
    {{-- SECTION 3 - RECOMMENDED STOCK-UP                              --}}
    {{-- ============================================================ --}}
    <div style="
        background: #ffffff;
        border-radius:12px; padding:24px 26px;
        border:1px solid #dce5e2;
        
        box-sizing:border-box; width:100%; min-width:0; max-width:100%;
    ">
        <div style="margin-bottom:18px;">
            <h2 style="font-size:20px; font-weight:700; color:#0f172a; margin:0 0 4px 0;" id="forecast-diseases">Medicines Linked to Reported Diseases</h2>
            <p style="font-size:13px; color:#64748b; margin:0;">
                Confirmed reports from the last {{ $unplanned['completed_periods'] }} completed weeks, ranked by reported cases. Linked medicines are shown for stock planning; quantities are not forecast from case counts.
            </p>
        </div>

        @if(count($unplanned['items']) > 0)
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:16px;">
                @foreach($unplanned['items'] as $disease)
                    @php
                        $rank  = $disease->rank  ?? ($loop->iteration);
                        $color = $disease->color ?? '#42ad8a';
                    @endphp
                    <div style="
                        background:#ffffff;
                        border:1px solid #e2e8f0;
                        border-left:4px solid {{ $color }};
                        border-radius:14px;
                        padding:18px 20px;
                        
                    ">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:10px;">
                            <div>
                                <div style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:{{ $color }}; margin-bottom:4px;">
                                    #{{ $rank }} Trending
                                </div>
                                <h4 style="font-size:18px; font-weight:700; color:#0f172a; margin:0; line-height:1.25;">
                                    {{ $disease->disease_name }}
                                </h4>
                            </div>
                            <div style="text-align:right; flex-shrink:0;">
                                <div style="font-size:22px; font-weight:800; color:{{ $color }}; line-height:1;">
                                    {{ number_format($disease->total_cases) }}
                                </div>
                                <div style="font-size:12px; color:#64748b; margin-top:2px;">cases</div>
                            </div>
                        </div>

                        <p style="font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#64748b; margin:14px 0 8px 0;">
                            Recommended Medicines
                        </p>

                        @if(count($disease->medicines) > 0)
                            <div style="display:flex; flex-direction:column; gap:6px;">
                                @foreach($disease->medicines as $m)
                                    <div style="
                                        display:flex; justify-content:space-between; align-items:center; gap:8px;
                                        background:#f8fafc; border:1px solid #eef2f6; border-radius:8px;
                                        padding:8px 12px; font-size:13px;
                                    ">
                                        <span style="font-weight:600; color:#0f172a;">
                                            {{ $m->medicine_name }}
                                            @if($m->dosage)<span style="color:#64748b; font-weight:400;"> ({{ $m->dosage }})</span>@endif
                                        </span>
                                        @if($m->requires_prescription)
                                            <span style="font-size:12px; font-weight:700; padding:2px 7px; border-radius:999px; background:#faeeda; color:#854f0b; border:1px solid #fac775;">Rx</span>
                                        @else
                                            <span style="font-size:12px; font-weight:700; padding:2px 7px; border-radius:999px; background:#E8F7F4; color:#3b6d11; border:1px solid #c0dd97;">OTC</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p style="font-size:13px; color:#64748b; margin:0;">No medicines linked to this disease yet.</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <p style="font-size:12px; color:#64748b; margin:14px 0 0 0; font-style:italic;">
                These are recommendations only. Verify with the RHU physician before procuring.
            </p>
        @else
            <div style="text-align:center; padding:36px 20px; background:#f8fafc; border-radius:12px; border:1px dashed #cbd5e1;">
                <div style="font-size:36px; margin-bottom:6px;">📈</div>
                <h4 style="font-size:17px; font-weight:600; color:#0f172a; margin:0 0 4px 0;">No confirmed weekly reports available</h4>
                <p style="font-size:14px; color:#64748b; margin:0;">Reports must cover a completed week and be confirmed before appearing here. A missing report does not mean zero cases.</p>
            </div>
        @endif
    </div>

    {{-- ============================================================ --}}
    {{-- SECTION 4 - STOCK HEALTH CHARTS                               --}}
    {{-- ============================================================ --}}
    <div style="display:grid; grid-template-columns: 1fr 1.3fr; gap:20px;" class="forecast-charts-row" id="forecast-charts">

        {{-- Stock Health Distribution --}}
        <div style="
            background: #ffffff;
            border-radius:12px; padding:24px 26px;
            border:1px solid #dce5e2;
            
            box-sizing:border-box; min-width:0;
        ">
            <div style="margin-bottom:16px;">
                <p style="font-size:13px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0 0 4px 0;">Stock Health</p>
                <h2 style="font-size:20px; font-weight:700; color:#0f172a; margin:0 0 4px 0;">Status Distribution</h2>
                <p style="font-size:13px; color:#64748b; margin:0;">Count of medicines by stock status.</p>
            </div>

            @if(count($forecast['all_items']) > 0)
                <div style="position:relative; width:100%; height:320px;">
                    <canvas id="statusChart"></canvas>
                </div>
            @else
                <div style="text-align:center; padding:36px 20px; background:#f8fafc; border-radius:12px; border:1px dashed #cbd5e1;">
                    <div style="font-size:36px; margin-bottom:6px;">📊</div>
                    <h4 style="font-size:17px; font-weight:600; color:#0f172a; margin:0 0 4px 0;">No stock data to chart</h4>
                    <p style="font-size:14px; color:#64748b; margin:0;">Add inventory to see the distribution.</p>
                </div>
            @endif
        </div>

        {{-- Available vs Need --}}
        <div style="
            background: #ffffff;
            border-radius:12px; padding:24px 26px;
            border:1px solid #dce5e2;
            
            box-sizing:border-box; min-width:0;
        ">
            <div style="margin-bottom:16px;">
                <p style="font-size:13px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0 0 4px 0;">Demand vs Supply</p>
                <h2 style="font-size:20px; font-weight:700; color:#0f172a; margin:0 0 4px 0;">Available Stock vs 3-Month Need</h2>
                <p style="font-size:13px; color:#64748b; margin:0;">Top 10 medicines by 3-month need. Gap indicates shortfall.</p>
            </div>

            @if(count($forecast['all_items']) > 0)
                <div style="position:relative; width:100%; height:320px;">
                    <canvas id="needChart"></canvas>
                </div>
            @else
                <div style="text-align:center; padding:36px 20px; background:#f8fafc; border-radius:12px; border:1px dashed #cbd5e1;">
                    <div style="font-size:36px; margin-bottom:6px;">📊</div>
                    <h4 style="font-size:17px; font-weight:600; color:#0f172a; margin:0 0 4px 0;">No stock data to chart</h4>
                    <p style="font-size:14px; color:#64748b; margin:0;">Add inventory to see the comparison.</p>
                </div>
            @endif
        </div>

    </div>

    {{-- Methodology --}}
    <details style="
        background: #ffffff;
        border-radius:16px; padding:16px 20px;
        border:1px solid #dce5e2;
        
        box-sizing:border-box; width:100%; min-width:0; max-width:100%;
    ">
        <summary>How to read this forecast</summary>
        <p style="font-size:14px; color:#475569; margin:12px 0 0;">
            📌 <strong>Methodology:</strong>
            <br>• <strong>Stock Analysis</strong>: linear regression of recorded weekly releases. Days Remaining uses cumulative projected releases (365+ means the forecast does not exhaust stock within a year).
            <strong>Reorder Point</strong> sums projected demand during the {{ config('forecasting.lead_time_days') }}-day lead time plus {{ config('forecasting.safety_stock_days') }} safety days. Items without completed usage history show <strong>N/A</strong>.
            <br>• <strong>Scheduled Events</strong>: derived from upcoming calendar events (next 90 days). Target population per event is the required quantity.
            <br>• <strong>Recommended Stock-up</strong>: confirmed weekly reports from the last {{ $unplanned['completed_periods'] }} completed weeks, ranked by case count and color-coded. Linked medicines come from the disease-to-medicine mapping.
            <br>• <strong>Charts</strong>: status distribution shows item count per status; need chart compares available stock against 3-month projected demand.
        </p>
    </details>

</div>



<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded');
        return;
    }

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
            note.textContent = `${method} | ${fit.periods} completed week(s)` +
                (fit.r_squared === null ? '' : ` | historical fit R-squared ${fit.r_squared.toFixed(3)} | slope ${fit.slope.toFixed(2)} units per week per period`);
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
                    label: 'Target Population',
                    data: data,
                    backgroundColor: 'rgba(66, 173, 138, 0.9)',
                    borderColor: '#369f7d',
                    borderWidth: 1,
                    borderRadius: 8,
                    maxBarThickness: 60,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 600, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#ffffff',
                        bodyColor: '#e2e8f0',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function (ctx) { return ' ' + Number(ctx.parsed.y).toLocaleString() + ' to be served'; }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#64748b', font: { size: 12 } },
                        grid: { display: false },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#64748b',
                            font: { size: 11 },
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
                ctx.font = '600 12px Arial';
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
                            font: { size: 12, weight: '600' },
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
            return i.medicine_name + (i.health_unit_name ? ' · ' + i.health_unit_name : '');
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
                            font: { size: 12, weight: '600' },
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
                            afterBody: function (items) {
                                const idx = items[0].dataIndex;
                                const item = sorted[idx];
                                if (!item) return '';
                                const short = Math.max(0, (Number(item.forecast_need_3mo) || 0) - (Number(item.available_stock) || 0));
                                return ['─────────────', 'Shortfall: ' + short.toLocaleString()];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            color: '#64748b',
                            font: { size: 11 },
                            precision: 0,
                            callback: function (v) { return Number(v).toLocaleString(); }
                        },
                        grid: { color: '#eef2f6' },
                    },
                    y: {
                        ticks: {
                            color: '#334155',
                            font: { size: 11 },
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