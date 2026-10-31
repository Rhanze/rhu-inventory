@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Alert Row --}}
    <div style="display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px;">
        <div style="background:#FAEEDA; border:1px solid #FAC775; border-radius:14px; padding:18px;">
            <p style="font-size:13px; font-weight:600; color:#854F0B;">Low Stock Alerts</p>
            <p style="font-size:32px; font-weight:700; color:#633806; margin-top:6px;">{{ $stats['low_stock_count'] ?? 0 }}</p>
            <p style="font-size:12px; color:#854F0B; margin-top:4px;">Items needing replenishment</p>
        </div>
        <div style="background:#FCEBEB; border:1px solid #F7C1C1; border-radius:14px; padding:18px;">
            <p style="font-size:13px; font-weight:600; color:#A32D2D;">Near Expiry</p>
            <p style="font-size:32px; font-weight:700; color:#791F1F; margin-top:6px;">{{ $stats['near_expiry_count'] ?? 0 }}</p>
            <p style="font-size:12px; color:#A32D2D; margin-top:4px;">Expiring within 30 days</p>
        </div>
        <div style="background:#E8F7F4; border:1px solid #42AD8A; border-radius:14px; padding:18px;">
            <p style="font-size:13px; font-weight:600; color:#0f5c4b;">Today's Activity</p>
            <p style="font-size:32px; font-weight:700; color:#0f5c4b; margin-top:6px;">
                {{ ($stats['allocations_count'] ?? 0) + ($stats['dispensing_count'] ?? 0) }}
            </p>
            <p style="font-size:12px; color:#0f5c4b; margin-top:4px;">Allocation & dispensing records</p>
        </div>
    </div>

    {{-- Welcome + Health Unit + Recent Activity --}}
    <div style="
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(12px);
        border-radius:24px;
        padding:32px;
        border:1px solid rgba(66,173,138,0.2);
        box-shadow:0 12px 24px rgba(0,0,0,0.08);
    ">
        <div style="display:grid; grid-template-columns:1.5fr 1fr; gap:32px;">
            {{-- Left column: Welcome + quick actions --}}
            <div>
                <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin-bottom:10px;">Staff Dashboard</p>
                <h1 style="font-size:28px; font-weight:700; color:#0f172a;">Welcome back, {{ $user->name }}</h1>
                <p style="font-size:15px; color:#475569; margin-top:8px; line-height:1.6;">
                    Manage daily inventory work, stock movement, allocations, and dispensing for
                    <strong style="color:#0f172a;">{{ $user->healthUnit->name ?? 'your RHU' }}</strong>.
                </p>

                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:24px;">
                    @if(Route::has('inventory.create'))
                        <a href="{{ route('inventory.create') }}" style="font-size:14px; font-weight:600; padding:9px 16px; border-radius:10px; background:#E8F7F4; border:1px solid #42AD8A; color:#0f5c4b; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.background='#d2efe6';" onmouseout="this.style.background='#E8F7F4';">
                            + Add Inventory
                        </a>
                    @endif
                    @if(Route::has('stock_logs.create'))
                        <a href="{{ route('stock_logs.create') }}" style="font-size:14px; font-weight:600; padding:9px 16px; border-radius:10px; background:#E8F7F4; border:1px solid #42AD8A; color:#0f5c4b; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.background='#d2efe6';" onmouseout="this.style.background='#E8F7F4';">
                            + Add Stock Log
                        </a>
                    @endif
                    @if(Route::has('allocation.create'))
                        <a href="{{ route('allocation.create') }}" style="font-size:14px; font-weight:600; padding:9px 16px; border-radius:10px; background:#E8F7F4; border:1px solid #42AD8A; color:#0f5c4b; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.background='#d2efe6';" onmouseout="this.style.background='#E8F7F4';">
                            New Allocation
                        </a>
                    @endif
                    @if(Route::has('dispensing.create'))
                        <a href="{{ route('dispensing.create') }}" style="font-size:14px; font-weight:600; padding:9px 16px; border-radius:10px; background:#E8F7F4; border:1px solid #42AD8A; color:#0f5c4b; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.background='#d2efe6';" onmouseout="this.style.background='#E8F7F4';">
                            New Dispensing
                        </a>
                    @endif
                </div>

                {{-- Recent Activity --}}
                <div style="margin-top:32px; border-top:1px solid #e2e8f0; padding-top:20px;">
                    <h3 style="font-size:16px; font-weight:700; color:#0f172a; margin:0 0 14px 0;">Recent Activity</h3>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        @forelse($recentStockLogs->take(3) as $log)
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="width:8px; height:8px; border-radius:50%; background:#42ad8a; flex-shrink:0;"></div>
                            <div style="font-size:13px;">
                                <span style="font-weight:600;">+{{ $log->quantity_added }} {{ $log->medicine->medicine_name ?? 'N/A' }}</span>
                                <span style="color:#64748b;">on {{ $log->date_added }}</span>
                            </div>
                        </div>
                        @empty
                        <div style="font-size:13px; color:#64748b;">No recent stock additions.</div>
                        @endforelse
                        @forelse($recentDispensing->take(3) as $disp)
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="width:8px; height:8px; border-radius:50%; background:#42ad8a; flex-shrink:0;"></div>
                            <div style="font-size:13px;">
                                <span style="font-weight:600;">{{ $disp->patient_name }}</span>
                                received {{ $disp->quantity_dispensed }} {{ $disp->medicine->medicine_name ?? 'N/A' }}
                                <span style="color:#64748b;">on {{ $disp->date_dispensed }}</span>
                            </div>
                        </div>
                        @empty
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Right column: Health Unit Info --}}
            <div style="background:#E8F7F4; border:1px solid #42AD8A; border-radius:20px; padding:24px;">
                <p style="font-size:14px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#0f5c4b; margin:0 0 20px 0; border-bottom:1px solid rgba(66,173,138,0.3); padding-bottom:12px;">Health Unit</p>

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
            </div>
        </div>
    </div>

    {{-- Stats Row --}}
    <div style="display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px;">
        @foreach (['Medicine List' => $stats['medicines_count'] ?? 0, 'Inventory Records' => $stats['inventory_count'] ?? 0, 'Available Stock' => number_format($stats['total_available_units'] ?? 0), 'Barangays' => $stats['barangays_count'] ?? 0, 'Transactions' => ($stats['stock_logs_count'] ?? 0) + ($stats['allocations_count'] ?? 0) + ($stats['dispensing_count'] ?? 0)] as $label => $value)
        <div style="background:rgba(255,255,255,0.95); border:1px solid rgba(66,173,138,0.15); border-radius:14px; padding:16px; box-shadow:0 4px 12px rgba(0,0,0,0.04);">
            <p style="font-size:13px; font-weight:600; color:#475569;">{{ $label }}</p>
            <p style="font-size:30px; font-weight:700; color:#0f5c4b; margin-top:6px;">{{ $value }}</p>
        </div>
        @endforeach
    </div>

    {{-- System Navigation --}}
    <div style="background:rgba(255,255,255,0.95); backdrop-filter:blur(8px); border-radius:20px; padding:24px; border:1px solid rgba(66,173,138,0.15); box-shadow:0 8px 20px rgba(0,0,0,0.06);">
        <p style="font-size:18px; font-weight:700; color:#0f172a;">System Navigation</p>
        <p style="font-size:14px; color:#64748b; margin-top:3px; margin-bottom:20px;">Quick access to core staff functions</p>

        <div style="display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px;">
            @foreach([
                ['medicine.index', 'Medicines', 'Medicines & vaccines'],
                ['stock_logs.index', 'Stock Logs', 'Track stock additions'],
                ['inventory.index', 'Inventory', 'RHU stock records'],
                ['barangay.index', 'BHS', 'Barangay Health Stations'],
                ['allocation.index', 'Allocation', 'Allocate to BHS'],
                ['dispensing.index', 'Dispensing', 'Dispense to patients'],
                ['reports.index', 'Reports', 'Inventory & activity reports'],
                ['activity_logs.index', 'Activity Logs', 'Track user actions'],
            ] as $link)
                @if(Route::has($link[0]))
                <a href="{{ route($link[0]) }}" style="display:flex;align-items:flex-start;gap:12px;background:#f8fafc;border:0.5px solid #e2e8f0;border-radius:12px;padding:16px;text-decoration:none; transition:all 0.25s;"
                   onmouseover="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)';"
                   onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    <div style="width:36px;height:36px;border-radius:8px;background:#E8F7F4;border:1px solid #42AD8A;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0f5c4b" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
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

    {{-- Bottom panels --}}
    <div style="display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px;">
        <!-- Low Stock -->
        <div style="background:rgba(255,255,255,0.95); border:1px solid rgba(66,173,138,0.15); border-radius:20px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.04);">
            <p style="font-size:16px;font-weight:700;color:#0f172a;">Low Stock Priority</p>
            <p style="font-size:12px;color:#64748b;margin-top:2px;margin-bottom:14px;">Items needing replenishment soon</p>
            <div style="display:flex;flex-direction:column;gap:8px;">
                @forelse($lowStockItems as $item)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#f8fafc;border:0.5px solid #e2e8f0;border-radius:10px;">
                    <div>
                        <p style="font-size:14px;font-weight:600;color:#0f172a;">{{ $item->medicine->medicine_name ?? 'N/A' }}</p>
                        <p style="font-size:12px;color:#64748b;margin-top:2px;">{{ $item->medicine->medicine_brand ?? 'N/A' }}</p>
                    </div>
                    <span style="font-size:12px;font-weight:600;padding:3px 10px;border-radius:6px;background:#FAEEDA;color:#854F0B;border:1px solid #FAC775;white-space:nowrap;">{{ $item->available_stock }} units</span>
                </div>
                @empty
                <div style="padding:16px;text-align:center;font-size:13px;color:#64748b;border:0.5px dashed #cbd5e1;border-radius:10px;">No low stock items right now.</div>
                @endforelse
            </div>
        </div>

        <!-- Near Expiry -->
        <div style="background:rgba(255,255,255,0.95); border:1px solid rgba(66,173,138,0.15); border-radius:20px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.04);">
            <p style="font-size:16px;font-weight:700;color:#0f172a;">Near Expiry Priority</p>
            <p style="font-size:12px;color:#64748b;margin-top:2px;margin-bottom:14px;">Items expiring within 30 days</p>
            <div style="display:flex;flex-direction:column;gap:8px;">
                @forelse($nearExpiryItems as $item)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#f8fafc;border:0.5px solid #e2e8f0;border-radius:10px;">
                    <div>
                        <p style="font-size:14px;font-weight:600;color:#0f172a;">{{ $item->medicine->medicine_name ?? 'N/A' }}</p>
                        <p style="font-size:12px;color:#64748b;margin-top:2px;">{{ $item->medicine->medicine_brand ?? 'N/A' }}</p>
                    </div>
                    <span style="font-size:12px;font-weight:600;padding:3px 10px;border-radius:6px;background:#FCEBEB;color:#A32D2D;border:1px solid #F7C1C1;white-space:nowrap;">{{ $item->expiration_date }}</span>
                </div>
                @empty
                <div style="padding:16px;text-align:center;font-size:13px;color:#64748b;border:0.5px dashed #cbd5e1;border-radius:10px;">No near expiry items right now.</div>
                @endforelse
            </div>
        </div>

        <!-- Recent Stock Logs -->
        <div style="background:rgba(255,255,255,0.95); border:1px solid rgba(66,173,138,0.15); border-radius:20px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.04);">
            <p style="font-size:16px;font-weight:700;color:#0f172a;">Recent Stock Activity</p>
            <p style="font-size:12px;color:#64748b;margin-top:2px;margin-bottom:14px;">Latest stock additions in your RHU</p>
            <div style="display:flex;flex-direction:column;gap:8px;">
                @forelse($recentStockLogs as $log)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#f8fafc;border:0.5px solid #e2e8f0;border-radius:10px;">
                    <div>
                        <p style="font-size:14px;font-weight:600;color:#0f172a;">{{ $log->medicine->medicine_name ?? 'N/A' }}</p>
                        <p style="font-size:12px;color:#64748b;margin-top:2px;">{{ $log->date_added }} · {{ $log->addedByUser->name ?? 'N/A' }}</p>
                    </div>
                    <span style="font-size:12px;font-weight:600;padding:3px 10px;border-radius:6px;background:#E8F7F4;color:#0f5c4b;border:1px solid #42AD8A;white-space:nowrap;">+{{ $log->quantity_added }} units</span>
                </div>
                @empty
                <div style="padding:16px;text-align:center;font-size:13px;color:#64748b;border:0.5px dashed #cbd5e1;border-radius:10px;">No recent stock log activity.</div>
                @endforelse
            </div>
        </div>

        <!-- Recent Dispensing -->
        <div style="background:rgba(255,255,255,0.95); border:1px solid rgba(66,173,138,0.15); border-radius:20px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.04);">
            <p style="font-size:16px;font-weight:700;color:#0f172a;">Recent Dispensing</p>
            <p style="font-size:12px;color:#64748b;margin-top:2px;margin-bottom:14px;">Latest dispensing records</p>
            <div style="display:flex;flex-direction:column;gap:8px;">
                @forelse($recentDispensing as $record)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#f8fafc;border:0.5px solid #e2e8f0;border-radius:10px;">
                    <div>
                        <p style="font-size:14px;font-weight:600;color:#0f172a;">{{ $record->patient_name }}</p>
                        <p style="font-size:12px;color:#64748b;margin-top:2px;">{{ $record->medicine->medicine_name ?? 'N/A' }} · {{ $record->date_dispensed }}</p>
                    </div>
                    <span style="font-size:12px;font-weight:600;padding:3px 10px;border-radius:6px;background:#EAF3DE;color:#3B6D11;border:1px solid #C0DD97;white-space:nowrap;">{{ $record->quantity_dispensed }} units</span>
                </div>
                @empty
                <div style="padding:16px;text-align:center;font-size:13px;color:#64748b;border:0.5px dashed #cbd5e1;border-radius:10px;">No recent dispensing activity.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection