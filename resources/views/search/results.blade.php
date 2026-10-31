@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header Card --}}
    <div class="no-print" style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Global Search</p>
        <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">Search Results</h2>
        <p style="font-size:14px; color:#475569; margin:6px 0 0 0;">
            @if($query !== '')
                Showing results for "<strong style="color:#0f172a;">{{ $query }}</strong>"
            @else
                Type a keyword in the search bar to begin.
            @endif
        </p>
    </div>

    @if($query !== '')

        {{-- Medicines --}}
        @if($scope === 'all' || $scope === 'medicine')
            <div style="
                background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
                border-radius:20px; padding:24px; border:1px solid rgba(255,255,255,0.4);
                box-shadow:0 4px 12px rgba(0,0,0,0.04);
            ">
                <h3 style="font-size:18px; font-weight:700; color:#0f172a; margin:0 0 16px 0;">Medicines</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    @forelse($medicineResults as $medicine)
                        <a href="{{ route('medicine.index', ['page' => $medicine->search_page, 'highlight' => $medicine->id]) }}#medicine-{{ $medicine->id }}"
                           style="display:block; background:#f8fafc; border:0.5px solid #e2e8f0; border-radius:12px; padding:14px 18px; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)';"
                           onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            <p style="font-size:14px; font-weight:600; color:#0f172a; margin:0;">{{ $medicine->medicine_name }}</p>
                            <p style="font-size:13px; color:#64748b; margin:4px 0 0 0;">
                                Dosage: {{ $medicine->dosage ?? '—' }} |
                                Category: {{ ucfirst($medicine->category) }}
                            </p>
                        </a>
                    @empty
                        <p style="font-size:13px; color:#64748b;">No medicine results found.</p>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- Users --}}
        @if(session('role') === 'admin' && ($scope === 'all' || $scope === 'users'))
            <div style="
                background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
                border-radius:20px; padding:24px; border:1px solid rgba(255,255,255,0.4);
                box-shadow:0 4px 12px rgba(0,0,0,0.04);
            ">
                <h3 style="font-size:18px; font-weight:700; color:#0f172a; margin:0 0 16px 0;">Users</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    @forelse($userResults as $user)
                        <a href="{{ route('users.index', ['page' => $user->search_page, 'highlight' => $user->id]) }}#user-{{ $user->id }}"
                           style="display:block; background:#f8fafc; border:0.5px solid #e2e8f0; border-radius:12px; padding:14px 18px; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)';"
                           onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            <p style="font-size:14px; font-weight:600; color:#0f172a; margin:0;">{{ $user->name }}</p>
                            <p style="font-size:13px; color:#64748b; margin:4px 0 0 0;">
                                Username: {{ $user->username }} |
                                Role: {{ ucfirst($user->role) }} |
                                RHU: {{ $user->healthUnit->name ?? 'N/A' }}
                            </p>
                        </a>
                    @empty
                        <p style="font-size:13px; color:#64748b;">No user results found.</p>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- RHU --}}
        @if(session('role') === 'admin' && ($scope === 'all' || $scope === 'rhu'))
            <div style="
                background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
                border-radius:20px; padding:24px; border:1px solid rgba(255,255,255,0.4);
                box-shadow:0 4px 12px rgba(0,0,0,0.04);
            ">
                <h3 style="font-size:18px; font-weight:700; color:#0f172a; margin:0 0 16px 0;">RHU</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    @forelse($rhuResults as $rhu)
                        <a href="{{ route('rhu.index', ['page' => $rhu->search_page, 'highlight' => $rhu->id]) }}#rhu-{{ $rhu->id }}"
                           style="display:block; background:#f8fafc; border:0.5px solid #e2e8f0; border-radius:12px; padding:14px 18px; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)';"
                           onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            <p style="font-size:14px; font-weight:600; color:#0f172a; margin:0;">{{ $rhu->name }}</p>
                            <p style="font-size:13px; color:#64748b; margin:4px 0 0 0;">
                                Municipality: {{ $rhu->municipality }} |
                                Address: {{ $rhu->address }}
                            </p>
                        </a>
                    @empty
                        <p style="font-size:13px; color:#64748b;">No RHU results found.</p>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- Inventory --}}
        @if($scope === 'all' || $scope === 'inventory')
            <div style="
                background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
                border-radius:20px; padding:24px; border:1px solid rgba(255,255,255,0.4);
                box-shadow:0 4px 12px rgba(0,0,0,0.04);
            ">
                <h3 style="font-size:18px; font-weight:700; color:#0f172a; margin:0 0 16px 0;">Inventory</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    @forelse($inventoryResults as $inventory)
                        <a href="{{ route('inventory.index', ['page' => $inventory->search_page, 'highlight' => $inventory->id]) }}#inventory-{{ $inventory->id }}"
                           style="display:block; background:#f8fafc; border:0.5px solid #e2e8f0; border-radius:12px; padding:14px 18px; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)';"
                           onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <p style="font-size:14px; font-weight:600; color:#0f172a; margin:0;">{{ $inventory->medicine->medicine_name ?? 'N/A' }}</p>
                                @if($inventory->batch_no)
                                    <span style="font-family:monospace; font-size:12px; background:#f1f5f9; padding:2px 8px; border-radius:6px; color:#334155;">
                                        Batch: {{ $inventory->batch_no }}
                                    </span>
                                @endif
                            </div>
                            <p style="font-size:13px; color:#64748b; margin:4px 0 0 0;">
                                Available: {{ $inventory->available_stock }} |
                                Expiration: {{ $inventory->expiration_date ?? 'N/A' }}
                            </p>
                        </a>
                    @empty
                        <p style="font-size:13px; color:#64748b;">No inventory results found.</p>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- Stock Logs --}}
        @if($scope === 'all' || $scope === 'stock_logs')
            <div style="
                background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
                border-radius:20px; padding:24px; border:1px solid rgba(255,255,255,0.4);
                box-shadow:0 4px 12px rgba(0,0,0,0.04);
            ">
                <h3 style="font-size:18px; font-weight:700; color:#0f172a; margin:0 0 16px 0;">Stock Logs</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    @forelse($stockLogResults as $log)
                        <a href="{{ route('stock_logs.index', ['page' => $log->search_page, 'highlight' => $log->id]) }}#stock-log-{{ $log->id }}"
                           style="display:block; background:#f8fafc; border:0.5px solid #e2e8f0; border-radius:12px; padding:14px 18px; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)';"
                           onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <p style="font-size:14px; font-weight:600; color:#0f172a; margin:0;">{{ $log->medicine->medicine_name ?? 'N/A' }}</p>
                                @if($log->batch_no)
                                    <span style="font-family:monospace; font-size:12px; background:#f1f5f9; padding:2px 8px; border-radius:6px; color:#334155;">
                                        Batch: {{ $log->batch_no }}
                                    </span>
                                @endif
                            </div>
                            <p style="font-size:13px; color:#64748b; margin:4px 0 0 0;">
                                Added: {{ $log->quantity_added }} |
                                Date: {{ $log->date_added }}
                            </p>
                        </a>
                    @empty
                        <p style="font-size:13px; color:#64748b;">No stock log results found.</p>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- Allocation --}}
        @if($scope === 'all' || $scope === 'allocation')
            <div style="
                background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
                border-radius:20px; padding:24px; border:1px solid rgba(255,255,255,0.4);
                box-shadow:0 4px 12px rgba(0,0,0,0.04);
            ">
                <h3 style="font-size:18px; font-weight:700; color:#0f172a; margin:0 0 16px 0;">Allocation</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    @forelse($allocationResults as $allocation)
                        <a href="{{ route('allocation.index', ['page' => $allocation->search_page, 'highlight' => $allocation->id]) }}#allocation-{{ $allocation->id }}"
                           style="display:block; background:#f8fafc; border:0.5px solid #e2e8f0; border-radius:12px; padding:14px 18px; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)';"
                           onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <p style="font-size:14px; font-weight:600; color:#0f172a; margin:0;">{{ $allocation->medicine->medicine_name ?? 'N/A' }}</p>
                                @if($allocation->inventory && $allocation->inventory->batch_no)
                                    <span style="font-family:monospace; font-size:12px; background:#f1f5f9; padding:2px 8px; border-radius:6px; color:#334155;">
                                        Batch: {{ $allocation->inventory->batch_no }}
                                    </span>
                                @endif
                            </div>
                            <p style="font-size:13px; color:#64748b; margin:4px 0 0 0;">
                                Destination: {{ $allocation->destination_display }} |
                                Qty: {{ $allocation->quantity_allocated }} |
                                Date: {{ $allocation->date_allocated }}
                            </p>
                        </a>
                    @empty
                        <p style="font-size:13px; color:#64748b;">No allocation results found.</p>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- Dispensing --}}
        @if($scope === 'all' || $scope === 'dispensing')
            <div style="
                background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
                border-radius:20px; padding:24px; border:1px solid rgba(255,255,255,0.4);
                box-shadow:0 4px 12px rgba(0,0,0,0.04);
            ">
                <h3 style="font-size:18px; font-weight:700; color:#0f172a; margin:0 0 16px 0;">Dispensing</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    @forelse($dispensingResults as $record)
                        <a href="{{ route('dispensing.index', ['page' => $record->search_page, 'highlight' => $record->id]) }}#dispensing-{{ $record->id }}"
                           style="display:block; background:#f8fafc; border:0.5px solid #e2e8f0; border-radius:12px; padding:14px 18px; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)';"
                           onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <p style="font-size:14px; font-weight:600; color:#0f172a; margin:0;">{{ $record->medicine->medicine_name ?? 'N/A' }}</p>
                                @if($record->batch_no)
                                    <span style="font-family:monospace; font-size:12px; background:#f1f5f9; padding:2px 8px; border-radius:6px; color:#334155;">
                                        Batch: {{ $record->batch_no }}
                                    </span>
                                @endif
                            </div>
                            <p style="font-size:13px; color:#64748b; margin:4px 0 0 0;">
                                Doctor: {{ $record->doctor_name ?? 'N/A' }} |
                                Qty: {{ $record->quantity_dispensed }} |
                                Date: {{ $record->date_dispensed }}
                            </p>
                        </a>
                    @empty
                        <p style="font-size:13px; color:#64748b;">No dispensing results found.</p>
                    @endforelse
                </div>
            </div>
        @endif

    @endif
</div>
@endsection