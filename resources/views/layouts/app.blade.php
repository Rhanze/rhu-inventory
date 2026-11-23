<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/web-icon.png') }}">
    <title>PHO Supply Portal | @yield('title', 'Inventory')</title>
    @vite('resources/css/app.css')

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(180deg, #d4f0ea 0%, #85e0b5 100%);
            color: #0f172a;
            min-height: 100vh;
            font-size: 1.0625rem;
            line-height: 1.5;
        }

        .app-shell { display: flex; min-width: 0; min-height: 100vh; }
        .app-shell *, .app-shell *::before, .app-shell *::after { box-sizing: border-box; }

        /* ========== SIDEBAR ========== */
        .sidebar {
            width: 17.5rem;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(12px);
            border-right: 0.5px solid rgba(66,173,138,0.3);
            display: flex;
            flex-direction: column;
            padding: 24px 0 0 0;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 10;
        }

        .sidebar-brand {
            padding: 0 20px 20px;
            border-bottom: 0.5px solid rgba(66,173,138,0.2);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-brand img { width: 40px; height: 40px; object-fit: contain; }

        .sidebar-brand h2 {
            font-size: 1.4375rem; font-weight: 700; color: #0f5c4b; margin: 0;
        }

        .sidebar-brand p { font-size: 0.9375rem; color: #475569; margin: 0; }
        .sidebar-brand > div, .sidebar-footer .footer-row > div { min-width: 0; overflow-wrap: break-word; }

        .nav-section { padding: 0 12px; margin-bottom: 8px; }

        .nav-section-title {
            font-size: 0.8125rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.08em; color: #64748b; margin: 0 0 4px 12px;
        }

        .nav-links {
            display: flex; flex-direction: column; gap: 2px;
            padding: 0 12px; flex: 0 0 auto;
        }

        .nav-links a {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 12px; border-radius: 10px;
            text-decoration: none; font-size: 1.0625rem; font-weight: 500;
            color: #334155; transition: all 0.2s;
        }

        .nav-links a svg {
            width: 20px; height: 20px; stroke: currentColor; flex-shrink: 0;
        }

        .nav-links a svg.icon-accent { stroke: #42ad8a; }

        .nav-links a:hover {
            background: rgba(66,173,138,0.08);
            color: #0f5c4b;
            transform: translateX(4px);
        }

        .nav-links a.active {
            background: #E8F7F4; color: #0f5c4b; font-weight: 600;
        }

        /* ========== SIDEBAR FOOTER ========== */
        .sidebar-footer {
            padding: 16px 20px 20px;
            border-top: 0.5px solid rgba(66,173,138,0.2);
            margin-top: auto;
        }

        .sidebar-footer .footer-row {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
        }

        .sidebar-footer .user-info {
            font-size: 1.0625rem; font-weight: 600; color: #0f172a; line-height: 1.2;
        }

        .sidebar-footer .user-role {
            font-size: 0.9375rem; color: #64748b; margin-top: 2px;
        }

        .sidebar-footer form { margin: 0; }

        .logout-btn {
            width: 42px; height: 42px;
            display: inline-flex; align-items: center; justify-content: center;
            background: #fee2e2; color: #991b1b;
            border: 1px solid #fca5a5; border-radius: 10px;
            cursor: pointer; transition: background 0.2s, transform 0.15s;
            padding: 0; flex-shrink: 0;
        }

        .logout-btn:hover { background: #fecaca; transform: scale(1.08); }
        .logout-btn:active { transform: scale(0.95); }

        .logout-btn svg {
            width: 22px; height: 22px; stroke: #991b1b;
            fill: none; stroke-width: 2;
            stroke-linecap: round; stroke-linejoin: round;
        }

        /* ========== HEADER ========== */
        .public-header {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 5px 10px rgba(0,0,0,0.08);
            padding: 14px 0;
        }

        .public-header-inner {
            margin: 0; padding-left: 20px; padding-right: 20px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 20px; min-height: 70px; flex-wrap: wrap;
        }

        .brand-wrap { display: flex; align-items: center; gap: 12px; flex: 1 1 26rem; min-width: 0; }
        .brand-wrap > div { min-width: 0; }

        .logo-box {
            width: 48px; height: 48px; border-radius: 10px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
        }

        .logo-box img { width: 100%; height: 100%; object-fit: contain; }

        .brand-title { font-size: clamp(1.25rem, 1rem + 0.8vw, 1.6875rem); font-weight: 700; margin: 0; color: #0f172a; line-height: 1.25; overflow-wrap: break-word; }

        .brand-sub { font-size: 1rem; margin: 3px 0 0 0; color: #64748b; }

        .header-actions { display: flex; align-items: center; justify-content: flex-end; gap: 12px; flex: 0 1 auto; min-width: 0; max-width: 100%; }

        .header-search { display: flex; align-items: center; gap: 8px; margin: 0; flex-wrap: wrap; min-width: 0; }

        .header-search input,
        .header-search select {
            min-height: 42px; border: 2px solid #bec6cf; border-radius: 10px;
            padding: 8px 12px; font-size: 1rem; outline: none; min-width: 0; max-width: 100%;
            background: white; color: #0f172a;
        }

        .header-search input:focus,
        .header-search select:focus {
            border-color: #42ad8a;
            box-shadow: 0 0 0 3px rgba(66,173,138,0.1);
        }

        .header-search input { width: 12.5rem; flex: 1 1 10rem; }
        .header-search select { width: 9.375rem; flex: 1 1 9rem; appearance: auto; }

        .header-search button {
            min-height: 42px; padding: 8px 18px;
            background: #0f5c4b; color: white; border: none;
            border-radius: 10px; font-weight: 600; font-size: 1rem;
            cursor: pointer; transition: background 0.25s, transform 0.1s;
        }

        .header-search button:hover { background: #0b483b; }
        .header-search button:active { transform: scale(0.97); }

        /* ===== NOTIFICATION BELL ===== */
        .notification-bell {
            position: relative; display: inline-flex; align-items: center; justify-content: center;
            width: 42px; height: 42px; border-radius: 10px; flex-shrink: 0;
            background: transparent; border: none; cursor: pointer;
            transition: background 0.2s; text-decoration: none;
            color: #334155; font-size: 1.375rem;
        }
        .notification-bell:hover { background: rgba(66,173,138,0.08); }

        .notification-badge {
            position: absolute; top: -4px; right: -4px;
            background: #dc2626; color: white;
            font-size: 0.8125rem; font-weight: 700;
            padding: 1px 7px; border-radius: 999px;
            min-width: 20px; text-align: center; display: none;
        }

        /* ========== AUTOCOMPLETE DROPDOWN ========== */
        .autocomplete-suggestions {
            position: absolute; background: white;
            border: 1px solid #ccc; max-height: 250px; overflow-y: auto;
            z-index: 9999; border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); min-width: min(250px, calc(100vw - 32px)); max-width: calc(100vw - 32px);
        }

        .autocomplete-suggestion-item {
            padding: 10px 14px; cursor: pointer; font-size: 0.9375rem;
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid #f0f0f0; color: #333; text-decoration: none;
        }
        .autocomplete-suggestion-item:is(:hover, :focus-visible) { background: #E8F7F4; }
        .autocomplete-suggestion-item:focus-visible { outline: 2px solid #0f5c4b; outline-offset: -2px; }
        .suggestion-type { color: #475569; font-size: 0.8125rem; margin-left: 12px; white-space: nowrap; }

        /* ========== MAIN CONTENT ========== */
        .main-content { flex: 1; min-width: 0; padding: clamp(1rem, 2vw, 1.75rem); overflow: auto; }
        .main-content input, .main-content select, .main-content textarea { max-width: 100%; }
        #confirm-modal {
            box-sizing: border-box; width: min(440px, calc(100% - 40px)); max-height: calc(100dvh - 40px);
            inset: 0; margin: auto; overflow: auto; border: 0; border-radius: 20px;
            padding: clamp(16px, 4vw, 28px);
            background: #fff; color: #0f172a; box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        #confirm-modal::backdrop { background: rgba(15,23,42,0.5); backdrop-filter: blur(2px); }
        #confirm-message { overflow-wrap: break-word; }

        .dashboard-page, .dashboard-page > * { min-width: 0; }
        .dashboard-page :is(h1, h2, h3, h4, p) { overflow-wrap: break-word; }
        .dashboard-alerts-grid, .dashboard-stats-grid, .dashboard-welcome-grid,
        .dashboard-actions-grid, .dashboard-charts-grid, .dashboard-panels-grid { display: grid; min-width: 0; }
        .dashboard-alerts-grid { grid-template-columns: repeat(auto-fit, minmax(min(100%, 14rem), 1fr)); }
        .dashboard-stats-grid { grid-template-columns: repeat(auto-fit, minmax(min(100%, 12rem), 1fr)); }
        .dashboard-welcome-grid { grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); }
        .dashboard-actions-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .dashboard-charts-grid, .dashboard-panels-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .dashboard-alerts-grid > *, .dashboard-stats-grid > *, .dashboard-welcome-grid > *,
        .dashboard-actions-grid > *, .dashboard-charts-grid > *, .dashboard-panels-grid > * { min-width: 0; }
        .dashboard-actions-grid > a > div { min-width: 0; }
        .dashboard-record-row { flex-wrap: wrap; gap: 10px; }
        .dashboard-record-row > div { flex: 1 1 12rem; min-width: 0; }
        .dashboard-record-row > span { flex: 0 0 auto; max-width: 100%; overflow-wrap: break-word; }

        @media (max-width: 72rem) {
            .dashboard-welcome-grid, .dashboard-charts-grid, .dashboard-panels-grid { grid-template-columns: minmax(0, 1fr); }
            .dashboard-actions-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 52rem) {
            .dashboard-actions-grid { grid-template-columns: minmax(0, 1fr); }
        }

        /* ========== FOOTER ========== */
        .public-footer {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(12px);
            border-top: 1px solid #e2e8f0;
            padding: 20px 28px 0;
            margin-top: auto;
        }

        .public-container { max-width: 1280px; margin: 0 auto; }

        .footer-grid {
            display: grid; grid-template-columns: minmax(0, 2fr) repeat(2, minmax(0, 1fr));
            gap: 24px; padding-bottom: 16px;
        }

        .footer-grid > div { position: relative; min-width: 0; padding-right: 16px; overflow-wrap: break-word; }

        .footer-grid > div:not(:last-child)::after {
            content: ""; position: absolute; top: 0; right: 0;
            width: 1px; height: 100%; background: #e2e8f0;
        }

        .footer-title {
            font-size: 1.1875rem; font-weight: 700; color: #0f172a; margin: 0 0 10px 0;
        }

        .footer-text { font-size: 1rem; color: #64748b; line-height: 1.5; margin: 0; }

        .footer-link {
            display: block; font-size: 1rem; color: #475569;
            text-decoration: none; margin-bottom: 4px;
        }

        .footer-link:hover { color: #0f172a; }

        .footer-logo-row {
            display: flex; gap: 8px; margin-top: 10px; padding-top: 10px;
            border-top: 1px solid #e2e8f0;
        }

        .mini-logo {
            width: 48px; height: 48px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
        }

        .mini-logo img { width: 100%; height: 100%; object-fit: contain; }

        .footer-bottom {
            border-top: 1px solid #e2e8f0; padding: 8px 0;
            display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap;
        }

        .small-muted { font-size: 0.875rem; color: #64748b; margin: 0; }

        @media print {
            .sidebar, .public-header, .public-footer, .no-print, .mw-bottom-bar { display: none !important; }
            .main-content { padding: 0 !important; }
        }

        @media (max-width: 768px) {
            .footer-grid { grid-template-columns: 1fr; }
            .footer-grid > div:not(:last-child)::after { display: none; }
            body:not(.role-midwife) .app-shell { flex-direction: column; }
            body:not(.role-midwife) .sidebar { position: static; width: 100%; height: auto; padding-top: 16px; }
            body:not(.role-midwife) .sidebar .nav-links { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 12rem), 1fr)); }
            body:not(.role-midwife) .sidebar-footer { margin-top: 16px; }
            body:not(.role-midwife) .header-actions { width: 100%; }
            .header-search { flex: 1; }
            .public-footer { padding: 20px 16px 0; }
        }

        .nav-divider {
            height: 1px; background: rgba(66,173,138,0.15);
            margin: 8px 12px 8px 12px;
        }

        .nav-scroll { scrollbar-width: thin; scrollbar-color: #42ad8a #e2e8f0; }
        .nav-scroll::-webkit-scrollbar { width: 6px; }
        .nav-scroll::-webkit-scrollbar-track { background: #e2e8f0; }
        .nav-scroll::-webkit-scrollbar-thumb { background: #42ad8a; border-radius: 10px; }


        /* ═══════════════════════════════════════════════════════════ */
        /* MOBILE: Midwife layout — hide sidebar, use bottom nav        */
        /* ═══════════════════════════════════════════════════════════ */
        @media (max-width: 767px) {
            body.role-midwife .main-content {
                padding: 16px 16px 100px 16px;
            }

            body.role-midwife .public-header {
                padding: 8px 0;
            }
            body.role-midwife .public-header-inner {
                min-height: 56px;
                padding-left: 16px;
                padding-right: 16px;
                gap: 10px;
                flex-wrap: nowrap;
            }
            body.role-midwife .brand-wrap { flex: 1; }
            body.role-midwife .header-actions { flex: 0 0 auto; }
            body.role-midwife .brand-title {
                font-size: 1rem;
                line-height: 1.2;
            }
            body.role-midwife .brand-sub {
                display: none;
            }
            body.role-midwife .logo-box {
                width: 34px;
                height: 34px;
            }
            body.role-midwife .header-search {
                display: none;
            }

            body.role-midwife .public-footer {
                padding: 20px 16px 0;
                margin-bottom: 80px;
            }
            body.role-midwife .footer-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            body.role-midwife .footer-grid > div {
                padding-right: 0;
            }
            body.role-midwife .footer-grid > div:not(:last-child)::after {
                display: none;
            }

        }
    </style>
    @if(session('role') === 'midwife')
        @include('layouts._midwife_styles')
    @endif
    @php
        $managementStyles = ['css/page-headings.css', 'css/form-choice-search.css'];
        if (request()->routeIs('stock_logs.index') || (request()->routeIs('inventory.index') && request('view') === 'history')) {
            $managementStyles[] = 'css/stock-movements.css';
        }
    @endphp
    @foreach($managementStyles as $stylesheet)
        @if(is_file(public_path($stylesheet)))
            <link rel="stylesheet" href="{{ asset($stylesheet) }}?v={{ filemtime(public_path($stylesheet)) }}">
        @endif
    @endforeach
    @foreach(['js/page-headings.js', 'js/form-choice-search.js'] as $script)
        @if(is_file(public_path($script)))
            <script src="{{ asset($script) }}?v={{ filemtime(public_path($script)) }}" defer></script>
        @endif
    @endforeach
</head>
<body class="{{ session('role') === 'midwife' ? 'role-midwife' : '' }}">
    <div class="app-shell">
        <aside class="sidebar nav-scroll" id="app-sidebar" aria-label="Main navigation">
            @if(session('role') === 'midwife')
                <button type="button" class="mw-sidebar-close" data-midwife-menu-close aria-label="Close navigation">Close menu <span aria-hidden="true">&times;</span></button>
            @endif
            <div class="sidebar-brand">
                <div>
                    @php
                        $isMidwife  = session('role') === 'midwife';
                        $isAdmin    = session('role') === 'admin';
                        $isStaff    = session('role') === 'staff';
                        $isPhoAdmin = $isAdmin && is_null(session('health_unit_id'));
                        $hasRhu     = (bool) session('health_unit_id');

                        $panelLabel = 'Admin Panel';
                        if ($isMidwife) {
                            $panelLabel = 'Midwife Panel';
                        } elseif ($isAdmin) {
                            $panelLabel = $isPhoAdmin ? 'Provincial Health Office' : 'Admin Panel';
                        } elseif ($isStaff) {
                            $panelLabel = 'Staff Panel';
                        }

                        if ($hasRhu) {
                            $rhuName = \App\Models\HealthUnit::find(session('health_unit_id'))->name ?? 'Unknown RHU';
                            $panelLabel .= ' | ' . $rhuName;
                        }
                    @endphp
                    <h2>PHO Supply Portal</h2>
                    <p>{{ $panelLabel }}</p>
                </div>
            </div>

            {{-- ========== MAIN ========== --}}
            <div class="nav-section">
                <p class="nav-section-title">Main</p>
            </div>
            <nav class="nav-links">
                @php
                    $dashRoute  = $isMidwife ? 'dashboard.midwife' : 'dashboard';
                    $dashActive = $isMidwife
                        ? (request()->routeIs('dashboard.midwife') || request()->routeIs('dashboard'))
                        : request()->routeIs('dashboard');
                @endphp
                <a href="{{ route($dashRoute) }}" class="{{ $dashActive ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    Dashboard
                </a>

                @if(in_array(session('role'), ['admin', 'staff']) && $hasRhu)
                    <a href="{{ route('forecast.index') }}" class="{{ request()->routeIs('forecast.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent">
                            <path d="M3 3v18h18"/>
                            <path d="M7 14l4-4 4 4 4-4"/>
                        </svg>
                        Forecasting
                    </a>
                    <a href="{{ route('vaccination.calendar') }}" class="{{ request()->routeIs('vaccination.*', 'calendar_events.*') ? 'active' : '' }}" @if(request()->routeIs('vaccination.*', 'calendar_events.*')) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                            <path d="M16 2v4M8 2v4M3 10h18"/>
                        </svg>
                        Schedule Calendar
                    </a>
                @endif

                @if($hasRhu && in_array(session('role'), ['admin', 'staff', 'midwife']))
                    @php
                        $diseaseNavBarangay = $isMidwife ? \App\Support\BarangayDemandScope::options((int) session('health_unit_id'), (int) session('user_id'))->first() : null;
                        $diseaseNavFilters = $diseaseNavBarangay ? ['consumption_scope' => 'area', 'barangay' => $diseaseNavBarangay, 'period_type' => 'weekly'] : [];
                    @endphp
                    <a href="{{ route('disease_reports.index', $diseaseNavFilters) }}" class="{{ request()->routeIs('disease_reports.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3v18h18"/>
                            <path d="M6 13h3l3-7 4 12 2-5h3"/>
                        </svg>
                        Disease Trend Analysis
                    </a>
                @endif

                @if($hasRhu && !$isMidwife)
                    <a href="{{ route('stock_lookup.index') }}" class="{{ request()->routeIs('stock_lookup.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        Stock Lookup
                    </a>
                @endif
            </nav>

            {{-- ========== MIDWIFE SECTION ========== --}}
            @if($isMidwife)
                <div class="nav-divider"></div>
                <div class="nav-section">
                    <p class="nav-section-title">Clinical</p>
                </div>
                <nav class="nav-links">
                    <a href="{{ route('dispensing.index') }}" class="{{ request()->routeIs('dispensing.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.5 20.5a7 7 0 0 1-10-10l6-6a7 7 0 0 1 10 10z"/>
                            <line x1="8.5" y1="8.5" x2="15.5" y2="15.5"/>
                        </svg>
                        Dispensing
                    </a>

                    <a href="{{ route('public_users.index') }}"
                       class="{{ request()->routeIs('public_users.*') || request()->routeIs('patients.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                        </svg>
                        Patient Registry
                    </a>

                    <a href="{{ route('immunization.tcl') }}" class="{{ request()->routeIs('immunization.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        Immunization TCL
                    </a>

                    <a href="{{ route('vaccination.calendar') }}"
                       class="{{ request()->routeIs('vaccination.calendar') || request()->routeIs('vaccination.child') || request()->routeIs('vaccination.regenerate') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Schedule Calendar
                    </a>

                    <a href="{{ route('vaccination.monthly') }}"
                       class="{{ request()->routeIs('vaccination.monthly*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3v18h18"/>
                            <path d="M7 14l4-4 4 4 4-4"/>
                        </svg>
                        Monthly Forecast
                    </a>
                </nav>

                <div class="nav-divider"></div>
                <div class="nav-section">
                    <p class="nav-section-title">Maternal Care</p>
                </div>
                <nav class="nav-links">
                    <a href="{{ route('prenatal.index') }}" class="{{ request()->routeIs('prenatal.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="6" r="4"/>
                            <path d="M8 20c0-3 2-5 4-5s4 2 4 5"/>
                            <path d="M12 10v5"/>
                        </svg>
                        Prenatal Visits
                    </a>

                    <a href="{{ route('postpartum.index') }}" class="{{ request()->routeIs('postpartum.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="7" r="3"/>
                            <path d="M5 21c0-4 3.6-7 8-7s8 3 8 7"/>
                            <path d="M9 3c1 1 2 2 3 2s2-1 3-2"/>
                        </svg>
                        Postpartum Visits
                    </a>

                    <a href="{{ route('family_planning.index') }}" class="{{ request()->routeIs('family_planning.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        Family Planning
                    </a>
                </nav>

                <div class="nav-divider"></div>
                <div class="nav-section">
                    <p class="nav-section-title">Supply</p>
                </div>
                <nav class="nav-links">
                    <a href="{{ route('supply_requests.index') }}" class="{{ request()->routeIs('supply_requests.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                            <line x1="12" y1="22.08" x2="12" y2="12"/>
                        </svg>
                        Supply Requests
                    </a>
                </nav>
            @endif

            {{-- ========== INVENTORY (non-midwife only) ========== --}}
            @if(!$isMidwife)
                <div class="nav-divider"></div>
                <div class="nav-section">
                    <p class="nav-section-title">Inventory</p>
                </div>
                <nav class="nav-links">
                    @if(in_array(session('role'), ['admin', 'staff']))
                        <a href="{{ route('medicine.index') }}" class="{{ request()->routeIs('medicine.*') ? 'active' : '' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18"/></svg>
                            Masterlist / Stock on Hand
                        </a>
                        <a href="{{ route('inventory.index') }}" class="{{ (request()->routeIs('inventory.*') && !request()->routeIs('inventory.pho_receive*')) || request()->routeIs('stock_logs.*') ? 'active' : '' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                            Inventory / Stock Logs
                        </a>
                    @endif
                </nav>

                <div class="nav-divider"></div>
                <div class="nav-section">
                    <p class="nav-section-title">Operations</p>
                </div>
                <nav class="nav-links">
                    @if(in_array(session('role'), ['admin', 'staff']))

                        @if($hasRhu)
                            <a href="{{ route('allocation.index') }}"
                               class="{{ request()->routeIs('allocation.*') || request()->routeIs('dispensing.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent">
                                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                                </svg>
                                Allocation &amp; Dispensing
                            </a>
                        @else
                            <a href="{{ route('allocation.index') }}"
                               class="{{ request()->routeIs('allocation.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent">
                                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                                </svg>
                                Allocation
                            </a>
                        @endif

                        @if($isPhoAdmin)
                            <a href="{{ route('inventory.pho_receive') }}" class="{{ request()->routeIs('inventory.pho_receive*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7 10 12 15 17 10"/>
                                    <line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                                DOH Receive Record
                            </a>
                        @endif

                        @if(is_null(session('health_unit_id')))
                            <a href="{{ route('transfers.index') }}" class="{{ request()->routeIs('transfers.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="2.5"/>
                                    <path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>
                                    <path d="M5.64 5.64l2.83 2.83M15.54 15.54l2.83 2.83M18.36 5.64l-2.83 2.83M8.46 15.54l-2.83 2.83"/>
                                </svg>
                                Transfer
                            </a>
                        @else
                            <a href="{{ route('transfers.index') }}" class="{{ request()->routeIs('transfers.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="17 1 21 5 17 9"/>
                                    <path d="M3 11V9a4 4 0 0 1 4-4h14"/>
                                    <polyline points="7 23 3 19 7 15"/>
                                    <path d="M21 13v2a4 4 0 0 1-4 4H3"/>
                                </svg>
                                Transfer
                            </a>
                        @endif

                        <a href="{{ route('reports.index') }}"
                           class="{{ request()->routeIs('reports.*') || request()->routeIs('elmis.*') ? 'active' : '' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent">
                                <line x1="18" y1="20" x2="18" y2="10"/>
                                <line x1="12" y1="20" x2="12" y2="4"/>
                                <line x1="6" y1="20" x2="6" y2="14"/>
                            </svg>
                            Reports
                        </a>

                    @endif
                </nav>
            @endif

            {{-- ========== USER MANAGEMENT ========== --}}
            @php
                $showUserMgmt = $isAdmin || ($hasRhu && !$isMidwife) || in_array(session('role'), ['admin', 'staff']);
            @endphp
            @if($showUserMgmt)
                <div class="nav-divider"></div>
                <div class="nav-section">
                    <p class="nav-section-title">User Management</p>
                </div>
                <nav class="nav-links">
                    @if($isAdmin)
                        <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent">
                                <circle cx="12" cy="8" r="4"/>
                                <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                            </svg>
                            Users
                        </a>
                    @endif

                    @if($hasRhu && !$isMidwife)
                        <a href="{{ route('public_users.index') }}"
                           class="{{ request()->routeIs('public_users.*') || request()->routeIs('patients.*') ? 'active' : '' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent">
                                <circle cx="12" cy="8" r="4"/>
                                <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                            </svg>
                            Public Users
                        </a>
                    @endif

                    @if(in_array(session('role'), ['admin', 'staff']))
                        <a href="{{ route('activity_logs.index') }}" class="{{ request()->routeIs('activity_logs.*') ? 'active' : '' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                            Activity Logs
                        </a>
                    @endif
                </nav>
            @endif

            <div class="nav-divider"></div>

            {{-- ========== ACCOUNT ========== --}}
            <div class="nav-section">
                <p class="nav-section-title">Account</p>
            </div>
            <nav class="nav-links">
                <a href="{{ route('settings') }}" class="{{ request()->routeIs('settings') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" class="icon-accent">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path>
                    </svg>
                    Account Settings
                </a>
            </nav>

            {{-- ========== SIDEBAR FOOTER ========== --}}
            <div class="sidebar-footer">
                <div class="footer-row">
                    <div style="min-width:0;">
                        <div class="user-info">{{ session('user_name') }}</div>
                        <div class="user-role">{{ ucfirst(session('role')) }}</div>
                    </div>

                    <form action="{{ route('logout') }}" method="POST"
                          onsubmit="return confirmDialog(event, 'Are you sure you want to log out?');">
                        @csrf
                        <button type="submit" class="logout-btn" title="Logout" aria-label="Logout">
                            <svg viewBox="0 0 24 24">
                                <path d="M18.36 6.64a9 9 0 1 1-12.73 0"/>
                                <line x1="12" y1="2" x2="12" y2="12"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div style="flex:1; min-width:0; display:flex; flex-direction:column; min-height:100vh;">
            <header class="public-header">
                <div class="public-header-inner">
                    <div class="brand-wrap">
                        <div class="logo-box">
                            <img src="{{ asset('images/rhu-icon.png') }}" alt="Rhu">
                        </div>
                        <div>
                            <h1 class="brand-title">Provincial Health Office Supply Portal</h1>
                            <p class="brand-sub">Medicine and Vaccine Stock Availability for the Province of Antique</p>
                        </div>
                    </div>

                    <div class="header-actions">
                        <a href="{{ route('notifications.index') }}" class="notification-bell" title="Notifications">
                            🔔
                            <span id="notification-badge" class="notification-badge">0</span>
                        </a>

                        {{-- Header search — now a real form (no more form= attribute gymnastics) --}}
                        <form class="header-search" action="{{ route('search.index') }}" method="GET">
                            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search..."
                                   aria-label="Search management records" autocomplete="off">
                            <select name="scope" data-native-select aria-label="Search section">
                                @foreach(\App\Support\SearchSections::forViewer(session('role'), session('health_unit_id') !== null) as $searchSection => $searchLabel)
                                    <option value="{{ $searchSection }}" @selected(request('scope', 'all') === $searchSection)>{{ $searchLabel }}</option>
                                @endforeach
                            </select>
                            <button type="submit">Search</button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="main-content" id="main-content">
                @if(session('success'))
                    <div style="background:#eaf3de; border:0.5px solid #c0dd97; border-radius:10px; padding:12px; margin-bottom:20px; color:#3b6d11; font-size: 1.0625rem;">
                        {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div style="background:#fcebeb; border:0.5px solid #f7c1c1; border-radius:10px; padding:12px; margin-bottom:20px; color:#a32d2d; font-size: 1.0625rem;">
                        {{ session('error') }}
                    </div>
                @endif

                @yield('content')
            </main>

            <footer class="public-footer">
                <div class="public-container">
                    <div class="footer-grid">
                        <div>
                            <h3 class="footer-title">About Us</h3>
                            <p class="footer-text">
                                This portal helps residents locate available medicines and vaccines
                                across Rural Health Units in the Province of Antique. It promotes faster
                                access, transparency, and better public healthcare coordination.
                            </p>

                            <div class="footer-logo-row">
                                <div class="mini-logo">
                                    <img src="{{ asset('images/antique.png') }}" alt="Province of Antique">
                                </div>
                                <div class="mini-logo" style="margin-left: 10px;">
                                    <img src="{{ asset('images/doh.png') }}" alt="DOH">
                                </div>
                                <div class="mini-logo" style="margin-left: 10px;">
                                    <img src="{{ asset('images/pho-icon.png') }}" alt="RHU">
                                </div>
                                <div class="mini-logo" style="margin-left: 10px;">
                                    <img src="{{ asset('images/rhu-icon.png') }}" alt="RHU">
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="footer-title">Policies</h3>
                            <a href="{{ route('privacy') }}" class="footer-link">Privacy Policy</a>
                            <a href="{{ route('terms') }}" class="footer-link">Terms of Use</a>
                            <a href="{{ route('accessibility') }}" class="footer-link">Accessibility</a>
                        </div>

                        <div>
                            <h3 class="footer-title">Contact Details</h3>
                            <p class="footer-text">
                                Provincial Health Office<br>
                                San Jose de Buenavista, Antique, Philippines<br><br>
                                Facebook: Antique Provincial Health Office
                                Email: iphoantique@gmail.com<br>
                                Phone: +63 36 540 7054
                            </p>
                        </div>
                    </div>

                    <div class="footer-bottom">
                        <p class="small-muted">
                            © {{ date('Y') }} RHU Inventory System. All rights reserved.
                        </p>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- REUSABLE CONFIRM MODAL --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    @if(session('role') === 'midwife')
        @include('layouts._midwife_navigation')
    @endif

    <dialog id="confirm-modal" aria-labelledby="confirm-title" aria-describedby="confirm-message">
        <h3 id="confirm-title" style="font-size: 1.3125rem; font-weight: 700; margin: 0 0 8px;">Confirm action</h3>
        <p id="confirm-message" style="font-size: 1rem; color: #475569; margin: 0 0 20px; line-height: 1.5;"></p>
        <div style="display:flex; flex-wrap:wrap; gap:10px; justify-content:flex-end;">
            <button type="button" onclick="confirmCancel()" autofocus style="
                font-weight: 600; padding: 10px 20px; border-radius: 10px;
                background: #fff; color: #334155; border: 1px solid #94a3b8; cursor: pointer;
            ">Cancel</button>
            <button type="button" onclick="confirmAccept()" style="
                font-weight: 600; padding: 10px 20px; border-radius: 10px;
                background: #dc2626; color: #fff; border: none; cursor: pointer;
            ">Confirm</button>
        </div>
    </dialog>

    @include('layouts._empty_alert')
    <script src="{{ asset('js/scroll-navigation.js') }}" defer></script>
    <script src="{{ asset('js/empty-feedback.js') }}" defer></script>
    @if(request()->routeIs('disease_reports.index'))
        <script src="{{ asset('js/disease-navigation.js') }}" defer></script>
    @endif

    {{-- Global search autocomplete --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.querySelector('.header-search input[name="q"]');
            if (!searchInput) return;

            const scopeSelect = document.querySelector('.header-search select[name="scope"]');
            let dropdown = null;
            let currentRequest = null;
            let debounceTimer;

            searchInput.addEventListener('input', scheduleSuggestions);
            scopeSelect?.addEventListener('change', scheduleSuggestions);

            searchInput.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowDown' && dropdown?.firstElementChild) {
                    event.preventDefault();
                    dropdown.firstElementChild.focus();
                } else if (event.key === 'Escape') {
                    dismissSuggestions();
                }
            });

            function scheduleSuggestions() {
                dismissSuggestions();
                const query = searchInput.value.trim();
                if (query.length >= 2) {
                    const scope = scopeSelect?.value ?? 'all';
                    debounceTimer = setTimeout(() => fetchSuggestions(query, scope), 250);
                }
            }

            function dismissSuggestions() {
                clearTimeout(debounceTimer);
                currentRequest?.abort();
                removeDropdown();
            }

            function fetchSuggestions(query, scope) {
                const request = new AbortController();
                currentRequest = request;
                fetch({{ Illuminate\Support\Js::from(route('search.autocomplete')) }} + '?q=' + encodeURIComponent(query) + '&scope=' + encodeURIComponent(scope), {
                    signal: request.signal,
                    headers: { 'Accept': 'application/json' }
                })
                .then(response => response.json())
                .then(data => {
                    if (request.signal.aborted) return;
                    removeDropdown();
                    if (Array.isArray(data) && data.length > 0) {
                        showDropdown(data);
                    }
                })
                .catch(err => {
                    if (err.name !== 'AbortError') console.error(err);
                });
            }

            function showDropdown(items) {
                dropdown = document.createElement('div');
                dropdown.className = 'autocomplete-suggestions';
                items.forEach(item => {
                    const link = document.createElement('a');
                    link.href = item.url;
                    if (link.origin !== window.location.origin) return;
                    link.className = 'autocomplete-suggestion-item';
                    const label = document.createElement('span');
                    label.textContent = item.text;
                    const type = document.createElement('span');
                    type.className = 'suggestion-type';
                    type.textContent = item.type;
                    link.append(label, type);
                    dropdown.appendChild(link);
                });
                dropdown.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        searchInput.focus();
                        removeDropdown();
                    } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        const next = event.key === 'ArrowDown' ? event.target.nextElementSibling : event.target.previousElementSibling;
                        (next || searchInput).focus();
                    }
                });

                const rect = searchInput.getBoundingClientRect();
                dropdown.style.top = (rect.bottom + window.scrollY) + 'px';
                dropdown.style.left = (rect.left + window.scrollX) + 'px';
                dropdown.style.width = rect.width + 'px';
                document.body.appendChild(dropdown);
            }

            function removeDropdown() {
                if (dropdown) {
                    dropdown.remove();
                    dropdown = null;
                }
            }

            document.addEventListener('click', function (e) {
                if (!searchInput.contains(e.target) && !scopeSelect?.contains(e.target) && !dropdown?.contains(e.target)) {
                    dismissSuggestions();
                }
            });
        });
    </script>

    {{-- Notification bell polling --}}
    <script>
        function fetchNotifications() {
            fetch('{{ route("notifications.unread") }}', { headers: { 'Accept': 'application/json' } })
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('notification-badge');
                    if (data.count > 0) {
                        badge.textContent = data.count > 99 ? '99+' : data.count;
                        badge.style.display = 'block';
                    } else {
                        badge.style.display = 'none';
                    }
                })
                .catch(err => console.error('Notification fetch error:', err));
        }

        document.addEventListener('DOMContentLoaded', function() {
            fetchNotifications();
            setInterval(fetchNotifications, 30000);
        });
    </script>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- CONFIRM MODAL LOGIC --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <script>
        (function () {
            const modal = document.getElementById('confirm-modal');
            let pendingForm = null;

            window.confirmDialog = function (event, message) {
                event.preventDefault();
                pendingForm = event.currentTarget;
                document.getElementById('confirm-message').textContent = message;
                modal.showModal();
                return false;
            };

            window.confirmCancel = function () {
                pendingForm = null;
                modal.close();
            };

            window.confirmAccept = function () {
                const form = pendingForm;
                window.confirmCancel();
                if (form) {
                    window.RhuNavigation?.rememberForm(form);
                    HTMLFormElement.prototype.submit.call(form);
                }
            };

            modal.addEventListener('cancel', function () {
                pendingForm = null;
            });
        })();
    </script>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- KEYBOARD SHORTCUT: Ctrl+Shift+U → Open Public Portal --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <script>
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'u') {
            e.preventDefault();
            window.open('{{ route("public.home.citizen") }}', '_blank', 'noopener');
        }
    });
    </script>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- AUTO-PRINT WHEN ?print=1 (from Export modal → Print button) --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    @if(request('print') === '1')
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
                const url = new URL(window.location.href);
                url.searchParams.delete('print');
                history.replaceState({}, '', url.toString());
            }, 400);
        });
    </script>
    @endif
    @stack('importDialogs')
    @stack('programPlanningDialogs')
</body>
</html>
