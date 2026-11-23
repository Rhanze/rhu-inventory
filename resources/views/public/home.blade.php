@extends('layouts.public')

@section('title', 'Medicine and Vaccine Search')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/form-choice-search.css') }}?v={{ filemtime(public_path('css/form-choice-search.css')) }}">
    <script src="{{ asset('js/form-choice-search.js') }}?v={{ filemtime(public_path('js/form-choice-search.js')) }}" defer></script>
@endpush

@section('content')
<div class="stack">

    @php
        $rhuList       = $rhuList       ?? collect();
        $selectedRhuId = $selectedRhuId ?? request('health_unit_id');
        $selectedRhu   = $selectedRhu   ?? ($selectedRhuId ? $rhuList->firstWhere('id', $selectedRhuId) : null);
        $q             = $q             ?? '';
    @endphp

    <style>
        .hero-card {
            background:rgba(255,255,255,.85);
            backdrop-filter:blur(12px);
            position:relative;
            overflow:visible;
            margin-top:30px;
            margin-bottom:50px;
            padding:30px 0;
            border-radius:20px;
            box-shadow:0 5px 4px rgba(0,0,0,.2);
            border:1px solid rgba(255,255,255,.08);
        }

        .hero-inner {
            position:relative;
            z-index:2;
            display:flex;
            flex-direction:column;
            justify-content:center;
            align-items:center;
            text-align:center;
            min-width:0;
            padding:20px;
        }

        .hero-heading {
            max-width:47.5rem;
            margin:0;
            font-size:clamp(2.125rem,8vw,3rem);
            font-weight:800;
            line-height:1.25;
            word-break:normal;
            overflow-wrap:normal;
        }

        .hero-sub {
            max-width:47.5rem;
            margin:14px auto 0;
            color:#64748b;
            font-size:1.375rem;
            line-height:1.8;
        }

        .public-search-form {
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            position:relative;
            width:100%;
            margin:28px auto 0;
            text-align:left;
        }

        .public-search-form input[name="q"], .public-search-form .search-submit,
        .rhu-field select, .rhu-field .record-choice .record-choice-input {
            width: 100%;
            min-width: 0;
            min-height: 3.25rem;
            padding: .75rem 1rem;
            border: 2px solid #bec6cf;
            border-radius: .75rem;
            background: #fff;
            color: #0f172a;
            font: inherit;
            font-size: 1.0625rem;
            line-height: 1.5;
        }

        .public-search-form .search-submit {
            border-color: #0f5c4b;
            background: #0f5c4b;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
        }

        .public-search-form .search-submit:hover {
            background: #0b483b;
            border-color: #0b483b;
        }

        .public-search-form :is(input, select, .search-submit):focus-visible {
            outline: 3px solid #0f766e;
            outline-offset: 2px;
        }

        .rhu-field {
            min-width: 0;
            width: 100%;
        }

        .rhu-field select {
            appearance: auto;
        }

        .rhu-hint {
            margin: .375rem .25rem 0;
            color: #64748b;
            font-size: .875rem;
            line-height: 1.5;
        }

        .search-guide {
            width:100%;
            max-width:47.5rem;
            opacity:.8;
            margin-top:28px;
            padding:18px 24px;
            border:1px solid #42ad8a;
            border-radius:18px;
            background:#E8F7F4;
        }

        .search-guide-title {
            margin:0 0 10px;
            color:#42ad8a;
            font-size:.9375rem;
            font-weight:800;
            letter-spacing:.08em;
            text-transform:uppercase;
        }

        .search-guide-copy {
            margin:0;
            color:#1e3a8a;
            font-size:1.125rem;
            line-height:1.8;
        }

        #search-results {
            margin-top: 1.5rem;
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 19rem), 1fr));
            gap: 1rem;
        }

        .result-card {
            min-width: 0;
        }

        .result-card:focus-visible {
            outline: 3px solid #0f766e;
            outline-offset: 3px;
        }

        .result-card-heading {
            flex-wrap: wrap;
        }

        .result-card-heading h3 {
            flex: 1 1 10rem;
            min-width: 0;
        }

        .result-tag {
            flex: none;
        }

        .result-detail {
            gap: .75rem;
            align-items: baseline;
            flex-wrap: wrap;
        }

        .result-detail > span:first-child {
            flex: 0 1 auto;
            min-width: 0;
            overflow-wrap: normal;
        }

        .result-detail > span:last-child {
            min-width: 0;
            margin-left: auto;
            text-align: right;
            overflow-wrap: anywhere;
        }

        .autocomplete-suggestions-public {
            position: absolute;
            background: #fff;
            border: 1px solid #ccc;
            max-height: min(15.625rem, 45dvh);
            overflow-y: auto;
            z-index: 99999;
            border-radius: 0 0 .625rem .625rem;
            box-shadow: 0 4px 12px rgba(0,0,0,.1);
            min-width: 0;
            max-width: calc(100vw - 2rem);
            box-sizing: border-box;
            overscroll-behavior: contain;
        }

        .autocomplete-suggestion-item {
            display: block;
            width: 100%;
            min-height: 2.75rem;
            padding: .625rem .875rem;
            border: 0;
            border-bottom: 1px solid #f0f0f0;
            background: #fff;
            color: #333;
            font: inherit;
            line-height: 1.5;
            text-align: left;
            cursor: pointer;
            overflow-wrap: anywhere;
        }

        .autocomplete-suggestion-item:hover, .autocomplete-suggestion-item:focus-visible {
            background: #E8F7F4;
        }

        @media (max-width: 36rem) {
            .hero-sub {
                font-size: 1rem;
            }
            #search-results {
                padding: 1rem !important;
            }
            .result-card {
                padding: 1rem !important;
            }
            .rhu-selection-notice {
                padding: 1rem !important;
                align-items: flex-start !important;
            }
        }


        .search-form-flex {
            width:100%;
            max-width:53.75rem;
            display:flex;
            gap:14px;
            align-items:stretch;
            flex-wrap:wrap;
            justify-content:center;
        }
        .public-search-form input[name="q"] {
            flex:3 1 17.5rem;
            min-height:4.25rem;
            padding:12px 22px;
            font-size:1.375rem;
            border-radius:16px;
        }
        .public-search-form .search-submit {
            flex:1 1 9rem;
            width:auto;
            min-height:4.25rem;
            padding:12px 24px;
            font-size:1.75rem;
            border-radius:16px;
            box-shadow:0 4px 12px rgba(47,158,143,.2);
        }
        .public-search-form .rhu-field {
            max-width:53.75rem;
            margin:16px auto 0;
        }
        .rhu-hint {
            font-size:.9375rem;
            margin:8px 4px 0;
        }
        @media(max-width:768px) {
            .hero-card {
                margin-top:16px;
                margin-bottom:24px;
                padding:20px 0;
                border-radius:18px;
            }
            .hero-inner {
                padding:12px;
            }
            .hero-heading {
                font-size:1.875rem;
            }
            .hero-sub {
                font-size:1.0625rem;
                line-height:1.6;
            }
            .search-form-flex {
                gap:10px;
            }
            .public-search-form input[name="q"], .public-search-form .search-submit {
                min-height:3.5rem;
                font-size:1.125rem;
            }
            .public-search-form .search-submit {
                font-size:1.25rem;
            }
            .search-guide {
                padding:14px 16px;
            }
            .search-guide-copy {
                font-size:1rem;
                line-height:1.6;
            }
        }
        @media(min-width:769px) and (max-height:1024px) {
            .hero-card {
                margin-top:12px;
                margin-bottom:24px;
                padding:20px 0;
            }
            .hero-inner {
                padding:16px;
            }
            .hero-heading {
                font-size:2.5rem;
            }
            .hero-sub {
                margin-top:10px;
                font-size:1.125rem;
                line-height:1.6;
            }
            .public-search-form, .search-guide {
                margin-top:18px;
            }
            .public-search-form input[name="q"], .public-search-form .search-submit {
                min-height:3.5rem;
            }
            .public-search-form input[name="q"] {
                font-size:1.25rem;
            }
            .public-search-form .search-submit {
                font-size:1.5rem;
            }
            .search-guide {
                padding:12px 18px;
            }
            .search-guide-title {
                margin-bottom:6px;
            }
            .search-guide-copy {
                font-size:1rem;
                line-height:1.5;
            }
        }
        @media(min-width:769px) and (max-height:768px) {
            .hero-card {
                margin-top:8px;
                margin-bottom:16px;
                padding:12px 0;
            }
            .hero-inner {
                padding:12px;
            }
            .hero-heading {
                font-size:2rem;
            }
            .hero-sub {
                margin-top:6px;
                font-size:1rem;
                line-height:1.5;
            }
            .public-search-form, .search-guide {
                margin-top:12px;
            }
            .public-search-form input[name="q"], .public-search-form .search-submit,
            .rhu-field select, .rhu-field .record-choice .record-choice-input {
                min-height:3rem;
                padding-top:8px;
                padding-bottom:8px;
            }
            .public-search-form input[name="q"] {
                font-size:1.125rem;
            }
            .public-search-form .search-submit {
                font-size:1.25rem;
            }
            .public-search-form .rhu-field {
                margin-top:8px;
            }
            .rhu-hint {
                margin-top:4px;
                font-size:.875rem;
            }
            .search-guide {
                padding:8px 16px;
            }
            .search-guide-title {
                margin-bottom:4px;
            }
        }
    </style>

    <div class="card hero-card">
        <div class="hero-inner">

            <h1 class="hero-heading">
                Find Available <span style="color:#2a9d8f;">Medicine &amp;</span><br>
                <span class="highlight" style="color:#3bb4c1">Vaccines</span>
            </h1>

            <p class="hero-sub">
                Search and check current stocks and availability of medicines and vaccines in your nearby RHU before you travel
            </p>

            <form class="public-search-form" action="{{ route('public.home.citizen') }}" method="GET">
                <div class="search-form-flex">
                    <input type="text" name="q" id="public-search-input" value="{{ $q }}" maxlength="200" aria-label="Medicine name or dosage" enterkeyhint="search" required
                       @if($missingSearchQuery) aria-invalid="true" aria-describedby="public-search-error" @endif
                       placeholder="Search by name or dosage" autocomplete="off">
                    <button class="search-submit" type="submit">Search</button>
                </div>
                @if($missingSearchQuery)
                    <p id="public-search-error" role="alert" style="margin:0;color:#a32d2d;font-weight:600;">
                        Enter a medicine name or dosage to search.
                    </p>
                @endif

                <div class="rhu-field">
                    <select name="health_unit_id" id="rhu-hidden-input" aria-label="Rural health unit"
                            aria-describedby="public-rhu-hint" data-search-placeholder="Search for your RHU"
                            @disabled($rhuList->isEmpty())>
                        <option value="" @selected(!$selectedRhu)>{{ $rhuList->isEmpty() ? 'No RHUs available' : 'Search for your RHU' }}</option>
                        @foreach($rhuList as $rhu)
                            <option value="{{ $rhu->id }}" @selected((string) $selectedRhuId === (string) $rhu->id)>{{ $rhu->name }}</option>
                        @endforeach
                    </select>
                    <p class="rhu-hint" id="public-rhu-hint">Choose the RHU for your municipality.</p>
                </div>
            </form>

            <div class="search-guide">
                <p class="search-guide-title">Search Guide</p>
                <p class="search-guide-copy">
                    Input medicine name, select your RHU, then click <strong>Search</strong>.
                    Results will show medicine details and stocks available in the RHU you selected.
                </p>
            </div>
        </div>
    </div>

    @if($q !== '' && !$selectedRhu)
    <div class="rhu-selection-notice" style="background: #FCEBEB; border: 1px solid #F7C1C1; border-radius: 12px;
                padding: 20px 24px; margin-top: 30px; color: #A32D2D; font-size: 1.125rem;
                display: flex; align-items: center; gap: 12px; max-width: 600px;
                margin-left: auto; margin-right: auto;">
        <span style="font-size: 1.625rem;">🏥</span>
        <span><strong>Please select your RHU.</strong> Choose your municipality’s RHU above, then click <strong>Search</strong> again.</span>
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
                style="font-size: 1.875rem; font-weight: 700; color: #0f172a; margin: 0 0 6px 0;">
                Search Results
            </h2>
            <p style="font-size: 1.125rem; color: #64748b; margin: 0;">
                Showing results for "<strong style="color:#0f172a;">{{ $q }}</strong>"
                in <strong style="color:#0f172a;">{{ $selectedRhu->name }}</strong>
            </p>
        </div>

        @if($medicines->count() > 0)
            <div class="results-grid">
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
                    <div class="result-card-heading" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 14px;">
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin: 0; line-height: 1.3; word-break: break-word;">
                            {{ $medicine->medicine_name }}
                        </h3>
                        @if($medicine->requires_prescription)
                            <span class="result-tag" style="display: inline-block; font-size: 0.8125rem; font-weight: 600; padding: 4px 10px; border-radius: 999px; white-space: nowrap; background: #faeeda; color: #854f0b; border: 1px solid #fac775;">Prescription</span>
                        @else
                            <span class="result-tag" style="display: inline-block; font-size: 0.8125rem; font-weight: 600; padding: 4px 10px; border-radius: 999px; white-space: nowrap; background: #E8F7F4; color: #3b6d11; border: 1px solid #c0dd97;">OTC</span>
                        @endif
                    </div>
                    <div class="result-details" style="display: flex; flex-direction: column; gap: 6px;">
                        <div class="result-detail" style="display: flex; justify-content: space-between; font-size: 0.9375rem;">
                            <span style="color: #64748b;">Dosage:</span>
                            <span style="font-weight: 600; color: #0f172a;">{{ $medicine->dosage }}</span>
                        </div>
                        <div class="result-detail" style="display: flex; justify-content: space-between; font-size: 0.9375rem;">
                            <span style="color: #64748b;">Category:</span>
                            <span style="font-weight: 600; color: #0f172a;">{{ ucfirst($medicine->category) }}</span>
                        </div>
                        <div class="result-detail" style="display: flex; justify-content: space-between; font-size: 0.9375rem; margin-top: 4px;">
                            <span style="color: #64748b;">Total Available:</span>
                            <span style="font-weight: 700; color: #2a9d8f;">{{ $medicine->total_available }}</span>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
        @else
            <div class="result-empty-state" style="text-align: center; padding: 40px 20px; background: #f8fafc;
                        border-radius: 16px; border: 1px dashed #cbd5e1;">
                <div style="font-size: 2.5rem; margin-bottom: 8px;">
                    @if(isset($medicineExists) && $medicineExists) 📦 @else 💊 @endif
                </div>
                <h3 style="font-size: 1.375rem; font-weight: 600; color: #0f172a; margin: 0 0 6px 0;">
                    @if(isset($medicineExists) && $medicineExists)
                        Out of Stock
                    @else
                        No medicines or vaccines found
                    @endif
                </h3>
                <p style="font-size: 1rem; color: #64748b; margin: 0;">
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

        var rhu = document.getElementById('rhu-hidden-input');
        var dropdown = null;
        var currentRequest = null;
        var AUTOCOMPLETE_URL = "{{ route('public.autocomplete') }}";

        function removeDropdown() {
            if (dropdown && dropdown.parentNode) dropdown.parentNode.removeChild(dropdown);
            dropdown = null;
        }
        function showDropdown(items) {
            removeDropdown();
            dropdown = document.createElement('div');
            dropdown.className = 'autocomplete-suggestions-public';
            items.forEach(function (item) {
                var div = document.createElement('button');
                div.type = 'button';
                div.className = 'autocomplete-suggestion-item';
                div.textContent = item.text;
                div.addEventListener('click', function (e) {
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
            var rhuId = rhu.value;
            if (currentRequest) currentRequest.abort();
            currentRequest = new AbortController();
            var request = currentRequest;
            var url = AUTOCOMPLETE_URL + '?q=' + encodeURIComponent(query)
                    + (rhuId ? '&health_unit_id=' + encodeURIComponent(rhuId) : '');
            fetch(url, { signal: request.signal })
                .then(function (r) { return r.ok ? r.json() : Promise.reject('HTTP ' + r.status); })
                .then(function (data) {
                    if (request.signal.aborted) return;
                    removeDropdown();
                    if (Array.isArray(data) && data.length > 0) showDropdown(data);
                })
                .catch(function (err) { if (err.name !== 'AbortError') console.error('[autocomplete]', err); });
        }
        function refreshPublicAutocomplete() {
            var query = input.value.trim();
            if (query.length >= 2) fetchSuggestions(query);
            else {
                if (currentRequest) currentRequest.abort();
                removeDropdown();
            }
        }
        input.addEventListener('input', refreshPublicAutocomplete);
        rhu.addEventListener('change', refreshPublicAutocomplete);
        window.addEventListener('resize', removeDropdown);
        document.addEventListener('click', function (e) {
            if (!input.contains(e.target) && dropdown && !dropdown.contains(e.target)) removeDropdown();
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>
@endsection
