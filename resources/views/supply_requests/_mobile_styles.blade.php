<style>
    @media screen and (max-width: 767px) {
        body.role-midwife .supply-request-page,
        body.role-midwife .supply-request-page > *,
        body.role-midwife .supply-request-page form > * {
            min-width: 0;
            max-width: 100%;
            box-sizing: border-box;
            overflow-wrap: anywhere;
        }
        body.role-midwife .supply-request-header,
        body.role-midwife .supply-request-panel {
            padding: 18px !important;
            border-radius: 16px !important;
        }
        body.role-midwife .supply-request-header > a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 44px;
            box-sizing: border-box;
        }
        body.role-midwife .supply-request-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }
        body.role-midwife .supply-request-stats > div { padding: 16px 10px !important; }
        body.role-midwife .supply-request-filters > div {
            flex: 1 1 100% !important;
            min-width: 0;
        }
        body.role-midwife .supply-request-filter-actions {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        body.role-midwife .supply-request-filter-actions > * { justify-content: center; }
        body.role-midwife .supply-request-page input:not([type="hidden"]),
        body.role-midwife .supply-request-page select,
        body.role-midwife .supply-request-page textarea {
            font-size: 16px !important;
            min-height: 44px;
            max-width: 100%;
        }
        body.role-midwife .supply-request-form-grid { grid-template-columns: minmax(0, 1fr) !important; }
        body.role-midwife .supply-request-form-actions { flex-direction: column-reverse; }
        body.role-midwife .supply-request-form-actions > * {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            width: 100%;
            box-sizing: border-box;
            text-align: center;
        }
        body.role-midwife .supply-request-page #medicine-search { padding-right: 58px !important; }
        body.role-midwife .supply-request-page #clear-medicine-btn {
            min-width: 44px;
            min-height: 44px;
            right: 4px !important;
        }
        body.role-midwife .supply-request-page #medicine-suggestions {
            max-height: 45vh !important;
            max-height: 45dvh !important;
            overscroll-behavior: contain;
        }
        body.role-midwife .supply-medicine-option {
            flex-wrap: wrap;
            align-items: flex-start !important;
            gap: 6px !important;
        }
        body.role-midwife .supply-medicine-option > div:first-child { flex-basis: 100% !important; }

        /* Keep every request field and action visible without a wide table on phones. */
        body.role-midwife .supply-request-table-wrap { overflow: visible !important; }
        body.role-midwife .supply-request-table {
            display: block;
            min-width: 0 !important;
            width: 100%;
        }
        body.role-midwife .supply-request-table thead {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip-path: inset(50%);
            white-space: nowrap;
        }
        body.role-midwife .supply-request-table tbody { display: grid; gap: 12px; padding: 12px; }
        body.role-midwife .supply-request-table tbody tr {
            display: block;
            min-width: 0;
            padding: 6px 0;
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px;
            background: #fff;
        }
        body.role-midwife .supply-request-table td[data-label] {
            display: grid;
            grid-template-columns: minmax(84px, .4fr) minmax(0, 1fr);
            gap: 4px 12px;
            padding: 10px 12px !important;
            text-align: left !important;
            white-space: normal !important;
            overflow-wrap: anywhere;
        }
        body.role-midwife .supply-request-table td[data-label]::before {
            content: attr(data-label);
            grid-column: 1;
            grid-row: 1 / span 4;
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
        }
        body.role-midwife .supply-request-table td[data-label] > * { min-width: 0; }
        body.role-midwife .supply-request-table td[data-label] button {
            min-height: 44px;
            width: 100%;
            padding: 10px !important;
            font-size: 14px !important;
        }
        body.role-midwife .supply-request-table td[data-label] form { display: block !important; }
        body.role-midwife .supply-request-table td[colspan] { display: block; padding: 24px 14px !important; }
    }
</style>
