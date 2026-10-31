@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Allocation Management</p>
        <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">Add Allocation</h2>
        <p style="font-size:14px; color:#475569; margin:6px 0 0 0;">Record stock released to a destination (barangay, outreach, patient, etc.).</p>
    </div>

    {{-- Form Card --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius:20px; padding:28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <form action="{{ route('allocation.store') }}" method="POST" style="display:flex; flex-direction:column; gap:20px;">
            @csrf

            {{-- Inventory Item --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Inventory Item</label>
                <select name="inventory_id" id="inventory_id"
                        style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box; appearance:auto;"
                        onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                        onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <option value="">Select inventory</option>
                    @foreach($inventories as $inventory)
                        @php
                            $expiry = $inventory->expiration_date
                                ? \Carbon\Carbon::parse($inventory->expiration_date)->format('M d, Y')
                                : null;
                        @endphp
                        <option value="{{ $inventory->id }}"
                                data-unit="{{ $inventory->medicine->unit ?? '' }}"
                                data-pack-type="{{ $inventory->pack_type ?? $inventory->medicine->pack_type ?? '' }}"
                                data-pieces-per-pack="{{ $inventory->pieces_per_pack ?? $inventory->medicine->pieces_per_pack ?? '' }}"
                                data-batch="{{ $inventory->batch_no ?? '' }}"
                                data-expiry="{{ $expiry ?? '' }}"
                                data-stock="{{ $inventory->available_stock }}"
                                {{ old('inventory_id') == $inventory->id ? 'selected' : '' }}>
                            [{{ $inventory->batch_no ?? 'NO BATCH' }}]
                            | {{ $inventory->medicine->medicine_name ?? 'N/A' }}{{ $inventory->medicine->dosage ? ' - ' . $inventory->medicine->dosage : '' }}
                            (Available: {{ $inventory->available_stock }})
                            @if($expiry) · Exp: {{ $expiry }}@endif
                        </option>
                    @endforeach
                </select>

                {{-- Batch info panel --}}
                <div id="batchInfo" style="display:none; margin-top:10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 14px;">
                    <p style="font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#64748b; margin:0 0 6px 0;">This Batch</p>
                    <div style="display:flex; flex-wrap:wrap; gap:8px 16px; font-size:13px; color:#0f172a;">
                        <div><span style="color:#64748b;">Batch:</span> <strong id="info-batch" style="font-family:monospace;">—</strong></div>
                        <div><span style="color:#64748b;">Expiry:</span> <strong id="info-expiry">—</strong></div>
                        <div><span style="color:#64748b;">Packaging:</span> <strong id="info-packaging">—</strong></div>
                    </div>
                </div>

                @error('inventory_id')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Destination --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Destination</label>
                <input type="text" name="destination" id="destination-input" value="{{ old('destination') }}"
                       list="prior-destinations"
                       placeholder="e.g., Brgy Poblacion, Mobile Outreach Zone 4, Patient house visit"
                       autocomplete="off"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">

                @if(isset($priorDestinations) && $priorDestinations->count())
                    <datalist id="prior-destinations">
                        @foreach($priorDestinations as $prior)
                            <option value="{{ $prior }}"></option>
                        @endforeach
                    </datalist>
                @endif

                <small style="color:#64748b; font-size:13px;">
                    💡 Type any destination — barangay name, outreach site, or purpose. Previous entries will appear as suggestions.
                </small>
                @error('destination')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Recipient (optional) --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">
                    Recipient <span style="color:#64748b; font-weight:400;">(optional)</span>
                </label>
                <select name="recipient_user_id"
                        style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box; appearance:auto;"
                        onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                        onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <option value="">— No recipient (immediate allocation) —</option>
                    @foreach(($recipients ?? collect()) as $r)
                        <option value="{{ $r->id }}" {{ old('recipient_user_id') == $r->id ? 'selected' : '' }}>
                            {{ $r->name }} ({{ ucfirst($r->role ?? 'user') }})
                        </option>
                    @endforeach
                </select>
                <p style="margin-top:6px; font-size:13px; color:#64748b;">
                    If picked, they'll be notified and must confirm receipt. The stock is reserved until they confirm.
                    Leave blank for an immediate allocation (status set to Completed).
                </p>
                @error('recipient_user_id')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Quantity Allocated --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">
                    Quantity Allocated <span id="qty-unit-label" style="font-weight:400; color:#64748b;"></span>
                </label>
                <input type="number" name="quantity_allocated" id="quantity_allocated" value="{{ old('quantity_allocated') }}"
                       placeholder="Enter quantity"
                       min="1"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                <p id="qty-help" style="margin:6px 0 0 0; font-size:13px; color:#64748b;"></p>
                @error('quantity_allocated')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Date Allocated --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Date Allocated</label>
                <input type="date" name="date_allocated" value="{{ old('date_allocated', date('Y-m-d')) }}"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                @error('date_allocated')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Buttons — right-aligned --}}
            <div style="display:flex; gap:12px; margin-top:4px; justify-content:flex-end;">
                <a href="{{ route('allocation.index') }}" style="
                    font-size:14px; font-weight:600; padding:10px 20px; border-radius:10px;
                    background:#ffffff; color:#334155; border:1px solid #e2e8f0;
                    text-decoration:none;
                " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'">
                    Cancel
                </a>

                <button type="submit" style="
                    font-size:14px; font-weight:600; padding:10px 20px; border-radius:10px;
                    background:#42ad8a; color:#ffffff; border:none; cursor:pointer;
                " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                    Save Allocation
                </button>
            </div>
        </form>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const inventorySelect = document.getElementById('inventory_id');
    const batchInfo       = document.getElementById('batchInfo');
    const infoBatch       = document.getElementById('info-batch');
    const infoExpiry      = document.getElementById('info-expiry');
    const infoPackage     = document.getElementById('info-packaging');

    const qtyInput   = document.getElementById('quantity_allocated');
    const qtyHelp    = document.getElementById('qty-help');
    const qtyUnitLbl = document.getElementById('qty-unit-label');

    function pluralUnit(unit, qty) {
        if (!unit) return 'units';
        return parseInt(qty, 10) === 1 ? unit : unit + 's';
    }

    function updateFromSelection() {
        const opt = inventorySelect.options[inventorySelect.selectedIndex];

        batchInfo.style.display = 'none';
        qtyHelp.textContent = '';
        qtyUnitLbl.textContent = '';

        if (!opt || !opt.value) return;

        const batch    = opt.dataset.batch || '';
        const expiry   = opt.dataset.expiry || '';
        const packType = opt.dataset.packType || '';
        const ppp      = parseInt(opt.dataset.piecesPerPack || 0, 10);
        const unit     = opt.dataset.unit || '';

        infoBatch.textContent  = batch || '—';
        infoExpiry.textContent = expiry || '—';

        if (packType && ppp) {
            infoPackage.textContent = ppp + ' ' + pluralUnit(unit, ppp) + ' per ' + packType;
        } else if (packType) {
            infoPackage.textContent = packType;
        } else {
            infoPackage.textContent = '—';
        }
        batchInfo.style.display = 'block';

        qtyUnitLbl.textContent = unit ? `(${unit}s)` : '';

        updateQtyPreview();
    }

    function updateQtyPreview() {
        const opt = inventorySelect.options[inventorySelect.selectedIndex];
        if (!opt || !opt.value) { qtyHelp.textContent = ''; return; }

        const qty = parseInt(qtyInput.value || 0, 10);
        if (qty < 1) { qtyHelp.textContent = ''; return; }

        const unit     = opt.dataset.unit || 'unit';
        const packType = opt.dataset.packType || '';
        const ppp      = parseInt(opt.dataset.piecesPerPack || 0, 10);

        if (!packType || !ppp) {
            qtyHelp.textContent = `= ${qty} ${pluralUnit(unit, qty)}`;
            return;
        }

        const packs  = Math.floor(qty / ppp);
        const pieces = qty % ppp;
        const parts  = [];

        if (packs)  parts.push(`${packs} ${packs === 1 ? packType : packType + 's'}`);
        if (pieces) parts.push(`${pieces} ${pluralUnit(unit, pieces)}`);

        qtyHelp.innerHTML = `<strong style="color:#0f5c4b;">= ${parts.join(' + ')} (${qty} ${pluralUnit(unit, qty)} total)</strong>`;
    }

    inventorySelect.addEventListener('change', updateFromSelection);
    qtyInput.addEventListener('input', updateQtyPreview);
    updateFromSelection();
});
</script>
@endsection