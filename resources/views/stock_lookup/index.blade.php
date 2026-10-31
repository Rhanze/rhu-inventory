@extends('layouts.app')

@section('content')
<style>
/* ═══════════════════════════════════════════════════════════
   All styling lives here — font sizes bumped to match the
   layout's FONT SCALE OVERRIDE values.
   ═══════════════════════════════════════════════════════════ */

.lookup-page {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding-bottom: 2rem;
}

.lookup-card {
    background: rgba(255,255,255,0.9);
    backdrop-filter: blur(12px);
    border-radius: 20px;
    padding: 24px 28px;
    border: 1px solid rgba(255,255,255,0.4);
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
}

.lookup-eyebrow {
    font-size: 15px;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: #42ad8a;
    margin: 0 0 6px 0;
}

.lookup-title {
    font-size: 27px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.lookup-sub {
    font-size: 17px;
    color: #475569;
    margin: 6px 0 0 0;
}

/* ─── Search card ─── */
.search-card {
    position: relative;
    z-index: 5;
    overflow: visible;
}

.search-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.search-field {
    flex: 1 1 320px;
    min-width: 220px;
    position: relative;
}

.search-label {
    display: block;
    margin-bottom: 6px;
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
}

.category-select,
.search-input {
    height: 56px;
    padding: 0 18px;
    font-size: 18px;
    border: 2px solid #bec6cf;
    border-radius: 12px;
    background: #fff;
    color: #0f172a;
    box-sizing: border-box;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.category-select {
    width: 220px;
    appearance: auto;
    cursor: pointer;
}

.search-input {
    width: 100%;
    padding: 0 46px 0 18px;
}

.category-select:focus,
.search-input:focus {
    border-color: #42ad8a;
    box-shadow: 0 0 0 3px rgba(66,173,138,0.1);
}

.input-shell {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}

.clear-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 28px;
    height: 28px;
    display: none;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    cursor: pointer;
    border-radius: 6px;
    color: #64748b;
    font-size: 18px;
    line-height: 1;
    padding: 0;
}

.clear-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
}

/* ─── Search button — matches other buttons in the app ─── */
.search-btn {
    height: 56px;
    padding: 0 40px;
    font-size: 19px;
    font-weight: 700;
    letter-spacing: 0.02em;
    background: #42ad8a;
    color: #ffffff;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: background 0.2s;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.search-btn:hover {
    background: #369f7d;
}

/* ─── Suggestions dropdown ─── */
.suggestions {
    display: none;
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: #fff;
    border: 2px solid #42ad8a;
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(0,0,0,0.12);
    max-height: 340px;
    overflow-y: auto;
    z-index: 9999;
}

.suggestion-item {
    padding: 12px 16px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.1s;
}

.suggestion-item:last-child {
    border-bottom: none;
}

.suggestion-item:hover,
.suggestion-item.highlighted {
    background: #E8F7F4;
}

.suggestion-name {
    font-weight: 600;
    color: #0f172a;
    font-size: 17px;
}

.suggestion-meta {
    font-size: 14px;
    color: #64748b;
    margin-top: 2px;
}

.suggestion-empty {
    padding: 14px 16px;
    font-size: 16px;
    color: #94a3b8;
    text-align: center;
}

/* ─── Result summary ─── */
.result-summary {
    background: #E8F7F4;
    border: 1px solid #42ad8a;
    border-radius: 16px;
    padding: 16px 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.result-summary-name {
    font-size: 15px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #0f5c4b;
    margin: 0 0 4px 0;
}

.result-summary-meta {
    font-size: 15px;
    color: #334155;
    margin: 0;
}

.result-summary-count {
    font-size: 17px;
    font-weight: 700;
    color: #0f5c4b;
}

/* ─── Table ─── */
.result-table-wrap {
    overflow: hidden;
    display: none;
    padding: 0;
}

.result-table-scroll {
    overflow-x: auto;
}

.result-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 17px;
    min-width: 700px;
}

