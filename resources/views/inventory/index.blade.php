@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Helper styles --}}
    <style>
        .hidden { display: none !important; }
        .filter-card input, .filter-card select {
            height: 44px;
            padding: 0 14px;
            font-size: 16px;
            border: 2px solid #bec6cf;
            border-radius: 10px;
            outline: none;
            background: white;
            color: #0f172a;
            width: 100%;
            box-sizing: border-box;
        }
        .filter-card input:focus, .filter-card select:focus {
            border-color: #42ad8a;
            box-shadow: 0 0 0 3px rgba(66,173,138,0.1);
        }
        .filter-card label {
            display: block;
            margin-bottom: 6px;
            font-size: 15px;
            font-weight: 600;
            color: #0f172a;
        }
        .filter-card .btn-filter {
            font-size: 15px;
            font-weight: 600;
            padding: 8px 22px;
            border-radius: 10px;
            background: #42ad8a;
            color: white;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
            height: 44px;
        }
        .filter-card .btn-filter:hover { background: #369f7d; }
        .filter-card .btn-reset {
            font-size: 15px;
            font-weight: 600;
            padding: 8px 22px;
            border-radius: 10px;
            background: #ffffff;
            color: #334155;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            transition: all 0.2s;
            height: 44px;
            display: inline-flex;
            align-items: center;
        }
        .filter-card .btn-reset:hover {
            background: #f8fafc;
            border-color: #42ad8a;
        }
        .table-scroll::-webkit-scrollbar { height: 10px; }
        .table-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1; border-radius: 10px; border: 2px solid #f1f5f9;
        }
        .table-scroll::-webkit-scrollbar-thumb:hover { background: #42ad8a; }
        .table-scroll { scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9; }

        @media print {
            .no-print { display: none !important; }
        }

        /* Medicine Info Modal */
        #medicine-info-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(2px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        #medicine-info-modal .modal-card {
            background: #ffffff;
            border-radius: 22px;
            padding: 32px 36px;
            max-width: 620px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        #medicine-info-modal .modal-section-label {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #42ad8a;
            margin: 0 0 8px 0;
        }
        #medicine-info-modal .modal-subsection-label {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #64748b;
            margin: 0 0 14px 0;
        }
        #medicine-info-modal .modal-title {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
        }
        #medicine-info-modal .field-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px 24px;
        }
        #medicine-info-modal .field-grid > div {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        #medicine-info-modal .field-grid > div.full-width {
            grid-column: 1 / -1;
        }
        #medicine-info-modal .field-grid label {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #64748b;
        }
        #medicine-info-modal .field-grid .value {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            line-height: 1.45;
        }
        #medicine-info-modal .field-grid .value.empty {
            color: #94a3b8;
            font-weight: 400;
        }
    </style>

    {{-- Header Card --}}
    <div class="no-print" style="
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(12px);
        border-radius: 20px;
        padding: 24px 28px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    ">
        <div style="display:flex; align-items:center; gap:24px; flex-wrap:wrap;">
            <div>
                <p style="font-size: 13px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #42ad8a; margin: 0 0 6px 0;">Inventory Management</p>
                @if(is_null(session('health_unit_id')))
                    <h2 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0;">
                        @if(request('rhu_filter') === 'pho')
                            PHO Inventory
                        @elseif(request('rhu_filter'))
                            @php $selectedRhu = $healthUnits->firstWhere('id', request('rhu_filter')); @endphp
                            {{ $selectedRhu->name ?? 'RHU Inventory' }}
                        @else
                            Health Units Inventory
                        @endif
                    </h2>
                @else
                    <h2 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0;">Inventory Records</h2>
                @endif
            </div>

            {{-- TAB BAR: Inventory / Stock Logs --}}
            <div style="background: #f1f5f9; border-radius: 12px; padding: 4px; display: inline-flex; gap: 4px;">
                <a href="{{ route('inventory.index', array_filter(['rhu_filter' => request('rhu_filter')])) }}" style="
                    display: inline-flex; align-items: center; gap: 8px;
                    padding: 8px 18px; border-radius: 9px;
                    font-size: 14px; font-weight: 600; text-decoration: none;
                    background: #42ad8a; color: #ffffff;
                    box-shadow: 0 2px 6px rgba(66,173,138,0.25);
                ">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                    Inventory
                </a>

                <a href="{{ route('stock_logs.index', array_filter(['rhu_filter' => request('rhu_filter')])) }}" style="
                    display: inline-flex; align-items: center; gap: 8px;
                    padding: 8px 18px; border-radius: 9px;
                    font-size: 14px; font-weight: 600; text-decoration: none;
                    background: transparent; color: #475569;
                    transition: all 0.2s;
                " onmouseover="this.style.background='rgba(255,255,255,0.7)'; this.style.color='#0f5c4b';"
                   onmouseout="this.style.background='transparent'; this.style.color='#475569';">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="8" y1="13" x2="16" y2="13"/>
                        <line x1="8" y1="17" x2="16" y2="17"/>
                    </svg>
                    Stock Logs
                </a>
            </div>
        </div>

        {{-- Actions --}}
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">

            {{-- Location filter (PHO admin only) --}}
            @if(is_null(session('health_unit_id')))
                <form method="GET" action="{{ route('inventory.index') }}" style="display: flex; gap: 8px; align-items: center;">
                    @if(request('search')) <input type="hidden" name="search" value="{{ request('search') }}"> @endif
                    @if(request('storage_location')) <input type="hidden" name="storage_location" value="{{ request('storage_location') }}"> @endif
                    @if(request('expiry_filter')) <input type="hidden" name="expiry_filter" value="{{ request('expiry_filter') }}"> @endif
                    <select name="rhu_filter" style="
                        height: 40px; padding: 0 12px; font-size: 14px;
                        border: 2px solid #bec6cf; border-radius: 10px; outline: none;
                        background: white; color: #0f172a; appearance: auto;
                    " onchange="this.form.submit()">
                        <option value="">All Health Units</option>
                        <option value="pho" {{ request('rhu_filter') === 'pho' ? 'selected' : '' }}>Your PHO Inventory</option>
                        @foreach($healthUnits as $rhu)
                            <option value="{{ $rhu->id }}" {{ request('rhu_filter') == $rhu->id ? 'selected' : '' }}>
                                {{ $rhu->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif

            {{-- Expiry Filter --}}
            <form method="GET" action="{{ route('inventory.index') }}" style="display: flex; gap: 8px; align-items: center;">
                @if(request('search')) <input type="hidden" name="search" value="{{ request('search') }}"> @endif
                @if(request('storage_location')) <input type="hidden" name="storage_location" value="{{ request('storage_location') }}"> @endif
                @if(request('rhu_filter')) <input type="hidden" name="rhu_filter" value="{{ request('rhu_filter') }}"> @endif
                <select name="expiry_filter" style="
                    height: 40px; padding: 0 12px; font-size: 14px;
                    border: 2px solid #bec6cf; border-radius: 10px; outline: none;
                    background: white; color: #0f172a; appearance: auto;
                " onchange="this.form.submit()">
                    <option value="">All Expiry</option>
                    <option value="expired"     {{ request('expiry_filter') == 'expired'     ? 'selected' : '' }}>🔴 Expired</option>
                    <option value="near_expiry" {{ request('expiry_filter') == 'near_expiry' ? 'selected' : '' }}>🟠 Near Expiry (≤90d)</option>
                    <option value="monitor"     {{ request('expiry_filter') == 'monitor'     ? 'selected' : '' }}>🟡 Monitor (≤180d)</option>
                    <option value="good"        {{ request('expiry_filter') == 'good'        ? 'selected' : '' }}>🟢 Good (&gt;180d)</option>
                    <option value="no_expiry"   {{ request('expiry_filter') == 'no_expiry'   ? 'selected' : '' }}>⚪ No Expiry Set</option>
                </select>
            </form>

            {{-- EXPORT BUTTON — opens inline modal --}}
            <button type="button" onclick="openInvExportModal()" style="
                display:inline-flex; align-items:center; gap:6px;
                font-size:14px; font-weight:600;
                padding:9px 18px; border-radius:10px;
                background:#42ad8a; color:#ffffff;
                border:none; cursor:pointer; transition: background 0.2s;
                white-space:nowrap;
            " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Export
            </button>

            @if(session('health_unit_id'))
                <a href="{{ route('stock_logs.create') }}" style="
                    display: inline-flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 600;
                    padding: 9px 16px; border-radius: 10px; background: #42ad8a; color: #ffffff;
                    text-decoration: none; border: none; cursor: pointer; transition: all 0.2s;
                " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Inventory
                </a>
            @endif
        </div>
    </div>

    {{-- FILTER CARD --}}
    <div class="no-print filter-card" style="
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(8px);
        border-radius: 16px;
        padding: 22px 26px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    ">
        <form method="GET" action="{{ route('inventory.index') }}" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
            @if(request('rhu_filter')) <input type="hidden" name="rhu_filter" value="{{ request('rhu_filter') }}"> @endif
            @if(request('expiry_filter')) <input type="hidden" name="expiry_filter" value="{{ request('expiry_filter') }}"> @endif

            <div style="flex: 1 1 220px; min-width: 180px;">
                <label for="storage_location">Storage Location</label>
                <input type="text" name="storage_location" id="storage_location" 
                       placeholder="e.g., Cold Room" value="{{ request('storage_location') }}">
            </div>

            <div style="flex: 1 1 220px; min-width: 180px;">
                <label for="search">Medicine Name</label>
                <input type="text" name="search" id="search" placeholder="Search medicine..." value="{{ request('search') }}">
            </div>

            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="submit" class="btn-filter">Apply Filters</button>
                <a href="{{ route('inventory.index') }}" class="btn-reset">Reset</a>
            </div>
        </form>
    </div>

    {{-- Table card --}}
    <div style="
        background: rgba(255,255,255,0.9);
        border-radius: 20px;
        padding: 0;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        overflow: hidden;
    ">
        <div class="table-scroll" style="width: 100%; overflow-x: auto; overflow-y: hidden; -webkit-overflow-scrolling: touch;">
            <table style="width:100%; min-width: 1000px; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th class="no-print" style="padding: 14px 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">#</th>
                        <th style="padding: 14px 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Batch No.</th>
                        <th style="padding: 14px 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Medicine</th>
                        @if(is_null(session('health_unit_id')))
                            <th style="padding: 14px 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Health Unit</th>
                        @endif
                        <th style="padding: 14px 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Available</th>
                        <th style="padding: 14px 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Allocated</th>
                        <th style="padding: 14px 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Expiration</th>
                        <th style="padding: 14px 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Storage Location</th>
                        <th class="no-print" style="padding: 14px 16px; text-align: center; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($inventories as $inventory)
                        @php
                            $isHighlighted = (string) request('highlight') === (string) $inventory->id;
                            $rowBg = $isHighlighted ? '#f1f5f9' : 'transparent';

                            $med  = $inventory->medicine;
                            $unit = $med?->unit ?? null;

                            $packType = $inventory->pack_type ?? $med?->pack_type;
                            $ppp      = $inventory->pieces_per_pack ?? $med?->pieces_per_pack;

                            $pluralUnit = null;
                            if ($unit) {
                                if ($inventory->available_stock == 1) {
                                    $pluralUnit = $unit;
                                } else {
                                    $pluralUnit = (substr($unit, -1) === 's') ? $unit : $unit . 's';
                                }
                            }
                            $unitLabel = $pluralUnit ? ' (' . $pluralUnit . ')' : ' (units)';

                            $pluralAlloc = null;
                            if ($unit) {
                                if ($inventory->allocated_stock == 1) {
                                    $pluralAlloc = $unit;
                                } else {
                                    $pluralAlloc = (substr($unit, -1) === 's') ? $unit : $unit . 's';
                                }
                            }
                            $allocLabel = $pluralAlloc ? ' (' . $pluralAlloc . ')' : ' (units)';

                            $expiry = $inventory->expiration_date;
                            $expiryBadge = null;
                            $daysLeft = null;

                            if ($expiry) {
                                $daysLeft = now()->startOfDay()->diffInDays($expiry->copy()->startOfDay(), false);

                                if ($daysLeft < 0) {
                                    $expiryBadge = ['label' => 'Expired',     'bg' => '#FECACA', 'color' => '#991B1B', 'border' => '#FCA5A5'];
                                } elseif ($daysLeft <= 90) {
                                    $expiryBadge = ['label' => 'Near Expiry', 'bg' => '#FFE4CC', 'color' => '#B85C00', 'border' => '#FFB870'];
                                } elseif ($daysLeft <= 180) {
                                    $expiryBadge = ['label' => 'Monitor',     'bg' => '#FEF3C7', 'color' => '#92400E', 'border' => '#FCD34D'];
                                } else {
                                    $expiryBadge = ['label' => 'Good',        'bg' => '#DCFCE7', 'color' => '#166534', 'border' => '#86EFAC'];
                                }
                            }

                            $isPhoRow = is_null(session('health_unit_id'))
                                && optional($inventory->healthUnit)->facility_type === 'Provincial Health Office';
                        @endphp

                        <tr id="inventory-{{ $inventory->id }}" style="background: {{ $rowBg }}; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; scroll-margin-top: 80px;"
                            onmouseover="if('{{ $rowBg }}'=='transparent') this.style.background='#f8fafc';" 
                            onmouseout="if('{{ $rowBg }}'=='transparent') this.style.background='transparent';">
                            <td class="no-print" style="padding: 14px 16px; color: #334155; text-align: center;">{{ $loop->iteration + ($inventories->currentPage() - 1) * $inventories->perPage() }}</td>
                            <td style="padding: 14px 16px; color: #334155; font-weight: 600;">{{ $inventory->batch_no ?? 'N/A' }}</td>
                            <td style="padding: 14px 16px; font-weight: 600; color: #0f172a;">{{ $med?->medicine_name ?? 'N/A' }}</td>

                            @if(is_null(session('health_unit_id')))
                                <td style="padding: 14px 16px; color: #334155;">{{ $inventory->healthUnit->name ?? 'N/A' }}</td>
                            @endif

                            <td style="padding: 14px 16px; color: #334155; font-weight: 600;">
                                {{ $inventory->available_stock }}<span style="font-weight: 500; color: #64748b;">{{ $unitLabel }}</span>
                            </td>

                            <td style="padding: 14px 16px; color: #334155;">
                                {{ $inventory->allocated_stock }}<span style="font-weight: 500; color: #64748b;">{{ $allocLabel }}</span>
                            </td>

                            <td style="padding: 14px 16px; color: #334155; white-space: nowrap;">
                                @if($expiry)
                                    <div style="font-weight: 600;">{{ $expiry->format('m/d/Y') }}</div>
                                    @if($expiryBadge)
                                        <span title="{{ $daysLeft >= 0 ? $daysLeft . ' day(s) remaining' : abs($daysLeft) . ' day(s) past expiry' }}"
                                              style="
                                                  display:inline-block; margin-top:4px;
                                                  padding:2px 8px; border-radius:6px;
                                                  font-size:11px; font-weight:700; letter-spacing:0.02em;
                                                  background:{{ $expiryBadge['bg'] }};
                                                  color:{{ $expiryBadge['color'] }};
                                                  border:1px solid {{ $expiryBadge['border'] }};
                                              ">
                                            {{ $expiryBadge['label'] }}
                                        </span>
                                    @endif
                                @else
                                    <span style="color:#94a3b8;">N/A</span>
                                @endif
                            </td>

                            <td style="padding: 14px 16px; color: #334155;">
                                @if($inventory->storage_location)
                                    <span style="display:inline-flex; align-items:center; gap:4px; background:#f1f5f9; padding:2px 10px; border-radius:12px; font-size:12px; color:#0f172a;">
                                        📦 {{ $inventory->storage_location }}
                                    </span>
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>

                            <td class="no-print" style="padding: 14px 16px;">
                                <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                                    <button type="button" title="View medicine info"
                                            class="view-medicine-btn"
                                            data-med-name="{{ $med?->medicine_name ?? '' }}"
                                            data-dosage="{{ $med?->dosage ?? '' }}"
                                            data-dosage-form="{{ $med?->dosage_form ?? '' }}"
                                            data-category="{{ $med?->category_label ?? '' }}"
                                            data-unit="{{ $med?->unit ?? '' }}"
                                            data-pack-type="{{ $packType ?? '' }}"
                                            data-pieces-per-pack="{{ $ppp ?? '' }}"
                                            data-description="{{ $med?->description ?? '' }}"
                                            data-batch-no="{{ $inventory->batch_no ?? '' }}"
                                            data-available="{{ $inventory->available_stock }}"
                                            data-allocated="{{ $inventory->allocated_stock }}"
                                            data-expiration="{{ $expiry ? $expiry->format('m/d/Y') : '' }}"
                                            data-storage="{{ $inventory->storage_location ?? '' }}"
                                            onclick="openMedicineModal(this)"
                                            style="
                                                width: 34px; height: 34px;
                                                display: inline-flex; align-items: center; justify-content: center;
                                                border-radius: 8px;
                                                background: #EFF6FF; color: #1e40af; border: 1px solid #93c5fd;
                                                cursor: pointer; transition: all 0.2s;
                                            "
                                            onmouseover="this.style.background='#DBEAFE'" onmouseout="this.style.background='#EFF6FF'">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </button>

                                    @if($isPhoRow)
                                        <span style="font-size:11px; color:#94a3b8; text-align:center; max-width:140px; line-height:1.3;">
                                            Read-only<br><span style="font-size:10px;">(managed via DOH receive &amp; allocation)</span>
                                        </span>
                                    @else
                                        <a href="{{ route('inventory.edit', $inventory->id) }}" title="Edit"
                                           style="
                                               width: 34px; height: 34px;
                                               display: inline-flex; align-items: center; justify-content: center;
                                               border-radius: 8px;
                                               background: #E8F7F4; color: #0f5c4b; border: 1px solid #42AD8A;
                                               text-decoration: none; transition: all 0.2s;
                                           "
                                           onmouseover="this.style.background='#d2efe6'" onmouseout="this.style.background='#E8F7F4'">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </a>

                                        <form action="{{ route('inventory.destroy', $inventory->id) }}" method="POST"
                                              onsubmit="return confirmDialog(event, 'Are you sure you want to delete this inventory record?');"
                                              style="margin:0;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Delete"
                                                    style="
                                                        width: 34px; height: 34px;
                                                        display: inline-flex; align-items: center; justify-content: center;
                                                        border-radius: 8px;
                                                        background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;
                                                        cursor: pointer; transition: all 0.2s;
                                                    "
                                                    onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6"/>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                    <line x1="10" y1="11" x2="10" y2="17"/>
                                                    <line x1="14" y1="11" x2="14" y2="17"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="@php
                                $cols = 8;
                                if (is_null(session('health_unit_id'))) $cols += 1;
                                echo $cols;
                            @endphp" style="padding: 40px 20px; text-align: center; color: #64748b; border-bottom: 1px solid #f1f5f9;">
                                No inventory records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @if ($inventories->hasPages())
        <div class="no-print" style="
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(8px);
            border-radius: 16px;
            padding: 16px 24px;
            border: 1px solid rgba(255,255,255,0.4);
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        ">
            @if ($inventories->onFirstPage())
                <span style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: default; user-select: none;">← Previous</span>
            @else
                <a href="{{ $inventories->previousPageUrl() }}" style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                    ← Previous
                </a>
            @endif

            @foreach ($inventories->getUrlRange(1, $inventories->lastPage()) as $page => $url)
                @if ($page == $inventories->currentPage())
                    <span style="font-size: 14px; font-weight: 600; padding: 8px 14px; border-radius: 8px; background: #42ad8a; color: #ffffff; border: 1px solid #42ad8a; cursor: default;">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" style="font-size: 14px; font-weight: 500; padding: 8px 14px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                       onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                        {{ $page }}
                    </a>
                @endif
            @endforeach

            @if ($inventories->hasMorePages())
                <a href="{{ $inventories->nextPageUrl() }}" style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                    Next →
                </a>
            @else
                <span style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: default; user-select: none;">Next →</span>
            @endif
        </div>
    @endif

</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- INLINE EXPORT MODAL — Category + RHU + Batch + Expiry --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div id="invExportModal"
     onclick="if(event.target === this) closeInvExportModal()"
     style="
        display:none; position:fixed; inset:0; z-index:99999;
        background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);
        align-items:center; justify-content:center; padding:20px;
     ">
    <div style="
        background:#ffffff; border-radius:20px;
        width:100%; max-width:520px;
        display:flex; flex-direction:column;
        box-shadow: 0 24px 60px rgba(0,0,0,0.25);
        max-height:90vh; overflow-y:auto;
    ">
        {{-- Header --}}
        <div style="padding:22px 26px 14px; border-bottom:1px solid #f1f5f9;">
            <h3 style="font-size:19px; font-weight:700; color:#0f172a; margin:0 0 4px 0;">
                Export Inventory Records
            </h3>
            <p style="font-size:13px; color:#64748b; margin:0;">
                Choose what to include, then pick an action.
            </p>
        </div>

        {{-- Body --}}
        <div style="padding:20px 26px;">

            {{-- PROGRAM CATEGORY --}}
            <div style="margin-bottom:22px;">
                <label for="category-invExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Program Category
                </label>
                <select id="category-invExportModal"
                        style="
                            width:100%; height:44px;
                            padding:0 14px; font-size:14px;
                            border:2px solid #bec6cf; border-radius:10px;
                            outline:none; box-sizing:border-box;
                            background:#ffffff; color:#0f172a; appearance:auto; cursor:pointer;
                        "
                        onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                        onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <option value="" {{ !request('category') ? 'selected' : '' }}>All Categories</option>
                    <option value="medicine"                 {{ request('category') == 'medicine'                 ? 'selected' : '' }}>Medicine</option>
                    <option value="vaccine"                  {{ request('category') == 'vaccine'                  ? 'selected' : '' }}>Vaccine</option>
                    <option value="family_planning"          {{ request('category') == 'family_planning'          ? 'selected' : '' }}>Family Planning</option>
                    <option value="family_health_nutrition"  {{ request('category') == 'family_health_nutrition'  ? 'selected' : '' }}>Family Health &amp; Nutrition</option>
                    <option value="emerging_reemerging"      {{ request('category') == 'emerging_reemerging'      ? 'selected' : '' }}>Emerging &amp; Re-emerging</option>
                    <option value="non_communicable"         {{ request('category') == 'non_communicable'         ? 'selected' : '' }}>Non-Communicable Disease</option>
                    <option value="dengue_prevention"        {{ request('category') === 'dengue_prevention'       ? 'selected' : '' }}>Dengue Prevention</option>
                    <option value="tuberculosis"             {{ request('category') === 'tuberculosis'            ? 'selected' : '' }}>Tuberculosis</option>
                </select>
            </div>

            {{-- RHU (PHO admin only) --}}
            @if(is_null(session('health_unit_id')))
                <div style="margin-bottom:22px;">
                    <label for="rhu-invExportModal"
                           style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                        Health Unit (RHU)
                    </label>
                    <select id="rhu-invExportModal"
                            style="
                                width:100%; height:44px;
                                padding:0 14px; font-size:14px;
                                border:2px solid #bec6cf; border-radius:10px;
                                outline:none; box-sizing:border-box;
                                background:#ffffff; color:#0f172a; appearance:auto; cursor:pointer;
                            "
                            onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                            onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                        <option value="" {{ !request('rhu_filter') ? 'selected' : '' }}>All Health Units</option>
                        <option value="pho" {{ request('rhu_filter') === 'pho' ? 'selected' : '' }}>Your PHO Inventory</option>
                        @foreach($healthUnits as $rhu)
                            <option value="{{ $rhu->id }}" {{ request('rhu_filter') == $rhu->id ? 'selected' : '' }}>
                                {{ $rhu->name }}
                            </option>
                        @endforeach
                    </select>
                    <p style="font-size:12px; color:#64748b; margin:6px 0 0 0;">
                        Choose a specific RHU, or leave as "All Health Units" for province-wide.
                    </p>
                </div>
            @endif

            {{-- BATCH NUMBER(S) --}}
            <div style="margin-bottom:22px;">
                <label for="batchInput-invExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Batch Number(s)
                </label>
                <input type="text"
                       id="batchInput-invExportModal"
                       placeholder="e.g. BT-001, BT-002, 1-8"
                       value="{{ request('batch_input', '') }}"
                       style="
                           width:100%; height:44px;
                           padding:0 14px; font-size:14px;
                           border:2px solid #bec6cf; border-radius:10px;
                           outline:none; box-sizing:border-box;
                           background:#ffffff; color:#0f172a;
                       "
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                <p style="font-size:12px; color:#64748b; margin:6px 0 0 0;">
                    Separate multiple batch numbers with commas. Leave blank to include all batches.
                </p>
            </div>

            {{-- EXPIRY STATUS --}}
            <div>
                <label for="expiry-invExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Expiry Status
                </label>
                <select id="expiry-invExportModal"
                        style="
                            width:100%; height:44px;
                            padding:0 14px; font-size:14px;
                            border:2px solid #bec6cf; border-radius:10px;
                            outline:none; box-sizing:border-box;
                            background:#ffffff; color:#0f172a; appearance:auto; cursor:pointer;
                        "
                        onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                        onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <option value=""            {{ request('expiry_filter', '') === ''            ? 'selected' : '' }}>All Expiry</option>
                    <option value="near_expiry" {{ request('expiry_filter', '') === 'near_expiry' ? 'selected' : '' }}>Near Expiry (≤180 days)</option>
                    <option value="expired"     {{ request('expiry_filter', '') === 'expired'     ? 'selected' : '' }}>Expired Only</option>
                    <option value="not_expired" {{ request('expiry_filter', '') === 'not_expired' ? 'selected' : '' }}>Not Expiring Soon</option>
                </select>
            </div>
        </div>

        {{-- Footer --}}
        <div style="
            padding:16px 26px 20px;
            border-top:1px solid #f1f5f9;
            display:flex; gap:10px; justify-content:flex-end;
            background:#fafbfc;
            border-radius: 0 0 20px 20px;
        ">
            <button type="button"
                    onclick="closeInvExportModal()"
                    style="
                        font-size:14px; font-weight:600;
                        padding:10px 18px; border-radius:10px;
                        background:#ffffff; color:#334155;
                        border:1px solid #e2e8f0; cursor:pointer;
                    "
                    onmouseover="this.style.background='#f1f5f9'"
                    onmouseout="this.style.background='#ffffff'">
                Cancel
            </button>

            <button type="button"
                    onclick="runInvExport('print')"
                    style="
                        display:inline-flex; align-items:center; gap:6px;
                        font-size:14px; font-weight:600;
                        padding:10px 18px; border-radius:10px;
                        background:#ffffff; color:#334155;
                        border:1px solid #e2e8f0; cursor:pointer;
                    "
                    onmouseover="this.style.background='#f1f5f9'"
                    onmouseout="this.style.background='#ffffff'">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                Print
            </button>

            <button type="button"
                    onclick="runInvExport('csv')"
                    style="
                        display:inline-flex; align-items:center; gap:6px;
                        font-size:14px; font-weight:600;
                        padding:10px 18px; border-radius:10px;
                        background:#42ad8a; color:#ffffff;
                        border:none; cursor:pointer;
                    "
                    onmouseover="this.style.background='#369f7d'"
                    onmouseout="this.style.background='#42ad8a'">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Export CSV
            </button>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- MEDICINE INFO MODAL --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div id="medicine-info-modal" onclick="if(event.target === this) closeMedicineModal()">
    <div class="modal-card">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px; gap:12px;">
            <div>
                <p class="modal-section-label">Medicine Information</p>
                <h3 id="modal-med-name" class="modal-title">—</h3>
            </div>
            <button type="button" onclick="closeMedicineModal()" aria-label="Close"
                    style="background:transparent; border:none; cursor:pointer; padding:4px 10px; color:#64748b; font-size:26px; line-height:1; border-radius:8px; transition: all 0.2s;"
                    onmouseover="this.style.background='#f1f5f9'; this.style.color='#0f172a';"
                    onmouseout="this.style.background='transparent'; this.style.color='#64748b';">✕</button>
        </div>

        {{-- Medicine-level info --}}
        <div class="field-grid">
            <div>
                <label>Dosage Strength</label>
                <span class="value" id="modal-dosage">—</span>
            </div>
            <div>
                <label>Dosage Form</label>
                <span class="value" id="modal-dosage-form">—</span>
            </div>
            <div>
                <label>Program Category</label>
                <span class="value" id="modal-category">—</span>
            </div>
            <div>
                <label>Base Unit</label>
                <span class="value" id="modal-unit">—</span>
            </div>
            <div class="full-width">
                <label>Description</label>
                <span class="value" id="modal-description">—</span>
            </div>
        </div>

        <div style="margin:26px 0 20px; border-top:1px solid #e2e8f0;"></div>

        {{-- Batch-level info --}}
        <p class="modal-subsection-label">This Batch</p>
        <div class="field-grid">
            <div>
                <label>Batch No.</label>
                <span class="value" id="modal-batch-no" style="font-family:monospace;">—</span>
            </div>
            <div>
                <label>Expiration</label>
                <span class="value" id="modal-expiration">—</span>
            </div>
            <div>
                <label>Pack Type</label>
                <span class="value" id="modal-pack-type">—</span>
            </div>
            <div>
                <label>Units per Pack</label>
                <span class="value" id="modal-pieces-per-pack">—</span>
            </div>
            <div>
                <label>Available</label>
                <span class="value" id="modal-available">—</span>
            </div>
            <div>
                <label>Allocated</label>
                <span class="value" id="modal-allocated">—</span>
            </div>
            <div class="full-width">
                <label>Storage Location</label>
                <span class="value" id="modal-storage">—</span>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; margin-top:28px;">
            <button type="button" onclick="closeMedicineModal()"
                    style="padding:12px 26px; background:#42ad8a; color:white; border:none; border-radius:10px; font-weight:700; font-size:16px; cursor:pointer; transition: background 0.2s;"
                    onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                Close
            </button>
        </div>
    </div>
</div>

<script>
function setModalField(id, value, suffix) {
    const el = document.getElementById(id);
    if (value === null || value === undefined || value === '' || value === 'null') {
        el.textContent = '—';
        el.classList.add('empty');
    } else {
        el.textContent = suffix ? (value + suffix) : value;
        el.classList.remove('empty');
    }
}

function pluralizeUnit(unit, qty) {
    if (!unit) return 'units';
    if (parseInt(qty, 10) === 1) return unit;
    return (unit.slice(-1) === 's') ? unit : (unit + 's');
}

function openMedicineModal(btn) {
    const d = btn.dataset;

    setModalField('modal-med-name',       d.medName);
    setModalField('modal-dosage',         d.dosage);
    setModalField('modal-dosage-form',    d.dosageForm);
    setModalField('modal-category',       d.category);
    setModalField('modal-unit',           d.unit);
    setModalField('modal-description',    d.description);

    setModalField('modal-batch-no',       d.batchNo);
    setModalField('modal-expiration',     d.expiration);
    setModalField('modal-pack-type',      d.packType);

    if (d.piecesPerPack && d.packType) {
        setModalField('modal-pieces-per-pack', d.piecesPerPack + ' ' + pluralizeUnit(d.unit, d.piecesPerPack) + ' per ' + d.packType);
    } else if (d.piecesPerPack) {
        setModalField('modal-pieces-per-pack', d.piecesPerPack);
    } else {
        setModalField('modal-pieces-per-pack', null);
    }

    const unitWord = pluralizeUnit(d.unit, d.available);
    setModalField('modal-available', d.available, ' ' + unitWord);

    const allocUnit = pluralizeUnit(d.unit, d.allocated);
    setModalField('modal-allocated', d.allocated, ' ' + allocUnit);

    setModalField('modal-storage', d.storage);

    document.getElementById('medicine-info-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeMedicineModal() {
    document.getElementById('medicine-info-modal').style.display = 'none';
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function (e) {
    const modal = document.getElementById('medicine-info-modal');
    if (modal && modal.style.display === 'flex' && e.key === 'Escape') {
        closeMedicineModal();
    }
});

// ─── Export modal logic ───────────────────────────────────
function openInvExportModal() {
    document.getElementById('invExportModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeInvExportModal() {
    document.getElementById('invExportModal').style.display = 'none';
    document.body.style.overflow = '';
}
function runInvExport(mode) {
    const categorySelect = document.getElementById('category-invExportModal');
    const rhuSelect      = document.getElementById('rhu-invExportModal');
    const batchInput     = document.getElementById('batchInput-invExportModal');
    const expirySelect   = document.getElementById('expiry-invExportModal');

    const categoryVal = categorySelect ? categorySelect.value : '';
    const rhuVal      = rhuSelect      ? rhuSelect.value      : '';
    const batchVal    = batchInput     ? batchInput.value.trim() : '';
    const expiryVal   = expirySelect   ? expirySelect.value   : '';

    const baseUrl = mode === 'csv'
        ? '{{ route('inventory.export') }}'
        : '{{ route('inventory.index') }}';

    const url = new URL(baseUrl, window.location.origin);
    const params = new URLSearchParams(window.location.search);
    params.delete('page');
    params.delete('print');
    params.delete('category');
    params.delete('rhu_filter');
    params.delete('batch_input');
    params.delete('expiry_filter');

    if (categoryVal)     params.set('category', categoryVal);
    if (rhuVal)          params.set('rhu_filter', rhuVal);
    if (batchVal !== '') params.set('batch_input', batchVal);
    if (expiryVal)       params.set('expiry_filter', expiryVal);
    if (mode === 'print') params.set('print', '1');

    url.search = params.toString();
    window.location.href = url.toString();
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('invExportModal');
        if (modal && modal.style.display === 'flex') {
            closeInvExportModal();
        }
    }
});
</script>
@endsection