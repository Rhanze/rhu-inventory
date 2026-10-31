@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    <style>
        .table-scroll::-webkit-scrollbar { height: 10px; }
        .table-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1; border-radius: 10px; border: 2px solid #f1f5f9;
        }
        .table-scroll::-webkit-scrollbar-thumb:hover { background: #42ad8a; }
        .table-scroll { scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9; }
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
                <p style="font-size: 13px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #42ad8a; margin: 0 0 6px 0;">Stock Logs</p>
                @if(is_null(session('health_unit_id')))
                    <h2 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0;">
                        @if(request('rhu_filter') === 'pho')
                            PHO Stock Logs
                        @elseif(request('rhu_filter'))
                            @php $selectedRhu = $healthUnits->firstWhere('id', request('rhu_filter')); @endphp
                            {{ $selectedRhu->name ?? 'RHU Stock Logs' }}
                        @else
                            Health Units Stock Logs
                        @endif
                    </h2>
                @else
                    <h2 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0;">Manage Stocks</h2>
                @endif
            </div>

            {{-- TAB BAR: Inventory / Stock Logs --}}
            <div style="background: #f1f5f9; border-radius: 12px; padding: 4px; display: inline-flex; gap: 4px;">
                <a href="{{ route('inventory.index', array_filter(['rhu_filter' => request('rhu_filter')])) }}" style="
                    display: inline-flex; align-items: center; gap: 8px;
                    padding: 8px 18px; border-radius: 9px;
                    font-size: 14px; font-weight: 600; text-decoration: none;
                    background: transparent; color: #475569;
                    transition: all 0.2s;
                " onmouseover="this.style.background='rgba(255,255,255,0.7)'; this.style.color='#0f5c4b';"
                   onmouseout="this.style.background='transparent'; this.style.color='#475569';">
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
                    background: #42ad8a; color: #ffffff;
                    box-shadow: 0 2px 6px rgba(66,173,138,0.25);
                ">
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
                <form method="GET" action="{{ route('stock_logs.index') }}" style="display: flex; gap: 8px; align-items: center;">
                    @if(request('search'))
                        <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    @if(request('type'))
                        <input type="hidden" name="type" value="{{ request('type') }}">
                    @endif
                    <select name="rhu_filter" style="
                        height: 40px; padding: 0 12px; font-size: 14px;
                        border: 2px solid #bec6cf; border-radius: 10px; outline: none;
                        background: white; color: #0f172a; appearance: auto;
                    " onchange="this.form.submit()">
                        <option value="">All Health Units</option>
                        <option value="pho" {{ request('rhu_filter') === 'pho' ? 'selected' : '' }}>Your PHO Stock Logs</option>
                        @foreach($healthUnits as $rhu)
                            <option value="{{ $rhu->id }}" {{ request('rhu_filter') == $rhu->id ? 'selected' : '' }}>
                                {{ $rhu->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif

            {{-- Type Filter --}}
            <form method="GET" action="{{ route('stock_logs.index') }}" style="display: flex; gap: 8px; align-items: center;">
                @if(request('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                @endif
                @if(request('rhu_filter'))
                    <input type="hidden" name="rhu_filter" value="{{ request('rhu_filter') }}">
                @endif
                <select name="type" style="
                    height: 40px; padding: 0 12px; font-size: 14px;
                    border: 2px solid #bec6cf; border-radius: 10px; outline: none;
                    background: white; color: #0f172a; appearance: auto;
                " onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="addition" {{ request('type') == 'addition' ? 'selected' : '' }}>Stock In</option>
                    <option value="release"  {{ request('type') == 'release' ? 'selected' : '' }}>Stock Out</option>
                </select>
            </form>

            {{-- EXPORT BUTTON — opens inline modal --}}
            <button type="button" onclick="openStockLogsExportModal()" style="
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
        </div>
    </div>

    {{-- Table card --}}
    <div style="
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(8px);
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
                        <th style="padding: 14px 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Type</th>
                        <th style="padding: 14px 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Dosage</th>
                        <th style="padding: 14px 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Quantity</th>
                        <th style="padding: 14px 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Date</th>
                        <th style="padding: 14px 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Added By</th>
                        @if(is_null(session('health_unit_id')))
                            <th style="padding: 14px 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">RHU</th>
                        @endif
                    </tr>
                </thead>

                <tbody>
                    @forelse($stockLogs as $log)
                        @php
                            $isHighlighted = (string) request('highlight') === (string) $log->id;
                            $rowBg = $isHighlighted ? '#f1f5f9' : 'transparent';
                            $isRelease = stripos($log->remarks ?? '', 'Released') !== false;

                            if ($isRelease) {
                                $typeLabel   = 'Stock Out';
                                $typeIcon    = '↑';
                                $typeStyles  = 'background:#FFF0F0; color:#A32D2D; border:1px solid #F7C1C1;';
                            } else {
                                $typeLabel   = 'Stock In';
                                $typeIcon    = '↓';
                                $typeStyles  = 'background:#E8F7F4; color:#0f5c4b; border:1px solid #42AD8A;';
                            }

                            $med  = $log->medicine;
                            $unit = $med->unit ?? null;

                            $pluralUnit = null;
                            if ($unit) {
                                if ((int) $log->quantity_added === 1) {
                                    $pluralUnit = $unit;
                                } else {
                                    $pluralUnit = (substr($unit, -1) === 's') ? $unit : $unit . 's';
                                }
                            }
                            $unitLabel = $pluralUnit ? ' (' . $pluralUnit . ')' : ' (units)';
                        @endphp

                        <tr id="stock-log-{{ $log->id }}" style="background: {{ $rowBg }}; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; scroll-margin-top: 80px;"
                            onmouseover="if('{{ $rowBg }}'=='transparent') this.style.background='#f8fafc';" 
                            onmouseout="if('{{ $rowBg }}'=='transparent') this.style.background='transparent';">
                            <td class="no-print" style="padding: 14px 16px; color: #334155; text-align: center;">{{ $loop->iteration + ($stockLogs->currentPage() - 1) * $stockLogs->perPage() }}</td>

                            <td style="padding: 14px 16px; color: #334155; font-weight: 600;">{{ $log->batch_no ?? 'N/A' }}</td>

                            <td style="padding: 14px 16px; font-weight: 600; color: #0f172a;">{{ $med->medicine_name ?? 'N/A' }}</td>

                            <td style="padding: 14px 16px;">
                                <span style="display:inline-flex; align-items:center; gap:4px; font-size:12px; font-weight:600; padding:3px 10px; border-radius:999px; {{ $typeStyles }}">
                                    <span style="font-size:14px; line-height:1;">{{ $typeIcon }}</span>
                                    {{ $typeLabel }}
                                </span>
                            </td>

                            <td style="padding: 14px 16px; color: #334155;">
                                <div>{{ $med->dosage ?? '—' }}</div>
                                @if($med && $med->dosage_form)
                                    <div style="font-size: 12px; color: #64748b; margin-top: 2px;">{{ $med->dosage_form }}</div>
                                @endif
                            </td>

                            <td style="padding: 14px 16px; color: #334155; font-weight: 600;">
                                {{ $log->quantity_added }}<span style="font-weight: 500; color: #64748b;">{{ $unitLabel }}</span>
                            </td>

                            <td style="padding: 14px 16px; color: #334155;">{{ $log->date_added }}</td>

                            <td style="padding: 14px 16px; color: #334155;">{{ $log->addedByUser->name ?? 'N/A' }}</td>

                            @if(is_null(session('health_unit_id')))
                                <td style="padding: 14px 16px; color: #334155;">{{ $log->healthUnit->name ?? 'N/A' }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="@php
                                $cols = 8;
                                if (is_null(session('health_unit_id'))) $cols += 1;
                                echo $cols;
                            @endphp" style="padding: 40px 20px; text-align: center; color: #64748b; border-bottom: 1px solid #f1f5f9;">
                                No stock log records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @if ($stockLogs->hasPages())
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
            @if ($stockLogs->onFirstPage())
                <span style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: default; user-select: none;">← Previous</span>
            @else
                <a href="{{ $stockLogs->previousPageUrl() }}" style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                    ← Previous
                </a>
            @endif

            @foreach ($stockLogs->getUrlRange(1, $stockLogs->lastPage()) as $page => $url)
                @if ($page == $stockLogs->currentPage())
                    <span style="font-size: 14px; font-weight: 600; padding: 8px 14px; border-radius: 8px; background: #42ad8a; color: #ffffff; border: 1px solid #42ad8a; cursor: default;">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" style="font-size: 14px; font-weight: 500; padding: 8px 14px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                       onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                        {{ $page }}
                    </a>
                @endif
            @endforeach

            @if ($stockLogs->hasMorePages())
                <a href="{{ $stockLogs->nextPageUrl() }}" style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
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
{{-- INLINE EXPORT MODAL --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div id="stockLogsExportModal"
     onclick="if(event.target === this) closeStockLogsExportModal()"
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
        <div style="padding:22px 26px 14px; border-bottom:1px solid #f1f5f9;">
            <h3 style="font-size:19px; font-weight:700; color:#0f172a; margin:0 0 4px 0;">
                Export Stock Logs
            </h3>
            <p style="font-size:13px; color:#64748b; margin:0;">
                Choose what to include, then pick an action.
            </p>
        </div>

        <div style="padding:20px 26px;">

            {{-- 1. PROGRAM CATEGORY --}}
            <div style="margin-bottom:22px;">
                <label for="category-stockLogsExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Program Category
                </label>
                <select id="category-stockLogsExportModal"
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

            {{-- 2. RHU (PHO admin only) --}}
            @if(is_null(session('health_unit_id')))
                <div style="margin-bottom:22px;">
                    <label for="rhu-stockLogsExportModal"
                           style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                        Health Unit (RHU)
                    </label>
                    <select id="rhu-stockLogsExportModal"
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
                        <option value="pho" {{ request('rhu_filter') === 'pho' ? 'selected' : '' }}>Your PHO Stock Logs</option>
                        @foreach($healthUnits as $rhu)
                            <option value="{{ $rhu->id }}" {{ request('rhu_filter') == $rhu->id ? 'selected' : '' }}>
                                {{ $rhu->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- 3. MOVEMENT TYPE --}}
            <div style="margin-bottom:22px;">
                <label for="type-stockLogsExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Movement Type
                </label>
                <select id="type-stockLogsExportModal"
                        style="
                            width:100%; height:44px;
                            padding:0 14px; font-size:14px;
                            border:2px solid #bec6cf; border-radius:10px;
                            outline:none; box-sizing:border-box;
                            background:#ffffff; color:#0f172a; appearance:auto; cursor:pointer;
                        "
                        onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                        onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <option value=""          {{ !request('type')              ? 'selected' : '' }}>All Movements</option>
                    <option value="addition"  {{ request('type') == 'addition'  ? 'selected' : '' }}>Stock In only</option>
                    <option value="release"   {{ request('type') == 'release'   ? 'selected' : '' }}>Stock Out only</option>
                </select>
            </div>

            {{-- 4. DATE RANGE --}}
            <div style="margin-bottom:22px;">
                <label style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Date Range
                </label>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div>
                        <label for="dateFrom-stockLogsExportModal"
                               style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">
                            From
                        </label>
                        <input type="date"
                               id="dateFrom-stockLogsExportModal"
                               value="{{ request('date_from', '') }}"
                               style="
                                   width:100%; height:44px;
                                   padding:0 12px; font-size:14px;
                                   border:2px solid #bec6cf; border-radius:10px;
                                   outline:none; box-sizing:border-box;
                                   background:#ffffff; color:#0f172a;
                               "
                               onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                               onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    </div>
                    <div>
                        <label for="dateTo-stockLogsExportModal"
                               style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">
                            To
                        </label>
                        <input type="date"
                               id="dateTo-stockLogsExportModal"
                               value="{{ request('date_to', '') }}"
                               style="
                                   width:100%; height:44px;
                                   padding:0 12px; font-size:14px;
                                   border:2px solid #bec6cf; border-radius:10px;
                                   outline:none; box-sizing:border-box;
                                   background:#ffffff; color:#0f172a;
                               "
                               onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                               onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    </div>
                </div>
                <p style="font-size:12px; color:#64748b; margin:6px 0 0 0;">
                    Leave both blank to include all dates.
                </p>
            </div>

            {{-- 5. BATCH NUMBER(S) --}}
            <div>
                <label for="batchInput-stockLogsExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Batch Number(s)
                </label>
                <input type="text"
                       id="batchInput-stockLogsExportModal"
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
        </div>

        <div style="
            padding:16px 26px 20px;
            border-top:1px solid #f1f5f9;
            display:flex; gap:10px; justify-content:flex-end;
            background:#fafbfc;
            border-radius: 0 0 20px 20px;
        ">
            <button type="button"
                    onclick="closeStockLogsExportModal()"
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
                    onclick="runStockLogsExport('print')"
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
                    onclick="runStockLogsExport('csv')"
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

<script>
function openStockLogsExportModal() {
    document.getElementById('stockLogsExportModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeStockLogsExportModal() {
    document.getElementById('stockLogsExportModal').style.display = 'none';
    document.body.style.overflow = '';
}
function runStockLogsExport(mode) {
    const categorySelect = document.getElementById('category-stockLogsExportModal');
    const rhuSelect      = document.getElementById('rhu-stockLogsExportModal');
    const typeSelect     = document.getElementById('type-stockLogsExportModal');
    const dateFrom       = document.getElementById('dateFrom-stockLogsExportModal');
    const dateTo         = document.getElementById('dateTo-stockLogsExportModal');
    const batchInput     = document.getElementById('batchInput-stockLogsExportModal');

    const categoryVal = categorySelect ? categorySelect.value : '';
    const rhuVal      = rhuSelect      ? rhuSelect.value      : '';
    const typeVal     = typeSelect     ? typeSelect.value     : '';
    const dateFromVal = dateFrom       ? dateFrom.value       : '';
    const dateToVal   = dateTo         ? dateTo.value         : '';
    const batchVal    = batchInput     ? batchInput.value.trim() : '';

    const baseUrl = mode === 'csv'
        ? '{{ route('stock_logs.export') }}'
        : '{{ route('stock_logs.index') }}';

    const url = new URL(baseUrl, window.location.origin);
    const params = new URLSearchParams(window.location.search);
    params.delete('page');
    params.delete('print');
    params.delete('category');
    params.delete('rhu_filter');
    params.delete('type');
    params.delete('date_from');
    params.delete('date_to');
    params.delete('batch_input');

    if (categoryVal)     params.set('category', categoryVal);
    if (rhuVal)          params.set('rhu_filter', rhuVal);
    if (typeVal)         params.set('type', typeVal);
    if (dateFromVal)     params.set('date_from', dateFromVal);
    if (dateToVal)       params.set('date_to', dateToVal);
    if (batchVal !== '') params.set('batch_input', batchVal);
    if (mode === 'print') params.set('print', '1');

    url.search = params.toString();
    window.location.href = url.toString();
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('stockLogsExportModal');
        if (modal && modal.style.display === 'flex') {
            closeStockLogsExportModal();
        }
    }
});
</script>
@endsection