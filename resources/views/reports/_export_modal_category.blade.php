@php
    $modalTitle  = $modalTitle  ?? 'Export Stock on Hand';
    $csvRoute    = $csvRoute    ?? 'medicine.stock_on_hand.csv';
    $printAction = $printAction ?? url()->current();
    $modalId     = 'exportModal-' . md5($csvRoute . $printAction);

    $categories = [
        ''                        => 'All Categories',
        'medicine'                => 'Medicine',
        'vaccine'                 => 'Vaccine',
        'family_planning'         => 'Family Planning',
        'family_health_nutrition' => 'Family Health & Nutrition',
        'emerging_reemerging'     => 'Emerging & Re-emerging',
        'non_communicable'        => 'Non-Communicable Disease',
        'dengue_prevention'       => 'Dengue Prevention',
        'tuberculosis'            => 'Tuberculosis',
    ];

    $stockStatuses = [
        ''            => 'All Items',
        'available'   => 'Available Only (stock > 0)',
        'allocated'   => 'Allocated Only',
    ];
@endphp

{{-- TRIGGER BUTTON --}}
<button type="button" onclick="openExportModal('{{ $modalId }}')" style="
    display:inline-flex; align-items:center; gap:6px;
    font-size:14px; font-weight:600;
    padding:9px 18px; border-radius:10px;
    background:#42ad8a; color:#ffffff;
    border:none; cursor:pointer; transition: background 0.2s;
" onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/>
        <line x1="12" y1="15" x2="12" y2="3"/>
    </svg>
    Export
</button>

{{-- MODAL --}}
<div id="{{ $modalId }}"
     onclick="if(event.target === this) closeExportModal('{{ $modalId }}')"
     style="
        display:none; position:fixed; inset:0; z-index:99999;
        background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);
        align-items:center; justify-content:center; padding:20px;
     ">
    <div style="
        background:#ffffff; border-radius:20px;
        width:100%; max-width:520px;
        display:flex; flex-direction:column;
        max-height: 90vh;
        box-shadow: 0 24px 60px rgba(0,0,0,0.25);
    ">
        {{-- Header --}}
        <div style="padding:22px 26px 14px; border-bottom:1px solid #f1f5f9;">
            <h3 style="font-size:19px; font-weight:700; color:#0f172a; margin:0 0 4px 0;">
                {{ $modalTitle }}
            </h3>
            <p style="font-size:13px; color:#64748b; margin:0;">
                Choose what to include, then pick an action.
            </p>
        </div>

        {{-- Body --}}
        <div style="padding:20px 26px; overflow-y:auto; flex:1;">

            {{-- STOCK STATUS --}}
            <div style="margin-bottom:22px;">
                <label style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:10px;">
                    Stock Status
                </label>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    @foreach($stockStatuses as $val => $label)
                        <label style="
                            display:flex; align-items:center; gap:10px;
                            padding:9px 12px; border:1px solid #e2e8f0; border-radius:10px;
                            cursor:pointer; font-size:14px; color:#0f172a;
                            transition: all .15s;
                        "
                               onmouseover="this.style.borderColor='#42ad8a'; this.style.background='#f0fdf9';"
                               onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#ffffff';">
                            <input type="radio"
                                   name="status_filter_{{ $modalId }}"
                                   value="{{ $val }}"
                                   {{ $val === request('status_filter', '') ? 'checked' : '' }}
                                   style="width:16px; height:16px; accent-color:#42ad8a; cursor:pointer;">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- CATEGORY --}}
            <div>
                <label style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:10px;">
                    Program Category
                </label>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    @foreach($categories as $val => $label)
                        <label style="
                            display:flex; align-items:center; gap:10px;
                            padding:9px 12px; border:1px solid #e2e8f0; border-radius:10px;
                            cursor:pointer; font-size:14px; color:#0f172a;
                            transition: all .15s;
                        "
                               onmouseover="this.style.borderColor='#42ad8a'; this.style.background='#f0fdf9';"
                               onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#ffffff';">
                            <input type="radio"
                                   name="category_filter_{{ $modalId }}"
                                   value="{{ $val }}"
                                   {{ $val === request('category', '') ? 'checked' : '' }}
                                   style="width:16px; height:16px; accent-color:#42ad8a; cursor:pointer;">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div style="
            padding:16px 26px 20px;
            border-top:1px solid #f1f5f9;
            display:flex; gap:10px; justify-content:flex-end;
            background:#fafbfc;
            border-radius: 0 0 20px 20px;
        ">
            <button type="button"
                    onclick="closeExportModal('{{ $modalId }}')"
                    style="font-size:14px; font-weight:600; padding:10px 18px;
                           border-radius:10px; background:#ffffff; color:#334155;
                           border:1px solid #e2e8f0; cursor:pointer;">
                Cancel
            </button>

            <button type="button"
                    onclick="runCategoryExport('{{ $modalId }}', '{{ $printAction }}', 'print')"
                    style="display:inline-flex; align-items:center; gap:6px;
                           font-size:14px; font-weight:600; padding:10px 18px;
                           border-radius:10px; background:#ffffff; color:#334155;
                           border:1px solid #e2e8f0; cursor:pointer;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                Print
            </button>

            <button type="button"
                    onclick="runCategoryExport('{{ $modalId }}', '{{ route($csvRoute) }}', 'csv')"
                    style="display:inline-flex; align-items:center; gap:6px;
                           font-size:14px; font-weight:600; padding:10px 18px;
                           border-radius:10px; background:#42ad8a; color:#ffffff;
                           border:none; cursor:pointer;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Export CSV
            </button>
        </div>
    </div>
</div>

<script>
    if (typeof window.openExportModal !== 'function') {
        window.openExportModal = function (id) {
            document.getElementById(id).style.display = 'flex';
            document.body.style.overflow = 'hidden';
        };
        window.closeExportModal = function (id) {
            document.getElementById(id).style.display = 'none';
            document.body.style.overflow = '';
        };
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('[id^="exportModal-"]').forEach(function (el) {
                    if (el.style.display === 'flex') {
                        el.style.display = 'none';
                        document.body.style.overflow = '';
                    }
                });
            }
        });
    }

    if (typeof window.runCategoryExport !== 'function') {
        window.runCategoryExport = function (modalId, baseUrl, mode) {
            const modal     = document.getElementById(modalId);
            const catRadio  = modal.querySelector('input[name="category_filter_' + modalId + '"]:checked');
            const statRadio = modal.querySelector('input[name="status_filter_'   + modalId + '"]:checked');

            const catVal  = catRadio  ? catRadio.value  : '';
            const statVal = statRadio ? statRadio.value : '';

            const url    = new URL(baseUrl, window.location.origin);
            const params = new URLSearchParams(window.location.search);
            params.delete('page');
            params.delete('category');
            params.delete('status_filter');
            params.delete('print');

            if (catVal)  params.set('category', catVal);
            if (statVal) params.set('status_filter', statVal);
            if (mode === 'print') params.set('print', '1');

            url.search = params.toString();
            window.location.href = url.toString();
        };
    }

    // Escape backdrop-filter containing block: move modal to <body>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[id^="exportModal-"]').forEach(function (el) {
            if (el.parentElement !== document.body) {
                document.body.appendChild(el);
            }
        });
    });
</script>