.result-table th {
    padding: 14px 20px;
    text-align: left;
    font-size: 15px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #475569;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.result-table th.text-right  { text-align: right; }
.result-table th.text-center { text-align: center; }

.result-table td {
    padding: 14px 20px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
}

.result-table td.text-right  { text-align: right; }
.result-table td.text-center { text-align: center; }
.result-table td.strong { font-weight: 600; color: #0f172a; }

.stock-pill {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 700;
}

.request-link {
    display: inline-block;
    padding: 6px 14px;
    font-size: 15px;
    font-weight: 600;
    background: #42ad8a;
    color: white;
    border-radius: 8px;
    text-decoration: none;
    transition: background 0.2s;
}

.request-link:hover {
    background: #369f7d;
}

/* ─── Empty / loading ─── */
.empty-state {
    background: rgba(255,255,255,0.9);
    backdrop-filter: blur(8px);
    border-radius: 20px;
    padding: 60px 24px;
    text-align: center;
    border: 1px solid rgba(255,255,255,0.4);
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
}

.empty-title {
    font-size: 19px;
    font-weight: 600;
    color: #334155;
    margin: 0 0 6px 0;
}

.empty-sub {
    font-size: 17px;
    color: #64748b;
    margin: 0;
}

.loading-state {
    display: none;
    text-align: center;
    padding: 40px 0;
    font-size: 17px;
    color: #64748b;
}

@media (max-width: 720px) {
    .search-field,
    .category-select,
    .search-btn {
        flex: 1 1 100%;
        width: 100%;
    }
}
</style>

<div class="lookup-page">

    {{-- Header --}}
    <div class="lookup-card">
        <p class="lookup-eyebrow">Inventory Lookup</p>
        <h2 class="lookup-title">Find Stock Across Partner RHUs</h2>
        <p class="lookup-sub">
            Pick a category, type a medicine name, and see which partner RHUs have it in stock.
        </p>
    </div>

    {{-- Search card --}}
    <div class="lookup-card search-card">
        <form class="search-form" onsubmit="handleSubmit(event); return false;">

            {{-- Medicine search (grows to fill space) --}}
            <div class="search-field">
                <label class="search-label" for="medicine-search">Search Medicine</label>

                <div class="input-shell">
                    <input type="text"
                           id="medicine-search"
                           class="search-input"
                           autocomplete="off"
                           placeholder="Type a medicine name... e.g. Amoxicillin"
                           oninput="handleInput(this.value)"
                           onfocus="if(this.value.length >= 1) handleInput(this.value)"
                           onkeydown="handleKeydown(event)">

                    <button type="button"
                            id="clear-btn"
                            class="clear-btn"
                            onclick="clearSearch()"
                            aria-label="Clear search">✕</button>
                </div>

                <div id="suggestions" class="suggestions"></div>
            </div>

            {{-- Category dropdown (right side, next to the button) --}}
            <select id="category-select"
                    class="category-select"
                    onchange="onCategoryChange(this.value)"
                    title="Filter by category">
                <option value="">All Categories</option>
                @foreach($categories as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>

            {{-- Large search button --}}
            <button type="submit" class="search-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                Search
            </button>
        </form>
    </div>

    {{-- Result summary --}}
    <div id="result-summary" style="display:none;"></div>

    {{-- Results table --}}
    <div id="result-container" class="lookup-card result-table-wrap">
        <div class="result-table-scroll">
            <table class="result-table">
                <thead>
                    <tr>
                        <th>RHU</th>
                        <th>Municipality</th>
                        <th class="text-right">Available</th>
                        <th class="text-right">Distance</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="result-body"></tbody>
            </table>
        </div>
    </div>

    {{-- Empty state --}}
    <div id="empty-state" class="empty-state">
        <p class="empty-title">Start typing a medicine name</p>
        <p class="empty-sub">
            Suggestions will appear as you type. Pick one to see which partner RHUs have it.
        </p>
    </div>

    {{-- Loading --}}
    <div id="loading-state" class="loading-state">
        Searching across RHUs...
    </div>

</div>

<script>
const ALL_MEDICINES = @json($medicines);

let currentCategory    = '';
let selectedMedicine   = null;
let highlightedIndex   = -1;
let currentSuggestions = [];

function onCategoryChange(value) {
    currentCategory = value || '';

    selectedMedicine = null;
    const input = document.getElementById('medicine-search');
    if (input.value.trim().length >= 1) {
        handleInput(input.value);
    } else {
        hideSuggestions();
    }
}

function handleInput(query) {
    const trimmed = query.trim();

    document.getElementById('clear-btn').style.display = trimmed ? 'flex' : 'none';

    if (selectedMedicine && selectedMedicine.medicine_name !== trimmed) {
        selectedMedicine = null;
    }

    if (trimmed.length < 1) {
        hideSuggestions();
        return;
    }

    const q = trimmed.toLowerCase();
    const pool = currentCategory
        ? ALL_MEDICINES.filter(m => (m.category || '').toLowerCase().includes(currentCategory))
        : ALL_MEDICINES;

    const matches = pool
        .filter(m => {
            const name   = (m.medicine_name || '').toLowerCase();
            const dosage = (m.dosage || '').toLowerCase();
            return name.includes(q) || dosage.includes(q);
        })
        .sort((a, b) => {
            const aName = (a.medicine_name || '').toLowerCase();
            const bName = (b.medicine_name || '').toLowerCase();
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
    const box = document.getElementById('suggestions');

    if (matches.length === 0) {
        box.innerHTML = `<div class="suggestion-empty">No medicines match "${escapeHtml(query)}"</div>`;
        box.style.display = 'block';
        return;
    }

    box.innerHTML = matches.map((m, i) => `
        <div class="suggestion-item"
             data-index="${i}"
             onclick="pickSuggestion(${i})"
             onmouseover="highlightSuggestion(${i})">
            <div class="suggestion-name">${highlightMatch(m.medicine_name, query)}</div>
            <div class="suggestion-meta">
                ${m.dosage || ''}${m.dosage_form ? ' · ' + m.dosage_form : ''}${m.category ? ' · ' + m.category : ''}
            </div>
        </div>
    `).join('');

    box.style.display = 'block';
}

function highlightSuggestion(index) {
    highlightedIndex = index;
    document.querySelectorAll('.suggestion-item').forEach(el => {
        const i = parseInt(el.dataset.index);
        el.classList.toggle('highlighted', i === index);
    });
}

function pickSuggestion(index) {
    const m = currentSuggestions[index];
    if (!m) return;

    selectedMedicine = m;
    document.getElementById('medicine-search').value = m.medicine_name;
    document.getElementById('clear-btn').style.display = 'flex';
    hideSuggestions();
    runSearch();
}

function handleKeydown(e) {
    const box = document.getElementById('suggestions');
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
        e.preventDefault();
        if (isOpen && highlightedIndex >= 0) {
            pickSuggestion(highlightedIndex);
        } else if (isOpen && currentSuggestions.length > 0) {
            pickSuggestion(0);
        } else {
            handleSubmit(e);
        }
    } else if (e.key === 'Escape') {
        hideSuggestions();
    }
}

function scrollToHighlighted() {
    const el = document.querySelector(`.suggestion-item[data-index="${highlightedIndex}"]`);
    if (el) el.scrollIntoView({ block: 'nearest' });
}

function handleSubmit(e) {
    if (e) e.preventDefault();
    hideSuggestions();

    if (selectedMedicine) {
        runSearch();
        return;
    }

    const q = document.getElementById('medicine-search').value.trim();
    if (!q) {
        alert('Please type a medicine name first.');
        return;
    }

    const pool = currentCategory
        ? ALL_MEDICINES.filter(m => (m.category || '').toLowerCase().includes(currentCategory))
        : ALL_MEDICINES;

    const exact   = pool.find(m => (m.medicine_name || '').toLowerCase() === q.toLowerCase());
    const partial = pool.find(m => (m.medicine_name || '').toLowerCase().includes(q.toLowerCase()));

    selectedMedicine = exact || partial;

    if (!selectedMedicine) {
        document.getElementById('empty-state').innerHTML = `
            <p class="empty-title">No medicine matches "${escapeHtml(q)}"</p>
            <p class="empty-sub">Check the spelling or try a different category.</p>
        `;
        document.getElementById('empty-state').style.display    = 'block';
        document.getElementById('result-summary').style.display = 'none';
        document.getElementById('result-container').style.display = 'none';
        return;
    }

    runSearch();
}

function clearSearch() {
    document.getElementById('medicine-search').value = '';
    document.getElementById('clear-btn').style.display = 'none';
    selectedMedicine = null;
    hideSuggestions();
    document.getElementById('empty-state').innerHTML = `
        <p class="empty-title">Start typing a medicine name</p>
        <p class="empty-sub">Suggestions will appear as you type. Pick one to see which partner RHUs have it.</p>
    `;
    document.getElementById('empty-state').style.display     = 'block';
    document.getElementById('result-summary').style.display  = 'none';
    document.getElementById('result-container').style.display = 'none';
    document.getElementById('loading-state').style.display   = 'none';
}

function hideSuggestions() {
    document.getElementById('suggestions').style.display = 'none';
    highlightedIndex = -1;
}

document.addEventListener('click', function (e) {
    const box   = document.getElementById('suggestions');
    const input = document.getElementById('medicine-search');
    if (box && !box.contains(e.target) && e.target !== input) {
        hideSuggestions();
    }
});

function runSearch() {
    if (!selectedMedicine) return;

    document.getElementById('empty-state').style.display      = 'none';
    document.getElementById('result-container').style.display = 'none';
    document.getElementById('result-summary').style.display   = 'none';
    document.getElementById('loading-state').style.display    = 'block';

    fetch(`{{ route('stock_lookup.search') }}?medicine_id=${encodeURIComponent(selectedMedicine.id)}`, {
        headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('loading-state').style.display = 'none';
        renderResults(data);
    })
    .catch(err => {
        console.error(err);
        document.getElementById('loading-state').style.display = 'none';
        alert('Search failed. Please try again.');
    });
}

function renderResults(data) {
    const med     = data.medicine || {};
    const results = data.results  || [];

    const summary = document.getElementById('result-summary');
    summary.innerHTML = `
        <div class="result-summary">
            <div>
                <p class="result-summary-name">${med.medicine_name || 'Medicine'}</p>
                <p class="result-summary-meta">
                    ${med.dosage || ''}${med.dosage_form ? ' · ' + med.dosage_form : ''}${med.category ? ' · ' + med.category : ''}
                </p>
            </div>
            <div class="result-summary-count">
                ${results.length} RHU${results.length === 1 ? '' : 's'} with stock
            </div>
        </div>
    `;
    summary.style.display = 'block';

    if (results.length === 0) {
        document.getElementById('empty-state').innerHTML = `
            <p class="empty-title">No partner RHU has this medicine in stock</p>
            <p class="empty-sub">Try another medicine or check back later.</p>
        `;
        document.getElementById('empty-state').style.display = 'block';
        return;
    }

    const tbody = document.getElementById('result-body');
    tbody.innerHTML = results.map(r => {
        const stockColor = r.available_stock >= 100 ? '#166534'
                          : r.available_stock >= 30 ? '#92400E'
                          : '#991B1B';
        const stockBg    = r.available_stock >= 100 ? '#DCFCE7'
                          : r.available_stock >= 30 ? '#FEF3C7'
                          : '#FEE2E2';
        const dist = r.distance_km !== null ? r.distance_km + ' km' : '—';

        return `
            <tr>
                <td class="strong">${r.rhu_name}</td>
                <td>${r.municipality || '—'}</td>
                <td class="text-right">
                    <span class="stock-pill" style="background:${stockBg}; color:${stockColor};">
                        ${r.available_stock}
                    </span>
                </td>
                <td class="text-right">${dist}</td>
                <td class="text-center">
                    <a class="request-link"
                       href="{{ route('transfers.create') }}?to_rhu=${r.rhu_id}&medicine_id=${med.id}">
                        Request
                    </a>
                </td>
            </tr>
        `;
    }).join('');

    document.getElementById('result-container').style.display = 'block';
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