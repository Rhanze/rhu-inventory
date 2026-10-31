@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

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
                @if(session('role') === 'midwife')
                    <p style="font-size: 13px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #42ad8a; margin: 0 0 6px 0;">Clinical</p>
                    <h2 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0;">Dispensing Records</h2>
                @else
                    <p style="font-size: 13px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #42ad8a; margin: 0 0 6px 0;">Allocation &amp; Dispensing</p>
                    <h2 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0;">Dispensing Records</h2>
                @endif
            </div>

            {{-- Tab bar — hidden for midwives --}}
            @if(session('role') !== 'midwife')
                <div style="background: #f1f5f9; border-radius: 12px; padding: 4px; display: inline-flex; gap: 4px;">
                    <a href="{{ route('allocation.index', request()->only('rhu_filter')) }}" style="
                        display: inline-flex; align-items: center; gap: 8px;
                        padding: 8px 18px; border-radius: 9px;
                        font-size: 14px; font-weight: 600; text-decoration: none;
                        background: transparent; color: #475569;
                        transition: all 0.2s;
                    " onmouseover="this.style.background='rgba(255,255,255,0.7)'; this.style.color='#0f5c4b';"
                       onmouseout="this.style.background='transparent'; this.style.color='#475569';">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                        Allocation
                    </a>

                    <a href="{{ route('dispensing.index', request()->only('rhu_filter')) }}" style="
                        display: inline-flex; align-items: center; gap: 8px;
                        padding: 8px 18px; border-radius: 9px;
                        font-size: 14px; font-weight: 600; text-decoration: none;
                        background: #42ad8a; color: #ffffff;
                        box-shadow: 0 2px 6px rgba(66,173,138,0.25);
                    ">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 11.5 5c-.57.55-1.08 1.15-1.5 1.79"/>
                            <path d="M5 10c-1.5 1.46-3 3.21-3 5.5a5.5 5.5 0 0 0 10.95 1"/>
                        </svg>
                        Dispensing
                    </a>
                </div>
            @endif
        </div>

        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            @if(is_null(session('health_unit_id')))
                <form method="GET" action="{{ route('dispensing.index') }}" style="display: flex; gap: 8px; align-items: center;">
                    @if(request('search'))
                        <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    <select name="rhu_filter" style="
                        height: 40px; padding: 0 12px; font-size: 14px;
                        border: 2px solid #bec6cf; border-radius: 10px; outline: none;
                        background: white; color: #0f172a; appearance: auto;
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

            @include('reports._export_modal', [
                'modalTitle'  => 'Export Dispensing Records',
                'showExpiry'  => true,
                'csvRoute'    => 'dispensing.export',
                'printAction' => route('dispensing.index'),
            ])

            @if(session('health_unit_id'))
                <a href="{{ route('dispensing.create') }}" style="
                    display: inline-flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 600;
                    padding: 9px 16px; border-radius: 10px; background: #42ad8a; color: #ffffff;
                    text-decoration: none; border: none; cursor: pointer; transition: all 0.2s;
                " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Dispense Items
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
        <div style="width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch;">
            <table style="width:100%; min-width: 1150px; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th class="no-print" style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">#</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Date</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Batch #</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Medicine</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Prescription</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Qty</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Doctor</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Dispensed By</th>
                        @if(is_null(session('health_unit_id')))
                            <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">RHU</th>
                        @endif
                        <th class="no-print" style="padding: 14px 20px; text-align: center; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($records as $record)
                        @php
                            $isHighlighted = (string) request('highlight') === (string) $record->id;
                            $rowBg = $isHighlighted ? '#f1f5f9' : 'transparent';

                            $med  = $record->medicine;
                            $unit = $med->unit ?? null;

                            $pluralUnit = null;
                            if ($unit) {
                                if ((int) $record->quantity_dispensed === 1) {
                                    $pluralUnit = $unit;
                                } else {
                                    $pluralUnit = (substr($unit, -1) === 's') ? $unit : $unit . 's';
                                }
                            }
                            $unitLabel = $pluralUnit ? ' (' . $pluralUnit . ')' : ' (units)';

                            $hasPrescriptionImage = !empty($record->prescription_image);

                            $formattedDate = $record->date_dispensed
                                ? \Carbon\Carbon::parse($record->date_dispensed)->format('M d, Y')
                                : '—';

                            // Only the midwife who created the record (or admin/staff)
                            // may edit or delete it.
                            $canManageThis = session('role') !== 'midwife'
                                          || ((int) $record->dispensed_by === (int) session('user_id'));
                        @endphp
                        <tr id="dispensing-{{ $record->id }}" style="background: {{ $rowBg }}; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; scroll-margin-top: 80px;"
                            onmouseover="if('{{ $rowBg }}'=='transparent') this.style.background='#f8fafc';"
                            onmouseout="if('{{ $rowBg }}'=='transparent') this.style.background='transparent';">
                            <td class="no-print" style="padding: 14px 20px; color: #334155;">{{ $loop->iteration + ($records->currentPage() - 1) * $records->perPage() }}</td>
                            <td style="padding: 14px 20px; color: #334155; white-space:nowrap;">{{ $formattedDate }}</td>

                            <td style="padding: 14px 20px; color: #334155;">
                                @if($record->batch_no)
                                    <span style="font-family:monospace; font-size:13px; background:#f1f5f9; padding:2px 8px; border-radius:6px;">
                                        {{ $record->batch_no }}
                                    </span>
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>

                            <td style="padding: 14px 20px; color: #334155; font-weight: 600;">{{ $record->medicine->medicine_name ?? 'N/A' }}</td>
                            <td style="padding: 14px 20px;">
                                @if($med && $med->requires_prescription)
                                    <span style="display:inline-block;font-size:11px;font-weight:600;padding:3px 10px;border-radius:999px;background:#FAEEDA;color:#854F0B;border:1px solid #FAC775;">Required</span>
                                @else
                                    <span style="display:inline-block;font-size:11px;font-weight:600;padding:3px 10px;border-radius:999px;background:#EAF3DE;color:#3B6D11;border:1px solid #C0DD97;">Not Required</span>
                                @endif
                                @if($hasPrescriptionImage)
                                    <span title="Prescription image attached" style="margin-left:6px; font-size:14px;">📎</span>
                                @endif
                            </td>
                            <td style="padding: 14px 20px; color: #334155; font-weight: 600;">
                                {{ $record->quantity_dispensed }}<span style="font-weight: 500; color: #64748b;">{{ $unitLabel }}</span>
                            </td>
                            <td style="padding: 14px 20px; color: #334155;">{{ $record->doctor_name ?? 'N/A' }}</td>
                            <td style="padding: 14px 20px; color: #334155;">{{ $record->dispensedByUser->name ?? 'N/A' }}</td>
                            @if(is_null(session('health_unit_id')))
                                <td style="padding: 14px 20px; color: #334155;">{{ $record->healthUnit->name ?? 'N/A' }}</td>
                            @endif
                            <td class="no-print" style="padding: 14px 20px;">
                                <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">

                                    {{-- VIEW BUTTON — always visible (RHU-wide read access) --}}
                                    <button type="button" title="View details"
                                            class="view-dispensing-btn"
                                            data-med-name="{{ $med->medicine_name ?? '' }}"
                                            data-dosage="{{ $med->dosage ?? '' }}"
                                            data-dosage-form="{{ $med->dosage_form ?? '' }}"
                                            data-category="{{ $med->category_label ?? '' }}"
                                            data-unit="{{ $med->unit ?? '' }}"
                                            data-pack-type="{{ $med->pack_type ?? '' }}"
                                            data-pieces-per-pack="{{ $med->pieces_per_pack ?? '' }}"
                                            data-description="{{ $med->description ?? '' }}"
                                            data-date="{{ $formattedDate }}"
                                            data-quantity="{{ $record->quantity_dispensed }}"
                                            data-batch-no="{{ $record->batch_no ?? '' }}"
                                            data-prescription="{{ $med && $med->requires_prescription ? 'Required' : 'Not Required' }}"
                                            data-doctor="{{ $record->doctor_name ?? '' }}"
                                            data-dispensed-by="{{ $record->dispensedByUser->name ?? '' }}"
                                            data-rhu="{{ $record->healthUnit->name ?? '' }}"
                                            data-image-url="{{ $hasPrescriptionImage ? route('dispensing.prescription', $record->id) : '' }}"
                                            onclick="openDispensingModal(this)"
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

                                    {{-- EDIT + DELETE — only for the midwife who created the record,
                                         or for admin/staff --}}
                                    @if($canManageThis)
                                        <a href="{{ route('dispensing.edit', $record->id) }}" title="Edit"
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

                                        <form action="{{ route('dispensing.destroy', $record->id) }}" method="POST"
                                              onsubmit="return confirmDialog(event, 'Are you sure you want to delete this dispensing record?');"
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
                                $cols = 9;
                                if (is_null(session('health_unit_id'))) $cols += 1;
                                echo $cols;
                            @endphp" style="padding: 40px 20px; text-align: center; color: #64748b; border-bottom: 1px solid #f1f5f9;">
                                No dispensing records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @if ($records->hasPages())
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
            @if ($records->onFirstPage())
                <span style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: default; user-select: none;">← Previous</span>
            @else
                <a href="{{ $records->previousPageUrl() }}" style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                    ← Previous
                </a>
            @endif

            @foreach ($records->getUrlRange(1, $records->lastPage()) as $page => $url)
                @if ($page == $records->currentPage())
                    <span style="font-size: 14px; font-weight: 600; padding: 8px 14px; border-radius: 8px; background: #42ad8a; color: #ffffff; border: 1px solid #42ad8a; cursor: default;">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" style="font-size: 14px; font-weight: 500; padding: 8px 14px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                       onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                        {{ $page }}
                    </a>
                @endif
            @endforeach

            @if ($records->hasMorePages())
                <a href="{{ $records->nextPageUrl() }}" style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
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
{{-- DISPENSING INFO MODAL --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div id="dispensing-info-modal" onclick="if(event.target === this) closeDispensingModal()">
    <div class="modal-card">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px; gap:12px;">
            <div>
                <p class="modal-section-label">Dispensing Details</p>
                <h3 id="dm-med-name-title" class="modal-title">—</h3>
            </div>
            <button type="button" onclick="closeDispensingModal()" aria-label="Close"
                    style="background:transparent; border:none; cursor:pointer; padding:4px 10px; color:#64748b; font-size:26px; line-height:1; border-radius:8px; transition: all 0.2s;"
                    onmouseover="this.style.background='#f1f5f9'; this.style.color='#0f172a';"
                    onmouseout="this.style.background='transparent'; this.style.color='#64748b';">✕</button>
        </div>

        <p class="modal-subsection-label">Transaction</p>
        <div class="field-grid">
            <div>
                <label>Date Dispensed</label>
                <span class="value" id="dm-date">—</span>
            </div>
            <div>
                <label>Dispensed By</label>
                <span class="value" id="dm-dispensed-by">—</span>
            </div>
            <div>
                <label>Doctor</label>
                <span class="value" id="dm-doctor">—</span>
            </div>
            <div>
                <label>RHU</label>
                <span class="value" id="dm-rhu">—</span>
            </div>
            <div>
                <label>Prescription</label>
                <span class="value" id="dm-prescription">—</span>
            </div>
        </div>

        <div style="margin:26px 0 20px; border-top:1px solid #e2e8f0;"></div>

        <p class="modal-subsection-label">Medicine</p>
        <div class="field-grid">
            <div class="full-width">
                <label>Medicine</label>
                <span class="value" id="dm-med-name">—</span>
            </div>
            <div>
                <label>Dosage Strength</label>
                <span class="value" id="dm-dosage">—</span>
            </div>
            <div>
                <label>Dosage Form</label>
                <span class="value" id="dm-dosage-form">—</span>
            </div>
            <div>
                <label>Program Category</label>
                <span class="value" id="dm-category">—</span>
            </div>
            <div>
                <label>Base Unit</label>
                <span class="value" id="dm-unit">—</span>
            </div>
            <div>
                <label>Pack Type</label>
                <span class="value" id="dm-pack-type">—</span>
            </div>
            <div>
                <label>Units per Pack</label>
                <span class="value" id="dm-pieces-per-pack">—</span>
            </div>
            <div>
                <label>Batch No.</label>
                <span class="value" id="dm-batch-no" style="font-family:monospace;">—</span>
            </div>
            <div>
                <label>Quantity</label>
                <span class="value" id="dm-quantity">—</span>
            </div>
            <div class="full-width">
                <label>Description</label>
                <span class="value" id="dm-description">—</span>
            </div>
        </div>

        {{-- Prescription image section --}}
        <div id="dm-image-section" style="margin-top:24px;">
            <div style="margin:0 0 16px; border-top:1px solid #e2e8f0;"></div>
            <p class="modal-subsection-label">Prescription Attachment</p>
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; text-align:center;">

                <img id="dm-image-preview" src="" alt="Prescription"
                     onclick="openFullscreenImage(this.src)"
                     style="max-width:100%; max-height:400px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); display:none; cursor:zoom-in;">

                <div id="dm-image-pdf" style="display:none;">
                    <a id="dm-image-pdf-link" href="#" target="_blank"
                       style="display:inline-flex; align-items:center; gap:8px; padding:12px 20px; background:#42ad8a; color:white; border-radius:10px; text-decoration:none; font-weight:600;">
                        📄 Open Prescription PDF
                    </a>
                </div>

                <div id="dm-image-loading" style="display:none; color:#64748b; padding:20px;">Loading…</div>

                <div id="dm-image-empty" style="display:none; padding:20px; color:#94a3b8;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                         stroke-linecap="round" stroke-linejoin="round"
                         style="display:block; margin:0 auto 10px;">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21 15 16 10 5 21"/>
                    </svg>
                    <p style="margin:0; font-size:14px;">No prescription attached</p>
                </div>
            </div>

            <p id="dm-image-hint" style="display:none; margin:8px 0 0 0; font-size:12px; color:#64748b; text-align:center;">
                Click the image to view it full size
            </p>
        </div>

        <div style="display:flex; justify-content:flex-end; margin-top:28px;">
            <button type="button" onclick="closeDispensingModal()"
                    style="padding:12px 26px; background:#42ad8a; color:white; border:none; border-radius:10px; font-weight:700; font-size:16px; cursor:pointer; transition: background 0.2s;"
                    onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                Close
            </button>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- FULLSCREEN IMAGE VIEWER --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div id="fullscreen-viewer" onclick="closeFullscreenImage()"
     style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:100000;
            align-items:center; justify-content:center; padding:40px; cursor:zoom-out;">
    <img id="fullscreen-image" src="" alt="Prescription full view"
         style="max-width:95%; max-height:95%; border-radius:8px; box-shadow:0 20px 60px rgba(0,0,0,0.5);">
    <button type="button" onclick="event.stopPropagation(); closeFullscreenImage()"
            style="position:absolute; top:20px; right:24px; background:rgba(255,255,255,0.15); color:white;
                   border:1px solid rgba(255,255,255,0.3); border-radius:10px; padding:8px 16px;
                   font-size:14px; font-weight:600; cursor:pointer; backdrop-filter:blur(8px);">
        Close ✕
    </button>
</div>

<style>
    #dispensing-info-modal {
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
    #dispensing-info-modal .modal-card {
        background: #ffffff;
        border-radius: 22px;
        padding: 32px 36px;
        max-width: 640px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    }
    #dispensing-info-modal .modal-section-label {
        font-size: 14px;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: #42ad8a;
        margin: 0 0 8px 0;
    }
    #dispensing-info-modal .modal-subsection-label {
        font-size: 14px;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: #64748b;
        margin: 0 0 14px 0;
    }
    #dispensing-info-modal .modal-title {
        margin: 0;
        font-size: 28px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.3;
    }
    #dispensing-info-modal .field-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px 24px;
    }
    #dispensing-info-modal .field-grid > div {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }
    #dispensing-info-modal .field-grid > div.full-width {
        grid-column: 1 / -1;
    }
    #dispensing-info-modal .field-grid label {
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #64748b;
    }
    #dispensing-info-modal .field-grid .value {
        font-size: 18px;
        font-weight: 600;
        color: #0f172a;
        line-height: 1.45;
    }
    #dispensing-info-modal .field-grid .value.empty {
        color: #94a3b8;
        font-weight: 400;
    }
