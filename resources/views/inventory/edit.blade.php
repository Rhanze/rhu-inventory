@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Inventory Management</p>
        <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">Edit Inventory Record</h2>
    </div>

    @php
        $med = $inventory->medicine;
        $unit = $med->unit ?? null;

        // Packaging text — now read from inventory, with fallback to medicine
        $packParts = [];
        $resolvedPackType = $inventory->pack_type ?? $med->pack_type;
        $resolvedPpp      = $inventory->pieces_per_pack ?? $med->pieces_per_pack;

        if ($resolvedPpp && $unit) {
            $packParts[] = $resolvedPpp . ' ' . $unit . ($resolvedPpp === 1 ? '' : 's');
        } elseif ($unit) {
            $packParts[] = $unit;
        }
        if ($resolvedPackType) {
            $packParts[] = $resolvedPackType;
        }
        $packText = $packParts ? implode(' / ', $packParts) : null;

        // Expiry badge
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
    @endphp

    {{-- Medicine Info Panel --}}
    <div style="
        background: #ffffff;
        border-radius: 20px; padding: 24px 28px;
        border: 1px solid #e2e8f0;
        border-left: 4px solid #42ad8a;
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    ">
        <div style="display:flex; align-items:flex-start; gap:16px; flex-wrap:wrap;">
            <div style="
                width:56px; height:56px; flex-shrink:0;
                display:flex; align-items:center; justify-content:center;
                background:#42ad8a; border-radius:14px;
                box-shadow: 0 4px 10px rgba(66,173,138,0.3);
            ">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18"/>
                </svg>
            </div>

            <div style="flex:1; min-width:240px;">
                <p style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#0f5c4b; margin:0 0 4px 0;">Medicine Details</p>
                <h3 style="font-size:22px; font-weight:700; color:#0f172a; margin:0 0 12px 0;">
                    {{ $med->medicine_name ?? 'N/A' }}
                </h3>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px 24px;">
                    <div>
                        <p style="font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#64748b; margin:0 0 2px 0;">Dosage</p>
                        <p style="font-size:14px; color:#0f172a; margin:0; font-weight:600;">
                            {{ $med->dosage ?? '—' }}
                            @if($med && $med->dosage_form)
                                <span style="color:#475569; font-weight:500;"> · {{ $med->dosage_form }}</span>
                            @endif
                        </p>
                    </div>

                    {{-- ← NEW: Program Category --}}
                    <div>
                        <p style="font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#64748b; margin:0 0 2px 0;">Program Category</p>
                        <p style="font-size:14px; color:#0f172a; margin:0; font-weight:600;">
                            {{ $med->category_label ?? '—' }}
                        </p>
                    </div>

                    <div>
                        <p style="font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#64748b; margin:0 0 2px 0;">Packaging</p>
                        <p style="font-size:14px; color:#0f172a; margin:0; font-weight:600;">
                            {{ $packText ?? '—' }}
                        </p>
                    </div>

                    <div>
                        <p style="font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#64748b; margin:0 0 2px 0;">Base Unit</p>
                        <p style="font-size:14px; color:#0f172a; margin:0; font-weight:600;">
                            {{ $unit ?? '—' }}
                        </p>
                    </div>

                    @if($expiryBadge)
                        <div>
                            <p style="font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#64748b; margin:0 0 2px 0;">Expiry Status</p>
                            <span title="{{ $daysLeft >= 0 ? $daysLeft . ' day(s) remaining' : abs($daysLeft) . ' day(s) past expiry' }}"
                                  style="
                                      display:inline-block;
                                      padding:2px 10px; border-radius:6px;
                                      font-size:12px; font-weight:700; letter-spacing:0.02em;
                                      background:{{ $expiryBadge['bg'] }};
                                      color:{{ $expiryBadge['color'] }};
                                      border:1px solid {{ $expiryBadge['border'] }};
                                  ">
                                {{ $expiryBadge['label'] }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Form Card --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius:20px; padding:28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <form action="{{ route('inventory.update', $inventory->id) }}" method="POST" style="display:flex; flex-direction:column; gap:18px;">
            @csrf
            @method('PUT')

            {{-- Preserve stock values --}}
            <input type="hidden" name="available_stock"  value="{{ $inventory->available_stock }}">
            <input type="hidden" name="allocated_stock"  value="{{ $inventory->allocated_stock }}">

            {{-- 1. Batch Number --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Batch Number</label>
                <input type="text" name="batch_no" value="{{ old('batch_no', $inventory->batch_no) }}"
                       placeholder="e.g., BATCH-2024-001"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                @error('batch_no')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- 2. Storage Location --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Storage Location</label>
                <input type="text" name="storage_location" value="{{ old('storage_location', $inventory->storage_location) }}"
                       placeholder="e.g., Cold Room A, Shelf 3B, Refrigerator 2"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                <small style="color:#64748b; font-size:13px;">Where is this batch physically stored?</small>
                @error('storage_location')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- 3. Pack Type + Pieces per Pack --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">
                        Pack Type
                        <span style="font-weight:400; color:#64748b; font-size:12px;">(optional)</span>
                    </label>
                    <input type="text" name="pack_type" value="{{ old('pack_type', $inventory->pack_type) }}"
                           placeholder="e.g., Stub, Box, Tray"
                           style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                           onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                           onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <small style="color:#64748b; font-size:13px;">How this batch is wrapped.</small>
                    @error('pack_type')
                        <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">
                        Pieces per Pack
                        <span style="font-weight:400; color:#64748b; font-size:12px;">(optional)</span>
                    </label>
                    <input type="number" name="pieces_per_pack" value="{{ old('pieces_per_pack', $inventory->pieces_per_pack) }}" min="1"
                           placeholder="e.g., 10"
                           style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                           onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                           onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <small style="color:#64748b; font-size:13px;">How many pieces fit in one pack.</small>
                    @error('pieces_per_pack')
                        <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- 4. Expiration Date --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Expiration Date</label>
                <input type="date" name="expiration_date" value="{{ old('expiration_date', $inventory->expiration_date?->format('Y-m-d')) }}"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                <small style="color:#64748b; font-size:13px;">Leave blank if not applicable. Expiry badge recalculates on save.</small>
                @error('expiration_date')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Buttons --}}
            <div style="display:flex; gap:12px; margin-top:8px;">
                <button type="submit" style="
                    font-size:14px; font-weight:600; padding:10px 20px; border-radius:10px;
                    background:#42ad8a; color:#ffffff; border:none; cursor:pointer;
                    transition: background 0.2s;
                " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                    Save Changes
                </button>

                <a href="{{ route('inventory.index') }}" style="
                    font-size:14px; font-weight:600; padding:10px 20px; border-radius:10px;
                    background:#ffffff; color:#334155; border:1px solid #e2e8f0;
                    text-decoration:none; transition: background 0.2s;
                " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'">
                    Cancel
                </a>
            </div>
        </form>
    </div>

</div>
@endsection