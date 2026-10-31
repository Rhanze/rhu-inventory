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
                <p style="font-size: 13px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #42ad8a; margin: 0 0 6px 0;">Allocation &amp; Dispensing</p>
                <h2 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0;">Manage Allocations</h2>
            </div>

            @if(session('health_unit_id'))
                <div style="
                    background: #f1f5f9;
                    border-radius: 12px;
                    padding: 4px;
                    display: inline-flex;
                    gap: 4px;
                ">
                    <a href="{{ route('allocation.index', request()->only('rhu_filter')) }}" style="
                        display: inline-flex; align-items: center; gap: 8px;
                        padding: 8px 18px; border-radius: 9px;
                        font-size: 14px; font-weight: 600; text-decoration: none;
                        background: #42ad8a; color: #ffffff;
                        box-shadow: 0 2px 6px rgba(66,173,138,0.25);
                    ">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                        Allocation
                    </a>

                    <a href="{{ route('dispensing.index', request()->only('rhu_filter')) }}" style="
                        display: inline-flex; align-items: center; gap: 8px;
                        padding: 8px 18px; border-radius: 9px;
                        font-size: 14px; font-weight: 600; text-decoration: none;
                        background: transparent; color: #475569;
                        transition: all 0.2s;
                    " onmouseover="this.style.background='rgba(255,255,255,0.7)'; this.style.color='#0f5c4b';"
                       onmouseout="this.style.background='transparent'; this.style.color='#475569';">
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
                <form method="GET" action="{{ route('allocation.index') }}" style="display: flex; gap: 8px; align-items: center;">
                    @if(request('search'))
                        <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    <select name="rhu_filter" style="
                        height: 40px; padding: 0 12px; font-size: 14px;
                        border: 2px solid #bec6cf; border-radius: 10px; outline: none;
                        background: white; color: #0f172a; appearance: auto;
                    " onchange="this.form.submit()">
                        <option value="">All RHUs</option>
                        <option value="pho" {{ request('rhu_filter') === 'pho' ? 'selected' : '' }}>Your PHO Allocations</option>
                        @foreach($healthUnits as $rhu)
                            <option value="{{ $rhu->id }}" {{ request('rhu_filter') == $rhu->id ? 'selected' : '' }}>
                                {{ $rhu->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif

            <button type="button" onclick="openAllocExportModal()" style="
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
                <a href="{{ route('allocation.create') }}" style="
                    display: inline-flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 600;
                    padding: 9px 16px; border-radius: 10px; background: #42ad8a; color: #ffffff;
                    text-decoration: none; border: none; cursor: pointer; transition: all 0.2s;
                " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Allocation
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
            <table style="width:100%; min-width: 1080px; border-collapse: collapse; font-size: 14px; table-layout: auto;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th class="no-print" style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">#</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Batch #</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Medicine</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Destination</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Quantity</th>
                        <th style="padding: 14px 20px; text-align: center; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Status</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Date</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Recipient</th>
                        @if(is_null(session('health_unit_id')))
                            <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Source</th>
                        @endif
                        <th class="no-print" style="padding: 14px 20px; text-align: center; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; white-space: nowrap;">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($allocations as $allocation)
                        @php
                            $isHighlighted = (string) request('highlight') === (string) $allocation->id;
                            $rowBg = $isHighlighted ? '#f1f5f9' : 'transparent';

                            $med  = $allocation->medicine;
                            $unit = $med->unit ?? null;
                            $pluralUnit = null;
                            if ($unit) {
                                if ((int) $allocation->quantity_allocated === 1) {
                                    $pluralUnit = $unit;
                                } else {
                                    $pluralUnit = (substr($unit, -1) === 's') ? $unit : $unit . 's';
                                }
                            }
                            $unitLabel = $pluralUnit ? ' (' . $pluralUnit . ')' : ' (units)';

                            $batchNo = $allocation->inventory->batch_no ?? null;

                            $isSupplyRequest = in_array($allocation->status ?? null, ['pending', 'approved', 'rejected', 'received'], true);
                            $isPending       = $isSupplyRequest && ($allocation->status === 'pending');

                            $statusStyle = match($allocation->status ?? null) {
                                'pending'          => ['bg'=>'#FEF3C7','text'=>'#92400E','border'=>'#FAC775','label'=>'⏳ Pending'],
                                'approved'         => ['bg'=>'#EFF6FF','text'=>'#1E40AF','border'=>'#93C5FD','label'=>'✓ Approved'],
                                'received'         => ['bg'=>'#E8F7F4','text'=>'#0f5c4b','border'=>'#42ad8a','label'=>'📦 Received'],
                                'rejected'         => ['bg'=>'#FCEBEB','text'=>'#991b1b','border'=>'#F7C1C1','label'=>'✗ Rejected'],
                                'awaiting_receipt' => ['bg'=>'#FEF3C7','text'=>'#92400E','border'=>'#FAC775','label'=>'⏳ Awaiting Receipt'],
                                'completed'        => ['bg'=>'#E8F7F4','text'=>'#0f5c4b','border'=>'#42ad8a','label'=>'✓ Completed'],
                                default            => ['bg'=>'#f1f5f9','text'=>'#475569','border'=>'#cbd5e1','label'=>'—'],
                            };

                            // Recipient — usually a midwife who requested the allocation
                            $recipient       = $allocation->recipient ?? null;
                            $recipientName   = $recipient->name ?? null;
                            $recipientRole   = strtolower($recipient->role ?? '');
                            $recipientLabel  = match($recipientRole) {
                                'midwife' => ['label' => 'Midwife',  'bg' => '#FCE7F3', 'text' => '#9D174D', 'border' => '#F9A8D4'],
                                'staff'   => ['label' => 'Staff',    'bg' => '#DBEAFE', 'text' => '#1E40AF', 'border' => '#93C5FD'],
                                'admin'   => ['label' => 'Admin',    'bg' => '#EDE9FE', 'text' => '#6D28D9', 'border' => '#C4B5FD'],
                                default   => null,
                            };
                        @endphp
                        <tr id="allocation-{{ $allocation->id }}" style="background: {{ $rowBg }}; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; scroll-margin-top: 80px;"
                            onmouseover="if('{{ $rowBg }}'=='transparent') this.style.background='#f8fafc';"
                            onmouseout="if('{{ $rowBg }}'=='transparent') this.style.background='transparent';">
                            <td class="no-print" style="padding: 14px 20px; color: #334155;">{{ $loop->iteration + ($allocations->currentPage() - 1) * $allocations->perPage() }}</td>

                            <td style="padding: 14px 20px; color: #334155;">
                                @if($batchNo)
                                    <span style="font-family:monospace; font-size:13px; background:#f1f5f9; padding:2px 8px; border-radius:6px;">
                                        {{ $batchNo }}
                                    </span>
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>

                            <td style="padding: 14px 20px; font-weight: 600; color: #0f172a;">{{ $allocation->medicine->medicine_name ?? 'N/A' }}</td>

                            {{-- DESTINATION — wraps cleanly inside a rounded rectangle --}}
                            <td style="padding: 14px 20px; color: #334155; max-width: 260px;">
                                @if($allocation->destination_health_unit_id)
                                    <span style="
                                        display: inline-flex; align-items: center; gap: 6px;
                                        background: #DBEAFE; color: #1E40AF; border: 1px solid #93C5FD;
                                        padding: 4px 12px; border-radius: 10px;
                                        font-size: 12px; font-weight: 600;
                                        max-width: 100%; box-sizing: border-box;
                                        word-break: break-word; white-space: normal;
                                        line-height: 1.4;
                                    ">
                                        <span style="flex-shrink:0;">🏥</span>
                                        <span style="min-width:0;">{{ $allocation->destination_display }}</span>
                                    </span>
                                @elseif($allocation->destination)
                                    <span style="
                                        display: inline-block;
                                        max-width: 100%; box-sizing: border-box;
                                        word-break: break-word; white-space: normal;
                                        line-height: 1.4;
                                    ">
                                        {{ $allocation->destination }}
                                    </span>
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>

                            <td style="padding: 14px 20px; color: #334155; font-weight: 600;">
                                {{ $allocation->quantity_allocated }}<span style="font-weight: 500; color: #64748b;">{{ $unitLabel }}</span>
                            </td>

                            <td style="padding: 14px 20px; text-align: center;">
                                <span style="
                                    display:inline-block; padding:4px 12px; border-radius:999px;
                                    font-size:12px; font-weight:700; white-space:nowrap;
                                    background:{{ $statusStyle['bg'] }};
                                    color:{{ $statusStyle['text'] }};
                                    border:1px solid {{ $statusStyle['border'] }};">
                                    {{ $statusStyle['label'] }}
                                </span>
                            </td>

                            <td style="padding: 14px 20px; color: #334155; white-space: nowrap;">
                                @if($allocation->date_allocated)
                                    <div style="font-weight: 600;">
                                        {{ \Carbon\Carbon::parse($allocation->date_allocated)->format('M d, Y') }}
                                    </div>
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>

                            {{-- RECIPIENT — midwife name + role badge --}}
                            <td style="padding: 14px 20px; color: #334155;">
                                @if($recipientName)
                                    <div style="display:flex; flex-direction:column; gap:4px;">
                                        <div style="font-weight:600; color:#0f172a;">{{ $recipientName }}</div>
                                        @if($recipientLabel)
                                            <span style="
                                                display:inline-block;
                                                padding:2px 10px;
                                                border-radius:999px;
                                                font-size:11px;
                                                font-weight:700;
                                                letter-spacing:0.02em;
                                                width:fit-content;
                                                background:{{ $recipientLabel['bg'] }};
                                                color:{{ $recipientLabel['text'] }};
                                                border:1px solid {{ $recipientLabel['border'] }};">
                                                {{ $recipientLabel['label'] }}
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <span style="color:#94a3b8; font-style:italic;">Unassigned</span>
                                @endif
                            </td>

                            @if(is_null(session('health_unit_id')))
                                <td style="padding: 14px 20px; color: #334155;">
                                    {{ $allocation->healthUnit->name ?? 'N/A' }}
                                </td>
                            @endif

                            <td class="no-print" style="padding: 14px 20px;">
                                @if($isPending)
                                    <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                                        <form action="{{ route('allocation.approve_supply_request', $allocation->id) }}" method="POST"
                                              onsubmit="return confirmDialog(event, 'Approve this supply request? Stock will be reserved.');"
                                              style="margin:0;">
                                            @csrf
                                            <button type="submit" title="Approve"
                                                    style="
                                                        display: inline-flex; align-items: center; gap: 4px;
                                                        padding: 7px 12px; border-radius: 8px;
                                                        background: #42ad8a; color: #fff;
                                                        border: none; font-size: 13px; font-weight: 700;
                                                        cursor: pointer; transition: all 0.2s;
                                                    "
                                                    onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="20 6 9 17 4 12"/>
                                                </svg>
                                                Approve
                                            </button>
                                        </form>

                                        <button type="button" title="Reject"
                                                onclick="rejectFromAllocation({{ $allocation->id }})"
                                                style="
                                                    display: inline-flex; align-items: center; gap: 4px;
                                                    padding: 7px 12px; border-radius: 8px;
                                                    background: #fee2e2; color: #991b1b;
                                                    border: 1px solid #fca5a5; font-size: 13px; font-weight: 700;
                                                    cursor: pointer; transition: all 0.2s;
                                                "
                                                onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="18" y1="6" x2="6" y2="18"/>
                                                <line x1="6" y1="6" x2="18" y2="18"/>
                                            </svg>
                                            Reject
                                        </button>
                                    </div>
                                @elseif($isSupplyRequest)
                                    <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                                        <form action="{{ route('allocation.destroy', $allocation->id) }}" method="POST"
                                              onsubmit="return confirmDialog(event, 'Delete this supply request? This cannot be undone.');"
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
                                @else
                                    <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                                        <a href="{{ route('allocation.edit', $allocation->id) }}" title="Edit"
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

                                        <form action="{{ route('allocation.destroy', $allocation->id) }}" method="POST"
                                              onsubmit="return confirmDialog(event, 'Are you sure you want to delete this allocation?');"
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
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="@php
                                $cols = 9;
                                if (is_null(session('health_unit_id'))) $cols += 1;
                                echo $cols;
                            @endphp" style="padding: 40px 20px; text-align: center; color: #64748b; border-bottom: 1px solid #f1f5f9;">
                                No allocation records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @if ($allocations->hasPages())
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
            @if ($allocations->onFirstPage())
                <span style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: default; user-select: none;">← Previous</span>
            @else
                <a href="{{ $allocations->previousPageUrl() }}" style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                    ← Previous
                </a>
            @endif

            @foreach ($allocations->getUrlRange(1, $allocations->lastPage()) as $page => $url)
                @if ($page == $allocations->currentPage())
                    <span style="font-size: 14px; font-weight: 600; padding: 8px 14px; border-radius: 8px; background: #42ad8a; color: #ffffff; border: 1px solid #42ad8a; cursor: default;">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" style="font-size: 14px; font-weight: 500; padding: 8px 14px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                       onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                        {{ $page }}
                    </a>
                @endif
            @endforeach

            @if ($allocations->hasMorePages())
                <a href="{{ $allocations->nextPageUrl() }}" style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                    Next →
                </a>
            @else
                <span style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: default; user-select: none;">Next →</span>
            @endif
        </div>
    @endif

</div>

{{-- EXPORT MODAL --}}
<div id="allocExportModal"
     onclick="if(event.target === this) closeAllocExportModal()"
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
                Export Allocation Records
            </h3>
            <p style="font-size:13px; color:#64748b; margin:0;">
                Choose what to include, then pick an action.
            </p>
        </div>

        <div style="padding:20px 26px;">
            <div>
                <label for="batchInput-allocExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Batch Number(s)
                </label>
                <input type="text"
                       id="batchInput-allocExportModal"
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

            <div style="margin-bottom:22px; margin-top:22px;">
                <label for="category-allocExportModal"
                       style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Program Category
                </label>
                <select id="category-allocExportModal"
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

            @if(is_null(session('health_unit_id')))
                <div style="margin-bottom:22px;">
                    <label for="rhu-allocExportModal"
                           style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                        Health Unit (RHU)
                    </label>
                    <select id="rhu-allocExportModal"
                            style="
                                width:100%; height:44px;
                                padding:0 14px; font-size:14px;
                                border:2px solid #bec6cf; border-radius:10px;
                                outline:none; box-sizing:border-box;
                                background:#ffffff; color:#0f172a; appearance:auto; cursor:pointer;
                            "
                            onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                            onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                        <option value="" {{ !request('rhu_filter') ? 'selected' : '' }}>All RHUs</option>
                        <option value="pho" {{ request('rhu_filter') === 'pho' ? 'selected' : '' }}>Your PHO Allocations</option>
                        @foreach($healthUnits as $rhu)
                            <option value="{{ $rhu->id }}" {{ request('rhu_filter') == $rhu->id ? 'selected' : '' }}>
                                {{ $rhu->name }}
                            </option>
                        @endforeach
                    </select>
                    <p style="font-size:12px; color:#64748b; margin:6px 0 0 0;">
                        Choose a specific RHU, or leave as "All RHUs" for province-wide.
                    </p>
                </div>
            @endif

            <div style="margin-bottom:22px;">
                <label style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                    Date Range
                </label>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div>
                        <label for="dateFrom-allocExportModal"
                               style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">
                            From
                        </label>
                        <input type="date"
                               id="dateFrom-allocExportModal"
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
                        <label for="dateTo-allocExportModal"
                               style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">
                            To
                        </label>
                        <input type="date"
                               id="dateTo-allocExportModal"
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
        </div>

        <div style="
            padding:16px 26px 20px;
            border-top:1px solid #f1f5f9;
            display:flex; gap:10px; justify-content:flex-end;
            background:#fafbfc;
            border-radius: 0 0 20px 20px;
        ">
            <button type="button"
                    onclick="closeAllocExportModal()"
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
                    onclick="runAllocExport('print')"
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
                    onclick="runAllocExport('csv')"
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
function rejectFromAllocation(id) {
    const reason = prompt('Reason for rejecting this request (optional):');
    if (reason === null) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ url("/allocation") }}/' + id + '/reject-supply-request';
    form.innerHTML = '@csrf<input type="hidden" name="rejection_reason" value="' +
                     reason.replace(/"/g, '&quot;') + '">';
    document.body.appendChild(form);
    form.submit();
}

function openAllocExportModal() {
    document.getElementById('allocExportModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeAllocExportModal() {
    document.getElementById('allocExportModal').style.display = 'none';
    document.body.style.overflow = '';
}
function runAllocExport(mode) {
    const categorySelect = document.getElementById('category-allocExportModal');
    const rhuSelect      = document.getElementById('rhu-allocExportModal');
    const dateFrom       = document.getElementById('dateFrom-allocExportModal');
    const dateTo         = document.getElementById('dateTo-allocExportModal');
    const batchInput     = document.getElementById('batchInput-allocExportModal');

    const categoryVal = categorySelect ? categorySelect.value    : '';
    const rhuVal      = rhuSelect      ? rhuSelect.value         : '';
    const dateFromVal = dateFrom       ? dateFrom.value          : '';
    const dateToVal   = dateTo         ? dateTo.value            : '';
    const batchVal    = batchInput     ? batchInput.value.trim() : '';

    const baseUrl = mode === 'csv'
        ? '{{ route('allocation.export') }}'
        : '{{ route('allocation.index') }}';

    const url = new URL(baseUrl, window.location.origin);
    const params = new URLSearchParams(window.location.search);
    params.delete('page');
    params.delete('print');
    params.delete('category');
    params.delete('rhu_filter');
    params.delete('date_from');
    params.delete('date_to');
    params.delete('batch_input');

    if (categoryVal)     params.set('category', categoryVal);
    if (rhuVal)          params.set('rhu_filter', rhuVal);
    if (dateFromVal)     params.set('date_from', dateFromVal);
    if (dateToVal)       params.set('date_to', dateToVal);
    if (batchVal !== '') params.set('batch_input', batchVal);
    if (mode === 'print') params.set('print', '1');

    url.search = params.toString();
    window.location.href = url.toString();
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('allocExportModal');
        if (modal && modal.style.display === 'flex') {
            closeAllocExportModal();
        }
    }
});
</script>
@endsection