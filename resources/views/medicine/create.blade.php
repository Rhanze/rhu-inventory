@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <p style="font-size:15px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Health Program Management</p>
        <h2 style="font-size:26px; font-weight:700; color:#0f172a; margin:0;">Add Item</h2>
        <p style="font-size:16px; color:#475569; margin:6px 0 0 0;">Create a new item record across any health program.</p>
    </div>

    {{-- Form Card --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius:20px; padding:28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <form action="{{ route('medicine.store') }}" method="POST" style="display:flex; flex-direction:column; gap:20px;">
            @csrf

            {{-- Item Name --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:16px; font-weight:600; color:#0f172a;">Item Name</label>
                <input type="text" name="medicine_name" value="{{ old('medicine_name') }}"
                       placeholder="Enter item name"
                       style="width:100%; padding:12px 16px; font-size:16px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                @error('medicine_name')
                    <p style="margin-top:4px; font-size:14px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Category + Dosage Strength --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; margin-bottom:6px; font-size:16px; font-weight:600; color:#0f172a;">Health Program / Category</label>
                    <select name="category" id="category-select"
                            style="width:100%; padding:12px 16px; font-size:16px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box; appearance:auto;"
                            onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                            onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                        <option value="">Select category</option>
                        <option value="medicine"              {{ old('category') === 'medicine'              ? 'selected' : '' }}>Medicine</option>
                        <option value="vaccine"               {{ old('category') === 'vaccine'               ? 'selected' : '' }}>Vaccine</option>
                        <option value="family_planning"       {{ old('category') === 'family_planning'       ? 'selected' : '' }}>Family Planning</option>
                        <option value="family_health_nutrition" {{ old('category') === 'family_health_nutrition' ? 'selected' : '' }}>Family Health &amp; Nutrition</option>
                        <option value="emerging_reemerging"    {{ old('category') === 'emerging_reemerging'    ? 'selected' : '' }}>Emerging &amp; Re-emerging</option>
                        <option value="non_communicable"         {{ old('category') === 'non_communicable'         ? 'selected' : '' }}>Non-Communicable Disease</option>
                    <option value="dengue_prevention"       {{ request('category') === 'dengue_prevention' ? 'selected' : '' }}>Dengue Prevention</option>
                    <option value="tuberculosis"            {{ request('category') === 'tuberculosis' ? 'selected' : '' }}>Tuberculosis</option>
                    </select>
                    @error('category')
                        <p style="margin-top:4px; font-size:14px; color:#dc2626;">{{ $message }}</p>
                    @enderror
                </div>

                <div id="dosage-strength-wrapper">
                    <label style="display:block; margin-bottom:6px; font-size:16px; font-weight:600; color:#0f172a;">
                        Dosage / Strength
                        <span id="dosage-strength-optional" style="font-weight:400; color:#64748b; font-size:14px; display:none;">(not applicable)</span>
                    </label>
                    <input type="text" name="dosage" id="dosage-input" value="{{ old('dosage') }}"
                           placeholder="e.g., 500 mg"
                           style="width:100%; padding:12px 16px; font-size:16px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                           onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                           onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <small id="dosage-strength-hint" style="color:#64748b; font-size:14px; display:none;">
                        💡 Devices like condoms or IUDs don't have a dosage strength.
                    </small>
                    @error('dosage')
                        <p style="margin-top:4px; font-size:14px; color:#dc2626;">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Dosage Form + Base Unit --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; margin-bottom:6px; font-size:16px; font-weight:600; color:#0f172a;">
                        Dosage Form
                    </label>
                    <select name="dosage_form" id="dosage-form-select"
                            style="width:100%; padding:12px 16px; font-size:16px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box; appearance:auto;"
                            onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                            onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                        <option value="">— Select —</option>
                        <option value="Tablet"      data-cats="medicine,family_planning,family_health_nutrition,emerging_reemerging,non_communicable" {{ old('dosage_form') == 'Tablet'      ? 'selected' : '' }}>Tablet</option>
                        <option value="Capsule"     data-cats="medicine,family_planning,family_health_nutrition,emerging_reemerging,non_communicable" {{ old('dosage_form') == 'Capsule'     ? 'selected' : '' }}>Capsule</option>
                        <option value="Syrup"       data-cats="medicine,family_health_nutrition,non_communicable"        {{ old('dosage_form') == 'Syrup'       ? 'selected' : '' }}>Syrup</option>
                        <option value="Suspension"  data-cats="medicine,family_health_nutrition,emerging_reemerging"   {{ old('dosage_form') == 'Suspension'  ? 'selected' : '' }}>Suspension</option>
                        <option value="Injection"   data-cats="medicine,vaccine,family_planning,emerging_reemerging,non_communicable" {{ old('dosage_form') == 'Injection' ? 'selected' : '' }}>Injection</option>
                        <option value="Cream"       data-cats="medicine"                                            {{ old('dosage_form') == 'Cream'       ? 'selected' : '' }}>Cream</option>
                        <option value="Ointment"    data-cats="medicine"                                            {{ old('dosage_form') == 'Ointment'    ? 'selected' : '' }}>Ointment</option>
                        <option value="Drops"       data-cats="medicine,family_health_nutrition"                      {{ old('dosage_form') == 'Drops'       ? 'selected' : '' }}>Drops</option>
                        <option value="Suppository" data-cats="medicine"                                            {{ old('dosage_form') == 'Suppository' ? 'selected' : '' }}>Suppository</option>
                        <option value="Powder"      data-cats="family_health_nutrition"                               {{ old('dosage_form') == 'Powder'      ? 'selected' : '' }}>Powder</option>
                        <option value="Implant"     data-cats="family_planning"                                     {{ old('dosage_form') == 'Implant'     ? 'selected' : '' }}>Implant</option>
                        <option value="Device"      data-cats="family_planning"                                     {{ old('dosage_form') == 'Device'      ? 'selected' : '' }}>Device</option>
                    </select>
                    <small id="dosage-form-hint" style="color:#64748b; font-size:14px; display:none;">
                        🔒 Vaccines are always <strong>Injection</strong>.
                    </small>
                    @error('dosage_form')
                        <p style="margin-top:4px; font-size:14px; color:#dc2626;">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label style="display:block; margin-bottom:6px; font-size:16px; font-weight:600; color:#0f172a;">
                        Base Unit
                        <span style="font-weight:400; color:#64748b; font-size:14px;">(how it's counted)</span>
                    </label>
                    <select name="unit" id="unit-select"
                            style="width:100%; padding:12px 16px; font-size:16px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box; appearance:auto;"
                            onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                            onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                        <option value="">— Select —</option>
                        <option value="piece"  data-cats="medicine,family_planning,family_health_nutrition,emerging_reemerging,non_communicable" {{ old('unit') == 'piece'  ? 'selected' : '' }}>piece</option>
                        <option value="stub"   data-cats="medicine,emerging_reemerging,non_communicable"   {{ old('unit') == 'stub'   ? 'selected' : '' }}>stub</option>
                        <option value="sachet" data-cats="medicine,family_health_nutrition"              {{ old('unit') == 'sachet' ? 'selected' : '' }}>sachet</option>
                        <option value="box"    data-cats="medicine,vaccine,family_planning,family_health_nutrition,emerging_reemerging,non_communicable" {{ old('unit') == 'box' ? 'selected' : '' }}>box</option>
                        <option value="vial"   data-cats="vaccine,emerging_reemerging,non_communicable"    {{ old('unit') == 'vial'   ? 'selected' : '' }}>vial</option>
                        <option value="ampule" data-cats="vaccine,emerging_reemerging"                  {{ old('unit') == 'ampule' ? 'selected' : '' }}>ampule</option>
                        <option value="bottle" data-cats="family_health_nutrition"                      {{ old('unit') == 'bottle' ? 'selected' : '' }}>bottle</option>
                    </select>
                    <small style="color:#64748b; font-size:14px;">💡 How stock is counted.</small>
                    @error('unit')
                        <p style="margin-top:4px; font-size:14px; color:#dc2626;">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Description --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:16px; font-weight:600; color:#0f172a;">Description</label>
                <textarea name="description" rows="4"
                          style="width:100%; padding:12px 16px; font-size:16px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box; resize:vertical;"
                          onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                          onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'"
                          placeholder="Enter description">{{ old('description') }}</textarea>
                @error('description')
                    <p style="margin-top:4px; font-size:14px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Requires Prescription --}}
            <div style="display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" id="requires_prescription" name="requires_prescription" value="1"
                       {{ old('requires_prescription') ? 'checked' : '' }}
                       style="width: 20px; height: 20px; accent-color: #42ad8a; cursor: pointer;">
                <label for="requires_prescription" style="font-size:16px; font-weight:500; color:#0f172a; cursor: pointer;">
                    Requires Prescription
                </label>
            </div>

            {{-- Buttons --}}
            <div style="display:flex; gap:12px; margin-top:4px;">
                <button type="submit" style="
                    font-size:16px; font-weight:600; padding:11px 22px; border-radius:10px;
                    background:#42ad8a; color:#ffffff; border:none; cursor:pointer;
                    transition: background 0.2s;
                " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                    Save Item
                </button>

                <a href="{{ route('medicine.index') }}" style="
                    font-size:16px; font-weight:600; padding:11px 22px; border-radius:10px;
                    background:#ffffff; color:#334155; border:1px solid #e2e8f0;
                    text-decoration:none; transition: background 0.2s;
                " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'">
                    Cancel
                </a>
            </div>
        </form>
    </div>

</div>

{{-- Category-aware filtering + Device hides Dosage Strength --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const categorySelect   = document.getElementById('category-select');
    const dosageFormSelect = document.getElementById('dosage-form-select');
    const unitSelect       = document.getElementById('unit-select');
    const dosageHint       = document.getElementById('dosage-form-hint');
    const dosageInput      = document.getElementById('dosage-input');
    const dosageWrapper    = document.getElementById('dosage-strength-wrapper');
    const dosageOptional   = document.getElementById('dosage-strength-optional');
    const dosageStrengthHint = document.getElementById('dosage-strength-hint');

    if (!categorySelect || !dosageFormSelect || !unitSelect) return;

    function snapshot(select) {
        return Array.from(select.options)
            .filter(opt => opt.value !== '')
            .map(opt => ({
                value: opt.value,
                label: opt.textContent,
                cats:  (opt.dataset.cats || '').split(',').map(s => s.trim())
            }));
    }

    const dosageFormOptions = snapshot(dosageFormSelect);
    const unitOptions       = snapshot(unitSelect);

    function rebuildSelect(select, options, category, currentValue) {
        select.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = '— Select —';
        select.appendChild(placeholder);

        options.forEach(opt => {
            if (!category || opt.cats.includes(category)) {
                const o = document.createElement('option');
                o.value = opt.value;
                o.textContent = opt.label;
                o.dataset.cats = opt.cats.join(',');
                if (opt.value === currentValue) o.selected = true;
                select.appendChild(o);
            }
        });
    }

    // ─── Dosage Strength visibility ───
    function updateDosageStrengthVisibility() {
        const form = dosageFormSelect.value;
        const isDevice = (form === 'Device');

        if (isDevice) {
            if (dosageInput) {
                dosageInput.value = '';
                dosageInput.style.display = 'none';
                dosageInput.removeAttribute('required');
            }
            if (dosageOptional) dosageOptional.style.display = 'inline';
            if (dosageStrengthHint) dosageStrengthHint.style.display = 'block';
        } else {
            if (dosageInput) dosageInput.style.display = 'block';
            if (dosageOptional) dosageOptional.style.display = 'none';
            if (dosageStrengthHint) dosageStrengthHint.style.display = 'none';
        }
    }

    function applyFilter() {
        const category = categorySelect.value;

        // ─── Dosage Form ───
        if (category === 'vaccine') {
            dosageFormSelect.innerHTML = '';
            const o = document.createElement('option');
            o.value = 'Injection';
            o.textContent = 'Injection';
            o.selected = true;
            dosageFormSelect.appendChild(o);

            dosageFormSelect.style.background    = '#f1f5f9';
            dosageFormSelect.style.color         = '#64748b';
            dosageFormSelect.style.pointerEvents = 'none';

            if (dosageHint) dosageHint.style.display = 'block';
        } else {
            dosageFormSelect.style.background    = 'white';
            dosageFormSelect.style.color         = '#0f172a';
            dosageFormSelect.style.pointerEvents = 'auto';

            rebuildSelect(dosageFormSelect, dosageFormOptions, category, dosageFormSelect.value);

            if (dosageHint) dosageHint.style.display = 'none';
        }

        // ─── Base Unit ───
        rebuildSelect(unitSelect, unitOptions, category, unitSelect.value);

        // ─── Dosage Strength visibility (based on form) ───
        updateDosageStrengthVisibility();
    }

    categorySelect.addEventListener('change', applyFilter);
    dosageFormSelect.addEventListener('change', updateDosageStrengthVisibility);

    applyFilter();
});
</script>
@endsection