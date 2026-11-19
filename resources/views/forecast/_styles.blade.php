<style>
    .forecast-page { min-width:0;max-width:100%;font-family:inherit;font-size:1.0625rem;line-height:1.55;color:#334155; }
    .forecast-page [style*="--forecast-text-size"] { font-size:var(--forecast-text-size); }
    .forecast-page h1, .forecast-page h2, .forecast-page h3, .forecast-page h4 { line-height:1.3; }
    .forecast-page [id] { scroll-margin-top:24px; }
    .forecast-panel { background:rgba(255,255,255,.9);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.4);border-radius:20px;box-shadow:0 8px 20px rgba(0,0,0,.06);min-width:0;box-sizing:border-box; }
    .forecast-header { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:20px;padding:24px 28px; }
    .forecast-heading { flex:1 1 320px;min-width:0; }
    .forecast-heading h1 { font-size:1.6875rem;font-weight:700;color:#0f172a;margin:0; }
    .forecast-intro { font-size:1.0625rem;color:#475569;margin:8px 0 0; }
    .forecast-trend { padding:24px 26px; }
    .forecast-charts-row { display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.3fr);gap:20px; }
    .forecast-eyebrow { font-size:1rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#42ad8a;margin:0 0 6px; }
    .forecast-select { width:100%;max-width:680px;padding:12px 16px;font:inherit;font-size:1.0625rem;color:#0f172a;background:white;border:2px solid #bec6cf;border-radius:14px;box-sizing:border-box;transition:border-color .2s,box-shadow .2s; }
    .forecast-select:focus { border-color:#42ad8a;box-shadow:0 0 0 3px rgba(66,173,138,.1); }
    .forecast-overview { display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,14rem),1fr));gap:16px; }
    .forecast-metric { display:flex;flex-direction:column;min-width:0;padding:22px 24px;background:rgba(255,255,255,.9);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.4);border-radius:20px;box-shadow:0 8px 20px rgba(0,0,0,.06);color:#334155;text-decoration:none;transition:border-color .2s,box-shadow .2s; }
    .forecast-metric > span { font-size:1.0625rem;font-weight:600; }
    .forecast-metric strong { display:block;font-size:2.1875rem;line-height:1.3;margin:8px 0;color:#0f172a;font-variant-numeric:tabular-nums; }
    .forecast-metric strong.forecast-critical { color:#b91c1c; }
    .forecast-metric small { color:#475569;font-size:1rem; }
    .forecast-metric { border-radius:14px;padding:18px;box-shadow:none; }
    .forecast-page small:not(.forecast-metric small) { font-size:0.9375rem; }
    .forecast-metric-danger { background:#FCEBEB;border-color:#F7C1C1; }
    .forecast-metric-warning { background:#FAEEDA;border-color:#FAC775; }
    .forecast-metric-neutral { background:#F3E8FF;border-color:#D8B4FE; }
    .forecast-metric-success { background:#E8F7F4;border-color:#b2d8c9; }
    .forecast-metric:hover { border-color:#42ad8a;box-shadow:0 8px 20px rgba(47,158,143,.12); }
    .forecast-page a:focus-visible,.forecast-page select:focus-visible,.forecast-page summary:focus-visible,.forecast-page [tabindex]:focus-visible { outline:3px solid #0f766e;outline-offset:3px; }
    .forecast-page :is(h1,h2,h3,h4,p,caption) { overflow-wrap:break-word; }
    .forecast-page table { font-variant-numeric:tabular-nums; }
    .forecast-table-scroll { width:100%;max-width:100%;min-width:0;overflow-x:auto;overscroll-behavior-x:contain;box-sizing:border-box;scrollbar-color:#94a3b8 #f1f5f9; }
    .forecast-stock-table { width:100%;min-width:68.75rem;table-layout:auto;border-collapse:collapse;font-size:1.0625rem; }
    .forecast-stock-table th,.forecast-stock-table td { padding:16px 12px;vertical-align:middle;overflow-wrap:break-word; }
    .forecast-page .forecast-stock-table th { white-space:normal;overflow-wrap:normal;word-break:normal;line-height:1.4;letter-spacing:.03em; }
    .forecast-stock-table td { line-height:1.5; }
    .forecast-stock-table small { margin-top:6px; }
    .forecast-stock-table .forecast-status { white-space:normal;max-width:100%;box-sizing:border-box; }
    .forecast-stock-table caption { box-sizing:border-box; }
    .forecast-page tbody tr:hover { background:#f8fafc; }
    .forecast-page th { white-space:nowrap; }
    .forecast-page thead th { font-size:0.9375rem;font-weight:600;line-height:1.5;text-transform:uppercase;letter-spacing:.06em;color:#475569; }
    .forecast-page select { min-height:44px; }
    .forecast-page summary { cursor:pointer;font-weight:600;color:#0f5c4b; }
    .forecast-page caption { text-align:left;padding:0 20px 12px;color:#475569;font-size:1rem; }
    .forecast-status { display:inline-block;font-size:0.9375rem;font-weight:600;padding:5px 12px;border:1px solid transparent;border-radius:999px;white-space:nowrap; }
    .forecast-status-critical { border-color:#fecaca; }
    .forecast-status-low { border-color:#fac775; }
    .forecast-status-adequate { border-color:#b2d8c9; }
    .forecast-status-surplus { border-color:#93c5fd; }
    .forecast-status-unknown { border-color:#e2e8f0; }
    .forecast-status-critical { background:#fef2f2;color:#991b1b; }
    .forecast-status-low { background:#fffbeb;color:#854d0e; }
    .forecast-status-adequate { background:#ecfdf5;color:#065f46; }
    .forecast-status-surplus { background:#eff6ff;color:#1e40af; }
    .forecast-status-unknown { background:#f1f5f9;color:#475569; }
    .forecast-scroll-note { margin:0;padding:0 26px 12px;font-size:1rem;color:#475569; }
    @media(max-width:1100px) {
        .forecast-overview { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .forecast-charts-row { grid-template-columns:minmax(0,1fr); }
    }
    @media(max-width:600px) {
        .forecast-overview { grid-template-columns:minmax(0,1fr);gap:12px; }
        .forecast-metric { padding:20px; }
        .forecast-trend { padding:20px; }
    }
    @media print {
        .forecast-panel,.forecast-metric { background:white;box-shadow:none;backdrop-filter:none;border:1px solid #e2e8f0; }
    }
</style>
