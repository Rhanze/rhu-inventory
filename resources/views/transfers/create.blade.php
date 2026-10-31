@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header --}}
    <div style="
        background: rgba(255,255,255,0.95); backdrop-filter: blur(12px);
        border-radius: 20px; padding: 24px 28px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);">
        <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">
            Transfer Request
        </p>
        <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">
            Request Stock from a Partner RHU
        </h2>
        <p style="font-size:15px; color:#64748b; margin:6px 0 0 0;">
            From <strong style="color:#0f5c4b;">{{ $currentRhu->name }}</strong>
        </p>
    </div>

    {{-- Form card --}}
    <div style="
        background: rgba(255,255,255,0.95); backdrop-filter: blur(8px);
        border-radius: 20px; padding: 28px 32px;
        border: 1px solid rgba(66,173,138,0.15);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);">

        <style>
            .tr-field label {
                display:block; font-size:20px; font-weight:600;
                color:#0f172a; margin-bottom:6px;
            }
            .tr-field input,
            .tr-field select,
            .tr-field textarea {
                width:100%; padding:14px 18px; font-size:20px;
                border:2px solid #bec6cf; border-radius:12px;
                background:#ffffff; color:#0f172a; outline:none;
                box-sizing:border-box;
                transition: border-color 0.15s, box-shadow 0.15s;
            }
            .tr-field input:focus,
            .tr-field select:focus,
            .tr-field textarea:focus {
                border-color:#42ad8a; box-shadow:0 0 0 3px rgba(66,173,138,0.1);
            }
            .tr-field input[readonly] {
                background:#f8fafc; color:#475569; cursor:not-allowed;
            }
            .tr-section-title {
                font-size:16px; font-weight:700; letter-spacing:.08em;
                text-transform:uppercase; color:#42ad8a; margin:0 0 16px 0;
            }
            .tr-divider { border-top:1px solid #e2e8f0; padding-top:24px; }
            .tr-helper { font-size:17px; color:#64748b; margin:6px 0 0 0; }

            /* Medicine autocomplete dropdown */
            .tr-med-wrap { position: relative; }
            .tr-med-drop {
                display:none; position:absolute; top:calc(100% + 4px); left:0; right:0;
                background:#fff; border:2px solid #42ad8a; border-radius:12px;
                box-shadow: 0 12px 32px rgba(0,0,0,0.12);
                max-height:340px; overflow-y:auto; z-index:9999;
            }
            .tr-med-drop .tr-sug {
                padding:14px 18px; cursor:pointer; border-bottom:1px solid #f1f5f9;
                transition: background 0.1s;
            }
            .tr-med-drop .tr-sug:last-child { border-bottom:none; }
            .tr-med-drop .tr-sug:hover { background:#E8F7F4; }
            .tr-med-drop .tr-sug-name { font-weight:600; font-size:19px; color:#0f172a; }
            .tr-med-drop .tr-sug-meta { font-size:17px; color:#64748b; margin-top:2px; }
            .tr-med-drop .tr-empty { padding:16px 18px; font-size:18px; color:#94a3b8; text-align:center; }
            .tr-med-drop .tr-group-title {
                padding:10px 18px; font-size:15px; font-weight:700;
                text-transform:uppercase; letter-spacing:.08em;
                color:#64748b; background:#f8fafc; border-bottom:1px solid #f1f5f9;
            }

            .tr-btn {
                padding:14px 32px; border-radius:12px;
                font-weight:600; font-size:20px; cursor:pointer;
                transition: background 0.2s, border-color 0.2s;
                text-decoration:none; display:inline-block;
            }
        </style>

        <form method="POST" action="{{ route('transfers.store') }}" style="display:flex; flex-direction:column; gap:24px;">
            @csrf

            {{-- SECTION 1 — MEDICINE --}}
            <div>
                <p class="tr-section-title">Medicine Details</p>

                <div class="tr-field tr-med-wrap" style="margin-bottom:16px;">
                    <label for="medicine-search">Medicine</label>
                    <input type="text" id="medicine-search" autocomplete="off"
                           placeholder="Type to search medicine...">
                    <input type="hidden" name="medicine_id" id="medicine-id">
                    <div id="medicine-dropdown" class="tr-med-drop"></div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                    <div class="tr-field">
                        <label for="snap-category">Program Category</label>
                        <select id="snap-category" name="category" required>
                            <option value="">— Select category —</option>
                            <option value="medicine">Medicine</option>
                            <option value="vaccine">Vaccine</option>
                            <option value="family_planning">Family Planning</option>
                            <option value="family_health_nutrition">Maternal &amp; Child Health</option>
                            <option value="emerging_reemerging">Disease Prevention &amp; Control</option>
                            <option value="non_communicable">NCD &amp; Lifestyle</option>
                            <option value="dengue_prevention">Dengue Prevention</option>
                            <option value="tuberculosis">Tuberculosis</option>
                        </select>
                    </div>

                    <div class="tr-field">
                        <label for="snap-form">Dosage Form</label>
                        <select id="snap-form" name="dosage_form">
                            <option value="">— Select category first —</option>
                        </select>
                    </div>
                </div>

                <div class="tr-field">
                    <label for="snap-dosage">Dosage Strength</label>
                    <input type="text" id="snap-dosage" name="dosage"
                           placeholder="e.g., 500 mg, 250 mg/5 mL" maxlength="100">
                </div>
            </div>

            {{-- SECTION 2 — SOURCE + QUANTITY --}}
            <div class="tr-divider">
                <p class="tr-section-title">Source &amp; Quantity</p>

                <div style="display:grid; grid-template-columns:2fr 1fr; gap:16px;">
                    <div class="tr-field">
                        <label for="source-rhu">Source RHU</label>
                        <select name="source_rhu_id" id="source-rhu" required onchange="loadStockInfo()">
                            <option value="">— Select medicine first —</option>
                        </select>
                        <div id="source-info" style="margin-top:8px; font-size:18px; color:#0f5c4b;"></div>
                    </div>

                    <div class="tr-field">
                        <label for="quantity">
                            Quantity <span id="qty-unit-label" style="font-weight:400; color:#64748b;"></span>
                        </label>
                        <input type="number" name="quantity" id="quantity" min="1" required value="10"
                               style="text-align:right;">
                        <p id="qty-help" class="tr-helper"></p>
                    </div>
                </div>
            </div>

            {{-- SECTION 3 — NOTES --}}
            <div class="tr-divider">
                <div class="tr-field">
                    <label for="notes">Notes (optional)</label>
                    <textarea name="notes" id="notes" rows="3" maxlength="500"
                              placeholder="Why do you need this stock? Any specific use or urgency?"></textarea>
                </div>
            </div>

            {{-- Actions --}}
            <div style="display:flex; gap:12px; border-top:1px solid #e2e8f0; padding-top:24px;">
                <button type="submit" class="tr-btn" style="background:#42ad8a; color:#ffffff; border:none;"
                        onmouseover="this.style.background='#369f7d'"
                        onmouseout="this.style.background='#42ad8a'">
                    Send Transfer Request
                </button>
                <a href="{{ route('transfers.index') }}" class="tr-btn"
                   style="background:#ffffff; color:#334155; border:1px solid #e2e8f0;"
                   onmouseover="this.style.background='#f8fafc'"
                   onmouseout="this.style.background='#ffffff'">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
const FORMS_BY_CATEGORY = {
    'medicine':              ['Tablet', 'Capsule', 'Syrup', 'Suspension', 'Injection', 'Cream', 'Ointment', 'Drops', 'Inhaler', 'Suppository', 'Patch', 'Granules', 'Powder'],
    'vaccine':               ['Injection'],
    'family_planning':       ['Tablet', 'Injection', 'Implant', 'IUD', 'Condom', 'Patch', 'Vaginal Ring'],
    'family_health_nutrition': ['Tablet', 'Capsule', 'Syrup', 'Suspension', 'Injection', 'Drops'],
    'emerging_reemerging':    ['Tablet', 'Capsule', 'Syrup', 'Injection', 'Suspension'],
    'non_communicable':         ['Tablet', 'Capsule', 'Injection', 'Inhaler', 'Patch'],
};

let sourceOptions  = [];
let pickedMedicine = null;

document.addEventListener('DOMContentLoaded', function () {
    const input    = document.getElementById('medicine-search');
    const dropdown = document.getElementById('medicine-dropdown');
    const hidden   = document.getElementById('medicine-id');
    const qtyInput = document.getElementById('quantity');
    const catSel   = document.getElementById('snap-category');
    const formSel  = document.getElementById('snap-form');
    let controller = null;
    let debounce   = null;

    input.addEventListener('input', function () {
        const q = this.value.trim();
        hidden.value = '';
        pickedMedicine = null;
        resetMedicineFields();

        clearTimeout(debounce);
        if (q.length < 2) { hideDropdown(); return; }

        debounce = setTimeout(() => searchMedicines(q), 180);
    });

    input.addEventListener('focus', function () {
        if (this.value.trim().length >= 2) {
            searchMedicines(this.value.trim());
        } else {
            showRecommendations();
        }
    });

    catSel.addEventListener('change', function () {
        const keepForm = formSel.value;
        populateFormOptions(this.value, keepForm);
    });

    qtyInput.addEventListener('input', renderQtyHelp);

    document.addEventListener('click', e => {
        const shell = input.closest('.tr-med-wrap');
        if (shell && !shell.contains(e.target)) hideDropdown();
    });

    function hideDropdown() {
        dropdown.style.display = 'none';
        dropdown.innerHTML = '';
    }

    function searchMedicines(q) {
        if (controller) controller.abort();
        controller = new AbortController();

        fetch(`/search/public-autocomplete?q=${encodeURIComponent(q)}`, {
            signal: controller.signal,
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(items => renderResults(items, q))
        .catch(err => { if (err.name !== 'AbortError') console.error(err); });
    }

    function showRecommendations() {
        fetch(`/search/public-autocomplete?q=a`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(items => {
            if (!items || !items.length) { hideDropdown(); return; }
            renderResults(items.slice(0, 6), '', true);
        })
        .catch(() => hideDropdown());
    }

    function renderResults(items, query, isRecommendation = false) {
        dropdown.innerHTML = '';

        if (!items || !items.length) {
            dropdown.innerHTML = `<div class="tr-empty">No medicines match "${escapeHtml(query)}"</div>`;
            dropdown.style.display = 'block';
            return;
        }

        if (isRecommendation) {
            const title = document.createElement('div');
            title.className = 'tr-group-title';
            title.textContent = 'Recommended';
            dropdown.appendChild(title);
        }

        items.forEach(item => {
            const row = document.createElement('div');
            row.className = 'tr-sug';

            const meta = [item.dosage, item.dosage_form].filter(Boolean).join(' · ');

            row.innerHTML = `
                <div class="tr-sug-name">${highlight(item.text || '', query)}</div>
                ${meta ? `<div class="tr-sug-meta">${escapeHtml(meta)}</div>` : ''}
            `;

            row.addEventListener('click', () => {
                input.value   = item.text || '';
                hidden.value  = item.id;
                pickedMedicine = item;
                hideDropdown();
                applyMedicine(item);
                fetchSources(item.id);
            });

            dropdown.appendChild(row);
        });

        dropdown.style.display = 'block';
    }

    function highlight(text, query) {
        const safe = escapeHtml(text);
        if (!query) return safe;
        const idx = safe.toLowerCase().indexOf(escapeHtml(query).toLowerCase());
        if (idx < 0) return safe;
        return safe.slice(0, idx)
             + `<span style="background:#FEF3C7;color:#92400E;padding:0 2px;border-radius:3px;">${safe.slice(idx, idx + query.length)}</span>`
             + safe.slice(idx + query.length);
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
});

function resetMedicineFields() {
    document.getElementById('snap-dosage').value     = '';
    document.getElementById('snap-form').innerHTML   = '<option value="">— Select category first —</option>';
    document.getElementById('snap-category').value   = '';
    document.getElementById('qty-unit-label').textContent = '';
    document.getElementById('qty-help').textContent = '';

    const sourceSelect = document.getElementById('source-rhu');
    sourceSelect.innerHTML = '<option value="">— Select medicine first —</option>';
    document.getElementById('source-info').textContent = '';
}

function applyMedicine(m) {
    const catSel = document.getElementById('snap-category');
    const catVal = m.category || 'medicine';
    catSel.value = catVal;

    populateFormOptions(catVal, m.dosage_form || '');

    document.getElementById('snap-dosage').value = m.dosage || '';

    const unitLabel = document.getElementById('qty-unit-label');
    unitLabel.textContent = m.unit ? `(${m.unit}s)` : '';

    renderQtyHelp();
}

function populateFormOptions(category, selectedForm) {
    const select = document.getElementById('snap-form');
    select.innerHTML = '';

    if (!category) {
        select.innerHTML = '<option value="">— Select category first —</option>';
        return;
    }

    const forms = (FORMS_BY_CATEGORY[category] || FORMS_BY_CATEGORY['medicine']).slice();

    if (selectedForm && !forms.includes(selectedForm)) {
        forms.unshift(selectedForm);
    }

    select.innerHTML = '<option value="">— Select form —</option>';
    forms.forEach(f => {
        const opt = document.createElement('option');
        opt.value = f;
        opt.textContent = f;
        select.appendChild(opt);
    });

    if (selectedForm) select.value = selectedForm;
}

function renderQtyHelp() {
    const help = document.getElementById('qty-help');
    if (!pickedMedicine) { help.textContent = ''; return; }

    const qty = parseInt(document.getElementById('quantity').value || 0, 10);
    if (qty < 1) { help.textContent = ''; return; }

    const unit = pickedMedicine.unit || 'unit';
    help.innerHTML = `<strong style="color:#0f5c4b;">= ${qty} ${qty === 1 ? unit : unit + 's'}</strong>`;
}

function fetchSources(medicineId) {
    fetch(`{{ route('stock_lookup.search') }}?medicine_id=${medicineId}`, {
        headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        const select = document.getElementById('source-rhu');
        select.innerHTML = '<option value="">— Select source RHU —</option>';
        sourceOptions = data.results || [];
        sourceOptions.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.rhu_id;
            opt.textContent = `${r.rhu_name} (${r.available_stock} units · ${r.distance_km ?? '?'} km)`;
            select.appendChild(opt);
        });
    });
}

function loadStockInfo() {
    const val  = document.getElementById('source-rhu').value;
    const info = document.getElementById('source-info');
    const qty  = document.getElementById('quantity');
    if (!val) { info.textContent = ''; return; }

    const r = sourceOptions.find(s => s.rhu_id == val);
    if (r) {
        info.innerHTML = `Available: <strong>${r.available_stock}</strong> units · ${r.distance_km ?? '?'} km away · ${r.municipality ?? ''}`;
        qty.max = r.available_stock;
    }
}
</script>
@endsection