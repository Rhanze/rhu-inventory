@extends('layouts.app')

@section('content')
@php
    // Unique dosage forms for the export filter
    $dosageFormOptions = \App\Models\Medicine::query()
        ->whereNotNull('dosage_form')
        ->where('dosage_form', '!=', '')
        ->distinct()
        ->orderBy('dosage_form')
        ->pluck('dosage_form');
@endphp

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
                <h2 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0; line-height: 1.2;">Item Masterlist</h2>
            </div>

            {{-- TAB BAR --}}
            <div style="background: #f1f5f9; border-radius: 12px; padding: 4px; display: inline-flex; gap: 4px;">
                <a href="{{ route('medicine.index', request()->only('rhu_filter')) }}" style="
                    display: inline-flex; align-items: center; gap: 8px;
                    padding: 8px 18px; border-radius: 9px;
                    font-size: 14px; font-weight: 600; text-decoration: none;
                    background: #42ad8a; color: #ffffff;
                    box-shadow: 0 2px 6px rgba(66,173,138,0.25);
                    white-space: nowrap;
                ">
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
                    background: transparent; color: #475569;
                    transition: all 0.2s;
                    white-space: nowrap;
                " onmouseover="this.style.background='rgba(255,255,255,0.7)'; this.style.color='#0f5c4b';"
                   onmouseout="this.style.background='transparent'; this.style.color='#475569';">
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
                <form method="GET" action="{{ route('medicine.index') }}" style="display: flex; gap: 8px; align-items: center;">
                    @if(request('search'))
                        <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    @if(request('category'))
                        <input type="hidden" name="category" value="{{ request('category') }}">
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
            <form method="GET" action="{{ route('medicine.index') }}" style="display: flex; gap: 8px; align-items: center;">
                @if(request('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                @endif
                @if(request('rhu_filter'))
                    <input type="hidden" name="rhu_filter" value="{{ request('rhu_filter') }}">
                @endif
                <select name="category" style="
                    height: 40px; padding: 0 12px; font-size: 14px;
                    border: 2px solid #bec6cf; border-radius: 10px; outline: none;
                    background: white; color: #0f172a; appearance: auto;
                    max-width: 210px;
                " onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <option value="medicine"                 {{ request('category') == 'medicine'                 ? 'selected' : '' }}>Medicine</option>
                    <option value="vaccine"                  {{ request('category') == 'vaccine'                  ? 'selected' : '' }}>Vaccine</option>
                    <option value="family_planning"          {{ request('category') == 'family_planning'          ? 'selected' : '' }}>Family Planning</option>
                    <option value="family_health_nutrition"  {{ request('category') == 'family_health_nutrition'  ? 'selected' : '' }}>Family Health &amp; Nutrition</option>
                    <option value="emerging_reemerging"      {{ request('category') == 'emerging_reemerging'      ? 'selected' : '' }}>Emerging &amp; Re-emerging</option>
                    <option value="non_communicable"         {{ request('category') == 'non_communicable'         ? 'selected' : '' }}>Non-Communicable Disease</option>
                    <option value="dengue_prevention"        {{ request('category') === 'dengue_prevention'       ? 'selected' : '' }}>Dengue Prevention</option>
                    <option value="tuberculosis"             {{ request('category') === 'tuberculosis'            ? 'selected' : '' }}>Tuberculosis</option>
                </select>
            </form>

            {{-- Export button — opens inline modal --}}
            <button type="button" onclick="openMedicineExportModal()" style="
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

            {{-- Add Item — PHO Admin only --}}
            @if(session('role') === 'admin' && is_null(session('health_unit_id')))
                <a href="{{ route('medicine.create') }}" style="
                    display: inline-flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 600;
                    padding: 9px 16px; border-radius: 10px; background: #42ad8a; color: #ffffff;
                    text-decoration: none; border: none; cursor: pointer; transition: all 0.2s;
                    white-space: nowrap;
                " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Item
                </a>
            @endif
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
            <table style="width:100%; min-width: 800px; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th class="no-print" style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">#</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Item Name</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Dosage Strength</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Dosage Form</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Category</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Prescription</th>

                        @if(session('role') === 'admin' && is_null(session('health_unit_id')))
                            <th class="no-print" style="padding: 14px 20px; text-align: center; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Actions</th>
                        @endif
                    </tr>
                </thead>

                <tbody>
                    @forelse($medicines as $medicine)
                        @php
                            $isHighlighted = (string) request('highlight') === (string) $medicine->id;
                            $rowBg = $isHighlighted ? '#f1f5f9' : 'transparent';

                            $badges = [
                                'medicine'                => ['label' => 'Medicine',                  'bg' => '#DBEAFE', 'color' => '#1E40AF', 'border' => '#93C5FD'],
                                'vaccine'                 => ['label' => 'Vaccine',                   'bg' => '#DCFCE7', 'color' => '#166534', 'border' => '#86EFAC'],
                                'family_planning'         => ['label' => 'Family Planning',           'bg' => '#F3E8FF', 'color' => '#6B21A8', 'border' => '#D8B4FE'],
                                'family_health_nutrition' => ['label' => 'Family Health & Nutrition', 'bg' => '#FCE7F3', 'color' => '#9D174D', 'border' => '#F9A8D4'],
                                'emerging_reemerging'     => ['label' => 'Emerging & Re-emerging',    'bg' => '#FEE2E2', 'color' => '#991B1B', 'border' => '#FCA5A5'],
                                'non_communicable'        => ['label' => 'Non-Communicable Disease',  'bg' => '#FEF3C7', 'color' => '#92400E', 'border' => '#FCD34D'],
                                'dengue_prevention'       => ['label' => 'Dengue Prevention',         'bg' => '#FFE4E6', 'color' => '#9F1239', 'border' => '#FDA4AF'],
                                'tuberculosis'            => ['label' => 'Tuberculosis',              'bg' => '#E0E7FF', 'color' => '#3730A3', 'border' => '#A5B4FC'],
                            ];
                            $badge = $badges[$medicine->category] ?? ['label' => ucfirst(str_replace('_', ' ', $medicine->category ?? '—')), 'bg' => '#F1F5F9', 'color' => '#475569', 'border' => '#CBD5E1'];
                        @endphp

                        <tr id="medicine-{{ $medicine->id }}" style="background: {{ $rowBg }}; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; scroll-margin-top: 80px;"
                            onmouseover="if('{{ $rowBg }}'=='transparent') this.style.background='#f8fafc';"
                            onmouseout="if('{{ $rowBg }}'=='transparent') this.style.background='transparent';">
                            <td class="no-print" style="padding: 14px 20px; color: #334155;">{{ $loop->iteration + ($medicines->currentPage() - 1) * $medicines->perPage() }}</td>
                            <td style="padding: 14px 20px; font-weight: 600; color: #0f172a;">{{ $medicine->medicine_name }}</td>

                            <td style="padding: 14px 20px; color: #334155;">
                                {{ $medicine->dosage ?? '—' }}
                            </td>

                            <td style="padding: 14px 20px; color: #334155;">
                                @if($medicine->dosage_form)
                                    {{ $medicine->dosage_form }}
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>

                            <td style="padding: 14px 20px;">
                                <span style="display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:600; background:{{ $badge['bg'] }}; color:{{ $badge['color'] }}; border:1px solid {{ $badge['border'] }}; white-space:nowrap;">
                                    {{ $badge['label'] }}
                                </span>
                            </td>

                            <td style="padding: 14px 20px; color: #334155;">
                                {{ $medicine->requires_prescription ? 'Required' : 'Not Required' }}
                            </td>

                            @if(session('role') === 'admin' && is_null(session('health_unit_id')))
                                <td class="no-print" style="padding: 14px 20px;">
                                    <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                                        <a href="{{ route('medicine.edit', $medicine->id) }}" title="Edit"
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

                                        <form action="{{ route('medicine.destroy', $medicine->id) }}" method="POST"
                                              onsubmit="return confirmDialog(event, 'Are you sure you want to delete this item? This cannot be undone.');"
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
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="@php
                                $cols = 6;
                                if (session('role') === 'admin' && is_null(session('health_unit_id'))) $cols += 1;
                                echo $cols;
                            @endphp" style="padding: 40px 20px; text-align: center; color: #64748b; border-bottom: 1px solid #f1f5f9;">
                                No items found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @if ($medicines->hasPages())
        <div class="no-print" style="
            background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
            border-radius: 16px; padding: 16px 24px; border: 1px solid rgba(255,255,255,0.4);
            box-shadow: 0 4px 12px rgba(0,0,0,0.04); display: flex; align-items: center;
            justify-content: center; gap: 10px; flex-wrap: wrap;
        ">
            @if ($medicines->onFirstPage())
                <span style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: default; user-select: none;">← Previous</span>
            @else
                <a href="{{ $medicines->previousPageUrl() }}" style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';"
                   onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                    ← Previous
                </a>
            @endif

            @foreach ($medicines->getUrlRange(1, $medicines->lastPage()) as $page => $url)
                @if ($page == $medicines->currentPage())
                    <span style="font-size: 14px; font-weight: 600; padding: 8px 14px; border-radius: 8px; background: #42ad8a; color: #ffffff; border: 1px solid #42ad8a; cursor: default;">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" style="font-size: 14px; font-weight: 500; padding: 8px 14px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                       onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';"
                       onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                        {{ $page }}
                    </a>
                @endif
            @endforeach

            @if ($medicines->hasMorePages())
                <a href="{{ $medicines->nextPageUrl() }}" style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
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


<div id="medicineExportModal"
     onclick="if(event.target === this) closeMedicineExportModal()"
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
                Export Item Masterlist
            </h3>
            <p style="font-size:13px; color:#64748b; margin:0;">
                Choose what to include, then pick an action.
            </p>
        </div>

        <div style="padding:20px 26px;">

            {{-- 1. PROGRAM CATEGORY --}}
            <div style="margin-bottom:22px;">
                <label for="category-medicineExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Program Category
                </label>
                <select id="category-medicineExportModal"
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

            {{-- 2. PRESCRIPTION TYPE --}}
            <div style="margin-bottom:22px;">
                <label for="prescription-medicineExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Prescription Type
                </label>
                <select id="prescription-medicineExportModal"
                        style="
                            width:100%; height:44px;
                            padding:0 14px; font-size:14px;
                            border:2px solid #bec6cf; border-radius:10px;
                            outline:none; box-sizing:border-box;
                            background:#ffffff; color:#0f172a; appearance:auto; cursor:pointer;
                        "
                        onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                        onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <option value="" {{ !request('prescription') ? 'selected' : '' }}>All Prescription Types</option>
                    <option value="required"     {{ request('prescription') === 'required'     ? 'selected' : '' }}>Prescription Required</option>
                    <option value="not_required" {{ request('prescription') === 'not_required' ? 'selected' : '' }}>No Prescription Needed</option>
                </select>
            </div>

            {{-- 3. DOSAGE FORM --}}
            <div style="margin-bottom:22px;">
                <label for="dosageForm-medicineExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Dosage Form
                </label>
                <select id="dosageForm-medicineExportModal"
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

        <div style="
            padding:16px 26px 20px;
            border-top:1px solid #f1f5f9;
            display:flex; gap:10px; justify-content:flex-end;
            background:#fafbfc;
            border-radius: 0 0 20px 20px;
        ">
            <button type="button"
                    onclick="closeMedicineExportModal()"
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
                    onclick="runMedicineExport('print')"
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
                    onclick="runMedicineExport('csv')"
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
    function openMedicineExportModal() {
        document.getElementById('medicineExportModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function closeMedicineExportModal() {
        document.getElementById('medicineExportModal').style.display = 'none';
        document.body.style.overflow = '';
    }
    function runMedicineExport(mode) {
        const categorySelect     = document.getElementById('category-medicineExportModal');
        const prescriptionSelect = document.getElementById('prescription-medicineExportModal');
        const dosageFormSelect   = document.getElementById('dosageForm-medicineExportModal');

        const categoryVal     = categorySelect     ? categorySelect.value     : '';
        const prescriptionVal = prescriptionSelect ? prescriptionSelect.value : '';
        const dosageFormVal   = dosageFormSelect   ? dosageFormSelect.value   : '';

        const baseUrl = mode === 'csv'
            ? '{{ route('medicine.export') }}'
            : '{{ route('medicine.index') }}';

        const url = new URL(baseUrl, window.location.origin);
        const params = new URLSearchParams(window.location.search);
        params.delete('page');
        params.delete('print');
        params.delete('category');
        params.delete('prescription');
        params.delete('dosage_form');

        if (categoryVal)     params.set('category', categoryVal);
        if (prescriptionVal) params.set('prescription', prescriptionVal);
        if (dosageFormVal)   params.set('dosage_form', dosageFormVal);
        if (mode === 'print') params.set('print', '1');

        url.search = params.toString();
        window.location.href = url.toString();
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('medicineExportModal');
            if (modal && modal.style.display === 'flex') {
                closeMedicineExportModal();
            }
        }
    });
</script>
@endsection