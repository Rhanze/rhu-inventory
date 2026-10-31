@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- =================== ALERT ROW =================== --}}
    <div style="display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px;">
        <div style="background:#FAEEDA; border:1px solid #FAC775; border-radius:14px; padding:18px;">
            <p style="font-size:13px; font-weight:600; color:#854F0B;">Low Stock Alerts</p>
            <p style="font-size:32px; font-weight:700; color:#633806; margin-top:6px;">{{ $stats['low_stock_count'] ?? 0 }}</p>
            <p style="font-size:12px; color:#854F0B; margin-top:4px;">
                {{ $isPHOAdmin ? 'Items needing replenishment (all RHUs)' : 'Items needing replenishment' }}
            </p>
        </div>
        <div style="background:#FCEBEB; border:1px solid #F7C1C1; border-radius:14px; padding:18px;">
            <p style="font-size:13px; font-weight:600; color:#A32D2D;">Near Expiry</p>
            <p style="font-size:32px; font-weight:700; color:#791F1F; margin-top:6px;">{{ $stats['near_expiry_count'] ?? 0 }}</p>
            <p style="font-size:12px; color:#A32D2D; margin-top:4px;">
                {{ $isPHOAdmin ? 'Expiring within 30 days (all RHUs)' : 'Expiring within 30 days' }}
            </p>
        </div>
        <div style="background:#F3E8FF; border:1px solid #D8B4FE; border-radius:14px; padding:18px;">
            <p style="font-size:13px; font-weight:600; color:#6B21A8;">Overstocked</p>
            <p style="font-size:32px; font-weight:700; color:#4C1D95; margin-top:6px;">{{ $stats['overstock_count'] ?? 0 }}</p>
            <p style="font-size:12px; color:#6B21A8; margin-top:4px;">
                {{ $isPHOAdmin ? 'Items above max (all RHUs)' : 'Items above max threshold' }}
            </p>
        </div>
        <div style="background:#E8F7F4; border:1px solid #42AD8A; border-radius:14px; padding:18px;">
            <p style="font-size:13px; font-weight:600; color:#0f5c4b;">
                {{ session('role') === 'staff' ? "Today's Activity" : 'Total Transactions' }}
            </p>
            <p style="font-size:32px; font-weight:700; color:#0f5c4b; margin-top:6px;">
                @if(session('role') === 'staff')
                    {{ ($stats['allocations_count'] ?? 0) + ($stats['dispensing_count'] ?? 0) }}
                @elseif($isPHOAdmin)
                    {{ ($stats['stock_logs_count'] ?? 0) + ($stats['allocations_count'] ?? 0) }}
                @else
                    {{ ($stats['stock_logs_count'] ?? 0) + ($stats['allocations_count'] ?? 0) + ($stats['dispensing_count'] ?? 0) }}
                @endif
            </p>
            <p style="font-size:12px; color:#0f5c4b; margin-top:4px;">
                @if(session('role') === 'staff')
                    Allocation &amp; dispensing records
                @elseif($isPHOAdmin)
                    Stock &amp; allocation records
                @else
                    Stock, allocation &amp; dispensing
                @endif
            </p>
        </div>
    </div>

    {{-- =================== WELCOME + INFO =================== --}}
    <div style="
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(12px);
        border-radius:24px;
        padding:32px;
        border:1px solid rgba(66,173,138,0.2);
        box-shadow:0 12px 24px rgba(0,0,0,0.08);
    ">
        <div style="display:grid; grid-template-columns:1.5fr 1fr; gap:32px;">
            <div>
                <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin-bottom:10px;">
                    @if($isPHOAdmin)
                        PHO Dashboard
                    @elseif(session('role') === 'staff')
                        Staff Dashboard
                    @else
                        Admin Dashboard
                    @endif
                </p>
                <h1 style="font-size:28px; font-weight:700; color:#0f172a;">Welcome back, {{ $user->name }}</h1>
                <p style="font-size:15px; color:#475569; margin-top:8px; line-height:1.6;">
                    @if($isPHOAdmin)
                        You are viewing province‑wide inventory data for <strong style="color:#0f172a;">Southern Antique</strong>. All RHU records and reports are accessible.
                    @else
                        Manage daily inventory work, stock movement, allocations, and dispensing for
                        <strong style="color:#0f172a;">{{ $user->healthUnit->name ?? 'your RHU' }}</strong>.
                    @endif
                </p>

                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:24px;">
                    @if($isPHOAdmin)
                        @if(Route::has('rhu.create')) <a href="{{ route('rhu.create') }}" class="quick-btn">+ Add RHU</a> @endif
                        @if(Route::has('users.create')) <a href="{{ route('users.create') }}" class="quick-btn">+ Add User</a> @endif
                        @if(Route::has('reports.index')) <a href="{{ route('reports.index') }}" class="quick-btn">View Reports</a> @endif
                    @elseif(session('role') === 'staff')
                        @if(Route::has('inventory.create')) <a href="{{ route('inventory.create') }}" class="quick-btn">+ Add Inventory</a> @endif
                        @if(Route::has('stock_logs.create')) <a href="{{ route('stock_logs.create') }}" class="quick-btn">+ Add Stock Log</a> @endif
                        @if(Route::has('allocation.create')) <a href="{{ route('allocation.create') }}" class="quick-btn">New Allocation</a> @endif
                        @if(Route::has('dispensing.create')) <a href="{{ route('dispensing.create') }}" class="quick-btn">New Dispensing</a> @endif
                    @else
                        @if(Route::has('medicine.create')) <a href="{{ route('medicine.create') }}" class="quick-btn">+ Add Medicine</a> @endif
                        @if(Route::has('inventory.create')) <a href="{{ route('inventory.create') }}" class="quick-btn">+ Add Inventory</a> @endif
                        @if(Route::has('allocation.create')) <a href="{{ route('allocation.create') }}" class="quick-btn">Allocate</a> @endif
                        @if(Route::has('dispensing.create')) <a href="{{ route('dispensing.create') }}" class="quick-btn">Dispense</a> @endif
                    @endif
                </div>

                {{-- Recent Activity --}}
                <div style="margin-top:32px; border-top:1px solid #e2e8f0; padding-top:20px;">
                    <h3 style="font-size:16px; font-weight:700; color:#0f172a; margin:0 0 14px 0;">Recent Activity</h3>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        @forelse($recentStockLogs->getCollection()->take(3) as $log)
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="width:8px; height:8px; border-radius:50%; background:#42ad8a; flex-shrink:0;"></div>
                            <div style="font-size:13px;">
                                <span style="font-weight:600;">+{{ $log->quantity_added }} {{ $log->medicine->medicine_name ?? 'N/A' }}</span>
                                <span style="color:#64748b;">
                                    @if($isPHOAdmin)
                                        at {{ $log->healthUnit->name ?? 'Unknown RHU' }}
                                    @endif
                                    on {{ $log->date_added }}
                                </span>
                            </div>
                        </div>
                        @empty
                        <div style="font-size:13px; color:#64748b;">No recent stock additions.</div>
                        @endforelse

                        @forelse($recentDispensing->getCollection()->take(3) as $disp)
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="width:8px; height:8px; border-radius:50%; background:#42ad8a; flex-shrink:0;"></div>
                            <div style="font-size:13px;">
                                <span style="font-weight:600;">{{ $disp->medicine->medicine_name ?? 'N/A' }}</span>
                                — {{ $disp->quantity_dispensed }} unit(s) dispensed
                                <span style="color:#64748b;">
                                    @if($isPHOAdmin)
                                        at {{ $disp->healthUnit->name ?? 'Unknown RHU' }}
                                    @endif
                                    on {{ $disp->date_dispensed }}
                                </span>
                            </div>
                        </div>
                        @empty
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Right column: Info card --}}
            <div style="background:#E8F7F4; border:1px solid #42AD8A; border-radius:20px; padding:24px;">
                <p style="font-size:14px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#0f5c4b; margin:0 0 20px 0; border-bottom:1px solid rgba(66,173,138,0.3); padding-bottom:12px;">
                    @if($isPHOAdmin)
                        Province Overview
                    @else
                        Health Unit
                    @endif
                </p>

                @if($isPHOAdmin)
                    <div style="margin-bottom:16px;">
                        <span style="font-size:13px; font-weight:600; color:#1a6e5c; display:block;">Total RHUs</span>
                        <span style="font-size:18px; font-weight:700; color:#0f172a;">{{ $stats['rhus_count'] ?? 0 }}</span>
                    </div>
                    <div style="margin-bottom:16px; border-top:1px solid rgba(66,173,138,0.15); padding-top:12px;">
                        <span style="font-size:13px; font-weight:600; color:#1a6e5c; display:block;">Total Users</span>
                        <span style="font-size:18px; font-weight:700; color:#0f172a;">{{ $stats['users_count'] ?? 0 }}</span>
                    </div>
                    <div style="margin-bottom:16px; border-top:1px solid rgba(66,173,138,0.15); padding-top:12px;">
                        <span style="font-size:13px; font-weight:600; color:#1a6e5c; display:block;">Total Medicines</span>
                        <span style="font-size:18px; font-weight:700; color:#0f172a;">{{ $stats['medicines_count'] ?? 0 }}</span>
                    </div>
                    <div style="margin-bottom:16px; border-top:1px solid rgba(66,173,138,0.15); padding-top:12px;">
                        <span style="font-size:13px; font-weight:600; color:#1a6e5c; display:block;">Total Inventory Records</span>
                        <span style="font-size:18px; font-weight:700; color:#0f172a;">{{ $stats['inventory_count'] ?? 0 }}</span>
                    </div>
                    <div style="border-top:1px solid rgba(66,173,138,0.15); padding-top:12px;">
                        <span style="font-size:13px; font-weight:600; color:#1a6e5c; display:block;">Total Available Stock</span>
                        <span style="font-size:18px; font-weight:700; color:#0f172a;">{{ number_format($stats['total_available_units'] ?? 0) }}</span>
                    </div>
                @else
                    <div style="margin-bottom:16px;">
                        <span style="font-size:13px; font-weight:600; color:#1a6e5c; display:block;">Assigned RHU</span>
                        <span style="font-size:18px; font-weight:700; color:#0f172a;">{{ $user->healthUnit->name ?? 'N/A' }}</span>
                    </div>
                    <div style="margin-bottom:16px; border-top:1px solid rgba(66,173,138,0.15); padding-top:12px;">
                        <span style="font-size:13px; font-weight:600; color:#1a6e5c; display:block;">Municipality</span>
                        <span style="font-size:18px; font-weight:700; color:#0f172a;">{{ $user->healthUnit->municipality ?? 'N/A' }}</span>
                    </div>
                    <div style="margin-bottom:16px; border-top:1px solid rgba(66,173,138,0.15); padding-top:12px;">
                        <span style="font-size:13px; font-weight:600; color:#1a6e5c; display:block;">Address</span>
                        <span style="font-size:15px; color:#0f172a; line-height:1.5;">{{ $user->healthUnit->address ?? 'N/A' }}</span>
                    </div>
                    <div style="border-top:1px solid rgba(66,173,138,0.15); padding-top:12px;">
                        <span style="font-size:13px; font-weight:600; color:#1a6e5c; display:block;">Role</span>
                        <span style="display:inline-block; margin-top:6px; padding:5px 16px; border-radius:999px; background:#0f5c4b; color:#ffffff; font-size:14px; font-weight:600;">{{ ucfirst($user->role) }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- =================== EXECUTIVE CHARTS =================== --}}
    <div style="display:flex; flex-direction:column; gap:20px;">
        <div style="
            background: rgba(255,255,255,0.95);
            border-radius:20px;
            padding:24px;
            border:1px solid rgba(66,173,138,0.15);
            box-shadow:0 8px 20px rgba(0,0,0,0.06);
        ">
            <p style="font-size:18px; font-weight:700; color:#0f172a; margin:0 0 4px 0;">📊 Analytics & Insights</p>
            <p style="font-size:14px; color:#64748b; margin:0 0 20px 0;">Visual summary of inventory performance, utilization, and risks</p>

            {{-- Charts Grid --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">

                {{-- ═══════════════════════════════════════════════════════ --}}
                {{-- TOP UTILIZED MEDICINES — full-width card w/ chart + table --}}
                {{-- ═══════════════════════════════════════════════════════ --}}
                <div style="grid-column:1 / -1; background:#f8fafc; border-radius:16px; padding:20px; border:0.5px solid #e2e8f0;">

                    {{-- Header row --}}
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                        <div>
                            <h4 style="margin:0 0 4px 0; color:#0f172a; font-size:15px; font-weight:700;">📈 Top Utilized Medicines</h4>
                            <p style="margin:0; font-size:13px; color:#64748b;">
                                Ranked by units dispensed over the last 90 days
                                @if($isPHOAdmin)
                                    (province-wide)
                                @else
                                    (this RHU)
                                @endif
                            </p>
                        </div>

                        @php
                            $utilSummary = $chartDataJson ? (json_decode($chartDataJson, true)['utilization']['summary'] ?? null) : null;
                        @endphp
                        @if($utilSummary)
                            <div style="background:#E8F7F4; border:1px solid #42AD8A; border-radius:10px; padding:8px 14px;">
                                <div style="font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#0f5c4b;">Top 10 Coverage</div>
                                <div style="font-size:18px; font-weight:700; color:#0f5c4b;">
                                    {{ number_format($utilSummary['top_units']) }} units
                                    <span style="font-size:13px; color:#64748b; font-weight:500;">
                                        ({{ $utilSummary['top_share_pct'] }}% of all dispensing)
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Chart --}}
                    <div style="position:relative; height:280px; margin-bottom:22px;">
                        <canvas id="utilizationChart"></canvas>
                    </div>

                    {{-- Detail table --}}
                    <div style="overflow-x:auto; border-radius:12px; border:1px solid #e2e8f0; background:#ffffff;">
                        <table style="width:100%; border-collapse:collapse; font-size:13px; min-width:720px;">
                            <thead>
                                <tr style="background:#f1f5f9; border-bottom:1px solid #e2e8f0;">
                                    <th style="padding:10px 12px; text-align:center; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#475569;">#</th>
                                    <th style="padding:10px 12px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#475569;">Medicine</th>
                                    <th style="padding:10px 12px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#475569;">Category</th>
                                    <th style="padding:10px 12px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#475569;">Dispensed (90d)</th>
                                    <th style="padding:10px 12px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#475569;">Share</th>
                                    <th style="padding:10px 12px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#475569;">Last 30d</th>
                                    <th style="padding:10px 12px; text-align:center; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#475569;">Trend</th>
                                    @if($isPHOAdmin)
                                        <th style="padding:10px 12px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#475569;">Top RHU</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody id="utilizationTableBody">
                                {{-- Filled by JS from chartDataJson.utilization.details --}}
                            </tbody>
                        </table>
                    </div>

                </div>

                {{-- Stock Movement Chart --}}
                <div style="background:#f8fafc; border-radius:16px; padding:16px; border:0.5px solid #e2e8f0;">
                    <h4 style="margin:0 0 12px 0; color:#0f172a; font-size:14px; font-weight:600;">📊 Stock Movement (6 Months)</h4>
                    <div style="position:relative; height:300px;">
                        <canvas id="stockMovementChart"></canvas>
                    </div>
                </div>

                {{-- Expiration Risk Chart — ENLARGED --}}
                <div style="background:#f8fafc; border-radius:16px; padding:16px; border:0.5px solid #e2e8f0;">
                    <h4 style="margin:0 0 12px 0; color:#0f172a; font-size:14px; font-weight:600;">⚠️ Expiration Risk</h4>
                    <div style="position:relative; height:300px;">
                        <canvas id="expirationChart"></canvas>
                    </div>
                </div>

                {{-- Low Stock Chart --}}
                <div style="background:#f8fafc; border-radius:16px; padding:16px; border:0.5px solid #e2e8f0;">
                    <h4 style="margin:0 0 12px 0; color:#0f172a; font-size:14px; font-weight:600;">
                        @if($isPHOAdmin)
                            🚨 Low Stock by RHU
                        @else
                            🚨 Low Stock by Category
                        @endif
                    </h4>
                    <div style="position:relative; height:300px;">
                        <canvas id="lowStockChart"></canvas>
                    </div>
                </div>

                {{-- Overstocking Chart --}}
                <div style="background:#f8fafc; border-radius:16px; padding:16px; border:0.5px solid #e2e8f0;">
                    <h4 style="margin:0 0 12px 0; color:#0f172a; font-size:14px; font-weight:600;">
                        📦 Overstocking Risk
                        <span style="font-weight:400; color:#64748b; font-size:12px;">
                            @if($isPHOAdmin)
                                (by RHU)
                            @else
                                (by category)
                            @endif
                        </span>
                    </h4>
                    <div style="position:relative; height:300px;">
                        <canvas id="overstockChart"></canvas>
                    </div>
                </div>

                {{-- Redistribution / Transfer --}}
                <div style="background:#f8fafc; border-radius:16px; padding:16px; border:0.5px solid #e2e8f0; {{ $isPHOAdmin ? 'grid-column:1 / -1;' : '' }}">
                    <h4 style="margin:0 0 12px 0; color:#0f172a; font-size:14px; font-weight:600;">
                        📦 {{ $isPHOAdmin ? 'Distribution Activity' : 'Transfer Activity' }}
                    </h4>
                    <div style="position:relative; height:300px;">
                        <canvas id="redistributionChart"></canvas>
                    </div>
                </div>

                {{-- FORECAST SUMMARY — RHU users only --}}
                @if(!$isPHOAdmin)
                    <div style="background:#f8fafc; border-radius:16px; padding:16px; border:0.5px solid #e2e8f0; display:flex; flex-direction:column;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                            <h4 style="margin:0; color:#0f172a; font-size:14px; font-weight:600;">🔮 Forecast Summary</h4>
                            <span style="font-size:10px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; padding:2px 8px; border-radius:999px; background:#E8F7F4; color:#0f5c4b; border:1px solid #42AD8A;">
                                {{ ($forecastSummary['scope'] ?? 'rhu') === 'province' ? 'Province' : 'This RHU' }}
                            </span>
                        </div>

                        <div style="display:flex; flex-direction:column; gap:10px; flex:1;">
                            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#64748b;">3-Month Shortage</span>
                                    <span style="font-size:24px; font-weight:800; color:#dc2626; line-height:1;">
                                        {{ number_format($forecastSummary['shortage'] ?? 0) }}
                                    </span>
                                </div>
                                <p style="font-size:11px; color:#94a3b8; margin:4px 0 0 0;">Units projected to be short</p>
                            </div>

                            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#64748b;">Low Stock Items</span>
                                    <span style="font-size:24px; font-weight:800; color:#eab308; line-height:1;">
                                        {{ number_format($forecastSummary['low_stock_items'] ?? 0) }}
                                    </span>
                                </div>
                                <p style="font-size:11px; color:#94a3b8; margin:4px 0 0 0;">Items flagged by forecast</p>
                            </div>

                            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#64748b;">Items Analysed</span>
                                    <span style="font-size:24px; font-weight:800; color:#0f5c4b; line-height:1;">
                                        {{ number_format($forecastSummary['total_items'] ?? 0) }}
                                    </span>
                                </div>
                                <p style="font-size:11px; color:#94a3b8; margin:4px 0 0 0;">Active items in forecast</p>
                            </div>
                        </div>

                        @if(Route::has('forecast.index'))
                            <a href="{{ route('forecast.index') }}"
                               style="display:block; text-align:center; margin-top:12px; padding:10px; border-radius:10px;
                                      background:#42ad8a; color:white; text-decoration:none; font-size:13px; font-weight:600;
                                      transition: background 0.2s;"
                               onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                                View Full Forecast →
                            </a>
                        @endif
                    </div>
                @endif

            </div>
        </div>
    </div>

    {{-- =================== SYSTEM NAVIGATION =================== --}}
    <div style="background:rgba(255,255,255,0.95); backdrop-filter:blur(8px); border-radius:20px; padding:24px; border:1px solid rgba(66,173,138,0.15); box-shadow:0 8px 20px rgba(0,0,0,0.06);">
        <p style="font-size:18px; font-weight:700; color:#0f172a;">System Navigation</p>
        <p style="font-size:14px; color:#64748b; margin-top:3px; margin-bottom:20px;">
            {{ $isPHOAdmin ? 'Quick access to core PHO functions' : 'Quick access to core ' . (session('role') === 'staff' ? 'staff' : 'admin') . ' functions' }}
        </p>

        <div style="display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px;">
            @foreach($navLinks as $link)
                @if(Route::has($link[0]))
                <a href="{{ route($link[0]) }}" style="display:flex;align-items:flex-start;gap:12px;background:#f8fafc;border:0.5px solid #e2e8f0;border-radius:12px;padding:16px;text-decoration:none; transition:all 0.25s;"
                   onmouseover="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)';"
                   onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    <div style="width:36px;height:36px;border-radius:8px;background:#E8F7F4;border:1px solid #42AD8A;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0f5c4b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="{{ $link[3] ?? 'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z' }}"/>
                        </svg>
                    </div>
                    <div>
                        <p style="font-size:14px;font-weight:600;color:#0f172a;">{{ $link[1] }}</p>
                        <p style="font-size:13px;color:#64748b;margin-top:2px;line-height:1.4;">{{ $link[2] }}</p>
                    </div>
                </a>
                @endif
            @endforeach
        </div>
    </div>

    {{-- =================== BOTTOM PANELS =================== --}}
    <div style="display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px;">

        <!-- Low Stock -->
        <div id="dashboard-low-stock" data-dashboard-panel style="scroll-margin-top:24px;background:rgba(255,255,255,0.95); border:1px solid rgba(66,173,138,0.15); border-radius:20px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.04); display:flex; flex-direction:column;">
            <p style="font-size:16px;font-weight:700;color:#0f172a;">
                {{ $isPHOAdmin ? 'Low Stock Priority (All RHUs)' : 'Low Stock Priority' }}
            </p>
            <p style="font-size:12px;color:#64748b;margin-top:2px;margin-bottom:14px;">
                {{ $isPHOAdmin ? 'Items needing replenishment across the province' : 'Items needing replenishment soon' }}
            </p>
            <div style="flex:1; display:flex; flex-direction:column; gap:8px; max-height:350px; overflow-y:auto; padding-right:4px;">
                @forelse($lowStockItems as $item)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#f8fafc;border:0.5px solid #e2e8f0;border-radius:10px;">
                    <div>
                        <p style="font-size:14px;font-weight:600;color:#0f172a;">{{ $item->medicine->medicine_name ?? 'N/A' }}</p>
                        <p style="font-size:12px;color:#64748b;margin-top:2px;">
                            {{ $item->medicine->dosage ?? '' }}
                            @if($isPHOAdmin)
                                · {{ $item->healthUnit->name ?? 'Unknown RHU' }}
                            @endif
                        </p>
                    </div>
                    <span style="font-size:12px;font-weight:600;padding:3px 10px;border-radius:6px;background:#FAEEDA;color:#854F0B;border:1px solid #FAC775;white-space:nowrap;">{{ $item->available_stock }} units</span>
                </div>
                @empty
                <div style="padding:16px;text-align:center;font-size:13px;color:#64748b;border:0.5px dashed #cbd5e1;border-radius:10px;">No low stock items right now.</div>
                @endforelse
            </div>
            @if($lowStockItems->hasPages())
            <div class="dashboard-panel-pagination" style="margin-top:12px; border-top:1px solid #e2e8f0; padding-top:12px;">
                {{ $lowStockItems->links() }}
            </div>
            @endif
            <p class="dashboard-pagination-status" data-pagination-status role="status" aria-live="polite" aria-atomic="true"></p>
        </div>

        <!-- Near Expiry -->
        <div id="dashboard-near-expiry" data-dashboard-panel style="scroll-margin-top:24px;background:rgba(255,255,255,0.95); border:1px solid rgba(66,173,138,0.15); border-radius:20px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.04); display:flex; flex-direction:column;">
            <p style="font-size:16px;font-weight:700;color:#0f172a;">
                {{ $isPHOAdmin ? 'Near Expiry Priority (All RHUs)' : 'Near Expiry Priority' }}
            </p>
            <p style="font-size:12px;color:#64748b;margin-top:2px;margin-bottom:14px;">
                {{ $isPHOAdmin ? 'Items expiring within 30 days across the province' : 'Items expiring within 30 days' }}
            </p>
            <div style="flex:1; display:flex; flex-direction:column; gap:8px; max-height:350px; overflow-y:auto; padding-right:4px;">
                @forelse($nearExpiryItems as $item)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#f8fafc;border:0.5px solid #e2e8f0;border-radius:10px;">
                    <div>
                        <p style="font-size:14px;font-weight:600;color:#0f172a;">{{ $item->medicine->medicine_name ?? 'N/A' }}</p>
                        <p style="font-size:12px;color:#64748b;margin-top:2px;">
                            {{ $item->medicine->dosage ?? '' }}
                            @if($isPHOAdmin)
                                · {{ $item->healthUnit->name ?? 'Unknown RHU' }}
                            @endif
                        </p>
                    </div>
                    <span style="font-size:12px;font-weight:600;padding:3px 10px;border-radius:6px;background:#FCEBEB;color:#A32D2D;border:1px solid #F7C1C1;white-space:nowrap;">{{ $item->expiration_date }}</span>
                </div>
                @empty
                <div style="padding:16px;text-align:center;font-size:13px;color:#64748b;border:0.5px dashed #cbd5e1;border-radius:10px;">No near expiry items right now.</div>
                @endforelse
            </div>
            @if($nearExpiryItems->hasPages())
            <div class="dashboard-panel-pagination" style="margin-top:12px; border-top:1px solid #e2e8f0; padding-top:12px;">
                {{ $nearExpiryItems->links() }}
            </div>
            @endif
            <p class="dashboard-pagination-status" data-pagination-status role="status" aria-live="polite" aria-atomic="true"></p>
        </div>

        <!-- Recent Stock Activity -->
        <div id="dashboard-stock-activity" data-dashboard-panel style="scroll-margin-top:24px;background:rgba(255,255,255,0.95); border:1px solid rgba(66,173,138,0.15); border-radius:20px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.04); display:flex; flex-direction:column;">
            <p style="font-size:16px;font-weight:700;color:#0f172a;">
                {{ $isPHOAdmin ? 'Recent Stock Activity (Province) - Last 30 Days' : 'Recent Stock Activity' }}
            </p>
            <p style="font-size:12px;color:#64748b;margin-top:2px;margin-bottom:14px;">
                {{ $isPHOAdmin ? 'Latest stock additions across all RHUs (last 30 days)' : 'Latest stock additions in your RHU (last 30 days)' }}
            </p>
            <div style="flex:1; display:flex; flex-direction:column; gap:8px; max-height:350px; overflow-y:auto; padding-right:4px;">
                @forelse($recentStockLogs as $log)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#f8fafc;border:0.5px solid #e2e8f0;border-radius:10px;">
                    <div>
                        <p style="font-size:14px;font-weight:600;color:#0f172a;">{{ $log->medicine->medicine_name ?? 'N/A' }}</p>
                        <p style="font-size:12px;color:#64748b;margin-top:2px;">
                            {{ $log->date_added }} · {{ $log->addedByUser->name ?? 'N/A' }}
                            @if($isPHOAdmin)
                                · {{ $log->healthUnit->name ?? '' }}
                            @endif
                        </p>
                    </div>
                    <span style="font-size:12px;font-weight:600;padding:3px 10px;border-radius:6px;background:#E8F7F4;color:#0f5c4b;border:1px solid #42AD8A;white-space:nowrap;">+{{ $log->quantity_added }} units</span>
                </div>
                @empty
                <div style="padding:16px;text-align:center;font-size:13px;color:#64748b;border:0.5px dashed #cbd5e1;border-radius:10px;">No stock log activity in the last 30 days.</div>
                @endforelse
            </div>
            @if($recentStockLogs->hasPages())
            <div class="dashboard-panel-pagination" style="margin-top:12px; border-top:1px solid #e2e8f0; padding-top:12px;">
                {{ $recentStockLogs->links() }}
            </div>
            @endif
            <p class="dashboard-pagination-status" data-pagination-status role="status" aria-live="polite" aria-atomic="true"></p>
        </div>

        <!-- Recent Dispensing -->
        <div id="dashboard-dispensing" data-dashboard-panel style="scroll-margin-top:24px;background:rgba(255,255,255,0.95); border:1px solid rgba(66,173,138,0.15); border-radius:20px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.04); display:flex; flex-direction:column;">
            <p style="font-size:16px;font-weight:700;color:#0f172a;">
                {{ $isPHOAdmin ? 'Recent Dispensing (Province) - Last 30 Days' : 'Recent Dispensing' }}
            </p>
            <p style="font-size:12px;color:#64748b;margin-top:2px;margin-bottom:14px;">
                {{ $isPHOAdmin ? 'Latest patient dispensing records across all RHUs (last 30 days)' : 'Latest patient dispensing records (last 30 days)' }}
            </p>
            <div style="flex:1; display:flex; flex-direction:column; gap:8px; max-height:350px; overflow-y:auto; padding-right:4px;">
                @forelse($recentDispensing as $record)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#f8fafc;border:0.5px solid #e2e8f0;border-radius:10px;">
                    <div>
                        <p style="font-size:14px;font-weight:600;color:#0f172a;">{{ $record->medicine->medicine_name ?? 'N/A' }}</p>
                        <p style="font-size:12px;color:#64748b;margin-top:2px;">
                            Dr. {{ $record->doctor_name ?? 'N/A' }} · {{ $record->date_dispensed }}
                            @if($isPHOAdmin)
                                · {{ $record->healthUnit->name ?? '' }}
                            @endif
                        </p>
                    </div>
                    <span style="font-size:12px;font-weight:600;padding:3px 10px;border-radius:6px;background:#EAF3DE;color:#3B6D11;border:1px solid #C0DD97;white-space:nowrap;">{{ $record->quantity_dispensed }} units</span>
                </div>
                @empty
                <div style="padding:16px;text-align:center;font-size:13px;color:#64748b;border:0.5px dashed #cbd5e1;border-radius:10px;">No dispensing activity in the last 30 days.</div>
                @endforelse
            </div>
            @if($recentDispensing->hasPages())
            <div class="dashboard-panel-pagination" style="margin-top:12px; border-top:1px solid #e2e8f0; padding-top:12px;">
                {{ $recentDispensing->links() }}
            </div>
            @endif
            <p class="dashboard-pagination-status" data-pagination-status role="status" aria-live="polite" aria-atomic="true"></p>
        </div>
    </div>

