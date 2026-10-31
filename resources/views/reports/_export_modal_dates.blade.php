@php
    $modalTitle  = $modalTitle  ?? 'Export Report';
    $showExpiry  = $showExpiry  ?? true;
    $csvRoute    = $csvRoute    ?? 'reports.inventory.csv';
    $printAction = $printAction ?? url()->current();
    $modalId     = 'exportModal-' . md5($csvRoute . $printAction);
@endphp

<button type="button" onclick="openExportModal('{{ $modalId }}')" style="
    display:inline-flex; align-items:center; gap:6px;
    font-size:14px; font-weight:600;
    padding:9px 18px; border-radius:10px;
    background:#42ad8a; color:#ffffff;
    border:none; cursor:pointer; transition: background .2s;
" onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/>
        <line x1="12" y1="15" x2="12" y2="3"/>
    </svg>
    Export
</button>

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
        box-shadow: 0 24px 60px rgba(0,0,0,0.25);
    ">
        <div style="padding:22px 26px 14px; border-bottom:1px solid #f1f5f9;">
            <h3 style="font-size:19px; font-weight:700; color:#0f172a; margin:0 0 4px 0;">
                {{ $modalTitle }}
            </h3>
            <p style="font-size:13px; color:#64748b; margin:0;">
                Choose what to include, then pick an action.
            </p>
        </div>

        <div style="padding:20px 26px;">
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
                    Separate multiple entries with commas. Use a range like <code style="background:#f1f5f9; padding:1px 5px; border-radius:4px; font-size:11px;">1-8</code> to include batches 1 through 8.
                    Leave blank to include all batches.
                </p>
            </div>

            @if($showExpiry)
                <div>
                    <label style="font-size:13px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:10px;">
                        Expiry Status
                    </label>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        @foreach([
                            ''            => 'All Expiry',
                            'near_expiry' => 'Near Expiry (≤180 days)',
                            'expired'     => 'Expired Only',
                            'not_expired' => 'Not Expiring Soon',
                        ] as $val => $label)
                            <label style="
                                display:flex; align-items:center; gap:10px;
                                padding:9px 12px; border:1px solid #e2e8f0; border-radius:10px;
                                cursor:pointer; font-size:14px; color:#0f172a;
                                transition: all .15s;
                            "
                                   onmouseover="this.style.borderColor='#42ad8a'; this.style.background='#f0fdf9';"
                                   onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#ffffff';">
                                <input type="radio"
                                       name="expiry_filter_{{ $modalId }}"
                                       value="{{ $val }}"
                                       {{ $val === request('expiry_filter', '') ? 'checked' : '' }}
                                       style="width:16px; height:16px; accent-color:#42ad8a; cursor:pointer;">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

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
        window.runReportExport = function (modalId, baseUrl, mode) {
            const modal = document.getElementById(modalId);
            const batchInput = document.getElementById('batchInput-' + modalId);
            const expiryRadio = modal.querySelector('input[name="expiry_filter_' + modalId + '"]:checked');

            const rawBatch = batchInput ? batchInput.value.trim() : '';
            const expiryVal = expiryRadio ? expiryRadio.value : '';

            const url = new URL(baseUrl, window.location.origin);
            const params = new URLSearchParams(window.location.search);
            params.delete('page');
            params.delete('batch_input');
            params.delete('expiry_filter');
            params.delete('print');

            if (rawBatch !== '') params.set('batch_input', rawBatch);
            if (expiryVal) params.set('expiry_filter', expiryVal);
            if (mode === 'print') params.set('print', '1');

            url.search = params.toString();
            window.location.href = url.toString();
        };
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('[id^="exportModal-"]').forEach(el => {
                    if (el.style.display === 'flex') {
                        el.style.display = 'none';
                        document.body.style.overflow = '';
                    }
                });
            }
        });
    }
</script>