</style>

<script>
function dmSetField(id, value, suffix) {
    const el = document.getElementById(id);
    if (!el) return;
    if (value === null || value === undefined || value === '' || value === 'null') {
        el.textContent = '—';
        el.classList.add('empty');
    } else {
        el.textContent = suffix ? (value + suffix) : value;
        el.classList.remove('empty');
    }
}

function dmPluralizeUnit(unit, qty) {
    if (!unit) return 'units';
    if (parseInt(qty, 10) === 1) return unit;
    return (unit.slice(-1) === 's') ? unit : (unit + 's');
}

function openDispensingModal(btn) {
    const d = btn.dataset;

    dmSetField('dm-med-name-title', d.medName);

    dmSetField('dm-date',          d.date);
    dmSetField('dm-dispensed-by',  d.dispensedBy);
    dmSetField('dm-doctor',        d.doctor);
    dmSetField('dm-rhu',           d.rhu);
    dmSetField('dm-prescription',  d.prescription);

    dmSetField('dm-med-name',      d.medName);
    dmSetField('dm-dosage',        d.dosage);
    dmSetField('dm-dosage-form',   d.dosageForm);
    dmSetField('dm-category',      d.category);
    dmSetField('dm-unit',          d.unit);
    dmSetField('dm-pack-type',     d.packType);
    dmSetField('dm-description',   d.description);

    if (d.piecesPerPack && d.packType) {
        dmSetField('dm-pieces-per-pack', d.piecesPerPack + ' per ' + d.packType);
    } else if (d.piecesPerPack) {
        dmSetField('dm-pieces-per-pack', d.piecesPerPack);
    } else {
        dmSetField('dm-pieces-per-pack', null);
    }

    dmSetField('dm-batch-no', d.batchNo);

    const unitWord = dmPluralizeUnit(d.unit, d.quantity);
    dmSetField('dm-quantity', d.quantity, ' ' + unitWord);

    const imgPreview = document.getElementById('dm-image-preview');
    const pdfBox     = document.getElementById('dm-image-pdf');
    const pdfLink    = document.getElementById('dm-image-pdf-link');
    const loading    = document.getElementById('dm-image-loading');
    const empty      = document.getElementById('dm-image-empty');
    const hint       = document.getElementById('dm-image-hint');

    imgPreview.style.display = 'none';
    imgPreview.src = '';
    pdfBox.style.display = 'none';
    pdfLink.href = '#';
    loading.style.display = 'none';
    empty.style.display = 'none';
    hint.style.display = 'none';

    if (d.imageUrl && d.imageUrl.trim() !== '') {
        loading.style.display = 'block';

        const probe = new Image();
        probe.onload = () => {
            imgPreview.src = d.imageUrl;
            imgPreview.style.display = 'block';
            loading.style.display = 'none';
            hint.style.display = 'block';
        };
        probe.onerror = () => {
            pdfLink.href = d.imageUrl;
            pdfBox.style.display = 'block';
            loading.style.display = 'none';
        };
        probe.src = d.imageUrl;
    } else {
        empty.style.display = 'block';
    }

    document.getElementById('dispensing-info-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeDispensingModal() {
    document.getElementById('dispensing-info-modal').style.display = 'none';
    document.body.style.overflow = '';

    const imgPreview = document.getElementById('dm-image-preview');
    imgPreview.src = '';
    imgPreview.style.display = 'none';
    document.getElementById('dm-image-pdf').style.display = 'none';
    document.getElementById('dm-image-loading').style.display = 'none';
    document.getElementById('dm-image-empty').style.display = 'none';
    document.getElementById('dm-image-hint').style.display = 'none';
}

function openFullscreenImage(src) {
    if (!src) return;
    document.getElementById('fullscreen-image').src = src;
    document.getElementById('fullscreen-viewer').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeFullscreenImage() {
    document.getElementById('fullscreen-viewer').style.display = 'none';
    document.getElementById('fullscreen-image').src = '';
    document.body.style.overflow = 'hidden';
}

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;

    const viewer = document.getElementById('fullscreen-viewer');
    if (viewer && viewer.style.display === 'flex') {
        closeFullscreenImage();
        return;
    }

    const modal = document.getElementById('dispensing-info-modal');
    if (modal && modal.style.display === 'flex') {
        closeDispensingModal();
    }
});
</script>
@endsection