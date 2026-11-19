<style>
    .mw-bottom-bar, .mw-menu-backdrop, .mw-sidebar-close { display: none; }

    @media screen and (max-width: 900px) {
        body.role-midwife { --mw-nav-height: 68px; }
        body.role-midwife *, body.role-midwife *::before, body.role-midwife *::after { box-sizing: border-box; }
        body.role-midwife .app-shell { min-width: 0; }
        body.role-midwife .main-content {
            min-width: 0;
            width: 100%;
            padding: 16px 12px 24px;
            overflow-wrap: anywhere;
        }
        body.role-midwife .main-content > *, body.role-midwife .main-content form > div { min-width: 0; max-width: 100%; }
        body.role-midwife .main-content h2 { font-size: 24px !important; line-height: 1.25; }
        body.role-midwife .main-content input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),
        body.role-midwife .main-content select, body.role-midwife .main-content textarea {
            min-width: 0 !important;
            max-width: 100%;
            min-height: 44px;
            font-size: 16px !important;
        }
        body.role-midwife .main-content input[type="file"] { width: 100%; }
        body.role-midwife .main-content button,
        body.role-midwife .main-content a[style*="padding"],
        body.role-midwife .main-content a[title] { min-width: 44px; min-height: 44px; }
        body.role-midwife .main-content a, body.role-midwife .main-content button { overflow-wrap: anywhere; }
        body.role-midwife .mw-form-grid, body.role-midwife .mw-mobile-panels { grid-template-columns: minmax(0, 1fr) !important; }
        body.role-midwife .mw-form-grid > * { min-width: 0; }
        body.role-midwife .mw-page-card { padding: 16px !important; }
        body.role-midwife .mw-patient-heading { min-width: 0 !important; }
        body.role-midwife .mw-form-actions { flex-wrap: wrap; }
        body.role-midwife .mw-form-actions > * { flex: 1 1 160px; justify-content: center; text-align: center; }
        body.role-midwife .mw-notification-heading { min-width: 0; width: 100%; }
        body.role-midwife .mw-notification-heading h2 { flex-wrap: wrap; }
        body.role-midwife .mw-notification-tabs { min-width: 0; max-width: 100%; flex-wrap: wrap; }
        body.role-midwife .mw-notification-tabs > a {
            flex: 1 1 auto;
            min-width: 0;
            justify-content: center;
            padding: 8px 10px !important;
        }
        body.role-midwife .public-header { padding: 8px 0; }
        body.role-midwife .public-header-inner { min-height: 56px; padding: 0 12px; gap: 10px; }
        body.role-midwife .brand-wrap, body.role-midwife .brand-wrap > div { min-width: 0; }
        body.role-midwife .brand-title { font-size: 17px; line-height: 1.25; }
        body.role-midwife .brand-sub, body.role-midwife .header-search { display: none; }
        body.role-midwife .logo-box { width: 34px; height: 34px; flex-shrink: 0; }
        body.role-midwife .header-actions { flex-shrink: 0; }
        body.role-midwife .notification-bell { width: 44px; height: 44px; }
        body.role-midwife .public-footer {
            padding: 20px 16px calc(var(--mw-nav-height) + env(safe-area-inset-bottom, 0px) + 20px);
            margin-bottom: 0;
        }
        body.role-midwife .footer-grid { grid-template-columns: minmax(0, 1fr); gap: 20px; }
        body.role-midwife .footer-grid > div { min-width: 0; padding-right: 0; }
        body.role-midwife .footer-grid > div::after { display: none; }
        body.role-midwife .footer-logo-row { flex-wrap: wrap; }

        body.role-midwife .sidebar {
            display: flex;
            position: fixed;
            inset: 0 auto 0 0;
            width: min(300px, calc(100vw - 44px));
            height: 100vh;
            height: 100dvh;
            z-index: 10002;
            visibility: hidden;
            transform: translateX(-100%);
            overscroll-behavior: contain;
            padding-top: max(12px, env(safe-area-inset-top, 0px));
            padding-bottom: env(safe-area-inset-bottom, 0px);
            background: #fff;
        }
        body.role-midwife.mw-menu-open { overflow: hidden; }
        body.role-midwife.mw-menu-open .sidebar { visibility: visible; transform: translateX(0); }
        body.role-midwife .mw-sidebar-close {
            display: flex; align-items: center; justify-content: space-between;
            min-height: 44px; margin: 0 12px 12px; padding: 8px 12px;
            border: 1px solid #cbd5e1; border-radius: 10px;
            background: #f8fafc; color: #0f172a; font: inherit; cursor: pointer;
        }
        body.role-midwife .mw-sidebar-close span { font-size: 26px; }
        body.role-midwife .mw-menu-backdrop {
            position: fixed; inset: 0; z-index: 10001;
            border: 0; background: rgba(15, 23, 42, .5);
        }
        body.role-midwife.mw-menu-open .mw-menu-backdrop { display: block; }
        body.role-midwife .mw-bottom-bar {
            display: grid; grid-template-columns: repeat(5, minmax(0, 1fr));
            position: fixed; inset: auto 0 0; z-index: 10000;
            min-height: var(--mw-nav-height);
            padding: 4px max(4px, env(safe-area-inset-right, 0px)) env(safe-area-inset-bottom, 0px) max(4px, env(safe-area-inset-left, 0px));
            border-top: 1px solid #d6e6e0; background: #fff;
            box-shadow: 0 -3px 15px rgba(15, 23, 42, .08);
        }
        body.role-midwife .mw-bottom-btn {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            min-width: 0; min-height: 60px; padding: 6px 2px; gap: 4px;
            border: 0; border-radius: 10px; background: transparent; color: #475569;
            text-decoration: none; font: inherit; cursor: pointer;
        }
        body.role-midwife .mw-bottom-btn svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 1.8; }
        body.role-midwife .mw-bottom-btn span { font-size: 12px; font-weight: 600; }
        body.role-midwife .mw-bottom-btn.active, body.role-midwife .mw-bottom-btn[aria-expanded="true"] { color: #0f766e; background: #e8f7f4; }
        body.role-midwife :is(.mw-bottom-btn, .mw-sidebar-close, .nav-links a):focus-visible { outline: 3px solid #0f766e; outline-offset: -3px; }

        body.role-midwife .mw-records-enhanced { min-width: 0 !important; width: 100%; border: 0; }
        body.role-midwife .mw-records-enhanced thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
        body.role-midwife .mw-records-enhanced tbody { display: block; padding: 4px 12px; }
        body.role-midwife .mw-records-enhanced tbody tr { display: block; margin: 12px 0; padding: 8px 12px; border: 1px solid #dbe6e2 !important; border-radius: 12px; }
        body.role-midwife .mw-records-enhanced td { display: block; padding: 8px 0 !important; min-width: 0; max-width: 100%; text-align: left !important; white-space: normal !important; overflow-wrap: anywhere; }
        body.role-midwife .mw-records-enhanced td[data-label]::before { content: attr(data-label); display: block; margin-bottom: 4px; color: #64748b; font-size: 12px; font-weight: 700; }
        body.role-midwife .mw-records-enhanced td > div { flex-wrap: wrap; justify-content: flex-start !important; }
        body.role-midwife .mw-records-enhanced td button { padding: 8px !important; }
        body.role-midwife .mw-records-enhanced td a[title] { display: inline-flex; align-items: center; justify-content: center; }
        body.role-midwife #dispensing-info-modal { z-index: 10003; padding: 12px; }
        body.role-midwife #dispensing-info-modal .modal-card { padding: 20px; max-height: calc(100dvh - 24px); }
        body.role-midwife #dispensing-info-modal .field-grid { grid-template-columns: minmax(0, 1fr); }
        body.role-midwife #dispensing-info-modal .modal-title { font-size: 24px; }
        body.role-midwife #confirm-modal > div { padding: 20px !important; max-height: calc(100dvh - 32px); overflow-y: auto; }
    }
</style>
