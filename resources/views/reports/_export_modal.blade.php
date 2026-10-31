@php
    $modalTitle  = $modalTitle  ?? 'Export Report';
    $showExpiry  = $showExpiry  ?? false;
    $showBatch   = $showBatch   ?? true;
    $showProgram = $showProgram ?? true;
    $csvRoute    = $csvRoute    ?? 'reports.inventory.csv';
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

            {{-- BATCH NUMBER --}}
            @if($showBatch)
                <div style="margin-bottom:22px;">
                    <label for="batchInput-{{ $modalId }}"
                           style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                        Batch Number(s)
                    </label>
                    <input type="text"
                           id="batchInput-{{ $modalId }}"
                           placeholder="e.g. BT-001, 1-8, BT-020, 25"
                           value="{{ request('batch_input', '') }}"
                           style="
                               width:100%; height:44px;
                               padding:0 14px; font-size:14px;
                               border:2px solid #bec6cf; border-radius:10px;
                               outline:none; box-sizing:border-box;
                               background:#ffffff; color:#0f172a;
                           "
                           onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                           onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <p style="font-size:12px; color:#64748b; margin:6px 0 0 0; line-height:1.5;">
                        Separate with commas. Use a range like <code style="background:#f1f5f9; padding:1px 5px; border-radius:4px; font-size:11px;">1-8</code> for batches 1 through 8.
                        Leave blank for all.
                    </p>
                </div>
            @endif

            {{-- EXPIRY --}}
            @if($showExpiry)
                <div style="margin-bottom:22px;">
                    <label for="expirySelect-{{ $modalId }}"
                           style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                        Expiry Status
                    </label>
                    <select id="expirySelect-{{ $modalId }}"
                            style="
                                width:100%; height:44px;
                                padding:0 14px; font-size:14px;
                                border:2px solid #bec6cf; border-radius:10px;
                                outline:none; box-sizing:border-box;
                                background:#ffffff; color:#0f172a;
                                appearance:auto; cursor:pointer;
                            "
                            onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                            onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                        <option value=""            {{ request('expiry_filter', '') === ''            ? 'selected' : '' }}>All Expiry</option>
                        <option value="near_expiry" {{ request('expiry_filter', '') === 'near_expiry' ? 'selected' : '' }}>Near Expiry (≤180 days)</option>
                        <option value="expired"     {{ request('expiry_filter', '') === 'expired'     ? 'selected' : '' }}>Expired Only</option>
                        <option value="not_expired" {{ request('expiry_filter', '') === 'not_expired' ? 'selected' : '' }}>Not Expiring Soon</option>
                    </select>
                </div>
            @endif

            {{-- PROGRAM / CATEGORY --}}
            @if($showProgram)
                <div>
                    <label for="categorySelect-{{ $modalId }}"
                           style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:8px;">
                        Program Category
                    </label>
                    <select id="categorySelect-{{ $modalId }}"
                            style="
                                width:100%; height:44px;
                                padding:0 14px; font-size:14px;
                                border:2px solid #bec6cf; border-radius:10px;
                                outline:none; box-sizing:border-box;
                                background:#ffffff; color:#0f172a;
                                appearance:auto; cursor:pointer;
                            "
                            onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                            onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                        @foreach($categories as $val => $label)
                            <option value="{{ $val }}" {{ request('category', '') === $val ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
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
                    onclick="runReportExport('{{ $modalId }}', '{{ $printAction }}', 'print')"
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
                    onclick="runReportExport('{{ $modalId }}', '{{ route($csvRoute) }}', 'csv')"
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

    if (typeof window.runReportExport !== 'function') {
        window.runReportExport = function (modalId, baseUrl, mode) {
            const modal          = document.getElementById(modalId);
            const batchInput     = document.getElementById('batchInput-' + modalId);
            const expirySelect   = document.getElementById('expirySelect-' + modalId);
            const categorySelect = document.getElementById('categorySelect-' + modalId);

            const rawBatch  = batchInput     ? batchInput.value.trim() : '';
            const expiryVal = expirySelect   ? expirySelect.value      : '';
            const catVal    = categorySelect ? categorySelect.value    : '';

            const url    = new URL(baseUrl, window.location.origin);
            const params = new URLSearchParams(window.location.search);
            params.delete('page');
            params.delete('batch_input');
            params.delete('expiry_filter');
            params.delete('category');
            params.delete('print');

            if (rawBatch  !== '') params.set('batch_input', rawBatch);
            if (expiryVal)        params.set('expiry_filter', expiryVal);
            if (catVal)           params.set('category', catVal);
            if (mode === 'print') params.set('print', '1');

            url.search = params.toString();
            window.location.href = url.toString();
        };
    }

    // Escape backdrop-filter containing block
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[id^="exportModal-"]').forEach(function (el) {
            if (el.parentElement !== document.body) {
                document.body.appendChild(el);
            }
        });
    });
</script>