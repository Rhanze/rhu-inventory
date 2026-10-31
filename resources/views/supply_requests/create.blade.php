@extends('layouts.app')

@php
    // Prepare medicines JSON for the JS autocomplete, enriched with stock info
    $medicinesJson = $medicines->map(function ($m) use ($stockLevels) {
        $info = $stockLevels[$m->id] ?? null;
        return [
            'id'         => $m->id,
            'name'       => $m->medicine_name,
            'dosage'     => $m->dosage,
            'form'       => $m->dosage_form,
            'unit'       => $m->unit,
            'stock'      => $info ? (int) $info['total'] : 0,
            'low'        => $info['low'] ?? false,
            'near_expiry'=> $info['near_expiry'] ?? null,
        ];
    })->values();
@endphp

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius: 20px; padding: 24px 28px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 16px;">
        <div>
            <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">
                Supply
            </p>
            <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">
                New Supply Request
            </h2>
            <p style="font-size:14px; color:#475569; margin:6px 0 0 0;">
                Request stock from your RHU for immunization, prenatal care, family planning, or outreach.
            </p>
        </div>
        <a href="{{ route('supply_requests.index') }}" style="
            padding:10px 18px; border-radius:10px; background:white; color:#334155;
            border:1px solid #e2e8f0; font-size:16px; font-weight:600; text-decoration:none;">
            ← Back
        </a>
    </div>

    @if($errors->any())
        <div style="background:#FCEBEB; border:1px solid #F7C1C1; border-radius:12px; padding:14px 20px; color:#991b1b; font-size:16px;">
            <strong>Please fix the following:</strong>
            <ul style="margin:6px 0 0 0; padding-left:20px;">
                @foreach($errors->all() as $err) <li>{{ $err }}</li> @endforeach
            </ul>
        </div>
    @endif

    <div style="
        background: rgba(255,255,255,0.95); border-radius: 20px;
        border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        padding: 28px;">
        <form action="{{ route('supply_requests.store') }}" method="POST"
              style="display:flex; flex-direction:column; gap:20px;">
            @csrf

            {{-- ─────── MEDICINE (searchable) ─────── --}}
            <div>
                <label style="display:block; font-size:14px; font-weight:700; color:#0f172a; margin-bottom:6px;">
                    Medicine <span style="color:#dc2626;">*</span>
                </label>

                <div style="position:relative;" id="medicine-input-shell">
                    <input type="text"
                           id="medicine-search"
                           autocomplete="off"
                           placeholder="Type medicine name to search..."
                           oninput="onMedicineInput(this.value)"
                           onkeydown="onMedicineKeydown(event)"
                           onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'; if(this.value.length >= 1) onMedicineInput(this.value);"
                           onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none';"
                           style="width:100%; height:48px; padding:0 44px 0 16px; font-size:16px;
                                  border:2px solid #bec6cf; border-radius:12px;
                                  background:#fff; color:#0f172a;
                                  box-sizing:border-box; outline:none;">

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
                                background:#fff; border:2px solid #42ad8a; border-radius:12px;
                                box-shadow: 0 12px 32px rgba(0,0,0,0.12);
                                max-height:360px; overflow-y:auto; z-index:9999;">
                    </div>
                </div>

                <input type="hidden" name="medicine_id" id="medicine-id-hidden"
                       value="{{ old('medicine_id', $preselectId) }}" required>

                {{-- Live stock panel — populates when a medicine is picked --}}
                <div id="stock-panel" style="
                    display:none; margin-top:10px; padding:12px 16px;
                    border-radius:12px; font-size:15px; line-height:1.5;
                    background:#f8fafc; border:1px solid #e2e8f0;">
                </div>
            </div>

            {{-- ─────── QUANTITY + URGENCY ─────── --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; font-size:14px; font-weight:700; color:#0f172a; margin-bottom:6px;">
                        Quantity <span style="color:#dc2626;">*</span>
                    </label>
                    <input type="number" name="quantity" id="quantity" min="1" required
                           value="{{ old('quantity', 1) }}"
                           style="width:100%; height:48px; padding:0 16px; font-size:16px;
                                  border:2px solid #bec6cf; border-radius:12px;
                                  background:#fff; color:#0f172a; box-sizing:border-box; outline:none;"
                           onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                           onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <p id="qty-help" style="font-size:13px; color:#64748b; margin:6px 0 0 0;"></p>
                </div>
                <div>
                    <label style="display:block; font-size:14px; font-weight:700; color:#0f172a; margin-bottom:6px;">
                        Urgency <span style="color:#dc2626;">*</span>
                    </label>
                    <select name="urgency" required
                            style="width:100%; height:48px; padding:0 16px; font-size:16px;
                                   border:2px solid #bec6cf; border-radius:12px;
                                   background:#fff; color:#0f172a; box-sizing:border-box; outline:none;"
                            onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                            onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                        <option value="normal"    {{ old('urgency') === 'normal'    ? 'selected' : '' }}>Normal</option>
                        <option value="urgent"    {{ old('urgency') === 'urgent'    ? 'selected' : '' }}>Urgent</option>
                        <option value="emergency" {{ old('urgency') === 'emergency' ? 'selected' : '' }}>Emergency</option>
                    </select>
                </div>
            </div>

            {{-- ─────── PURPOSE ─────── --}}
            <div>
                <label style="display:block; font-size:14px; font-weight:700; color:#0f172a; margin-bottom:6px;">
                    Purpose <span style="color:#dc2626;">*</span>
                </label>
                <input type="text" name="purpose" required maxlength="255"
                       value="{{ old('purpose') }}"
                       placeholder="e.g., Immunization outreach — Barangay Poblacion, Oct 15"
                       style="width:100%; height:48px; padding:0 16px; font-size:16px;
                              border:2px solid #bec6cf; border-radius:12px;
                              background:#fff; color:#0f172a; box-sizing:border-box; outline:none;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                <p style="font-size:13px; color:#64748b; margin:6px 0 0 0;">
                    Tell your RHU admin what this stock is for — the activity, the barangay, or the date.
                </p>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end; padding-top:8px;">
                <a href="{{ route('supply_requests.index') }}" style="
                    padding:12px 24px; border-radius:10px; background:#fff; color:#334155;
                    border:1px solid #e2e8f0; font-size:16px; font-weight:600; text-decoration:none;">
                    Cancel
                </a>
                <button type="submit" style="
                    padding:12px 24px; border-radius:10px; background:#42ad8a; color:#fff;
                    border:none; font-size:16px; font-weight:700; cursor:pointer;">
                    Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ─── All medicines passed from server, with stock info ───
const ALL_MEDICINES = @json($medicinesJson);

// ─── State ───
let pickedMedicine   = null;
let highlightedIndex = -1;
let currentSuggestions = [];

function capitalize(s) {
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : s;
}

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ═══ Search input handlers ═══
function onMedicineInput(query) {
    const trimmed = query.trim();

    document.getElementById('clear-medicine-btn').style.display = trimmed ? 'flex' : 'none';

    if (pickedMedicine) {
        pickedMedicine = null;
        document.getElementById('medicine-id-hidden').value = '';
        hideStockPanel();
        document.getElementById('qty-help').textContent = '';
    }

    if (trimmed.length < 1) {
        hideSuggestions();
        return;
    }

    const q = trimmed.toLowerCase();
    const matches = ALL_MEDICINES
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
    highlightedIndex = -1;
    renderSuggestions(matches, trimmed);
}

function renderSuggestions(matches, query) {
    const box = document.getElementById('medicine-suggestions');

    if (matches.length === 0) {
        box.innerHTML = `<div style="padding:14px 16px; font-size:14px; color:#94a3b8; text-align:center;">No medicines match "${escapeHtml(query)}"</div>`;
        box.style.display = 'block';
        return;
    }

    box.innerHTML = matches.map((m, i) => {
        // Stock badge
        let stockHtml = '';
        if (m.stock === 0) {
            stockHtml = `<span style="font-size:12px; font-weight:700; color:#991b1b; background:#FCEBEB; border:1px solid #F7C1C1; padding:2px 8px; border-radius:999px;">No stock</span>`;
        } else if (m.low) {
            stockHtml = `<span style="font-size:12px; font-weight:700; color:#92400E; background:#FEF3C7; border:1px solid #FAC775; padding:2px 8px; border-radius:999px;">⚠ ${m.stock} on hand</span>`;
        } else {
            stockHtml = `<span style="font-size:12px; font-weight:700; color:#0f5c4b; background:#E8F7F4; border:1px solid #42ad8a; padding:2px 8px; border-radius:999px;">${m.stock} on hand</span>`;
        }

        const meta = [m.dosage, m.form].filter(Boolean).join(' · ');

        return `
            <div class="med-suggestion-item"
                 data-index="${i}"
                 onclick="pickMedicine(${i})"
                 onmouseover="highlightSuggestion(${i})"
                 style="padding:12px 16px; cursor:pointer; border-bottom:1px solid #f1f5f9; transition:background 0.1s;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                    <div style="min-width:0; flex:1;">
                        <div style="font-weight:600; color:#0f172a; font-size:15px;">${highlightMatch(m.name, query)}</div>
                        ${meta ? `<div style="font-size:13px; color:#64748b; margin-top:2px;">${escapeHtml(meta)}</div>` : ''}
                    </div>
                    <div style="flex-shrink:0;">${stockHtml}</div>
                </div>
            </div>
        `;
    }).join('');

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

    document.getElementById('medicine-search').value     = m.name;
    document.getElementById('medicine-id-hidden').value  = m.id;
    document.getElementById('clear-medicine-btn').style.display = 'flex';

    hideSuggestions();
    showStockPanel(m);
    renderQtyHelp();
    document.getElementById('quantity').focus();
    document.getElementById('quantity').select();
}

function clearMedicine() {
    document.getElementById('medicine-search').value = '';
    document.getElementById('medicine-id-hidden').value = '';
    document.getElementById('clear-medicine-btn').style.display = 'none';
    pickedMedicine = null;
    hideStockPanel();
    hideSuggestions();
    document.getElementById('qty-help').textContent = '';
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

document.addEventListener('click', function (e) {
    const shell = document.getElementById('medicine-input-shell');
    if (shell && !shell.contains(e.target)) {
        hideSuggestions();
    }
});

// ═══ Stock panel ═══
function showStockPanel(m) {
    const panel = document.getElementById('stock-panel');

    let html = '';

    // Total on hand
    if (m.stock === 0) {
        html += `<div style="display:flex; align-items:center; gap:8px;">
            <span style="font-size:18px;">⚠</span>
            <span><strong>No stock on hand</strong> — requesting this will bring new supply into your RHU.</span>
        </div>`;
        panel.style.background = '#FEF3C7';
        panel.style.border     = '1px solid #FAC775';
        panel.style.color      = '#92400E';
    } else {
        html += `<div style="display:flex; align-items:center; gap:8px; margin-bottom:${(m.near_expiry || m.low) ? '6px' : '0'};">
            <span style="font-size:18px;">📦</span>
            <span><strong>${m.stock}</strong> on hand at your RHU${m.unit ? ` (${m.unit}s)` : ''}</span>
        </div>`;

        if (m.low) {
            html += `<div style="display:flex; align-items:center; gap:8px; color:#92400E; font-weight:600; margin-bottom:${m.near_expiry ? '6px' : '0'};">
                <span>⚠</span><span>Below minimum stock level</span>
            </div>`;
        }

        if (m.near_expiry) {
            html += `<div style="display:flex; align-items:center; gap:8px; color:#854F0B; font-size:14px;">
                <span>⏳</span>
                <span>Batch ${escapeHtml(m.near_expiry.batch_no ?? '—')} (${m.near_expiry.qty} units) expires ${escapeHtml(m.near_expiry.date)} — use before requesting more.</span>
            </div>`;
        }

        if (m.low || m.near_expiry) {
            panel.style.background = '#FEF3C7';
            panel.style.border     = '1px solid #FAC775';
            panel.style.color      = '#854F0B';
        } else {
            panel.style.background = '#f8fafc';
            panel.style.border     = '1px solid #e2e8f0';
            panel.style.color      = '#334155';
        }
    }

    panel.innerHTML = html;
    panel.style.display = 'block';
}

function hideStockPanel() {
    const panel = document.getElementById('stock-panel');
    panel.style.display = 'none';
    panel.innerHTML = '';
}

// ═══ Qty helper ═══
document.getElementById('quantity').addEventListener('input', renderQtyHelp);

function renderQtyHelp() {
    const help = document.getElementById('qty-help');
    if (!pickedMedicine) { help.textContent = ''; return; }

    const qty = parseInt(document.getElementById('quantity').value || 0, 10);
    if (qty < 1) { help.textContent = ''; return; }

    const unit = pickedMedicine.unit || 'unit';
    help.innerHTML = `<strong style="color:#0f5c4b;">= ${qty} ${qty === 1 ? unit : unit + 's'}</strong>`;
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

// ═══ If the form was submitted with errors, restore the picked medicine ═══
document.addEventListener('DOMContentLoaded', function () {
    const hiddenId = document.getElementById('medicine-id-hidden').value;
    if (!hiddenId) return;

    const m = ALL_MEDICINES.find(x => String(x.id) === String(hiddenId));
    if (!m) return;

    pickedMedicine = m;
    document.getElementById('medicine-search').value = m.name;
    document.getElementById('clear-medicine-btn').style.display = 'flex';
    showStockPanel(m);
    renderQtyHelp();
});
</script>
@endsection