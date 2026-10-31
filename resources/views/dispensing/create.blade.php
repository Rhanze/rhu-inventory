@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Dispensing Management</p>
        <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">Add Dispensing</h2>
        <p style="font-size:14px; color:#475569; margin:6px 0 0 0;">Record medicine dispensing.</p>
    </div>

    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius:20px; padding:28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <form action="{{ route('dispensing.store') }}" method="POST" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:20px;">
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
                                data-prescription="{{ optional($inventory->medicine)->requires_prescription ? 1 : 0 }}"
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

                {{-- Prescription status badge --}}
                <div id="prescriptionStatus" style="margin-top:8px; display:none; border-radius:10px; padding:10px 14px; font-size:13px; font-weight:500;"></div>

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

            {{-- Quantity --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">
                    Quantity Dispensed <span id="qty-unit-label" style="font-weight:400; color:#64748b;"></span>
                </label>
                <input type="number" name="quantity_dispensed" id="quantity_dispensed" value="{{ old('quantity_dispensed') }}"
                       min="1" placeholder="Enter quantity"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                <p id="qty-help" style="margin:6px 0 0 0; font-size:13px; color:#64748b;"></p>
                @error('quantity_dispensed')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Doctor Name --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">
                    Doctor Name
                    <span id="doctorRequiredMarker" style="color:#dc2626; display:none;"> *</span>
                </label>
                <input type="text" name="doctor_name" id="doctor_name" value="{{ old('doctor_name') }}"
                       placeholder="Required only for prescription medicines"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                <p id="doctorHelpText" style="margin-top:4px; font-size:12px; color:#64748b;">Required only if the selected medicine needs a prescription.</p>
                @error('doctor_name')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Date --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Date Dispensed</label>
                <input type="date" name="date_dispensed" value="{{ old('date_dispensed', date('Y-m-d')) }}"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                @error('date_dispensed')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Prescription Image --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">
                    Prescription Image <span style="font-weight:400; color:#64748b;">(optional)</span>
                </label>

                <label for="prescription_image" id="uploadLabel" style="
                    display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px;
                    width:100%; padding:24px; border:2px dashed #bec6cf; border-radius:14px;
                    background:#f8fafc; cursor:pointer; transition: all 0.2s; box-sizing:border-box;
                ">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#42ad8a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    <span style="font-size:15px; font-weight:600; color:#0f172a;">Click to upload prescription scan/photo</span>
                    <span style="font-size:12px; color:#64748b;">JPG, PNG, WebP or PDF · Max 5 MB</span>
                </label>

                <input type="file" name="prescription_image" id="prescription_image"
                       accept="image/jpeg,image/png,image/webp,application/pdf"
                       style="display:none;">

                <div id="filePreview" style="display:none; margin-top:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                        <div style="min-width:0;">
                            <p style="margin:0; font-size:13px; font-weight:600; color:#0f172a; word-break:break-all;" id="fileName">—</p>
                            <p style="margin:2px 0 0 0; font-size:12px; color:#64748b;" id="fileSize">—</p>
                        </div>
                        <button type="button" onclick="clearPrescriptionFile()"
                                style="font-size:12px; font-weight:600; padding:6px 12px; border-radius:8px;
                                       background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; cursor:pointer;">
                            Remove
                        </button>
                    </div>
                    <div id="previewImgWrap" style="display:none; margin-top:10px; text-align:center;">
                        <img id="previewImg" src="" alt="Preview"
                             style="max-width:100%; max-height:280px; border-radius:8px; border:1px solid #e2e8f0;">
                    </div>
                    <div id="previewPdfWrap" style="display:none; margin-top:10px; padding:12px; text-align:center; background:white; border-radius:8px; border:1px solid #e2e8f0; font-size:14px; color:#0f172a;">
                        📄 PDF ready to upload
                    </div>
                </div>

                @error('prescription_image')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Buttons — right-aligned --}}
            <div style="display:flex; gap:12px; margin-top:4px; justify-content:flex-end;">
                <a href="{{ route('dispensing.index') }}" style="
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
                    Save Dispensing
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const inventorySelect      = document.getElementById('inventory_id');
    const prescriptionStatus   = document.getElementById('prescriptionStatus');
    const doctorField          = document.getElementById('doctor_name');
    const doctorRequiredMarker = document.getElementById('doctorRequiredMarker');
    const doctorHelpText       = document.getElementById('doctorHelpText');

    const batchInfo   = document.getElementById('batchInfo');
    const infoBatch   = document.getElementById('info-batch');
    const infoExpiry  = document.getElementById('info-expiry');
    const infoPackage = document.getElementById('info-packaging');

    const qtyInput   = document.getElementById('quantity_dispensed');
    const qtyHelp    = document.getElementById('qty-help');
    const qtyUnitLbl = document.getElementById('qty-unit-label');

    const fileInput      = document.getElementById('prescription_image');
    const filePreview    = document.getElementById('filePreview');
    const fileName       = document.getElementById('fileName');
    const fileSize       = document.getElementById('fileSize');
    const previewImgWrap = document.getElementById('previewImgWrap');
    const previewImg     = document.getElementById('previewImg');
    const previewPdfWrap = document.getElementById('previewPdfWrap');
    const uploadLabel    = document.getElementById('uploadLabel');

    function pluralUnit(unit, qty) {
        if (!unit) return 'units';
        return parseInt(qty, 10) === 1 ? unit : unit + 's';
    }

    function updateFromSelection() {
        const opt = inventorySelect.options[inventorySelect.selectedIndex];

        prescriptionStatus.style.display = 'none';
        doctorRequiredMarker.style.display = 'none';
        doctorField.required = false;
        doctorField.style.borderColor = '#bec6cf';
        doctorHelpText.textContent = 'Required only if the selected medicine needs a prescription.';
        batchInfo.style.display = 'none';
        qtyHelp.textContent = '';
        qtyUnitLbl.textContent = '';

        if (!opt || !opt.value) return;

        const requiresRx = opt.dataset.prescription === '1';
        prescriptionStatus.style.display = 'block';

        if (requiresRx) {
            prescriptionStatus.style.cssText = 'margin-top:8px; border-radius:10px; padding:10px 14px; font-size:13px; font-weight:500; background:#FAEEDA; color:#854F0B; border:1px solid #FAC775;';
            prescriptionStatus.textContent = 'Prescription Required — Doctor Name is required for this medicine.';
            doctorRequiredMarker.style.display = 'inline';
            doctorField.required = true;
            doctorField.style.borderColor = '#FAC775';
            doctorHelpText.textContent = 'This selected medicine requires a prescription, so Doctor Name is required.';
        } else {
            prescriptionStatus.style.cssText = 'margin-top:8px; border-radius:10px; padding:10px 14px; font-size:13px; font-weight:500; background:#EAF3DE; color:#3B6D11; border:1px solid #C0DD97;';
            prescriptionStatus.textContent = 'No Prescription Needed.';
        }

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

    uploadLabel.addEventListener('mouseenter', () => {
        uploadLabel.style.borderColor = '#42ad8a';
        uploadLabel.style.background = '#f0fbf7';
    });
    uploadLabel.addEventListener('mouseleave', () => {
        uploadLabel.style.borderColor = '#bec6cf';
        uploadLabel.style.background = '#f8fafc';
    });

    fileInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) { resetPreview(); return; }

        fileName.textContent = file.name;
        fileSize.textContent = formatBytes(file.size);

        const isPdf = file.type === 'application/pdf';
        const isImage = file.type.startsWith('image/');

        previewImgWrap.style.display = 'none';
        previewPdfWrap.style.display = 'none';

        if (isImage) {
            const reader = new FileReader();
            reader.onload = e => {
                previewImg.src = e.target.result;
                previewImgWrap.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else if (isPdf) {
            previewPdfWrap.style.display = 'block';
        }

        filePreview.style.display = 'block';
    });

    window.clearPrescriptionFile = function () {
        fileInput.value = '';
        resetPreview();
    };

    function resetPreview() {
        filePreview.style.display = 'none';
        previewImg.src = '';
        previewImgWrap.style.display = 'none';
        previewPdfWrap.style.display = 'none';
    }

    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }
});
</script>
@endsection