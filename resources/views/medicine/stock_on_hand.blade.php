@extends('layouts.app')

@php
    // Unique dosage forms for the export filter
    $dosageFormOptions = \App\Models\Medicine::query()
        ->whereNotNull('dosage_form')
        ->where('dosage_form', '!=', '')
        ->distinct()
        ->orderBy('dosage_form')
        ->pluck('dosage_form');
@endphp

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header Card with tabs beside title + actions --}}
    <div class="no-print" style="
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(12px);
        border-radius: 20px;
        padding: 24px 28px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    ">
        {{-- Title + Tabs --}}
        <div style="display:flex; align-items:flex-end; gap:24px; flex-wrap:wrap;">
            <div>
                <p style="font-size: 13px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #42ad8a; margin: 0 0 6px 0;">Medicine Management</p>
                <h2 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0; line-height: 1.2;">
                    @if(is_null(session('health_unit_id')))
                        Provincial Stock on Hand
                    @else
                        Stock on Hand
                    @endif
                </h2>
            </div>

            {{-- TAB BAR --}}
            <div style="
                background: #f1f5f9;
                border-radius: 12px;
                padding: 4px;
                display: inline-flex;
                gap: 4px;
            ">
                <a href="{{ route('medicine.index', request()->only('rhu_filter')) }}" style="
                    display: inline-flex; align-items: center; gap: 8px;
                    padding: 8px 18px; border-radius: 9px;
                    font-size: 14px; font-weight: 600; text-decoration: none;
                    background: transparent; color: #475569;
                    transition: all 0.2s;
                    white-space: nowrap;
                " onmouseover="this.style.background='rgba(255,255,255,0.7)'; this.style.color='#0f5c4b';"
                   onmouseout="this.style.background='transparent'; this.style.color='#475569';">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="8" y1="13" x2="16" y2="13"/>
                        <line x1="8" y1="17" x2="16" y2="17"/>
                    </svg>
                    Item Masterlist
                </a>

                <a href="{{ route('medicine.stock_on_hand', request()->only('rhu_filter')) }}" style="
                    display: inline-flex; align-items: center; gap: 8px;
                    padding: 8px 18px; border-radius: 9px;
                    font-size: 14px; font-weight: 600; text-decoration: none;
                    background: #42ad8a; color: #ffffff;
                    box-shadow: 0 2px 6px rgba(66,173,138,0.25);
                    white-space: nowrap;
                ">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                    Stock on Hand
                </a>
            </div>
        </div>

        {{-- Actions --}}
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">

            {{-- RHU Filter – PHO Admin only --}}
            @if(is_null(session('health_unit_id')))
                <form method="GET" action="{{ route('medicine.stock_on_hand') }}" style="display: flex; gap: 8px; align-items: center;">
                    @if(request('search'))
                        <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    @if(request('category'))
                        <input type="hidden" name="category" value="{{ request('category') }}">
                    @endif
                    @if(request('availability'))
                        <input type="hidden" name="availability" value="{{ request('availability') }}">
                    @endif
                    <select name="rhu_filter" style="
                        height: 40px; padding: 0 12px; font-size: 14px;
                        border: 2px solid #bec6cf; border-radius: 10px; outline: none;
                        background: white; color: #0f172a; appearance: auto;
                        max-width: 160px;
                    " onchange="this.form.submit()">
                        <option value="">All RHUs</option>
                        @foreach($healthUnits as $rhu)
                            <option value="{{ $rhu->id }}" {{ request('rhu_filter') == $rhu->id ? 'selected' : '' }}>
                                {{ $rhu->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif

            {{-- Category Filter --}}
            <form method="GET" action="{{ route('medicine.stock_on_hand') }}" style="display: flex; gap: 8px; align-items: center;">
                @if(request('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                @endif
                @if(request('rhu_filter'))
                    <input type="hidden" name="rhu_filter" value="{{ request('rhu_filter') }}">
                @endif
                @if(request('availability'))
                    <input type="hidden" name="availability" value="{{ request('availability') }}">
                @endif
                <select name="category" id="stock-category-filter" style="
                    height: 40px; padding: 0 12px; font-size: 14px;
                    border: 2px solid #bec6cf; border-radius: 10px; outline: none;
                    background: white; color: #0f172a; appearance: auto;
                    max-width: 160px;
                " onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <option value="medicine"              {{ request('category') == 'medicine'              ? 'selected' : '' }}>Medicine</option>
                    <option value="vaccine"               {{ request('category') == 'vaccine'               ? 'selected' : '' }}>Vaccine</option>
                    <option value="family_planning"       {{ request('category') == 'family_planning'       ? 'selected' : '' }}>Family Planning</option>
                    <option value="family_health_nutrition" {{ request('category') == 'family_health_nutrition' ? 'selected' : '' }}>Maternal &amp; Child</option>
                    <option value="emerging_reemerging"    {{ request('category') == 'emerging_reemerging'    ? 'selected' : '' }}>Disease Prev.</option>
                    <option value="non_communicable"         {{ request('category') == 'non_communicable'         ? 'selected' : '' }}>NCD</option>
                    <option value="dengue_prevention"       {{ request('category') === 'dengue_prevention' ? 'selected' : '' }}>Dengue Prevention</option>
                    <option value="tuberculosis"            {{ request('category') === 'tuberculosis' ? 'selected' : '' }}>Tuberculosis</option>
                </select>
            </form>

            {{-- Availability Filter --}}
            <form method="GET" action="{{ route('medicine.stock_on_hand') }}" style="display: flex; gap: 8px; align-items: center;">
                @if(request('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                @endif
                @if(request('rhu_filter'))
                    <input type="hidden" name="rhu_filter" value="{{ request('rhu_filter') }}">
                @endif
                @if(request('category'))
                    <input type="hidden" name="category" value="{{ request('category') }}">
                @endif
                <select name="availability" style="
                    height: 40px; padding: 0 12px; font-size: 14px;
                    border: 2px solid #bec6cf; border-radius: 10px; outline: none;
                    background: white; color: #0f172a; appearance: auto;
                    max-width: 150px;
                " onchange="this.form.submit()">
                    <option value="">All Stock</option>
                    <option value="available"   {{ request('availability') == 'available'   ? 'selected' : '' }}>✅ Available</option>
                    <option value="unavailable" {{ request('availability') == 'unavailable' ? 'selected' : '' }}>❌ Not Avail.</option>
                </select>
            </form>

            {{-- EXPORT BUTTON — opens inline modal --}}
            <button type="button" onclick="openStockExportModal()" style="
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

    {{-- Search Bar --}}
    <div class="no-print" style="
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(8px);
        border-radius: 16px;
        padding: 16px 20px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    ">
        <form method="GET" action="{{ route('medicine.stock_on_hand') }}" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            @if(request('rhu_filter')) <input type="hidden" name="rhu_filter" value="{{ request('rhu_filter') }}"> @endif
            @if(request('category')) <input type="hidden" name="category" value="{{ request('category') }}"> @endif
            @if(request('availability')) <input type="hidden" name="availability" value="{{ request('availability') }}"> @endif

            <div style="position: relative; flex: 1; min-width: 260px;">
                <span style="
                    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
                    color: #94a3b8; pointer-events: none;
                    display: inline-flex; align-items: center;
                    z-index: 1;
                ">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </span>
                <input type="text" name="search"
                       id="stock-search-input"
                       value="{{ request('search') }}"
                       placeholder="Search item name..."
                       autocomplete="off"
                       style="
                           width: 100%;
                           height: 44px;
                           padding: 0 40px 0 42px;
                           font-size: 15px;
                           border: 2px solid #bec6cf;
                           border-radius: 12px;
                           outline: none;
                           background: white;
                           color: #0f172a;
                           box-sizing: border-box;
                       "
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">

                @if(request('search'))
                    <a href="{{ route('medicine.stock_on_hand', request()->except('search', 'page')) }}"
                       title="Clear search"
                       style="
                           position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
                           width: 24px; height: 24px;
                           display: inline-flex; align-items: center; justify-content: center;
                           border-radius: 6px;
                           background: #f1f5f9; color: #64748b;
                           text-decoration: none; transition: all 0.2s;
                       "
                       onmouseover="this.style.background='#e2e8f0'; this.style.color='#0f172a';"
                       onmouseout="this.style.background='#f1f5f9'; this.style.color='#64748b';">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </a>
                @endif

                <div id="stock-search-dropdown" style="
                    display: none;
                    position: absolute;
                    top: calc(100% + 4px);
                    left: 0;
                    right: 0;
                    background: white;
                    border: 2px solid #42ad8a;
                    border-radius: 12px;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
                    z-index: 100;
                    max-height: 280px;
                    overflow-y: auto;
                "></div>
            </div>

            <button type="submit" style="
                height: 44px;
                padding: 0 22px;
                font-size: 15px;
                font-weight: 600;
                border-radius: 12px;
                background: #42ad8a;
                color: white;
                border: none;
                cursor: pointer;
                transition: background 0.2s;
            " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                Search
            </button>

            @if(request('search') || request('category') || request('availability'))
                <a href="{{ route('medicine.stock_on_hand', request()->only('rhu_filter')) }}" style="
                    height: 44px;
                    padding: 0 22px;
                    font-size: 15px;
                    font-weight: 600;
                    border-radius: 12px;
                    background: #ffffff;
                    color: #334155;
                    border: 1px solid #e2e8f0;
                    text-decoration: none;
                    display: inline-flex;
                    align-items: center;
                    transition: all 0.2s;
                " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'">
                    Reset Filters
                </a>
            @endif
        </form>
    </div>

    {{-- Summary strip --}}
    <div style="
        display:grid;
        grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));
        gap:16px;
    ">
        <div style="background:rgba(255,255,255,0.9); border-radius:16px; padding:18px 22px; border:1px solid rgba(255,255,255,0.4); box-shadow:0 4px 12px rgba(0,0,0,0.04);">
            <p style="font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#64748b; margin:0 0 4px 0;">Total Available</p>
            <p style="font-size:26px; font-weight:700; color:#0f5c4b; margin:0;">{{ number_format($grandAvailable) }}</p>
        </div>
        <div style="background:rgba(255,255,255,0.9); border-radius:16px; padding:18px 22px; border:1px solid rgba(255,255,255,0.4); box-shadow:0 4px 12px rgba(0,0,0,0.04);">
            <p style="font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#64748b; margin:0 0 4px 0;">Total Allocated</p>
            <p style="font-size:26px; font-weight:700; color:#475569; margin:0;">{{ number_format($grandAllocated) }}</p>
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
        <div class="table-scroll" style="
            width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
        ">
            <table style="width:100%; min-width: 850px; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th class="no-print" style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">#</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Item Name</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Category</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Dosage Strength</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Dosage Form</th>
                        <th style="padding: 14px 20px; text-align: right; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Available</th>
                        <th style="padding: 14px 20px; text-align: right; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Allocated</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($items as $item)
                        @php
                            $badges = [
                                'medicine'              => ['label' => 'Medicine',          'bg' => '#DBEAFE', 'color' => '#1E40AF', 'border' => '#93C5FD'],
                                'vaccine'               => ['label' => 'Vaccine',            'bg' => '#DCFCE7', 'color' => '#166534', 'border' => '#86EFAC'],
                                'family_planning'       => ['label' => 'Family Planning',    'bg' => '#F3E8FF', 'color' => '#6B21A8', 'border' => '#D8B4FE'],
                                'family_health_nutrition' => ['label' => 'Maternal & Child',   'bg' => '#FCE7F3', 'color' => '#9D174D', 'border' => '#F9A8D4'],
                                'emerging_reemerging'    => ['label' => 'Disease Prevention', 'bg' => '#FEE2E2', 'color' => '#991B1B', 'border' => '#FCA5A5'],
                                'non_communicable'         => ['label' => 'Non-Communicable Disease',    'bg' => '#FEF3C7', 'color' => '#92400E', 'border' => '#FCD34D'],
                            ];
                            $badge = $badges[$item->category] ?? ['label' => ucfirst($item->category ?? '—'), 'bg' => '#F1F5F9', 'color' => '#475569', 'border' => '#CBD5E1'];

                            $isLow  = $item->available_total > 0 && $item->available_total <= 10;
                            $isZero = $item->available_total == 0;
                        @endphp

                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;"
                            onmouseover="this.style.background='#f8fafc';"
                            onmouseout="this.style.background='transparent';">
                            <td class="no-print" style="padding: 14px 20px; color: #334155;">{{ $loop->iteration + ($items->currentPage() - 1) * $items->perPage() }}</td>
                            <td style="padding: 14px 20px; font-weight: 600; color: #0f172a;">{{ $item->medicine_name }}</td>

                            <td style="padding: 14px 20px;">
                                <span style="display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:600; background:{{ $badge['bg'] }}; color:{{ $badge['color'] }}; border:1px solid {{ $badge['border'] }}; white-space:nowrap;">
                                    {{ $badge['label'] }}
                                </span>
                            </td>

                            <td style="padding: 14px 20px; color: #334155;">{{ $item->dosage ?? '—' }}</td>

                            <td style="padding: 14px 20px; color: #334155;">
                                @if($item->dosage_form)
                                    {{ $item->dosage_form }}
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>

                            <td style="padding: 14px 20px; text-align:right; font-weight: 700;
                                @if($isZero) color:#dc2626;
                                @elseif($isLow) color:#b45309;
                                @else color:#0f5c4b; @endif">
                                {{ number_format($item->available_total) }}<span style="font-weight:500; font-size:13px; color:#64748b;"> (units)</span>
                            </td>
                            <td style="padding: 14px 20px; text-align:right; color: #475569;">
                                {{ number_format($item->allocated_total) }}<span style="font-weight:500; font-size:13px; color:#64748b;"> (units)</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding: 40px 20px; text-align: center; color: #64748b; border-bottom: 1px solid #f1f5f9;">
                                @if(request('availability') === 'available')
                                    No items with available stock.
                                @elseif(request('availability') === 'unavailable')
                                    🎉 All items have stock available!
                                @elseif(request('search'))
                                    No items match "<strong>{{ request('search') }}</strong>".
                                @else
                                    No stock on hand records found.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @if ($items->hasPages())
        <div class="no-print" style="
            background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
            border-radius: 16px; padding: 16px 24px; border: 1px solid rgba(255,255,255,0.4);
            box-shadow: 0 4px 12px rgba(0,0,0,0.04); display: flex; align-items: center;
            justify-content: center; gap: 10px; flex-wrap: wrap;
        ">
            @if ($items->onFirstPage())
                <span style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: default; user-select: none;">← Previous</span>
            @else
                <a href="{{ $items->previousPageUrl() }}" style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" 
                   onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                    ← Previous
                </a>
            @endif

            @foreach ($items->getUrlRange(1, $items->lastPage()) as $page => $url)
                @if ($page == $items->currentPage())
                    <span style="font-size: 14px; font-weight: 600; padding: 8px 14px; border-radius: 8px; background: #42ad8a; color: #ffffff; border: 1px solid #42ad8a; cursor: default;">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" style="font-size: 14px; font-weight: 500; padding: 8px 14px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                       onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" 
                       onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                        {{ $page }}
                    </a>
                @endif
            @endforeach

            @if ($items->hasMorePages())
                <a href="{{ $items->nextPageUrl() }}" style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" 
                   onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                    Next →
                </a>
            @else
                <span style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: default; user-select: none;">Next →</span>
            @endif
        </div>
    @endif

</div>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- INLINE EXPORT MODAL --}}
{{-- Filters: Program Category · Stock Status · Dosage Form --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div id="stockExportModal"
     onclick="if(event.target === this) closeStockExportModal()"
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
                Export Stock on Hand
            </h3>
            <p style="font-size:13px; color:#64748b; margin:0;">
                Choose what to include, then pick an action.
            </p>
        </div>

        {{-- Body --}}
        <div style="padding:20px 26px;">

            {{-- 1. PROGRAM CATEGORY --}}
            <div style="margin-bottom:22px;">
                <label for="category-stockExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Program Category
                </label>
                <select id="category-stockExportModal"
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
                    <option value="medicine"              {{ request('category') == 'medicine'              ? 'selected' : '' }}>Medicine</option>
                    <option value="vaccine"               {{ request('category') == 'vaccine'               ? 'selected' : '' }}>Vaccine</option>
                    <option value="family_planning"       {{ request('category') == 'family_planning'       ? 'selected' : '' }}>Family Planning</option>
                    <option value="family_health_nutrition" {{ request('category') == 'family_health_nutrition' ? 'selected' : '' }}>Family Health &amp; Nutrition</option>
                    <option value="emerging_reemerging"    {{ request('category') == 'emerging_reemerging'    ? 'selected' : '' }}>Emerging &amp; Re-emerging</option>
                    <option value="non_communicable"         {{ request('category') == 'non_communicable'         ? 'selected' : '' }}>Non-Communicable Disease</option>
                    <option value="dengue_prevention"       {{ request('category') === 'dengue_prevention' ? 'selected' : '' }}>Dengue Prevention</option>
                    <option value="tuberculosis"            {{ request('category') === 'tuberculosis' ? 'selected' : '' }}>Tuberculosis</option>
            </div>

            {{-- 2. STOCK STATUS --}}
            <div style="margin-bottom:22px;">
                <label for="availability-stockExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Stock Status
                </label>
                <select id="availability-stockExportModal"
                        style="
                            width:100%; height:44px;
                            padding:0 14px; font-size:14px;
                            border:2px solid #bec6cf; border-radius:10px;
                            outline:none; box-sizing:border-box;
                            background:#ffffff; color:#0f172a; appearance:auto; cursor:pointer;
                        "
                        onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                        onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <option value="" {{ !request('availability') ? 'selected' : '' }}>All Stock</option>
                    <option value="available"   {{ request('availability') === 'available'   ? 'selected' : '' }}>Available only</option>
                    <option value="unavailable" {{ request('availability') === 'unavailable' ? 'selected' : '' }}>Not Available only</option>
                </select>
            </div>

            {{-- 3. DOSAGE FORM --}}
            <div>
                <label for="dosageForm-stockExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Dosage Form
                </label>
                <select id="dosageForm-stockExportModal"
                        style="
                            width:100%; height:44px;
                            padding:0 14px; font-size:14px;
                            border:2px solid #bec6cf; border-radius:10px;
                            outline:none; box-sizing:border-box;
                            background:#ffffff; color:#0f172a; appearance:auto; cursor:pointer;
                        "
                        onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                        onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <option value="" {{ !request('dosage_form') ? 'selected' : '' }}>All Dosage Forms</option>
                    @foreach($dosageFormOptions as $form)
                        <option value="{{ $form }}" {{ request('dosage_form') === $form ? 'selected' : '' }}>
                            {{ $form }}
                        </option>
                    @endforeach
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
                    onclick="closeStockExportModal()"
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
                    onclick="runStockExport('print')"
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
                    onclick="runStockExport('csv')"
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

{{-- Search autocomplete --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input    = document.getElementById('stock-search-input');
    const dropdown = document.getElementById('stock-search-dropdown');
    const category = document.getElementById('stock-category-filter');
    if (!input || !dropdown) return;

    let abortController = null;
    let selectedIndex   = -1;

    input.addEventListener('input', function () {
        const query = this.value.trim();

        if (query.length < 2) {
            closeDropdown();
            return;
        }

        if (abortController) abortController.abort();
        abortController = new AbortController();

        const catValue = category ? category.value : '';
        const url = `/stock-logs/autocomplete?q=${encodeURIComponent(query)}&category=${encodeURIComponent(catValue)}`;

        fetch(url, { signal: abortController.signal })
        .then(res => res.json())
        .then(data => {
            if (data.length === 0) {
                closeDropdown();
                return;
            }
            renderSuggestions(data);
        })
        .catch(err => {
            if (err.name !== 'AbortError') console.error(err);
        });
    });

    function renderSuggestions(items) {
        dropdown.innerHTML = '';
        selectedIndex = -1;

        items.forEach((item, idx) => {
            const div = document.createElement('div');
            div.className = 'stock-search-item';
            div.dataset.text = item.text;
            div.style.cssText = 'padding:10px 16px; cursor:pointer; font-size:14px; color:#0f172a; border-bottom:1px solid #f1f5f9; transition:background 0.15s;';
            div.textContent = item.text;

            div.addEventListener('mouseenter', () => {
                Array.from(dropdown.children).forEach(c => c.style.background = 'white');
                div.style.background = '#E8F7F4';
                selectedIndex = idx;
            });

            div.addEventListener('click', () => {
                input.value = item.text;
                closeDropdown();
                input.closest('form').submit();
            });

            dropdown.appendChild(div);
        });

        dropdown.style.display = 'block';
    }

    function closeDropdown() {
        dropdown.style.display = 'none';
        dropdown.innerHTML = '';
        selectedIndex = -1;
    }

    input.addEventListener('keydown', function (e) {
        const items = dropdown.querySelectorAll('.stock-search-item');
        if (!items.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
            highlight(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, 0);
            highlight(items);
        } else if (e.key === 'Enter') {
            if (selectedIndex >= 0 && items[selectedIndex]) {
                e.preventDefault();
                input.value = items[selectedIndex].dataset.text;
                closeDropdown();
                input.closest('form').submit();
            }
        } else if (e.key === 'Escape') {
            closeDropdown();
        }
    });

    function highlight(items) {
        items.forEach((item, idx) => {
            if (idx === selectedIndex) {
                item.style.background = '#E8F7F4';
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.style.background = 'white';
            }
        });
    }

    document.addEventListener('click', function (e) {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            closeDropdown();
        }
    });
});

// ─── Export modal logic ───────────────────────────────────
function openStockExportModal() {
    document.getElementById('stockExportModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeStockExportModal() {
    document.getElementById('stockExportModal').style.display = 'none';
    document.body.style.overflow = '';
}
function runStockExport(mode) {
    const categorySelect     = document.getElementById('category-stockExportModal');
    const availabilitySelect = document.getElementById('availability-stockExportModal');
    const dosageFormSelect   = document.getElementById('dosageForm-stockExportModal');

    const categoryVal     = categorySelect     ? categorySelect.value     : '';
    const availabilityVal = availabilitySelect ? availabilitySelect.value : '';
    const dosageFormVal   = dosageFormSelect   ? dosageFormSelect.value   : '';

    const baseUrl = mode === 'csv'
        ? '{{ route('medicine.stock_on_hand.csv') }}'
        : '{{ route('medicine.stock_on_hand') }}';

    const url = new URL(baseUrl, window.location.origin);
    const params = new URLSearchParams(window.location.search);
    params.delete('page');
    params.delete('print');
    params.delete('category');
    params.delete('availability');
    params.delete('dosage_form');

    if (categoryVal)     params.set('category', categoryVal);
    if (availabilityVal) params.set('availability', availabilityVal);
    if (dosageFormVal)   params.set('dosage_form', dosageFormVal);
    if (mode === 'print') params.set('print', '1');

    url.search = params.toString();
    window.location.href = url.toString();
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('stockExportModal');
        if (modal && modal.style.display === 'flex') {
            closeStockExportModal();
        }
    }
});
</script>
@endsection