<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            font-size: 16px;
        }

        .login-link {
            display: block;
            text-align: left;
            margin-bottom: 0;
            font-size: 18px;
            color: #42ad8a;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 0;
        }

        .public-entry-nav { display:flex;gap:16px;flex-wrap:wrap;flex-shrink:0; }
        .public-entry-nav a { color:#0f5c4b;font-size:15px;font-weight:600;padding:10px 0;text-decoration:underline;text-underline-offset:4px; }
        .public-entry-nav a:focus-visible { outline:3px solid #0f766e;outline-offset:4px; }

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
        }

        .logo-box {
            width: 110px;
            height: 110px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            color: #475569;
            font-weight: 700;
        }

        .brand-title {
            font-size: 38px;
            font-weight: 750;
            margin: 0;
            color: #0f172a;
            line-height: 1.2;
        }

        .brand-sub {
            font-size: 22px;
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
            font-size: 13px;
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
            font-size: 16px;
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
            grid-template-columns: 2fr 1fr 1fr;
            gap: 30px;
        }

        .footer-grid > div {
            position: relative;
            padding-right: 20px;
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
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 14px 0;
        }

        .footer-text {
            font-size: 18px;
            color: #64748b;
            line-height: 1.7;
            margin: 0;
        }

        .footer-link {
            display: block;
            font-size: 18px;
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
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid #e2e8f0;
        }

        .mini-logo {
            width: 85px;
            height: 85px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #64748b;
            font-weight: 700;
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
            font-size: 14px;
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
                font-size: 26px;
            }

            .brand-sub {
                font-size: 18px;
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
                font-size: 20px;
            }

            .footer-text,
            .footer-link {
                font-size: 16px;
            }

            .mini-logo {
                width: 70px;
                height: 70px;
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
    </style>
</head>

<body>
    <div class="public-shell">

        <header class="public-header">
            <div class="public-container public-header-inner">
                <div class="brand-wrap">
                    <div class="logo-box">
                        <img src="{{ asset('images/rhu-icon.png') }}" style="width:100%;height:100%;object-fit:contain;">
                    </div>
                    <div>
                        <h1 class="brand-title">Provincial Health Office Supply Portal</h1>
                        <p class="brand-sub">
                            Medicine and Vaccine Stock Availability for the Province of Antique
                        </p>
                    </div>
                </div>

                <nav class="public-entry-nav" aria-label="Portal navigation">
                    <a href="{{ route('public.home') }}">Home</a>
                    @if(session('user_id'))
                        <a href="{{ route('dashboard') }}">Your dashboard</a>
                    @else
                        <a href="{{ route('login') }}">Staff login</a>
                    @endif
                </nav>
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
                            <div class="mini-logo" style="margin-left: 20px;">
                                <img src="{{ asset('images/doh.png') }}" alt="DOH">
                            </div>
                            <div class="mini-logo" style="margin-left: 20px;">
                                <img src="{{ asset('images/pho-icon.png') }}" alt="RHU">
                            </div>
                            <div class="mini-logo" style="margin-left: 20px;">
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

    {{-- Keyboard shortcut (Ctrl+J / Cmd+J) --}}
    <script>
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'j') {
            e.preventDefault();
            window.location.href = "{{ route('login') }}";
        }
    });
    </script>
</body>
</html>