@extends('layouts.app')

@php
    $medicinesJson = $medicines->map(function ($m) {
        return [
            'id'       => $m->id,
            'name'     => $m->medicine_name,
            'dosage'   => $m->dosage,
            'unit'     => $m->unit,
            'category' => $m->category,
        ];
    })->values();
@endphp

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- ═══════ HEADER ═══════ --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
        display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;
    ">
        <div>
            <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">
                Distribution
            </p>
            <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">
                Direct an RHU to Transfer Stock
            </h2>
        </div>

        <div style="display:flex; gap:8px;">
            <a href="{{ route('transfers.index') }}" style="
                padding:10px 18px; border-radius:10px; background:white; color:#334155;
                border:1px solid #e2e8f0; font-size:14px; font-weight:600; text-decoration:none;">
                ← Back to Transfers
            </a>
        </div>
    </div>

    @if(session('success'))
        <div style="background:#eaf3de; border:1px solid #c0dd97; border-radius:12px; padding:14px 20px; color:#3b6d11; font-size:14px;">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div style="background:#fcebeb; border:1px solid #f7c1c1; border-radius:12px; padding:14px 20px; color:#a32d2d; font-size:14px;">
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div style="background:#fcebeb; border:1px solid #f7c1c1; border-radius:12px; padding:14px 20px; color:#a32d2d; font-size:14px;">
            <strong>Please fix the following:</strong>
            <ul style="margin:6px 0 0 0; padding-left:20px;">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ═══════ FORM CARD ═══════ --}}
    <div style="
        background:rgba(255,255,255,0.95); border-radius:20px;
        border:1px solid rgba(66,173,138,0.15); box-shadow:0 8px 20px rgba(0,0,0,0.06);
        padding:28px;
    ">
        <form method="POST" action="{{ route('transfers.distribute.store') }}"
              style="display:flex; flex-direction:column; gap:24px;">
            @csrf

            {{-- ─────────── SECTION: ROUTING ─────────── --}}
            <div>
                <p style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0 0 16px 0;">
                    Routing
                </p>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:600; color:#0f172a; margin-bottom:6px;">
                            Source RHU (sends)
                        </label>
                        <select name="from_health_unit_id" required
                                style="width:100%; padding:12px 16px; font-size:15px; border:2px solid #bec6cf; border-radius:12px; background:white; color:#0f172a; box-sizing:border-box; appearance:auto; outline:none;"
                                onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                                onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                            <option value="">— Select source RHU —</option>
                            @foreach($healthUnits as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }} — {{ $unit->municipality }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label style="display:block; font-size:14px; font-weight:600; color:#0f172a; margin-bottom:6px;">
                            Destination RHU (receives)
                        </label>
                        <select name="to_health_unit_id" required
                                style="width:100%; padding:12px 16px; font-size:15px; border:2px solid #bec6cf; border-radius:12px; background:white; color:#0f172a; box-sizing:border-box; appearance:auto; outline:none;"
                                onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                                onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                            <option value="">— Select destination RHU —</option>
                            @foreach($healthUnits as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }} — {{ $unit->municipality }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div style="border-top:1px solid #e2e8f0;"></div>

            {{-- ─────────── SECTION: MEDICINE ─────────── --}}
            <div>
                <p style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0 0 16px 0;">
                    Medicine Details
                </p>

                {{-- Row 1: Program Category + Searchable Medicine --}}
                <div style="display:grid; grid-template-columns:1fr 2fr; gap:16px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:600; color:#0f172a; margin-bottom:6px;">
                            Program Category
                        </label>
                        <select id="category-select" onchange="onCategoryChange()"
                                style="width:100%; padding:12px 16px; font-size:15px; border:2px solid #bec6cf; border-radius:12px; background:white; color:#0f172a; box-sizing:border-box; appearance:auto; outline:none; cursor:pointer;"
                                onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                                onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                            <option value="">All Categories</option>
                            <option value="Medicine">Medicine</option>
                            <option value="Vaccine">Vaccine</option>
                            <option value="Family Planning">Family Planning</option>
                            <option value="Family Health & Nutrition">Family Health &amp; Nutrition</option>
                            <option value="Emerging & Re-emerging">Emerging &amp; Re-emerging</option>
                            <option value="Non-Communicable Disease">Non-Communicable Disease</option>
                            <option value="Dengue Prevention">Dengue Prevention</option>
                            <option value="Tuberculosis">Tuberculosis</option>
                        </select>
                    </div>

                    {{-- Medicine search field --}}
                    <div>
                        <label style="display:block; font-size:14px; font-weight:600; color:#0f172a; margin-bottom:6px;">
                            Medicine
                        </label>

                        <div style="position:relative;" id="medicine-input-shell">
                            <input type="text"
                                   id="medicine-search"
                                   autocomplete="off"
                                   placeholder="Type to search medicines..."
                                   oninput="onMedicineInput(this.value)"
                                   onkeydown="onMedicineKeydown(event)"
                                   onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'; if(this.value.length >= 1) onMedicineInput(this.value);"
                                   onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none';"
                                   style="width:100%; padding:12px 44px 12px 16px; font-size:15px; border:2px solid #bec6cf; border-radius:12px; background:white; color:#0f172a; box-sizing:border-box; outline:none; transition:border-color 0.2s, box-shadow 0.2s;">

                            <button type="button"
                                    id="clear-medicine-btn"
                                    onclick="clearMedicine()"
                                    style="position:absolute; right:12px; top:50%; transform:translateY(-50%);
                                           width:28px; height:28px;
                                           display:none; align-items:center; justify-content:center;
                                           background:transparent; border:none; cursor:pointer;
                                           border-radius:6px; color:#64748b; font-size:16px; line-height:1; padding:0;">✕</button>

                            <div id="medicine-suggestions"
                                 style="display:none; position:absolute; top:calc(100% + 4px); left:0; right:0;
                                        background:white; border:2px solid #42ad8a; border-radius:12px;
                                        box-shadow: 0 12px 32px rgba(0,0,0,0.12);
                                        max-height:340px; overflow-y:auto; z-index:9999;">
                            </div>
                        </div>

                        <input type="hidden" name="medicine_id" id="medicine-id-hidden" required>
                    </div>
                </div>

                {{-- Row 2: Dosage Strength + Dosage Form --}}
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:600; color:#0f172a; margin-bottom:6px;">
                            Dosage Strength
                        </label>
                        <input type="text" name="dosage" id="dosage-input"
                               placeholder="e.g. 500 mg"
                               style="width:100%; padding:12px 16px; font-size:15px; border:2px solid #bec6cf; border-radius:12px; background:white; color:#0f172a; box-sizing:border-box; outline:none;"
                               onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                               onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    </div>

                    <div>
                        <label style="display:block; font-size:14px; font-weight:600; color:#0f172a; margin-bottom:6px;">
                            Dosage Form
                        </label>
                        <input type="text" name="dosage_form" id="dosage_form-input"
                               placeholder="e.g. Capsule, Tablet, Syrup"
                               style="width:100%; padding:12px 16px; font-size:15px; border:2px solid #bec6cf; border-radius:12px; background:white; color:#0f172a; box-sizing:border-box; outline:none;"
                               onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                               onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    </div>
                </div>

                {{-- Hidden snapshot inputs --}}
                <input type="hidden" name="unit"     id="unit-hidden">
                <input type="hidden" name="category" id="category-hidden">

                {{-- Snapshot card --}}
                <div id="medicine-snapshot" style="display:none; background:#E8F7F4; border:1px solid #42ad8a; border-radius:14px; padding:16px 18px;">
                    <p style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#0f5c4b; margin:0 0 12px 0;">
                        Medicine Snapshot
                    </p>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px 20px; font-size:14px;">
                        <div>
                            <div style="font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#64748b;">Medicine Name</div>
                            <div style="font-weight:600; color:#0f172a; margin-top:2px;" id="snap-name">—</div>
                        </div>
                        <div>
                            <div style="font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#64748b;">Base Unit</div>
                            <div style="font-weight:600; color:#0f172a; margin-top:2px;" id="snap-unit">—</div>
                        </div>
                        <div style="grid-column:1/-1;">
                            <div style="font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#64748b;">Program Category</div>
                            <div style="font-weight:600; color:#0f172a; margin-top:2px;" id="snap-category">—</div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="border-top:1px solid #e2e8f0;"></div>

            {{-- ─────────── SECTION: QUANTITY & NOTES ─────────── --}}
            <div>
                <p style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0 0 16px 0;">
                    Quantity &amp; Notes
                </p>

                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:600; color:#0f172a; margin-bottom:6px;">
                        Quantity <span id="qty-unit-label" style="font-weight:400; color:#64748b;"></span>
                    </label>
                    <input type="number" name="quantity" id="quantity" min="1" required value="10"
                           oninput="renderQtyHelp()"
                           style="width:100%; padding:12px 16px; font-size:15px; border:2px solid #bec6cf; border-radius:12px; background:white; color:#0f172a; box-sizing:border-box; outline:none;"
                           onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                           onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <p id="qty-help" style="font-size:13px; color:#64748b; margin:6px 0 0 0;"></p>
                    <p style="font-size:12px; color:#94a3b8; margin:6px 0 0 0; font-style:italic;">
                        The source RHU will reserve a specific batch (FIFO). Pack format (Stub/Box) is determined at that time.
                    </p>
                </div>

                <div>
                    <label style="display:block; font-size:14px; font-weight:600; color:#0f172a; margin-bottom:6px;">
                        Notes (optional)
                    </label>
                    <textarea name="notes" rows="3" maxlength="500"
                              style="width:100%; padding:12px 16px; font-size:15px; border:2px solid #bec6cf; border-radius:12px; background:white; color:#0f172a; box-sizing:border-box; outline:none; resize:vertical;"
                              onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                              onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'"></textarea>
                </div>
            </div>

            {{-- ─────────── BUTTONS ─────────── --}}
            <div style="display:flex; gap:10px; padding-top:24px; border-top:1px solid #e2e8f0;">
                <button type="submit" style="
                    padding:10px 18px; border-radius:10px; background:#42ad8a; color:white;
                    font-size:14px; font-weight:600; border:none; cursor:pointer;
                    transition: background 0.2s;
                " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                    Send Directive
                </button>

                <a href="{{ route('transfers.index') }}" style="
                    padding:10px 18px; border-radius:10px; background:white; color:#334155;
                    border:1px solid #e2e8f0; font-size:14px; font-weight:600; text-decoration:none;
                    transition: background 0.2s;
                " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// ─── All medicines passed from server ───
const ALL_MEDICINES = @json($medicinesJson);

// ─── State ───
let pickedMedicine     = null;
let highlightedIndex   = -1;
let currentSuggestions = [];

function normalize(s) {
    return (s || '').toLowerCase().replace(/[_\s\-&]/g, '');
}

function humanizeCategory(category) {
    const map = {
        'medicine'                : 'Medicine',
        'vaccine'                 : 'Vaccine',
        'family_planning'         : 'Family Planning',
        'family_health_nutrition' : 'Family Health & Nutrition',
        'emerging_reemerging'     : 'Emerging & Re-emerging',
        'non_communicable'        : 'Non-Communicable Disease',
        'dengue_prevention'       : 'Dengue Prevention',
        'tuberculosis'            : 'Tuberculosis',
    };
    if (!category) return '—';
    return map[category] || category.charAt(0).toUpperCase() + category.slice(1).replace(/_/g, ' ');
}

function capitalize(s) {
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : s;
}

function getFilteredPool() {
    const category = document.getElementById('category-select').value;
    if (!category) return ALL_MEDICINES;
    const normCat = normalize(category);
    return ALL_MEDICINES.filter(m => {
        const optCat = normalize(m.category || '');
        return optCat === normCat || optCat.includes(normCat) || normCat.includes(optCat);
    });
}

function onCategoryChange() {
    clearMedicine(true);
}

function onMedicineInput(query) {
    const trimmed = query.trim();

    document.getElementById('clear-medicine-btn').style.display = trimmed ? 'flex' : 'none';

    if (pickedMedicine) {
        pickedMedicine = null;
        document.getElementById('medicine-id-hidden').value = '';
        document.getElementById('medicine-snapshot').style.display = 'none';
        document.getElementById('unit-hidden').value = '';
        document.getElementById('category-hidden').value = '';
        document.getElementById('qty-unit-label').textContent = '';
        document.getElementById('qty-help').textContent = '';
    }

    if (trimmed.length < 1) {
        hideSuggestions();
        return;
    }

    const q = trimmed.toLowerCase();
    const pool = getFilteredPool();

    const matches = pool
        .filter(m => (m.name || '').toLowerCase().includes(q))
        .sort((a, b) => {
            const aName = (a.name || '').toLowerCase();
            const bName = (b.name || '').toLowerCase();
            const aStarts = aName.startsWith(q) ? 0 : 1;
            const bStarts = bName.startsWith(q) ? 0 : 1;
            if (aStarts !== bStarts) return aStarts - bStarts;
            return aName.localeCompare(bName);
        })
        .slice(0, 12);

    currentSuggestions = matches;
    highlightedIndex   = -1;
    renderSuggestions(matches, trimmed);
}

function renderSuggestions(matches, query) {
    const box = document.getElementById('medicine-suggestions');

    if (matches.length === 0) {
        box.innerHTML = `<div style="padding:14px 16px; font-size:14px; color:#94a3b8; text-align:center;">No medicines match "${escapeHtml(query)}"</div>`;
        box.style.display = 'block';
        return;
    }

    box.innerHTML = matches.map((m, i) => `
        <div class="med-suggestion-item"
             data-index="${i}"
             onclick="pickMedicine(${i})"
             onmouseover="highlightSuggestion(${i})"
             style="padding:12px 16px; cursor:pointer; border-bottom:1px solid #f1f5f9; transition:background 0.1s;">
            <div style="font-weight:600; color:#0f172a; font-size:14px;">${highlightMatch(m.name, query)}</div>
            <div style="font-size:12px; color:#64748b; margin-top:2px;">
                ${m.dosage ? escapeHtml(m.dosage) : ''}${m.unit ? (m.dosage ? ' · ' : '') + capitalize(m.unit) : ''}${m.category ? ((m.dosage || m.unit) ? ' · ' : '') + humanizeCategory(m.category) : ''}
            </div>
        </div>
    `).join('');

    box.style.display = 'block';
}

function highlightSuggestion(index) {
    highlightedIndex = index;
    document.querySelectorAll('.med-suggestion-item').forEach(el => {
        const i = parseInt(el.dataset.index);
        el.style.background = i === index ? '#E8F7F4' : 'transparent';
    });
}

function scrollToHighlighted() {
    const el = document.querySelector(`.med-suggestion-item[data-index="${highlightedIndex}"]`);
    if (el) el.scrollIntoView({ block: 'nearest' });
}

function pickMedicine(index) {
    const m = currentSuggestions[index];
    if (!m) return;

    pickedMedicine = m;

    document.getElementById('medicine-search').value    = m.name;
    document.getElementById('medicine-id-hidden').value = m.id;
    document.getElementById('clear-medicine-btn').style.display = 'flex';

    document.getElementById('unit-hidden').value     = m.unit     || '';
    document.getElementById('category-hidden').value = m.category || '';

    // Auto-fill dosage strength from the medicine record
    const dosageInput = document.getElementById('dosage-input');
    if (m.dosage && !dosageInput.value) {
        dosageInput.value = m.dosage;
    }

    document.getElementById('snap-name').textContent     = m.name || '—';
    document.getElementById('snap-unit').textContent     = m.unit ? capitalize(m.unit) : '—';
    document.getElementById('snap-category').textContent = humanizeCategory(m.category);

    document.getElementById('medicine-snapshot').style.display = 'block';
    document.getElementById('qty-unit-label').textContent = m.unit ? `(${m.unit}s)` : '';

    hideSuggestions();
    renderQtyHelp();
}

function clearMedicine(silent) {
    document.getElementById('medicine-search').value = '';
    document.getElementById('medicine-id-hidden').value = '';
    document.getElementById('clear-medicine-btn').style.display = 'none';

    document.getElementById('medicine-snapshot').style.display = 'none';
    document.getElementById('unit-hidden').value = '';
    document.getElementById('category-hidden').value = '';
    document.getElementById('qty-unit-label').textContent = '';
    document.getElementById('qty-help').textContent = '';

    pickedMedicine = null;
    hideSuggestions();
}

function hideSuggestions() {
    document.getElementById('medicine-suggestions').style.display = 'none';
    highlightedIndex = -1;
}

function onMedicineKeydown(e) {
    const box    = document.getElementById('medicine-suggestions');
    const isOpen = box.style.display === 'block';

    if (e.key === 'ArrowDown' && isOpen) {
        e.preventDefault();
        highlightedIndex = Math.min(highlightedIndex + 1, currentSuggestions.length - 1);
        highlightSuggestion(highlightedIndex);
        scrollToHighlighted();
    } else if (e.key === 'ArrowUp' && isOpen) {
        e.preventDefault();
        highlightedIndex = Math.max(highlightedIndex - 1, 0);
        highlightSuggestion(highlightedIndex);
        scrollToHighlighted();
    } else if (e.key === 'Enter') {
        if (isOpen && highlightedIndex >= 0) {
            e.preventDefault();
            pickMedicine(highlightedIndex);
        } else if (isOpen && currentSuggestions.length > 0) {
            e.preventDefault();
            pickMedicine(0);
        }
    } else if (e.key === 'Escape') {
        hideSuggestions();
    }
}

// Close suggestions when clicking outside the shell
document.addEventListener('click', function (e) {
    const shell = document.getElementById('medicine-input-shell');
    if (shell && !shell.contains(e.target)) {
        hideSuggestions();
    }
});

function renderQtyHelp() {
    const help = document.getElementById('qty-help');
    if (!pickedMedicine) { help.textContent = ''; return; }

    const qty = parseInt(document.getElementById('quantity').value || 0, 10);
    if (qty < 1) { help.textContent = ''; return; }

    const unit = pickedMedicine.unit || 'unit';
    help.innerHTML = `<strong style="color:#0f5c4b;">= ${qty} ${qty === 1 ? unit : unit + 's'}</strong>`;
}

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function highlightMatch(text, query) {
    const safe = escapeHtml(text);
    if (!query) return safe;
    const idx = safe.toLowerCase().indexOf(escapeHtml(query).toLowerCase());
    if (idx < 0) return safe;
    const before = safe.slice(0, idx);
    const match  = safe.slice(idx, idx + query.length);
    const after  = safe.slice(idx + query.length);
    return `${before}<span style="background:#FEF3C7; color:#92400E; padding:0 2px; border-radius:3px;">${match}</span>${after}`;
}
</script>
@endsection