</div>

@include('dashboard._panel_pagination')

{{-- Chart.js CDN --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

{{-- Chart Initialization --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const data = {!! $chartDataJson !!};

    const colors = ['#42ad8a', '#3b82f6', '#eab308', '#dc2626', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#84cc16'];
    const expirationColors = ['#dc2626', '#eab308', '#f97316', '#42ad8a', '#94a3b8'];

    // ═══════════════════════════════════════════════════════════
    // 1. UTILIZATION CHART + DETAIL TABLE
    // ═══════════════════════════════════════════════════════════
    const utilizationDetails = data.utilization.details || [];
    const utilizationSummary = data.utilization.summary || {};

    (function renderUtilizationTable() {
        const tbody = document.getElementById('utilizationTableBody');
        if (!tbody) return;

        if (!utilizationDetails.length) {
            const isPho = utilizationSummary.is_pho;
            tbody.innerHTML = `
                <tr>
                    <td colspan="${isPho ? 8 : 7}" style="padding:28px 16px; text-align:center; color:#94a3b8; font-size:13px;">
                        No dispensing records in the last ${utilizationSummary.window_days || 90} days.
                    </td>
                </tr>
            `;
            return;
        }

        const rows = utilizationDetails.map(function (d, idx) {
            let trendHtml = '';
            if (d.change_pct === null) {
                trendHtml = `<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#EFF6FF;color:#1E40AF;border:1px solid #93C5FD;font-size:11px;font-weight:700;">NEW</span>`;
            } else if (d.change_pct > 0) {
                trendHtml = `<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#FEF3C7;color:#92400E;border:1px solid #FAC775;font-size:11px;font-weight:700;">▲ ${d.change_pct}%</span>`;
            } else if (d.change_pct < 0) {
                trendHtml = `<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#E8F7F4;color:#0f5c4b;border:1px solid #42AD8A;font-size:11px;font-weight:700;">▼ ${Math.abs(d.change_pct)}%</span>`;
            } else {
                trendHtml = `<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;font-size:11px;font-weight:700;">—</span>`;
            }

            const dosage = d.dosage ? `<span style="color:#94a3b8; font-weight:400;"> · ${d.dosage}</span>` : '';
            const category = d.category
                ? d.category.charAt(0).toUpperCase() + d.category.slice(1).replace(/_/g, ' ')
                : '—';

            const topRhuCell = utilizationSummary.is_pho
                ? `<td style="padding:10px 12px; color:#334155; font-size:13px;">${d.top_rhu || '<span style="color:#94a3b8;">—</span>'}</td>`
                : '';

            return `
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 12px; text-align:center; color:#94a3b8; font-size:12px; font-weight:600;">${idx + 1}</td>
                    <td style="padding:10px 12px; font-weight:600; color:#0f172a;">${d.medicine_name}${dosage}</td>
                    <td style="padding:10px 12px; color:#64748b; font-size:12px;">${category}</td>
                    <td style="padding:10px 12px; text-align:right; font-weight:700; color:#0f172a;">${d.total_90d.toLocaleString()}</td>
                    <td style="padding:10px 12px; text-align:right; color:#475569; font-size:12px;">${d.share_pct}%</td>
                    <td style="padding:10px 12px; text-align:right; color:#475569; font-size:12px;">${d.last_30d.toLocaleString()}</td>
                    <td style="padding:10px 12px; text-align:center;">${trendHtml}</td>
                    ${topRhuCell}
                </tr>
            `;
        }).join('');

        tbody.innerHTML = rows;
    })();

    const ctx1 = document.getElementById('utilizationChart');
    if (ctx1) {
        new Chart(ctx1.getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.utilization.labels,
                datasets: [{
                    label: 'Units Dispensed',
                    data: data.utilization.data,
                    backgroundColor: data.utilization.data.map(function (_, i) {
                        return colors[i % colors.length];
                    }),
                    borderColor: data.utilization.data.map(function (_, i) {
                        return colors[i % colors.length];
                    }),
                    borderWidth: 1,
                    borderRadius: 6,
                    maxBarThickness: 44,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 700, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#ffffff',
                        bodyColor: '#e2e8f0',
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: { size: 13, weight: '700' },
                        bodyFont: { size: 13 },
                        callbacks: {
                            label: function (ctx) {
                                const detail = utilizationDetails[ctx.dataIndex];
                                if (!detail) return ' ' + Number(ctx.parsed.y).toLocaleString() + ' units';
                                return [
                                    ' ' + ctx.parsed.y.toLocaleString() + ' units (90 days)',
                                    ' Share: ' + detail.share_pct + '%',
                                    ' Last 30d: ' + detail.last_30d.toLocaleString()
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#475569', font: { size: 11 } },
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
    }

    // 2. Stock Movement Chart
    const ctx2 = document.getElementById('stockMovementChart');
    if (ctx2) {
        new Chart(ctx2.getContext('2d'), {
            type: 'line',
            data: {
                labels: data.stockMovement.months,
                datasets: [
                    {
                        label: 'Additions',
                        data: data.stockMovement.additions,
                        borderColor: '#42ad8a',
                        backgroundColor: 'rgba(66,173,138,0.1)',
                        fill: true,
                        tension: 0.4,
                    },
                    {
                        label: 'Releases',
                        data: data.stockMovement.releases,
                        borderColor: '#dc2626',
                        backgroundColor: 'rgba(220,38,38,0.1)',
                        fill: true,
                        tension: 0.4,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    // 3. Expiration Risk Chart — ENLARGED
    const ctx3 = document.getElementById('expirationChart');
    if (ctx3) {
        new Chart(ctx3.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: data.expiration.labels,
                datasets: [{
                    data: data.expiration.data,
                    backgroundColor: expirationColors.slice(0, data.expiration.data.length),
                    borderWidth: 2,
                    borderColor: '#ffffff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '55%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 14,
                            font: { size: 12, weight: '600' },
                            color: '#334155',
                            usePointStyle: true,
                            pointStyle: 'circle',
                        }
                    }
                }
            }
        });
    }

    // 4. Low Stock Chart
    const ctx4 = document.getElementById('lowStockChart');
    if (ctx4) {
        new Chart(ctx4.getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.lowStock.labels,
                datasets: [{
                    label: 'Low Stock Items',
                    data: data.lowStock.data,
                    backgroundColor: '#eab308',
                    borderColor: '#eab308',
                    borderWidth: 1,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true } }
            }
        });
    }

    // 5. Overstocking Chart
    const ctxOver = document.getElementById('overstockChart');
    if (ctxOver) {
        new Chart(ctxOver.getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.overstock.labels,
                datasets: [{
                    label: 'Overstocked Items',
                    data: data.overstock.data,
                    backgroundColor: '#8b5cf6',
                    borderColor: '#8b5cf6',
                    borderWidth: 1,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true } }
            }
        });
    }

    // 6. Redistribution Chart
    const ctx5 = document.getElementById('redistributionChart');
    if (ctx5) {
        new Chart(ctx5.getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.redistribution.labels,
                datasets: [{
                    label: @json($isPHOAdmin ? 'Distributed to RHUs' : 'Transfers Received'),
                    data: data.redistribution.data,
                    backgroundColor: colors.slice(0, data.redistribution.data.length),
                    borderColor: colors.slice(0, data.redistribution.data.length),
                    borderWidth: 1,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }
});
</script>

<style>
.quick-btn {
    font-size:14px; font-weight:600; padding:9px 16px; border-radius:10px;
    background:#E8F7F4; border:1px solid #42AD8A; color:#0f5c4b;
    text-decoration:none; transition:all 0.2s;
}
.quick-btn:hover {
    background:#d2efe6;
}

/* Custom scrollbar for all bottom panels */
div[style*="max-height:350px"]::-webkit-scrollbar {
    width: 6px;
}
div[style*="max-height:350px"]::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
}
div[style*="max-height:350px"]::-webkit-scrollbar-thumb {
    background: #42ad8a;
    border-radius: 10px;
}
div[style*="max-height:350px"]::-webkit-scrollbar-thumb:hover {
    background: #369f7d;
}
</style>
@endsection