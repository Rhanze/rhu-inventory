@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    <div class="no-print" style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
        display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
        <div>
            <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Patient Registry</p>
            <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">Registered Patients</h2>
        </div>
        <a href="{{ route('public_users.create') }}" style="
            display:inline-flex; align-items:center; gap:6px; font-size:14px; font-weight:600;
            padding:9px 16px; border-radius:10px; background:#42ad8a; color:#ffffff; text-decoration:none;">
            + Register Patient
        </a>
    </div>

    <div class="no-print" style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius:16px; padding:22px 26px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 4px 12px rgba(0,0,0,0.04);">
        <div style="display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end;">

            <div style="flex:2 1 240px; min-width:180px; position:relative;">
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Search</label>
                <input type="text" id="pf-search" value="{{ request('search') }}"
                       placeholder="Name or PhilHealth ID" autocomplete="off"
                       style="height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; outline:none; width:100%; box-sizing:border-box;">
                <div id="pf-suggestions"
                     style="display:none; position:absolute; top:calc(100% + 4px); left:0; right:0;
                            background:white; border:1px solid #e2e8f0; border-radius:10px;
                            box-shadow:0 8px 20px rgba(0,0,0,0.12); max-height:280px; overflow-y:auto;
                            z-index:9999;">
                </div>
            </div>

            <div style="flex:1 1 180px; min-width:150px;">
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Barangay</label>
                <select id="pf-barangay" style="height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; outline:none; width:100%; box-sizing:border-box; background:white;">
                    <option value="">All Barangays</option>
                    @foreach($barangays as $b)
                        <option value="{{ $b }}" {{ request('barangay') === $b ? 'selected' : '' }}>{{ $b }}</option>
                    @endforeach
                </select>
            </div>

            <div style="flex:1 1 200px; min-width:170px;">
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Age Group</label>
                <select id="pf-age-group" style="height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; outline:none; width:100%; box-sizing:border-box; background:white;">
                    <option value="">All Ages</option>
                    <option value="infant"     {{ request('age_group') === 'infant'     ? 'selected' : '' }}>Infants (0–11 mo)</option>
                    <option value="child"      {{ request('age_group') === 'child'      ? 'selected' : '' }}>Children (1–4 y)</option>
                    <option value="school_age" {{ request('age_group') === 'school_age' ? 'selected' : '' }}>School Age (5–17 y)</option>
                    <option value="adult"      {{ request('age_group') === 'adult'      ? 'selected' : '' }}>Adults (18–59 y)</option>
                    <option value="pregnant"   {{ request('age_group') === 'pregnant'   ? 'selected' : '' }}>Pregnant</option>
                    <option value="senior"     {{ request('age_group') === 'senior'     ? 'selected' : '' }}>Seniors (60+)</option>
                </select>
            </div>
        </div>
    </div>

    <div style="
        background: rgba(255,255,255,0.9); border-radius:20px; padding:0;
        border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06); overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead>
                <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                    <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">#</th>
                    <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">Name</th>
                    <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">Age</th>
                    <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">Barangay</th>
                    <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">PhilHealth ID</th>
                    <th style="padding:14px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">Contact</th>
                    <th class="no-print" style="padding:14px 16px; text-align:center; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($members as $m)
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:14px 16px; color:#334155;">
                            {{ $loop->iteration + ($members->currentPage() - 1) * $members->perPage() }}
                        </td>
                        <td style="padding:14px 16px; font-weight:600; color:#0f172a;">
                            {{ $m->full_name }}
                            @if($m->is_pregnant)
                                <span style="margin-left:6px; font-size:11px; font-weight:700; background:#fce7f3; color:#9d174d; padding:1px 8px; border-radius:999px;">🤰 Pregnant</span>
                            @endif
                            @if($m->is_infant)
                                <span style="margin-left:6px; font-size:11px; font-weight:700; background:#fef3c7; color:#92400e; padding:1px 8px; border-radius:999px;">👶 Infant</span>
                            @endif
                        </td>
                        <td style="padding:14px 16px; color:#334155; font-weight:600;">{{ $m->age_display }}</td>
                        <td style="padding:14px 16px; color:#334155;">{{ $m->barangay ?? '—' }}</td>
                        <td style="padding:14px 16px; color:#334155; font-family:monospace;">{{ $m->philhealth_id ?? '—' }}</td>
                        <td style="padding:14px 16px; color:#334155;">{{ $m->contact_number ?? '—' }}</td>
                        <td class="no-print" style="padding:14px 16px;">
                            <div style="display:flex; align-items:center; justify-content:center; gap:6px;">
                                <a href="{{ route('public_users.edit', $m->id) }}" title="Edit"
                                   style="width:34px; height:34px;
                                          display:inline-flex; align-items:center; justify-content:center;
                                          border-radius:8px;
                                          background:#E8F7F4; color:#0f5c4b; border:1px solid #42AD8A;
                                          text-decoration:none;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </a>
                                @if(session('role') !== 'midwife')
                                    <form action="{{ route('public_users.destroy', $m->id) }}" method="POST" style="margin:0;"
                                          onsubmit="return confirmDialog(event, 'Delete this patient? This cannot be undone.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Delete"
                                                style="width:34px; height:34px;
                                                       display:inline-flex; align-items:center; justify-content:center;
                                                       border-radius:8px;
                                                       background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;
                                                       cursor:pointer;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"/>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                <line x1="10" y1="11" x2="10" y2="17"/>
                                                <line x1="14" y1="11" x2="14" y2="17"/>
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding:40px 20px; text-align:center; color:#64748b;">
                            No patients match your filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($members->hasPages())
        <div class="no-print" style="
            background: rgba(255,255,255,0.9); border-radius:16px; padding:16px 24px;
            border:1px solid rgba(255,255,255,0.4); box-shadow:0 4px 12px rgba(0,0,0,0.04);
            display:flex; justify-content:center;">
            {{ $members->links() }}
        </div>
    @endif

