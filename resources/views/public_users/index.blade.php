@extends('layouts.app')

@section('title', 'Patient Directory')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    <div class="no-print mw-page-card" style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
        display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
        <div>
            <p style="font-size:1.0625rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Patient Registry</p>
            <h2 style="font-size:1.5rem; font-weight:700; color:#0f172a; margin:0;">Registered Patients</h2>
        </div>
        <div class="no-print" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('public_users.create') }}" style="
            display:inline-flex; align-items:center; gap:6px; font-size:1.0625rem; font-weight:600;
            padding:9px 16px; border-radius:10px; background:#42ad8a; color:#ffffff; text-decoration:none;">
            + Register Patient
        </a>
            @include('imports._link', ['section' => 'patients'])
        </div>
    </div>

    <div class="no-print mw-page-card" style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius:16px; padding:22px 26px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 4px 12px rgba(0,0,0,0.04);">
        <form id="patient-directory-filter" action="{{ route('public_users.filter') }}" method="post" style="display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end;">
            @csrf
            @if(request()->filled('assigned_midwife_id'))
                <input type="hidden" name="assigned_midwife_id" value="{{ request('assigned_midwife_id') }}">
            @endif

            <div style="flex:2 1 240px; min-width:180px; position:relative;">
                <label for="pf-search" style="display:block; margin-bottom:6px; font-size:1.0625rem; font-weight:600; color:#0f172a;">Search</label>
                <input type="text" id="pf-search" name="search" value="{{ $search }}" maxlength="120"
                       role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="pf-suggestions"
                       placeholder="Name or PhilHealth ID" autocomplete="off"
                       style="min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; width:100%; box-sizing:border-box;">
                <div id="pf-suggestions" role="listbox" aria-label="Patient suggestions"
                     style="display:none; position:absolute; top:calc(100% + 4px); left:0; right:0;
                            background:white; border:1px solid #e2e8f0; border-radius:10px;
                            box-shadow:0 8px 20px rgba(0,0,0,0.12); max-height:280px; overflow-y:auto;
                            z-index:9999;">
                </div>
            </div>

            <div style="flex:1 1 180px; min-width:150px;">
                <label for="pf-barangay" style="display:block; margin-bottom:6px; font-size:1.0625rem; font-weight:600; color:#0f172a;">Barangay</label>
                <select id="pf-barangay" name="barangay" @disabled(session('role') === 'midwife' && $barangays->isEmpty()) style="min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; width:100%; box-sizing:border-box; background:white;">
                    <option value="">{{ session('role') === 'midwife' ? ($barangays->isEmpty() ? 'No assigned barangays' : 'All my patients') : 'All Barangays' }}</option>
                    @foreach($barangays as $b)
                        <option value="{{ $b }}" {{ request('barangay') === $b ? 'selected' : '' }}>{{ $b }}</option>
                    @endforeach
                </select>
            </div>

            <div style="flex:1 1 200px; min-width:170px;">
                <label for="pf-age-group" style="display:block; margin-bottom:6px; font-size:1.0625rem; font-weight:600; color:#0f172a;">Age Group</label>
                <select id="pf-age-group" name="age_group" style="min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; width:100%; box-sizing:border-box; background:white;">
                    <option value="">All Ages</option>
                    <option value="infant"     {{ request('age_group') === 'infant'     ? 'selected' : '' }}>Infants (0–11 mo)</option>
                    <option value="child"      {{ request('age_group') === 'child'      ? 'selected' : '' }}>Children (1–4 y)</option>
                    <option value="school_age" {{ request('age_group') === 'school_age' ? 'selected' : '' }}>School Age (5–17 y)</option>
                    <option value="adult"      {{ request('age_group') === 'adult'      ? 'selected' : '' }}>Adults (18–59 y)</option>
                    <option value="pregnant"   {{ request('age_group') === 'pregnant'   ? 'selected' : '' }}>Pregnant</option>
                    <option value="senior"     {{ request('age_group') === 'senior'     ? 'selected' : '' }}>Seniors (60+)</option>
                </select>
            </div>
            <button type="submit" style="min-height:2.75rem;padding:.5rem 1rem;border-radius:10px;background:#0f5c4b;color:#fff;">Search</button>
        </form>
    </div>

    <div style="
        background: rgba(255,255,255,0.9); border-radius:20px; padding:0;
        border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06); overflow-x:auto;">
        <table class="mw-mobile-records" style="width:100%; min-width:58rem; border-collapse:collapse; font-size:1.0625rem;">
            <thead>
                <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                    <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">#</th>
                    <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">Name</th>
                    <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">Age</th>
                    <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">Barangay</th>
                    <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">PhilHealth ID</th>
                    <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">Contact</th>
                    <th class="no-print" style="padding:14px 16px; text-align:center; font-size:1.0625rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">Actions</th>
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
                                <span style="margin-left:6px; font-size:1.0625rem; font-weight:700; background:#fce7f3; color:#9d174d; padding:1px 8px; border-radius:999px;">🤰 Pregnant</span>
                            @endif
                            @if($m->is_infant)
                                <span style="margin-left:6px; font-size:1.0625rem; font-weight:700; background:#fef3c7; color:#92400e; padding:1px 8px; border-radius:999px;">👶 Infant</span>
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
(() => {
    const form = document.getElementById('patient-directory-filter');
    const input = document.getElementById('pf-search');
    const list = document.getElementById('pf-suggestions');
    const url = {{ Illuminate\Support\Js::from(route('public_users.search.suggestions')) }};
    let timer;
    let pending;
    let items = [];
    let active = -1;

    function closeSuggestions() {
        clearTimeout(timer);
        pending?.abort();
        list.replaceChildren();
        list.style.display = 'none';
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        items = [];
        active = -1;
    }

    function select(index) {
        if (!items[index]) return;
        input.value = items[index].name;
        closeSuggestions();
        form.requestSubmit();
    }

    input.addEventListener('input', () => {
        closeSuggestions();
        const query = input.value.trim();
        if (query.length < 2) return;
        timer = setTimeout(async () => {
            const request = new AbortController();
            pending = request;
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.elements._token.value},
                    body: JSON.stringify({q: query}),
                    signal: request.signal
                });
                if (!response.ok || request.signal.aborted) return;
                const results = await response.json();
                if (!Array.isArray(results) || !results.length || request.signal.aborted) return;
                items = results;
                items.forEach((item, index) => {
                    const option = document.createElement('div');
                    option.id = 'pf-suggestion-' + index;
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');
                    option.style.cssText = 'padding:10px 14px;cursor:pointer;border-bottom:1px solid #e2e8f0;';
                    option.textContent = item.text;
                    option.addEventListener('click', () => select(index));
                    list.append(option);
                });
                list.style.display = 'block';
                input.setAttribute('aria-expanded', 'true');
            } catch (error) {
                if (error.name !== 'AbortError' && pending === request) closeSuggestions();
            }
        }, 220);
    });

    input.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeSuggestions();
        } else if (items.length && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            const direction = event.key === 'ArrowDown' ? 1 : -1;
            active = active === -1 && direction === -1 ? items.length - 1 : (active + direction + items.length) % items.length;
            Array.from(list.children).forEach((option, index) => {
                option.setAttribute('aria-selected', String(index === active));
                option.style.background = index === active ? '#e8f7f4' : '';
            });
            const option = list.children[active];
            input.setAttribute('aria-activedescendant', option.id);
            option.scrollIntoView({block: 'nearest'});
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault();
            select(active);
        }
    });

    document.addEventListener('click', event => {
        if (event.target !== input && !list.contains(event.target)) closeSuggestions();
    });
})();
</script>
@endsection
