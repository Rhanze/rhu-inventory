<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Search medicine and vaccine availability at Rural Health Units in the Province of Antique. No staff account is required.">
    <link rel="canonical" href="{{ request()->routeIs('public.home', 'public.home.citizen') ? route('public.home') : url()->current() }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/web-icon.png') }}">
    <title>PHO Supply Portal | Search</title>
    @vite('resources/css/app.css')

    <style>
        body {
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(180deg, #d4f0ea 0%, #85e0b5 100%);
            color: #0f172a;
            font-family: Arial, sans-serif;
            font-size: 1rem;
        }

        .login-link {
            display: block;
            text-align: left;
            margin-bottom: 0;
            font-size: 1.125rem;
            color: #42ad8a;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 0;
        }

        .public-entry-nav {
            display:flex;
            gap:16px;
            flex-wrap:wrap;
            flex-shrink:0;
        }
        .public-entry-nav a {
            color:#0f5c4b;
            font-size: 0.9375rem;
            font-weight:600;
            padding:10px 0;
            text-decoration:underline;
            text-underline-offset:4px;
        }
        .public-entry-nav a:focus-visible {
            outline:3px solid #0f766e;
            outline-offset:4px;
        }

        .public-shell {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .public-container {
            max-width: 1280px;
            width: 100%;
            margin: 0 auto;
            padding-left: 24px;
            padding-right: 24px;
            box-sizing: border-box;
        }

        .public-header {
            padding-top: 20px;
            padding-bottom: 20px;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.08);
        }

        .public-header-inner {
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .brand-wrap {
            display: flex;
            align-items: center;
            gap: 16px;
            min-width: 0;
        }

        .brand-wrap > div:last-child {
            min-width: 0;
        }
        .public-main {
            overflow-wrap: break-word;
        }

        .logo-box {
            width: 110px;
            height: 110px;
            flex-shrink: 0;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8125rem;
            color: #475569;
            font-weight: 700;
        }

        .brand-title {
            font-size: 2.375rem;
            font-weight: 750;
            margin: 0;
            color: #0f172a;
            line-height: 1.2;
        }

        .brand-sub {
            font-size: 1.375rem;
            margin: 6px 0 0 0;
            color: #64748b;
            line-height: 1.4;
        }

        .trust-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .badge {
            font-size: 0.8125rem;
            padding: 6px 12px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .btn-soft {
            background: #42ad8a;
            color: white;
            border: none;
            padding: 12px 22px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 1rem;
            font-weight: 600;
        }

        .btn-soft:hover {
            background: #369f7d;
        }

        .btn-soft:active {
            transform: scale(0.97);
        }

        .public-main {
            flex: 1;
            padding-top: 28px;
            padding-bottom: 28px;
        }

        .public-footer {
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            margin-top: auto;
        }

        .footer-grid {
            padding: 28px 0;
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr);
            gap: 30px;
        }

        .footer-grid > div {
            position: relative;
            padding-right: 20px;
            min-width: 0;
        }

        .footer-grid > div:not(:last-child)::after {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 1px;
            height: 100%;
            background: #e2e8f0;
        }

        .footer-title {
            font-size: 1.375rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 14px 0;
        }

        .footer-text {
            font-size: 1.125rem;
            color: #64748b;
            line-height: 1.7;
            margin: 0;
        }

        .footer-link {
            display: block;
            font-size: 1.125rem;
            color: #475569;
            text-decoration: none;
            margin-bottom: 10px;
        }

        .footer-link:hover {
            color: #0f172a;
        }

        .footer-logo-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid #e2e8f0;
        }

        .mini-logo {
            width: 85px;
            height: 85px;
            flex-shrink: 0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 700;
        }

        .mini-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .footer-bottom {
            border-top: 1px solid #e2e8f0;
            padding: 14px 0;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .small-muted {
            font-size: 0.875rem;
            color: #64748b;
            margin: 0;
        }

        /* ==================== Mobile Responsive ==================== */
        @media (max-width: 768px) {
            .public-container {
                padding-left: 16px;
                padding-right: 16px;
            }

            .public-header-inner {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
                padding-top: 14px;
                padding-bottom: 14px;
                min-height: unset;
            }

            .brand-wrap {
                flex-direction: row;
                gap: 10px;
                align-items: flex-start;
            }

            .logo-box {
                width: 80px;
                height: 80px;
            }

            .brand-title {
                font-size: 1.625rem;
            }

            .brand-sub {
                font-size: 1.125rem;
            }

            .login-link {
                display: none;
            }

            .public-main {
                padding-top: 16px;
                padding-bottom: 16px;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                padding: 20px 0;
            }

            .footer-grid > div:not(:last-child)::after {
                display: none;
            }

            .footer-title {
                font-size: 1.25rem;
            }

            .footer-text,
            .footer-link {
                font-size: 1rem;
            }

            .mini-logo {
                width: 70px;
                height: 70px;
                margin-left: 0 !important;
            }

            .card,
            .card-soft-blue {
                padding: 20px;
                border-radius: 18px;
            }

            .availability-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .btn-soft,
            .btn-ghost {
                width: 100%;
                text-align: center;
            }

            .public-footer .public-container {
                padding-left: 24px;
                padding-right: 24px;
            }
        }
    
        .public-shell, .public-shell *, .public-shell *::before, .public-shell *::after {
            box-sizing:border-box;
        }
        .public-shell {
            min-height:100dvh;
        }
        .public-main, .public-main > .stack {
            min-width:0;
        }
        .public-main {
            overflow-wrap:break-word;
        }
        .footer-text {
            overflow-wrap:break-word;
        }
        .footer-link:focus-visible, .btn-soft:focus-visible {
            outline:3px solid #0f766e;
            outline-offset:3px;
        }
        @media (min-width:769px) and (max-height:1024px) {
            .public-header {
                padding-block:12px;
            }
            .public-header-inner {
                min-height:80px;
            }
            .logo-box {
                width:80px;
                height:80px;
            }
            .brand-title {
                font-size:2rem;
            }
            .brand-sub {
                font-size:1.125rem;
            }
            .public-main {
                padding-block:18px;
            }
            .footer-grid {
                padding-block:18px;
                gap:24px;
            }
            .footer-title {
                font-size:1.25rem;
                margin-bottom:10px;
            }
            .footer-text, .footer-link {
                font-size:1rem;
                line-height:1.5;
            }
            .footer-logo-row {
                margin-top:10px;
                padding-top:10px;
            }
            .mini-logo {
                width:56px;
                height:56px;
            }
            .footer-bottom {
                padding-block:10px;
            }
        }
        @media (min-width:769px) and (max-height:768px) {
            .public-header {
                padding-block:8px;
            }
            .public-header-inner {
                min-height:68px;
            }
            .logo-box {
                width:68px;
                height:68px;
            }
            .brand-title {
                font-size:1.875rem;
            }
            .public-main {
                padding-block:10px;
            }
            .footer-grid {
                padding-block:12px;
                gap:20px;
            }
            .footer-title {
                font-size:1.125rem;
                margin-bottom:8px;
            }
            .footer-logo-row {
                margin-top:8px;
                padding-top:8px;
            }
            .mini-logo {
                width:40px;
                height:40px;
            }
            .footer-bottom {
                padding-block:6px;
            }
        }
    </style>
    @stack('head')
</head>

<body>
    <div class="public-shell">

        <header class="public-header">
            <div class="public-container public-header-inner">
                <div class="brand-wrap">
                    <div class="logo-box">
                        <img src="{{ asset('images/rhu-icon.png') }}" alt="Rural Health Unit" style="width:100%;height:100%;object-fit:contain;">
                    </div>
                    <div>
                        <h1 class="brand-title">Provincial Health Office Supply Portal</h1>
                        <p class="brand-sub">
                            Medicine and Vaccine Stock Availability for the Province of Antique
                        </p>
                    </div>
                </div>

            </div>
        </header>

        <main class="public-container public-main">
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
                            <div class="mini-logo">
                                <img src="{{ asset('images/doh.png') }}" alt="DOH">
                            </div>
                            <div class="mini-logo">
                                <img src="{{ asset('images/pho-icon.png') }}" alt="RHU">
                            </div>
                            <div class="mini-logo">
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
                            San Jose de Buenavista, Antique, Philippines<br>
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

    {{-- Keyboard shortcut (Ctrl+J / Cmd+J) --}}
    <script>
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey && e.key.toLowerCase() === 'j') {
            e.preventDefault();
            window.location.href = {{ Illuminate\Support\Js::from(route('login')) }};
        }
    });
    </script>
</body>
</html>