</div>

<script>
(function () {
    var SUGGEST_URL = "{{ route('public_users.search.suggestions') }}";

    function buildUrl() {
        var params = new URLSearchParams();
        var s = document.getElementById('pf-search').value.trim();
        var b = document.getElementById('pf-barangay').value;
        var a = document.getElementById('pf-age-group').value;

        if (s) params.set('search', s);
        if (b) params.set('barangay', b);
        if (a) params.set('age_group', a);

        params.set('_', Date.now());
        return window.location.pathname + '?' + params.toString();
    }

    function applyFilter() {
        window.location.href = buildUrl();
    }

    var suggestBox    = document.getElementById('pf-suggestions');
    var searchInput   = document.getElementById('pf-search');
    var currentRequest = null;
    var suggestTimer   = null;
    var activeIndex    = -1;
    var currentItems   = [];

    function hideSuggestions() {
        if (suggestBox) {
            suggestBox.style.display = 'none';
            suggestBox.innerHTML = '';
        }
        activeIndex = -1;
        currentItems = [];
    }

    function renderSuggestions(items) {
        currentItems = items;
        activeIndex = -1;
        if (!items.length) { hideSuggestions(); return; }
        suggestBox.innerHTML = '';
        items.forEach(function (item, i) {
            var row = document.createElement('div');
            row.style.cssText = 'padding:10px 14px; cursor:pointer; font-size:14px; color:#0f172a; border-bottom:1px solid #f1f5f9;';
            row.textContent = item.text;
            row.addEventListener('mouseenter', function () { activeIndex = i; highlightRows(); });
            row.addEventListener('mousedown', function (e) { e.preventDefault(); pickSuggestion(i); });
            suggestBox.appendChild(row);
        });
        suggestBox.style.display = 'block';
    }

    function highlightRows() {
        Array.prototype.forEach.call(suggestBox.children, function (row, i) {
            row.style.background = (i === activeIndex) ? '#E8F7F4' : '';
            row.style.fontWeight  = (i === activeIndex) ? '600' : '';
        });
    }

    function pickSuggestion(index) {
        var item = currentItems[index];
        if (!item) return;
        searchInput.value = item.name;
        hideSuggestions();
        applyFilter();
    }

    function fetchSuggestions(query) {
        if (currentRequest) currentRequest.abort();
        currentRequest = new AbortController();
        fetch(SUGGEST_URL + '?q=' + encodeURIComponent(query), {
            signal: currentRequest.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.ok ? r.json() : Promise.reject('HTTP ' + r.status); })
        .then(function (data) {
            if (Array.isArray(data)) renderSuggestions(data);
            else hideSuggestions();
        })
        .catch(function (err) { if (err.name !== 'AbortError') console.error(err); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var barangaySel = document.getElementById('pf-barangay');
        var ageGroupSel = document.getElementById('pf-age-group');

        if (barangaySel) barangaySel.addEventListener('change', applyFilter);
        if (ageGroupSel) ageGroupSel.addEventListener('change', applyFilter);
        if (!searchInput) return;

        var applyTimer = null;

        searchInput.addEventListener('input', function () {
            var q = this.value.trim();
            clearTimeout(suggestTimer);
            if (q.length >= 2) {
                suggestTimer = setTimeout(function () { fetchSuggestions(q); }, 250);
            } else {
                hideSuggestions();
            }
            clearTimeout(applyTimer);
            applyTimer = setTimeout(applyFilter, 800);
        });

        searchInput.addEventListener('keydown', function (e) {
            var visible = suggestBox.style.display === 'block';
            if (visible) {
                if (e.key === 'ArrowDown') { e.preventDefault(); activeIndex = Math.min(activeIndex + 1, currentItems.length - 1); highlightRows(); return; }
                if (e.key === 'ArrowUp')   { e.preventDefault(); activeIndex = Math.max(activeIndex - 1, 0); highlightRows(); return; }
                if (e.key === 'Enter')     { e.preventDefault(); clearTimeout(applyTimer); if (activeIndex >= 0) pickSuggestion(activeIndex); else { hideSuggestions(); applyFilter(); } return; }
                if (e.key === 'Escape')    { hideSuggestions(); return; }
            } else if (e.key === 'Enter') {
                e.preventDefault(); clearTimeout(applyTimer); applyFilter();
            }
        });

        searchInput.addEventListener('blur', function () { setTimeout(hideSuggestions, 200); });
        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !suggestBox.contains(e.target)) hideSuggestions();
        });
    });
})();
</script>
@endsection