@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem; overflow-wrap:anywhere; line-height:1.5;">

    {{-- Header Card --}}
    <div class="no-print" style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <p style="font-size:.9375rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Global Search</p>
        <h2 style="font-size:1.5rem; font-weight:700; color:#0f172a; margin:0;">Search Results</h2>
        <p style="font-size:1rem; color:#475569; margin:6px 0 0 0;">
            @if($query !== '')
                Showing results for "<strong style="color:#0f172a;">{{ $query }}</strong>"
            @else
                Type a keyword in the search bar to begin.
            @endif
        </p>
    </div>

    @if($query !== '')

        {{-- Medicines --}}
        @if($scope === 'medicine' || ($scope === 'all' && ($medicineResults->isNotEmpty() || $medicineResults->hasPages())))
            <div id="search-medicine" style="
                background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
                border-radius:20px; padding:24px; border:1px solid rgba(255,255,255,0.4);
                box-shadow:0 4px 12px rgba(0,0,0,0.04);
            ">
                <h3 style="font-size:1.125rem; font-weight:700; color:#0f172a; margin:0 0 16px 0;">Medicines</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    @forelse($medicineResults as $medicine)
                        <a href="{{ route('medicine.index', ['page' => $medicine->search_page, 'highlight' => $medicine->id]) }}#medicine-{{ $medicine->id }}"
                           style="display:block; background:#f8fafc; border:0.5px solid #e2e8f0; border-radius:12px; padding:14px 18px; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)';"
                           onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            <p style="font-size:1rem; font-weight:600; color:#0f172a; margin:0;">{{ $medicine->medicine_name }}</p>
                            <p style="font-size:.9375rem; color:#64748b; margin:4px 0 0 0;">
                                Dosage: {{ $medicine->dosage ?? '—' }} |
                                Category: {{ ucfirst($medicine->category) }}
                            </p>
                        </a>
                    @empty
                        <p style="font-size:.9375rem; color:#64748b;">No medicine results found.</p>
                    @endforelse
                </div>
                {{ $medicineResults->links() }}
            </div>
        @endif

        {{-- Users --}}
        @if(session('role') === 'admin' && ($scope === 'users' || ($scope === 'all' && ($userResults->isNotEmpty() || $userResults->hasPages()))))
            <div id="search-users" style="
                background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
                border-radius:20px; padding:24px; border:1px solid rgba(255,255,255,0.4);
                box-shadow:0 4px 12px rgba(0,0,0,0.04);
            ">
                <h3 style="font-size:1.125rem; font-weight:700; color:#0f172a; margin:0 0 16px 0;">Users</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    @forelse($userResults as $user)
                        <a href="{{ route('users.index', ['page' => $user->search_page, 'highlight' => $user->id]) }}#user-{{ $user->id }}"
                           style="display:block; background:#f8fafc; border:0.5px solid #e2e8f0; border-radius:12px; padding:14px 18px; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)';"
                           onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            <p style="font-size:1rem; font-weight:600; color:#0f172a; margin:0;">{{ $user->name }}</p>
                            <p style="font-size:.9375rem; color:#64748b; margin:4px 0 0 0;">
                                Username: {{ $user->username }} |
                                Role: {{ ucfirst($user->role) }} |
                                RHU: {{ $user->healthUnit->name ?? 'N/A' }}
                            </p>
                        </a>
                    @empty
                        <p style="font-size:.9375rem; color:#64748b;">No user results found.</p>
                    @endforelse
                </div>
                {{ $userResults->links() }}
            </div>
        @endif

        {{-- RHU --}}
        @if(session('role') === 'admin' && ($scope === 'rhu' || ($scope === 'all' && ($rhuResults->isNotEmpty() || $rhuResults->hasPages()))))
            <div id="search-rhu" style="
                background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
                border-radius:20px; padding:24px; border:1px solid rgba(255,255,255,0.4);
                box-shadow:0 4px 12px rgba(0,0,0,0.04);
            ">
                <h3 style="font-size:1.125rem; font-weight:700; color:#0f172a; margin:0 0 16px 0;">RHU</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    @forelse($rhuResults as $rhu)
                        <a href="{{ route('rhu.index', ['page' => $rhu->search_page, 'highlight' => $rhu->id]) }}#rhu-{{ $rhu->id }}"
                           style="display:block; background:#f8fafc; border:0.5px solid #e2e8f0; border-radius:12px; padding:14px 18px; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)';"
                           onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            <p style="font-size:1rem; font-weight:600; color:#0f172a; margin:0;">{{ $rhu->name }}</p>
                            <p style="font-size:.9375rem; color:#64748b; margin:4px 0 0 0;">
                                Municipality: {{ $rhu->municipality }} |
                                Address: {{ $rhu->address }}
                            </p>
                        </a>
                    @empty
                        <p style="font-size:.9375rem; color:#64748b;">No RHU results found.</p>
                    @endforelse
                </div>
                {{ $rhuResults->links() }}
            </div>
        @endif

        {{-- Inventory --}}
        @if($scope === 'inventory' || ($scope === 'all' && ($inventoryResults->isNotEmpty() || $inventoryResults->hasPages())))
            <div id="search-inventory" style="
                background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
                border-radius:20px; padding:24px; border:1px solid rgba(255,255,255,0.4);
                box-shadow:0 4px 12px rgba(0,0,0,0.04);
            ">
                <h3 style="font-size:1.125rem; font-weight:700; color:#0f172a; margin:0 0 16px 0;">Inventory</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    @forelse($inventoryResults as $inventory)
                        <a href="{{ $inventory->search_url }}"
                           style="display:block; background:#f8fafc; border:0.5px solid #e2e8f0; border-radius:12px; padding:14px 18px; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)';"
                           onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <p style="font-size:1rem; font-weight:600; color:#0f172a; margin:0;">{{ $inventory->medicine->medicine_name ?? 'N/A' }}</p>
                                @if($inventory->batch_no)
                                    <span style="font-family:monospace; font-size:.9375rem; background:#f1f5f9; padding:2px 8px; border-radius:6px; color:#334155;">
                                        Batch: {{ $inventory->batch_no }}
                                    </span>
                                @endif
                            </div>
                            <p style="font-size:.9375rem; color:#64748b; margin:4px 0 0 0;">
                                Available: {{ $inventory->available_stock }} |
                                Expiration: {{ $inventory->expiration_date ?? 'N/A' }}
                                @if(is_null(session('health_unit_id'))) | RHU: {{ $inventory->healthUnit?->name ?? 'Unknown facility' }} @endif
                            </p>
                        </a>
                    @empty
                        <p style="font-size:.9375rem; color:#64748b;">No inventory results found.</p>
                    @endforelse
                </div>
                {{ $inventoryResults->links() }}
            </div>
        @endif

        {{-- Stock Logs --}}
        @if($scope === 'stock_logs' || ($scope === 'all' && ($stockLogResults->isNotEmpty() || $stockLogResults->hasPages())))
            <div id="search-stock_logs" style="
                background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
                border-radius:20px; padding:24px; border:1px solid rgba(255,255,255,0.4);
                box-shadow:0 4px 12px rgba(0,0,0,0.04);
            ">
                <h3 style="font-size:1.125rem; font-weight:700; color:#0f172a; margin:0 0 16px 0;">Stock Logs</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    @forelse($stockLogResults as $log)
                        <a href="{{ $log->search_url }}"
                           style="display:block; background:#f8fafc; border:0.5px solid #e2e8f0; border-radius:12px; padding:14px 18px; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)';"
                           onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <p style="font-size:1rem; font-weight:600; color:#0f172a; margin:0;">{{ $log->medicine->medicine_name ?? 'N/A' }}</p>
                                @if($log->batch_no)
                                    <span style="font-family:monospace; font-size:.9375rem; background:#f1f5f9; padding:2px 8px; border-radius:6px; color:#334155;">
                                        Batch: {{ $log->batch_no }}
                                    </span>
                                @endif
                            </div>
                            <p style="font-size:.9375rem; color:#64748b; margin:4px 0 0 0;">
                                Added: {{ $log->quantity_added }} |
                                Date: {{ $log->date_added }}
                                @if(is_null(session('health_unit_id'))) | RHU: {{ $log->healthUnit?->name ?? 'Unknown facility' }} @endif
                            </p>
                        </a>
                    @empty
                        <p style="font-size:.9375rem; color:#64748b;">No stock log results found.</p>
                    @endforelse
                </div>
                {{ $stockLogResults->links() }}
            </div>
        @endif

        {{-- Allocation --}}
        @if($scope === 'allocation' || ($scope === 'all' && ($allocationResults->isNotEmpty() || $allocationResults->hasPages())))
            <div id="search-allocation" style="
                background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
                border-radius:20px; padding:24px; border:1px solid rgba(255,255,255,0.4);
                box-shadow:0 4px 12px rgba(0,0,0,0.04);
            ">
                <h3 style="font-size:1.125rem; font-weight:700; color:#0f172a; margin:0 0 16px 0;">Allocation</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    @forelse($allocationResults as $allocation)
                        <a href="{{ route('allocation.index', ['page' => $allocation->search_page, 'highlight' => $allocation->id]) }}#allocation-{{ $allocation->id }}"
                           style="display:block; background:#f8fafc; border:0.5px solid #e2e8f0; border-radius:12px; padding:14px 18px; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)';"
                           onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <p style="font-size:1rem; font-weight:600; color:#0f172a; margin:0;">{{ $allocation->medicine->medicine_name ?? 'N/A' }}</p>
                                @if($allocation->inventory && $allocation->inventory->batch_no)
                                    <span style="font-family:monospace; font-size:.9375rem; background:#f1f5f9; padding:2px 8px; border-radius:6px; color:#334155;">
                                        Batch: {{ $allocation->inventory->batch_no }}
                                    </span>
                                @endif
                            </div>
                            <p style="font-size:.9375rem; color:#64748b; margin:4px 0 0 0;">
                                Destination: {{ $allocation->destination_display }} |
                                Qty: {{ $allocation->quantity_allocated }} |
                                Date: {{ $allocation->date_allocated }}
                            </p>
                        </a>
                    @empty
                        <p style="font-size:.9375rem; color:#64748b;">No allocation results found.</p>
                    @endforelse
                </div>
                {{ $allocationResults->links() }}
            </div>
        @endif

        {{-- Dispensing --}}
        @if($scope === 'dispensing' || ($scope === 'all' && ($dispensingResults->isNotEmpty() || $dispensingResults->hasPages())))
            <div id="search-dispensing" style="
                background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
                border-radius:20px; padding:24px; border:1px solid rgba(255,255,255,0.4);
                box-shadow:0 4px 12px rgba(0,0,0,0.04);
            ">
                <h3 style="font-size:1.125rem; font-weight:700; color:#0f172a; margin:0 0 16px 0;">Dispensing</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    @forelse($dispensingResults as $record)
                        <a href="{{ $record->search_url }}"
                           style="display:block; background:#f8fafc; border:0.5px solid #e2e8f0; border-radius:12px; padding:14px 18px; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)';"
                           onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <p style="font-size:1rem; font-weight:600; color:#0f172a; margin:0;">{{ $record->medicine->medicine_name ?? 'N/A' }}</p>
                                @if($record->batch_no)
                                    <span style="font-family:monospace; font-size:.9375rem; background:#f1f5f9; padding:2px 8px; border-radius:6px; color:#334155;">
                                        Batch: {{ $record->batch_no }}
                                    </span>
                                @endif
                            </div>
                            <p style="font-size:.9375rem; color:#64748b; margin:4px 0 0 0;">
                                Doctor: {{ $record->prescribingDoctorDisplay() }} |
                                Qty: {{ $record->quantity_dispensed }} |
                                Date: {{ $record->date_dispensed }}
                            </p>
                        </a>
                    @empty
                        <p style="font-size:.9375rem; color:#64748b;">No dispensing results found.</p>
                    @endforelse
                </div>
                {{ $dispensingResults->links() }}
            </div>
        @endif


        @if($scope === 'transfers' || ($scope === 'all' && ($transferResults->isNotEmpty() || $transferResults->hasPages())))
            <div id="search-transfers" style="background:rgba(255,255,255,.9); border-radius:20px; padding:24px; border:1px solid #e2e8f0; box-shadow:0 4px 12px rgba(0,0,0,.04);">
                <h3 style="font-size:1.125rem; font-weight:700; color:#0f172a; margin:0 0 16px;">Transfers</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    @forelse($transferResults as $transfer)
                        <a href="{{ route('transfers.index', ['page' => $transfer->search_page]) }}#transfer-{{ $transfer->id }}"
                           style="display:block; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px 18px; text-decoration:none;">
                            <p style="font-size:1rem; font-weight:600; color:#0f172a; margin:0;">{{ $transfer->medicine_label }}</p>
                            <p style="font-size:.9375rem; color:#64748b; margin:4px 0 0;">
                                {{ $transfer->fromHealthUnit?->name ?? 'Unknown RHU' }} &rarr; {{ $transfer->toHealthUnit?->name ?? 'Unknown RHU' }} |
                                Quantity: {{ $transfer->formatQuantity() }} | Status: {{ ucfirst(str_replace('_', ' ', $transfer->status)) }}
                            </p>
                        </a>
                    @empty
                        <p style="font-size:1rem; color:#64748b;">No transfer results found.</p>
                    @endforelse
                </div>
                {{ $transferResults->links() }}
            </div>
        @endif

        @if($scope === 'forecast' || ($scope === 'all' && ($forecastResults->isNotEmpty() || $forecastResults->hasPages())))
            <div id="search-forecast" style="background:rgba(255,255,255,.9); border-radius:20px; padding:24px; border:1px solid #e2e8f0; box-shadow:0 4px 12px rgba(0,0,0,.04);">
                <h3 style="font-size:1.125rem; font-weight:700; color:#0f172a; margin:0 0 16px;">Forecast</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    @forelse($forecastResults as $item)
                        <a href="{{ $item['search_url'] }}" style="display:block; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px 18px; text-decoration:none;">
                            <p style="font-size:1rem; font-weight:600; color:#0f172a; margin:0;">{{ $item['medicine_name'] }} &mdash; {{ $item['health_unit_name'] }}</p>
                            <p style="font-size:.9375rem; color:#64748b; margin:4px 0 0;">
                                Available: {{ number_format($item['available_stock']) }} | 90-day need: {{ number_format($item['forecast_need_3mo']) }}
                            </p>
                        </a>
                    @empty
                        <p style="font-size:1rem; color:#64748b;">No forecast results found.</p>
                    @endforelse
                </div>
                {{ $forecastResults->links() }}
            </div>
        @endif

        @if($scope === 'all' && collect([$medicineResults, $userResults, $rhuResults, $inventoryResults, $stockLogResults, $allocationResults, $dispensingResults, $transferResults, $forecastResults])->every(fn ($results) => $results->isEmpty() && ! $results->hasPages()))
            <div style="background:rgba(255,255,255,.9); border-radius:20px; padding:24px; border:1px solid #e2e8f0;">
                <p style="font-size:1rem; color:#64748b; margin:0;">No matching records found in the sections you can access.</p>
            </div>
        @endif
    @endif
</div>
@endsection
