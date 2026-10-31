@extends('layouts.public')

@section('content')
<div class="stack">

    @php
        $rhuList       = $rhuList       ?? collect();
        $selectedRhuId = $selectedRhuId ?? request('health_unit_id');
        $selectedRhu   = $selectedRhu   ?? ($selectedRhuId ? $rhuList->firstWhere('id', $selectedRhuId) : null);
        $q             = $q             ?? '';
    @endphp

    <style>
        [style*="font-size:10px"] { font-size: 12px !important; }
        [style*="font-size: 10px"] { font-size: 12px !important; }
        [style*="font-size:11px"] { font-size: 13px !important; }
        [style*="font-size: 11px"] { font-size: 13px !important; }
        [style*="font-size:12px"] { font-size: 14px !important; }
        [style*="font-size: 12px"] { font-size: 14px !important; }
        [style*="font-size:13px"] { font-size: 15px !important; }
        [style*="font-size: 13px"] { font-size: 15px !important; }
        [style*="font-size:14px"] { font-size: 16px !important; }
        [style*="font-size: 14px"] { font-size: 16px !important; }
        [style*="font-size:15px"] { font-size: 17px !important; }
        [style*="font-size: 15px"] { font-size: 17px !important; }
        [style*="font-size:16px"] { font-size: 18px !important; }
        [style*="font-size: 16px"] { font-size: 18px !important; }
        [style*="font-size:17px"] { font-size: 19px !important; }
        [style*="font-size: 17px"] { font-size: 19px !important; }
        [style*="font-size:18px"] { font-size: 20px !important; }
        [style*="font-size: 18px"] { font-size: 20px !important; }
        [style*="font-size:20px"] { font-size: 22px !important; }
        [style*="font-size: 20px"] { font-size: 22px !important; }
        [style*="font-size:22px"] { font-size: 24px !important; }
        [style*="font-size: 22px"] { font-size: 24px !important; }
        [style*="font-size:24px"] { font-size: 26px !important; }
        [style*="font-size: 24px"] { font-size: 26px !important; }

        .rhu-dropdown { position: relative; width: 100%; }
        .rhu-trigger {
            width: 100%; height: 54px; padding: 0 44px 0 16px;
            font-size: 17px; font-weight: 400; border-radius: 12px;
            border: 2px solid #bec6cf; background-color: #ffffff;
            color: #0f172a; box-sizing: border-box; cursor: pointer;
            text-align: left; display: flex; align-items: center;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            position: relative; font-family: inherit;
        }
        .rhu-trigger:hover { border-color: #a8b2bd; }
        .rhu-trigger:focus, .rhu-trigger.open {
            border-color: #42ad8a; box-shadow: 0 0 0 4px rgba(66, 173, 138, 0.15); outline: none;
        }
        .rhu-trigger.placeholder { color: #94a3b8; }
        .rhu-trigger .rhu-chevron {
            position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%); width: 16px; height: 16px;
            stroke: #42ad8a; stroke-width: 3; fill: none;
            stroke-linecap: round; stroke-linejoin: round;
            transition: transform 0.2s ease; pointer-events: none;
        }
        .rhu-trigger.open .rhu-chevron { transform: translateY(-50%) rotate(180deg); }
        .rhu-menu {
            position: absolute; top: calc(100% + 6px); left: 0; width: 100%;
            max-height: 320px; overflow-y: auto; background: #ffffff;
            border: 1px solid #e2e8f0; border-radius: 12px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.12);
            padding: 6px; z-index: 100; display: none; box-sizing: border-box;
        }
        .rhu-menu.open { display: block; }
        .rhu-option {
            padding: 10px 14px; font-size: 16px; color: #0f172a;
            border-radius: 8px; cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .rhu-option:hover, .rhu-option.focused { background: #E8F7F4; color: #0f5c4b; }
        .rhu-option.selected { background: #E8F7F4; color: #0f5c4b; font-weight: 600; }
        .rhu-hint { font-size: 15px; color: #64748b; margin: 8px 4px 0 4px; line-height: 1.5; text-align: left; }

        @media (max-width: 768px) {
            .hero-card { margin-top: 16px !important; padding-top: 20px !important; padding-bottom: 20px !important; border-radius: 18px !important; }
            .hero-inner { padding: 12px !important; }
            .hero-heading { font-size: 30px !important; }
            .hero-sub { font-size: 17px !important; }
            .search-form-flex { flex-direction: column !important; align-items: stretch !important; gap: 10px !important; }
            .search-form-flex input[name="q"] { width: 100% !important; flex: none !important; min-width: 0 !important; height: 56px !important; font-size: 18px !important; padding: 0 18px !important; }
            .search-form-flex button { flex: 1 !important; height: 56px !important; font-size: 20px !important; padding: 0 24px !important; white-space: nowrap; }
            .rhu-trigger { height: 50px !important; font-size: 16px !important; padding: 0 42px 0 14px !important; }
            .rhu-trigger .rhu-chevron { right: 12px; width: 15px; height: 15px; }
            .rhu-menu { max-height: 260px; }
            .rhu-option { font-size: 15px; padding: 9px 12px; }
            .rhu-hint { font-size: 14px !important; }
            .results-heading { font-size: 26px !important; }
            .results-grid { grid-template-columns: 1fr !important; gap: 12px !important; }
            .result-card { padding: 16px !important; border-radius: 14px !important; }
            .result-card h3 { font-size: 18px !important; }
            .result-card .result-detail { font-size: 14px !important; }
            .result-card .result-details { gap: 4px !important; }
            .result-card .result-tag { font-size: 12px !important; padding: 2px 8px !important; }
            .search-guide { padding: 14px 16px !important; font-size: 15px !important; }
        }

        .autocomplete-suggestions-public {
            position: absolute; background: white; border: 1px solid #ccc;
            max-height: 250px; overflow-y: auto; z-index: 99999;
            border-radius: 0 0 10px 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            min-width: 250px;
        }
        .autocomplete-suggestion-item {
            padding: 10px 14px; cursor: pointer; font-size: 15px;
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid #f0f0f0; color: #333;
        }
        .autocomplete-suggestion-item:hover { background: #E8F7F4; }
    </style>

    <div class="card hero-card"
         style="
            backdrop-filter: blur(12px);
            background: rgba(255,255,255,0.85);
            position:relative; overflow:visible;
            margin-top: 30px; padding-top: 30px; padding-bottom: 30px;
            margin-bottom: {{ $q ? '30px' : '50px' }};
            border-radius: 20px;
            box-shadow: 0 5px 4px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,.08);
         ">

        <div class="hero-inner"
             style="position:relative; z-index:2; display:flex; flex-direction:column;
                    justify-content:center; align-items:center; text-align:center; padding: 20px;">

            <h1 class="hero-heading"
                style="font-size: clamp(34px, 8vw, 48px); font-weight:800;
                       color:#0f172a; line-height:1.25; margin:0; max-width:760px;">
                Find Available <span style="color:#2a9d8f;">Medicine &</span><br />
                <span class="highlight" style="color:#3bb4c1">Vaccines</span>
            </h1>

            <p class="hero-sub"
               style="font-size: clamp(18px, 4vw, 22px); color:#64748b;
                      margin-top:14px; line-height:1.8; max-width:760px;">
                Search and check current stocks and availability of medicines and vaccines in your nearby RHU before you travel
            </p>

            <form action="{{ route('public.home.citizen') }}" method="GET"
                  style="margin-top:28px; width:100%; display:flex; flex-direction:column;
                         align-items:center; justify-content:center; position: relative;">

                <div class="search-form-flex"
                     style="width:100%; max-width:860px; display:flex; gap:14px;
                            align-items:center; flex-wrap:wrap; justify-content:center;">
                    <input type="text" name="q" id="public-search-input" value="{{ $q }}"
                           placeholder="🔍 Search by name or dosage" autocomplete="off"
                           style="flex:3; min-width:280px; height:68px; padding:0 22px;
                                  font-size:22px; border-radius:16px; border:2px solid #bec6cf;
                                  outline:none; background:white; color:#0f172a; box-sizing:border-box;">

                    <button type="submit"
                            style="flex:1; height:68px; min-width:0; padding:0 24px;
                                   font-size:28px; font-weight:700; border-radius:16px;
                                   background:#42ad8a; color:#ffffff; cursor:pointer;
                                   box-shadow: 0 4px 12px rgba(47,158,143,0.2);
                                   transition: all 0.25s ease; box-sizing:border-box; white-space: nowrap;">
                        Search
                    </button>
                </div>

                <div style="width:100%; max-width:860px; margin:16px auto 0 auto; text-align:left;">
                    <div class="rhu-dropdown" id="rhu-dropdown">
                        <input type="hidden" name="health_unit_id" id="rhu-hidden-input"
                               value="{{ $selectedRhuId ?? '' }}">

                        <button type="button"
                                class="rhu-trigger {{ $selectedRhu ? '' : 'placeholder' }}"
                                id="rhu-trigger" aria-haspopup="listbox" aria-expanded="false">
                            <span id="rhu-trigger-label">
                                {{ $selectedRhu ? $selectedRhu->name : 'Select Your RHU' }}
                            </span>
                            <svg class="rhu-chevron" viewBox="0 0 24 24">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </button>

                        <div class="rhu-menu" id="rhu-menu" role="listbox">
                            <div class="rhu-option {{ $selectedRhu ? '' : 'selected' }}"
                                 role="option" data-value="">Select Your RHU</div>
                            @foreach($rhuList as $rhu)
                                <div class="rhu-option {{ (string) $selectedRhuId === (string) $rhu->id ? 'selected' : '' }}"
                                     role="option" data-value="{{ $rhu->id }}">
                                    {{ $rhu->name }}
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <p class="rhu-hint">Please select the RHU based on your municipality.</p>
                </div>
            </form>

            <div class="search-guide"
                 style="opacity: 0.80; margin-top:28px; background:#E8F7F4;
                        border:1px solid #42AD8A; border-radius:18px;
                        padding:18px 24px; max-width:760px; width:100%; box-sizing:border-box;">
                <p style="font-size:15px; font-weight:800; letter-spacing:.08em;
                          text-transform:uppercase; color:#42AD8A; margin:0 0 10px 0;">
                    Search Guide
                </p>
                <p style="font-size:18px; color:#1e3a8a; margin:0; line-height:1.8;">
                    Input medicine name, select your RHU, then click <strong>Search</strong>.
                    Results will show medicine details and stocks available in the RHU you selected.
                </p>
            </div>
        </div>
    </div>

    @if($q !== '' && !$selectedRhu)
    <div style="background: #FCEBEB; border: 1px solid #F7C1C1; border-radius: 12px;
                padding: 20px 24px; margin-top: 30px; color: #A32D2D; font-size: 18px;
                display: flex; align-items: center; gap: 12px; max-width: 600px;
                margin-left: auto; margin-right: auto;">
        <span style="font-size: 26px;">🏥</span>
        <span><strong>Please select your RHU.</strong> Choose the RHU for your municipality in the dropdown above, then click <strong>Search</strong> again.</span>
    </div>
    @endif

    @if($selectedRhu && $q !== '')
    <div id="search-results"
         style="background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
                border-radius: 20px; padding: 30px;
                border: 1px solid rgba(255,255,255,0.4);
                box-shadow: 0 8px 15px rgba(0,0,0,0.2);
                margin-bottom: 40px; box-sizing:border-box;">
        <div style="margin-bottom: 24px;">
            <h2 class="results-heading"
                style="font-size: 30px; font-weight: 700; color: #0f172a; margin: 0 0 6px 0;">
                Search Results
            </h2>
            <p style="font-size: 18px; color: #64748b; margin: 0;">
                Showing results for "<strong style="color:#0f172a;">{{ $q }}</strong>"
                in <strong style="color:#0f172a;">{{ $selectedRhu->name }}</strong>
            </p>
        </div>

        @if($medicines->count() > 0)
            <div class="results-grid"
                 style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
                @foreach($medicines as $medicine)
                <a href="{{ route('public.medicine.show', ['id' => $medicine->id, 'health_unit_id' => $selectedRhuId, 'q' => $q]) }}"
                   class="result-card"
                   style="display: block; background: #ffffff; border-radius: 16px;
                          padding: 20px; text-decoration: none;
                          border: 1px solid #e2e8f0;
                          box-shadow: 0 2px 6px rgba(0,0,0,0.04);
                          transition: all 0.25s ease; box-sizing:border-box;"
                   onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 24px rgba(0,0,0,0.1)'; this.style.borderColor='#42ad8a';"
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 6px rgba(0,0,0,0.04)'; this.style.borderColor='#e2e8f0';">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 14px;">
                        <h3 style="font-size: 20px; font-weight: 700; color: #0f172a; margin: 0; line-height: 1.3; word-break: break-word;">
                            {{ $medicine->medicine_name }}
                        </h3>
                        @if($medicine->requires_prescription)
                            <span class="result-tag" style="display: inline-block; font-size: 13px; font-weight: 600; padding: 4px 10px; border-radius: 999px; white-space: nowrap; background: #faeeda; color: #854f0b; border: 1px solid #fac775;">Prescription</span>
                        @else
                            <span class="result-tag" style="display: inline-block; font-size: 13px; font-weight: 600; padding: 4px 10px; border-radius: 999px; white-space: nowrap; background: #E8F7F4; color: #3b6d11; border: 1px solid #c0dd97;">OTC</span>
                        @endif
                    </div>
                    <div class="result-details" style="display: flex; flex-direction: column; gap: 6px;">
                        <div class="result-detail" style="display: flex; justify-content: space-between; font-size: 15px;">
                            <span style="color: #64748b;">Dosage:</span>
                            <span style="font-weight: 600; color: #0f172a;">{{ $medicine->dosage }}</span>
                        </div>
                        <div class="result-detail" style="display: flex; justify-content: space-between; font-size: 15px;">
                            <span style="color: #64748b;">Category:</span>
                            <span style="font-weight: 600; color: #0f172a;">{{ ucfirst($medicine->category) }}</span>
                        </div>
                        <div class="result-detail" style="display: flex; justify-content: space-between; font-size: 15px; margin-top: 4px;">
                            <span style="color: #64748b;">Total Available:</span>
                            <span style="font-weight: 700; color: #2a9d8f;">{{ $medicine->total_available }}</span>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
        @else
            <div style="text-align: center; padding: 40px 20px; background: #f8fafc;
                        border-radius: 16px; border: 1px dashed #cbd5e1;">
                <div style="font-size: 40px; margin-bottom: 8px;">
                    @if(isset($medicineExists) && $medicineExists) 📦 @else 💊 @endif
                </div>
                <h3 style="font-size: 22px; font-weight: 600; color: #0f172a; margin: 0 0 6px 0;">
                    @if(isset($medicineExists) && $medicineExists)
                        Out of Stock
                    @else
                        No medicines or vaccines found
                    @endif
                </h3>
                <p style="font-size: 16px; color: #64748b; margin: 0;">
                    @if(isset($medicineExists) && $medicineExists)
                        This medicine is not currently available in your RHU. Please check back later or contact your local health unit.
                    @else
                        Try a different name or check the spelling.
                    @endif
                </p>
            </div>
        @endif
    </div>
    @endif

</div>

<script>
(function () {
    function init() {
        var input = document.getElementById('public-search-input');
        if (!input) return;

        var dropdown = null;
        var currentRequest = null;
        var AUTOCOMPLETE_URL = "{{ route('public.autocomplete') }}";

        function getSelectedRhuId() {
            var hidden = document.getElementById('rhu-hidden-input');
            return hidden ? hidden.value : '';
        }
        function removeDropdown() {
            if (dropdown && dropdown.parentNode) dropdown.parentNode.removeChild(dropdown);
            dropdown = null;
        }
        function showDropdown(items) {
            removeDropdown();
            dropdown = document.createElement('div');
            dropdown.className = 'autocomplete-suggestions-public';
            items.forEach(function (item) {
                var div = document.createElement('div');
                div.className = 'autocomplete-suggestion-item';
                div.textContent = item.text;
                div.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    input.value = item.text;
                    removeDropdown();
                });
                dropdown.appendChild(div);
            });
            var rect = input.getBoundingClientRect();
            dropdown.style.top   = (rect.bottom + window.scrollY) + 'px';
            dropdown.style.left  = (rect.left   + window.scrollX) + 'px';
            dropdown.style.width = rect.width + 'px';
            document.body.appendChild(dropdown);
        }
        function fetchSuggestions(query) {
            var rhuId = getSelectedRhuId();
            if (currentRequest) currentRequest.abort();
            currentRequest = new AbortController();
            var url = AUTOCOMPLETE_URL + '?q=' + encodeURIComponent(query)
                    + (rhuId ? '&health_unit_id=' + encodeURIComponent(rhuId) : '');
            fetch(url, { signal: currentRequest.signal })
                .then(function (r) { return r.ok ? r.json() : Promise.reject('HTTP ' + r.status); })
                .then(function (data) {
                    removeDropdown();
                    if (Array.isArray(data) && data.length > 0) showDropdown(data);
                })
                .catch(function (err) { if (err.name !== 'AbortError') console.error('[autocomplete]', err); });
        }
        input.addEventListener('input', function () {
            var query = this.value.trim();
            if (query.length < 2) { removeDropdown(); return; }
            fetchSuggestions(query);
        });
        window.refreshPublicAutocomplete = function () {
            var query = input.value.trim();
            if (query.length >= 2) fetchSuggestions(query);
            else removeDropdown();
        };
        document.addEventListener('click', function (e) {
            if (!input.contains(e.target) && dropdown && !dropdown.contains(e.target)) removeDropdown();
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const wrap = document.getElementById('rhu-dropdown');
    if (!wrap) return;

    const trigger = document.getElementById('rhu-trigger');
    const label   = document.getElementById('rhu-trigger-label');
    const menu    = document.getElementById('rhu-menu');
    const hidden  = document.getElementById('rhu-hidden-input');
    const options = menu.querySelectorAll('.rhu-option');

    function openMenu() { menu.classList.add('open'); trigger.classList.add('open'); trigger.setAttribute('aria-expanded', 'true'); }
    function closeMenu() { menu.classList.remove('open'); trigger.classList.remove('open'); trigger.setAttribute('aria-expanded', 'false'); }
    function toggleMenu() { menu.classList.contains('open') ? closeMenu() : openMenu(); }

    trigger.addEventListener('click', function (e) { e.stopPropagation(); toggleMenu(); });

    options.forEach(function (opt) {
        opt.addEventListener('click', function (e) {
            e.stopPropagation();
            const val  = opt.getAttribute('data-value');
            const text = opt.textContent.trim();
            hidden.value = val;
            label.textContent = text;
            if (val === '') trigger.classList.add('placeholder');
            else trigger.classList.remove('placeholder');
            options.forEach(function (o) { o.classList.remove('selected'); });
            opt.classList.add('selected');
            closeMenu();
            if (typeof window.refreshPublicAutocomplete === 'function') window.refreshPublicAutocomplete();
        });
    });

    document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) closeMenu(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && menu.classList.contains('open')) { closeMenu(); trigger.focus(); }
    });
});
</script>
@endsection