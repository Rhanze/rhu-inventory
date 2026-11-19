<style>
.disease-stack,#disease-report-dialog { font-family:inherit;font-size:1.1875rem;line-height:1.55;color:#334155;box-sizing:border-box; }
.disease-stack *,#disease-report-dialog * { box-sizing:border-box; }
.disease-stack { display:flex;flex-direction:column;gap:24px;min-width:0;width:100%;max-width:100%;padding-bottom:2rem; }
.disease-stack [hidden] { display:none !important; }
.disease-card { background:rgba(255,255,255,.9);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.4);border-radius:20px;padding:24px 28px;box-shadow:0 8px 20px rgba(0,0,0,.06);min-width:0; }
.disease-header { display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap; }
.disease-header-copy { flex:1 1 380px;min-width:0; }
.disease-eyebrow { font-size:1rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#0f766e;margin:0 0 8px; }
.disease-heading { font-size:1.75rem;line-height:1.3;font-weight:700;margin:0 0 8px;color:#0f172a; }
.disease-section-heading { font-size:1.5rem;line-height:1.35;font-weight:700;margin:0 0 10px;color:#0f172a; }
.disease-subheading { font-size:1.3125rem;line-height:1.4;font-weight:700;margin:0 0 10px;color:#0f172a; }
.disease-muted { color:#475569;font-size:1.125rem;line-height:1.6;margin:8px 0 18px; }
.disease-header .disease-muted { margin-bottom:0; }
.disease-view-nav { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px; }
.disease-view-link { display:flex;flex-direction:column;gap:8px;min-width:0;min-height:112px;padding:20px 24px;border:2px solid #d6e5df;border-radius:16px;background:rgba(255,255,255,.9);color:#334155;font-size:1.3125rem;font-weight:700;line-height:1.4;text-decoration:none;transition:background .2s,border-color .2s; }
.disease-view-link:hover { background:#f0fdf7;border-color:#42ad8a; }
.disease-view-link[aria-current="page"] { color:#0f5c4b;background:#E8F7F4;border-color:#0f766e; }
.disease-view-description { display:block;color:#475569;font-size:1.125rem;font-weight:400;line-height:1.5; }
.disease-scope-note { margin:0;padding:16px 20px;border-left:4px solid #42ad8a;border-radius:0 12px 12px 0;background:#f0fdf7;color:#285e4c;font-size:1.125rem;line-height:1.6; }
.disease-toolbar { display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin:18px 0; }
.disease-toolbar > form { margin:0; }
.disease-row { display:flex;gap:16px;flex-wrap:wrap;align-items:end; }
.disease-row > div { min-width:0; }
.disease-header-actions { align-items:center;gap:10px; }
.disease-row label,.disease-form-label,.disease-filters label { display:block;font-size:1.125rem;font-weight:600;margin-bottom:8px;color:#0f172a; }
.disease-control { font:inherit;font-size:1.125rem;min-height:48px;padding:10px 14px;border:2px solid #a6b7af;border-radius:10px;max-width:100%;background:#fff;color:#0f172a;box-sizing:border-box;transition:border-color .2s,box-shadow .2s; }
.disease-control:focus { border-color:#42ad8a;outline:none;box-shadow:0 0 0 3px rgba(66,173,138,.1); }
.disease-button { display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:10px 18px;box-sizing:border-box;border:1px solid #258663;border-radius:10px;background:#258663;color:white;font:inherit;font-size:1.125rem;font-weight:600;line-height:1.4;cursor:pointer;text-decoration:none;transition:background .2s,border-color .2s; }
.disease-button:hover { background:#1d6e51;border-color:#1d6e51; }
.disease-button:disabled { opacity:.6;cursor:not-allowed; }
.disease-secondary { color:#334155;background:white;border-color:#e2e8f0; }
.disease-secondary:hover { color:#0f5c4b;background:#f8fafc;border-color:#42ad8a; }
.disease-stack button:focus-visible,.disease-stack summary:focus-visible,.disease-stack a:focus-visible,.disease-stack input:focus-visible,.disease-stack select:focus-visible,.disease-scroll:focus-visible,#disease-report-dialog button:focus-visible { outline:3px solid #0f766e;outline-offset:3px; }
.disease-filters { display:grid;grid-template-columns:minmax(160px,1fr) minmax(220px,1fr) minmax(140px,.55fr) auto;gap:16px;align-items:end;margin:20px 0 24px;padding:20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px; }
.disease-filters > div { min-width:0; }
.disease-report-filters { grid-template-columns:minmax(180px,1fr) minmax(220px,1fr) auto; }
.disease-filters .disease-control { width:100%; }
.disease-table { width:100%;border-collapse:collapse;min-width:57.5rem;text-align:left;font-size:1.125rem;line-height:1.55;font-variant-numeric:tabular-nums; }
.disease-table th,.disease-table td { padding:14px 16px;border-bottom:1px solid #e2e8f0;vertical-align:middle;overflow-wrap:break-word; }
.disease-table th { background:#f8fafc;color:#334155;font-size:1.0625rem;font-weight:700;line-height:1.4;overflow-wrap:normal;word-break:normal; }
.disease-table tbody tr:hover { background:#f8fafc; }
.disease-medicine-evidence + .disease-medicine-evidence { margin-top:20px;padding-top:20px;border-top:1px solid #e2e8f0; }
.disease-medicine-evidence .disease-muted { margin:8px 0 12px; }
.disease-history-table { table-layout:auto;min-width:68.75rem; }
.disease-history-table .disease-button { width:100%;font-size:1.125rem;padding:8px 10px;min-height:46px; }
.disease-scroll { overflow-x:auto;overscroll-behavior-x:contain;width:100%;max-width:100%;min-width:0;border:1px solid #e2e8f0;border-radius:12px;box-sizing:border-box;scrollbar-color:#94a3b8 #f1f5f9; }
.disease-signal-count { margin:0 0 12px; }
.disease-signals-scroll { max-height:520px;overflow-y:scroll;overscroll-behavior-y:contain;scrollbar-gutter:stable; }
.disease-stats { display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin:20px 0; }
.disease-stat { min-width:0;padding:20px;border:1px solid #e2e8f0;border-radius:14px;font-size:1.125rem;overflow-wrap:break-word; }
.disease-stat strong { font-size:1.5625rem;line-height:1.35;display:block;margin:8px 0;color:#0f172a; }
.disease-stat .disease-muted { display:block;margin:0;font-weight:600;color:#475569; }
.disease-stat-success { background:#E8F7F4;border-color:#b2d8c9; }
.disease-stat-info { background:#EFF6FF;border-color:#bfdbfe; }
.disease-stat-purple { background:#F3E8FF;border-color:#D8B4FE; }
.disease-badge { display:inline-block;font-size:1rem;font-weight:600;padding:5px 10px;border:1px solid #e2e8f0;border-radius:999px;line-height:1.4; }
.disease-badge-warning { background:#FAEEDA;color:#854F0B;border-color:#FAC775; }
.disease-badge-success { background:#E8F7F4;color:#0f5c4b;border-color:#b2d8c9; }
.disease-badge-neutral { background:#f1f5f9;color:#475569; }
.disease-chart { position:relative;height:360px;min-width:0;margin:24px 0; }
.disease-consumption-graphs { margin:28px 0;min-width:0; }
.disease-graph-panel { padding:20px;margin-top:18px;border:1px solid #d6e5df;border-radius:14px;background:#fff;min-width:0; }
.disease-graph-data-scroll {
    margin-top:16px;
    max-height:min(24rem,60vh);
    overflow-y:auto;
    scrollbar-gutter:stable;
}
@media screen {
    .disease-graph-data-scroll thead th { position:sticky;top:0;z-index:1;box-shadow:0 1px 0 #e2e8f0; }
}
.disease-card details { padding:16px 20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px; }
.disease-card summary { min-height:44px;font-size:1.125rem;font-weight:600;line-height:1.5;cursor:pointer;color:#0f5c4b; }
.disease-details > summary,.disease-history-dates > summary { min-height:44px;font-size:1.3125rem;font-weight:700;line-height:1.4;color:#0f5c4b;cursor:pointer; }
.disease-details > summary .disease-view-description,.disease-history-dates > summary .disease-view-description { margin-top:8px; }
.disease-details[open] > summary,.disease-history-dates[open] > summary { margin-bottom:20px;padding-bottom:18px;border-bottom:1px solid #d6e5df; }
.disease-history-dates { padding:20px 24px;border:1px solid #d6e5df;border-radius:16px;background:rgba(255,255,255,.9); }
.disease-stack .history-filter-panel { font-family:inherit;font-size:1.125rem; }
.disease-stack .history-filter-panel h3 { font-size:1.3125rem;line-height:1.4;margin-bottom:8px; }
.disease-stack .history-filter-panel p,.disease-stack .history-filter-panel label { font-size:1.125rem;line-height:1.55; }
.disease-stack .history-filter-panel form { gap:16px; }
.disease-stack .history-filter-panel label { flex-basis:180px;gap:8px; }
.disease-stack .history-filter-panel input { min-height:48px;height:auto;padding:10px 12px;font:inherit;font-size:1.125rem;border-width:2px;border-radius:10px; }
.disease-stack .history-filter-panel button,.disease-stack .history-filter-panel .history-clear { min-height:48px;padding:10px 18px;border-radius:10px;font-family:inherit;font-size:1.125rem;line-height:1.4; }
.disease-stack .history-filter-panel button { background:#258663;border-color:#258663; }
.disease-stack .history-filter-panel button:hover { background:#1d6e51;border-color:#1d6e51; }
.disease-stack .history-filter-panel .history-clear:hover { background:#f8fafc;border-color:#42ad8a;color:#0f5c4b; }
.disease-stack .disease-history-dates .history-filter-panel { padding:0;background:transparent;border:0;border-radius:0; }
#disease-report-dialog { border:1px solid #e2e8f0;border-radius:22px;padding:28px 32px;width:680px;max-width:calc(100vw - 32px);max-height:90vh;overflow:auto;box-shadow:0 20px 40px rgba(0,0,0,.2); }
#disease-report-dialog::backdrop { background:rgba(15,23,42,.5);backdrop-filter:blur(2px); }
#disease-report-dialog .disease-row > div { flex:1 1 220px; }
#disease-report-dialog .disease-control { width:100%; }
#disease-report-dialog input[type=checkbox] { width:18px;height:18px;flex-shrink:0;margin-top:5px;accent-color:#42ad8a; }
@media(max-width:1100px) {
    .disease-filters { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .disease-stats { grid-template-columns:minmax(0,1fr); }
}
@media screen and (min-width:901px) {
    .disease-signals-scroll thead th { position:sticky;top:0;z-index:1;box-shadow:0 1px 0 #e2e8f0; }
}
@media screen and (max-width:900px) {
    body.role-midwife .disease-stack .mw-records-enhanced { display:block;table-layout:auto; }
    body.role-midwife .disease-stack .mw-records-enhanced colgroup { display:none; }
    body.role-midwife .disease-stack .mw-records-enhanced tbody,
    body.role-midwife .disease-stack .mw-records-enhanced tbody tr { width:100%; }
    body.role-midwife .main-content .disease-stack input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),
    body.role-midwife .main-content .disease-stack select,
    body.role-midwife .main-content .disease-stack textarea,
    body.role-midwife #disease-report-dialog .disease-control { font-size:1.125rem !important;min-height:48px; }
    body.role-midwife .disease-stack .mw-records-enhanced td[data-label]::before { color:#475569;font-size:1.0625rem;line-height:1.45;margin-bottom:6px; }
}
@media(max-width:600px) {
    .disease-card { padding:20px; }
    .disease-graph-panel { padding:14px; }
    .disease-signals-scroll { max-height:420px;max-height:min(420px,65dvh); }
    .disease-consumption-graphs .disease-chart { height:320px; }
    .disease-view-nav { gap:10px; }
    .disease-view-link { justify-content:center;min-height:72px;padding:12px;font-size:1.125rem;text-align:center; }
    .disease-view-link .disease-view-description { display:none; }
    .disease-filters { grid-template-columns:minmax(0,1fr);padding:16px; }
    .disease-header-actions { width:100%; }
    .disease-header-actions .disease-button { flex:1 1 180px; }
    .disease-toolbar { align-items:stretch; }
    .disease-toolbar > .disease-button { flex:1 1 180px; }
    .disease-history-dates { padding:20px; }
    .disease-stack .history-filter-panel label { flex-basis:100%; }
    #disease-report-dialog { padding:24px 20px; }
}
.disease-signals-card { container:disease-signal / inline-size; }
.disease-filters.disease-trend-filters { grid-template-columns:minmax(0,1.25fr) minmax(0,1.25fr) minmax(12rem,.8fr) auto;align-items:end; }
.disease-trend-filters > div { display:flex;flex-direction:column;gap:8px;min-width:0; }
.disease-trend-filters label { margin:0;line-height:1.4; }
.disease-trend-filters .disease-control,.disease-trend-filters .disease-button { min-height:3.25rem;height:3.25rem;line-height:1.4; }
.disease-trend-filters .disease-button { align-self:end;max-width:100%; }
@container disease-signal (max-width:66rem) {
    .disease-filters.disease-trend-filters { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .disease-trend-filters .disease-button { width:100%; }
}
@container disease-signal (max-width:36rem) {
    .disease-filters.disease-trend-filters { grid-template-columns:minmax(0,1fr); }
}
@supports not (container-type:inline-size) {
    @media(max-width:1366px) {
        .disease-filters.disease-trend-filters { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .disease-trend-filters .disease-button { width:100%; }
    }
    @media(max-width:768px) {
        .disease-filters.disease-trend-filters { grid-template-columns:minmax(0,1fr); }
    }
}
@media print {
    .disease-card { background:white;box-shadow:none;backdrop-filter:none;border:1px solid #e2e8f0; }
    .disease-header-actions,.disease-filters,.disease-view-nav,.disease-history-dates { display:none; }
    .disease-signals-scroll,.disease-graph-data-scroll { max-height:none;overflow:visible;scrollbar-gutter:auto; }
}
</style>
