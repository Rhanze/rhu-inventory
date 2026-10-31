@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    <style>
        @media print {
            .no-print, .sidebar, .public-header, .public-footer { display: none !important; }
            .main-content { padding: 0 !important; }
            body { background: #fff !important; }
            .print-card { box-shadow: none !important; border: none !important; }
        }
        .info-grid {
            display:grid; grid-template-columns:1fr 1fr; gap:18px 32px;
        }
        .info-grid .field label {
            display:block; font-size:14px; font-weight:700; letter-spacing:.06em;
            text-transform:uppercase; color:#64748b; margin-bottom:4px;
        }
        .info-grid .field .value {
            font-size:18px; font-weight:600; color:#0f172a;
        }
        .info-grid .field .value.mono { font-family:'Courier New', monospace; }
        .info-grid .field.full { grid-column: 1 / -1; }
    </style>

    {{-- Header --}}
    <div class="no-print" style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px;
        border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
        display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
        <div>
            <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">
                Provincial Health Office
            </p>
            <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">
                Receive Record {{ $shipment->shipment_no ?? ('#' . $shipment->id) }}
            </h2>
        </div>

        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('inventory.pho_receive') }}" style="
                padding:10px 18px; border-radius:10px; background:white; color:#334155;
                border:1px solid #e2e8f0; font-size:16px; font-weight:600; text-decoration:none;">
                ← Back
            </a>
            <button type="button" onclick="window.print()" style="
                display:inline-flex; align-items:center; gap:6px;
                padding:10px 18px; border-radius:10px;
                background:#42ad8a; color:#ffffff; border:1px solid #42ad8a;
                font-size:16px; font-weight:600; cursor:pointer;
                transition: background 0.2s, border-color 0.2s;"
                onmouseover="this.style.background='#369f7d'; this.style.borderColor='#369f7d'"
                onmouseout="this.style.background='#42ad8a'; this.style.borderColor='#42ad8a'">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                Print
            </button>
            <form action="{{ route('inventory.pho_receive.destroy', $shipment->id) }}" method="POST" style="margin:0;"
                  onsubmit="return confirmDialog(event, 'Delete this receive record and reverse its inventory? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" style="
                    padding:10px 18px; border-radius:10px; background:#fee2e2; color:#991b1b;
                    border:1px solid #fca5a5; font-size:16px; font-weight:600; cursor:pointer;">
                    Delete
                </button>
            </form>
        </div>
    </div>

    {{-- Receive info card --}}
    <div class="print-card" style="
        background:rgba(255,255,255,0.95); border-radius:20px;
        border:1px solid rgba(66,173,138,0.15); box-shadow:0 8px 20px rgba(0,0,0,0.06);
        padding:28px;">
        <p style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0 0 20px 0;">
            Receive Information
        </p>

        <div class="info-grid">
            <div class="field">
                <label>Shipment No.</label>
                <div class="value mono">{{ $shipment->shipment_no ?? '—' }}</div>
            </div>
            <div class="field">
                <label>DOH Reference No.</label>
                <div class="value mono">{{ $shipment->reference_no ?? '—' }}</div>
            </div>
            <div class="field">
                <label>Supplier</label>
                <div class="value">{{ $shipment->supplier ?? '—' }}</div>
            </div>
            <div class="field">
                <label>Received Date</label>
                <div class="value">{{ \Carbon\Carbon::parse($shipment->received_date)->format('m/d/Y') }}</div>
            </div>
            <div class="field">
                <label>Received By</label>
                <div class="value">{{ $shipment->receivedByUser->name ?? '—' }}</div>
            </div>
            <div class="field">
                <label>Total Items / Units</label>
                <div class="value">{{ $shipment->items->count() }} item(s) · {{ $shipment->total_items }} units</div>
            </div>
            @if($shipment->remarks)
                <div class="field full">
                    <label>Remarks</label>
                    <div class="value" style="font-weight:400; font-size:17px;">{{ $shipment->remarks }}</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Items card --}}
    <div class="print-card" style="
        background:rgba(255,255,255,0.95); border-radius:20px;
        border:1px solid rgba(66,173,138,0.15); box-shadow:0 8px 20px rgba(0,0,0,0.06);
        padding:0; overflow:hidden;">
        <div style="padding:20px 28px; border-bottom:1px solid #f1f5f9;">
            <p style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0;">
                Received Items
            </p>
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%; min-width: 700px; border-collapse:collapse; font-size:16px;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <th style="padding:14px 16px; text-align:left; font-size:14px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#475569;">#</th>
                        <th style="padding:14px 16px; text-align:left; font-size:14px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#475569;">Medicine</th>
                        <th style="padding:14px 16px; text-align:left; font-size:14px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#475569;">Batch No.</th>
                        <th style="padding:14px 16px; text-align:left; font-size:14px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#475569;">Expiry</th>
                        <th style="padding:14px 16px; text-align:right; font-size:14px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#475569;">Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shipment->items as $item)
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:14px 16px; color:#94a3b8; font-weight:600;">{{ $loop->iteration }}</td>
                            <td style="padding:14px 16px; font-weight:600; color:#0f172a;">
                                {{ $item->medicine->medicine_name ?? '—' }}
                                @if($item->medicine && ($item->medicine->dosage || $item->medicine->dosage_form))
                                    <div style="font-size:14px; color:#64748b; font-weight:400; margin-top:2px;">
                                        {{ collect([$item->medicine->dosage, $item->medicine->dosage_form])->filter()->join(' · ') }}
                                    </div>
                                @endif
                            </td>
                            <td style="padding:14px 16px; color:#334155; font-family:monospace;">{{ $item->batch_no ?? '—' }}</td>
                            <td style="padding:14px 16px; color:#334155; white-space:nowrap;">
                                {{ $item->expiration_date ? \Carbon\Carbon::parse($item->expiration_date)->format('m/d/Y') : '—' }}
                            </td>
                            <td style="padding:14px 16px; text-align:right; font-weight:600; color:#0f5c4b;">
                                {{ $item->quantity }}
                                @if($item->medicine && $item->medicine->unit)
                                    <span style="font-weight:400; color:#64748b;">
                                        ({{ $item->quantity == 1 ? $item->medicine->unit : $item->medicine->unit . 's' }})
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding:40px 20px; text-align:center; color:#64748b;">
                                No items in this record.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection