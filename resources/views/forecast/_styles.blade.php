<style>
    .forecast-page { line-height:1.5; }
    .forecast-page h1, .forecast-page h2, .forecast-page h3, .forecast-page h4 { line-height:1.3; }
    .forecast-page [id] { scroll-margin-top:24px; }
    .forecast-overview { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px; }
    .forecast-metric { display:flex;flex-direction:column;padding:20px;background:#fff;border:1px solid #dce5e2;border-radius:12px;color:#334155;text-decoration:none; }
    .forecast-metric > span { font-size:14px;font-weight:600; }
    .forecast-metric strong { display:block;font-size:30px;line-height:1.3;margin:8px 0;color:#0f172a;font-variant-numeric:tabular-nums; }
    .forecast-metric strong.forecast-critical { color:#b91c1c; }
    .forecast-metric small { color:#475569;font-size:13px; }
    .forecast-metric:hover { border-color:#0f766e; }
    .forecast-section-nav { display:flex;gap:6px;border-bottom:1px solid #b8cec5;padding-bottom:12px; }
    .forecast-section-nav a { color:#0f5c4b;text-decoration:none;padding:8px 12px;border-radius:6px;font-size:14px;font-weight:600; }
    .forecast-section-nav a:hover { background:#e9f5ef; }
    .forecast-page a:focus-visible,.forecast-page select:focus-visible,.forecast-page summary:focus-visible,.forecast-page [tabindex]:focus-visible { outline:3px solid #0f766e;outline-offset:3px; }
    .forecast-page table { font-variant-numeric:tabular-nums; }
    .forecast-page tbody tr:hover { background:#f8fafc; }
    .forecast-page th { white-space:nowrap; }
    .forecast-page select { min-height:44px; }
    .forecast-page summary { cursor:pointer;font-weight:600;color:#0f5c4b; }
    .forecast-page caption { text-align:left;padding:0 16px 12px;color:#475569;font-size:13px; }
    .forecast-status { display:inline-block;font-size:12px;font-weight:600;padding:4px 10px;border-radius:6px;white-space:nowrap; }
    .forecast-status-critical { background:#fef2f2;color:#991b1b; }
    .forecast-status-low { background:#fffbeb;color:#854d0e; }
    .forecast-status-adequate { background:#ecfdf5;color:#065f46; }
    .forecast-status-surplus { background:#eff6ff;color:#1e40af; }
    .forecast-status-unknown { background:#f1f5f9;color:#475569; }
    .forecast-scroll-note { margin:0;padding:0 26px 12px;font-size:13px;color:#475569; }
</style>
