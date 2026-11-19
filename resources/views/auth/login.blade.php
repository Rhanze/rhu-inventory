@extends('layouts.guest')

@section('content')
<style>
    /* ============================================================
       LOGIN PAGE — full-bleed hero + floating card
       ============================================================ */

    .login-shell {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
        min-height: 100vh;
        width: 100%;
        position: relative;
        overflow: hidden;

        background-image:
            linear-gradient(120deg, rgba(15, 92, 75, 0.82) 0%, rgba(24, 122, 96, 0.72) 55%, rgba(66, 173, 138, 0.70) 100%),
            url("{{ asset('images/background1.png') }}");
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        background-color: #145c4b;
    }

    /* Subtle decorative blobs */
    .login-shell::before,
    .login-shell::after {
        content: "";
        position: absolute;
        border-radius: 50%;
        filter: blur(80px);
        pointer-events: none;
        z-index: 0;
    }
    .login-shell::before {
        width: 420px; height: 420px;
        top: -120px; left: -120px;
        background: rgba(133, 224, 181, 0.35);
    }
    .login-shell::after {
        width: 380px; height: 380px;
        bottom: -140px; right: -100px;
        background: rgba(66, 173, 138, 0.30);
    }

    /* ============ LEFT — HERO / BRANDING ============ */
    .login-hero {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-width: 0;
        padding: clamp(2rem, 6vh, 4.5rem) clamp(2rem, 4vw, 4rem) clamp(2rem, 6vh, 4.5rem) clamp(2rem, 5vw, 5.5rem);
        color: #ffffff;
    }

    .hero-title {
        font-size: clamp(2.25rem, 1.5rem + 2vw, 3.5rem);
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: -0.02em;
        margin: 0 0 8px 0;
        color: #ffffff;
        text-shadow: 0 2px 12px rgba(0,0,0,0.25);
    }

    .hero-title .accent {
        color: #a5f3d6;
    }

    .hero-tagline {
        font-size: clamp(1.125rem, 1rem + 0.3vw, 1.3125rem);
        font-weight: 500;
        color: rgba(255, 255, 255, 0.92);
        margin: 0 0 32px 0;
        letter-spacing: 0.01em;
    }

    .hero-desc {
        font-size: 1.0625rem;
        line-height: 1.75;
        color: rgba(255, 255, 255, 0.85);
        max-width: 520px;
        margin: 0 0 40px 0;
    }

    .hero-features {
        display: flex;
        flex-direction: column;
        gap: 14px;
        max-width: 460px;
        margin-top: 8px;
    }

    .hero-feature {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        font-size: 1rem;
        color: rgba(255, 255, 255, 0.9);
        line-height: 1.5;
    }

    .hero-feature .dot {
        flex-shrink: 0;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8125rem;
        color: #a5f3d6;
        font-weight: 700;
        margin-top: 1px;
    }

    .hero-footer {
        margin-top: auto;
        padding-top: 48px;
        font-size: 0.875rem;
        color: rgba(255, 255, 255, 0.6);
        letter-spacing: 0.02em;
    }

    /* ============ RIGHT — LOGIN CARD ============ */
    .login-panel {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 0;
        padding: clamp(1.5rem, 4vh, 3rem) clamp(1.5rem, 4vw, 3.5rem) clamp(1.5rem, 4vh, 3rem) clamp(1.5rem, 3vw, 2.5rem);
    }

    .login-card {
        width: 100%;
        max-width: 470px;
        min-width: 0;
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 24px;
        padding: clamp(1.5rem, 4vh, 2.75rem) clamp(1.25rem, 3vw, 2.5rem) clamp(1.5rem, 3vh, 2.25rem);
        border: 1px solid rgba(255, 255, 255, 0.8);
        box-shadow:
            0 24px 60px rgba(0, 0, 0, 0.18),
            0 2px 0 rgba(255, 255, 255, 0.9) inset;
        box-sizing: border-box;
        animation: cardRise 0.55s ease-out;
    }

    @keyframes cardRise {
        from { opacity: 0; transform: translateY(16px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .card-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 22px;
    }

    .card-brand img {
        width: 44px;
        height: 44px;
        object-fit: contain;
    }

    .card-brand-text {
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: #42ad8a;
    }

    .card-title {
        font-size: 1.75rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 6px 0;
        letter-spacing: -0.01em;
        line-height: 1.2;
    }

    .card-subtitle {
        font-size: 1rem;
        color: #64748b;
        margin: 0 0 28px 0;
        line-height: 1.5;
    }

    .error-box {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fca5a5;
        padding: 13px 16px;
        border-radius: 12px;
        margin-bottom: 22px;
        font-size: 0.9375rem;
        text-align: left;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-group {
        margin-bottom: 20px;
        text-align: left;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-size: 0.9375rem;
        font-weight: 600;
        color: #0f172a;
        letter-spacing: 0.01em;
    }

    .form-input {
        width: 100%;
        min-width: 0;
        min-height: 54px;
        padding: 12px 18px;
        font-size: 1.0625rem;
        border: 2px solid #d8dee6;
        border-radius: 14px;
        outline: none;
        background: #ffffff;
        color: #0f172a;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        font-family: inherit;
    }

    .form-input::placeholder {
        color: #94a3b8;
        font-size: 1rem;
    }

    .form-input:hover {
        border-color: #b8c2cd;
    }

    .form-input:focus {
        border-color: #42ad8a;
        box-shadow: 0 0 0 4px rgba(66, 173, 138, 0.14);
        background: #ffffff;
    }

    .password-wrap {
        position: relative;
    }

    .password-wrap .form-input {
        padding-right: 52px;
    }

    .password-toggle {
        position: absolute;
        right: 4px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        width: 44px;
        height: 44px;
        padding: 10px;
        border: 0;
        border-radius: 8px;
        background: transparent;
        margin-top: 0;
        box-shadow: none;
        opacity: 0.55;
        transition: opacity 0.2s ease, transform 0.15s ease;
        user-select: none;
    }
    .password-toggle:hover {
        opacity: 1;
        transform: translateY(-50%) scale(1.08);
    }
    .password-toggle img { display: block; width: 24px; height: 24px; }
    .password-toggle:focus-visible { outline: 2px solid #0f766e; outline-offset: -2px; opacity: 1; }

    .error-text {
        color: #dc2626;
        font-size: 0.875rem;
        margin-top: 6px;
        display: block;
    }

    .remember-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin: 4px 0 22px 0;
    }

    .remember-label {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
        font-size: 0.9375rem;
        color: #334155;
        cursor: pointer;
        user-select: none;
    }

    .remember-label input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: #42ad8a;
        cursor: pointer;
        margin: 0;
    }

    .btn-submit {
        width: 100%;
        min-height: 56px;
        background: linear-gradient(135deg, #42ad8a 0%, #369f7d 100%);
        color: #ffffff;
        border: none;
        font-size: 1.125rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        border-radius: 14px;
        cursor: pointer;
        transition: transform 0.18s ease, box-shadow 0.22s ease, filter 0.2s ease;
        box-shadow: 0 6px 18px rgba(47, 158, 143, 0.28);
        font-family: inherit;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 26px rgba(47, 158, 143, 0.4);
        filter: brightness(1.03);
    }

    .btn-submit:active {
        transform: translateY(0) scale(0.985);
        box-shadow: 0 4px 12px rgba(47, 158, 143, 0.3);
    }

    .card-footer {
        margin-top: 26px;
        padding-top: 20px;
        border-top: 1px solid #eef2f6;
        font-size: 0.875rem;
        color: #94a3b8;
        text-align: center;
        line-height: 1.55;
    }

    .card-footer strong {
        color: #64748b;
        font-weight: 600;
    }

    /* ============ RESPONSIVE ============ */
    @media (max-width: 1100px) {
        .login-hero { padding: 32px; }
        .login-panel { padding: 28px; }
    }

    @media (max-width: 900px) {
        .login-shell {
            grid-template-columns: 1fr;
            min-height: auto;
        }
        .login-hero {
            padding: 48px 32px 24px 32px;
            text-align: center;
            align-items: center;
        }
        .hero-desc, .hero-features { text-align: left; }
        .hero-features { display: none; }
        .hero-footer { display: none; }
        .login-panel {
            padding: 24px 24px 56px 24px;
        }
        .login-card {
            padding: 36px 28px 30px;
            border-radius: 20px;
        }
    }

    @media (max-width: 480px) {
        .login-hero { padding: 28px 20px 8px; }
        .login-panel { padding: 20px 16px 32px; }
        .login-card { padding: 28px 20px; }
        .hero-title { font-size: 1.875rem; }
        .hero-tagline { font-size: 1rem; }
        .hero-desc { font-size: 0.9375rem; line-height: 1.6; }
        .card-title { font-size: 1.5rem; }
        .card-subtitle { font-size: 0.9375rem; }
        .form-input { min-height: 52px; font-size: 1rem; }
        .btn-submit { min-height: 54px; font-size: 1.0625rem; }
    }

    @media (min-width: 901px) and (max-height: 800px) {
        .hero-tagline { margin-bottom: 20px; }
        .hero-desc { margin-bottom: 20px; line-height: 1.6; }
        .hero-features { gap: 10px; }
        .hero-footer { padding-top: 24px; }
        .card-brand { margin-bottom: 16px; }
        .card-subtitle { margin-bottom: 20px; }
        .form-group { margin-bottom: 16px; }
        .card-footer { margin-top: 20px; padding-top: 16px; }
    }
</style>

<div class="login-shell">

    {{-- =================== LEFT: BRANDING HERO =================== --}}
    <div class="login-hero">

        <h1 class="hero-title">
            Provincial <span class="accent">Supply</span><br>
            Portal
        </h1>

        <p class="hero-tagline">
            Web-based pharmaceutical &amp; vaccine stock management
        </p>

        <p class="hero-desc">
            A centralised platform connecting the Provincial Health Office
            with every Rural Health Unit across Antique. Providing health
            workers real-time visibility into medicine availability,
            allocation, dispensing, and forecasting in one secure place.
        </p>

        <div class="hero-features">
            <div class="hero-feature">
                <span class="dot">✓</span>
                <span>Live stock levels across all RHUs</span>
            </div>
            <div class="hero-feature">
                <span class="dot">✓</span>
                <span>Automated allocation and redistribution</span>
            </div>
            <div class="hero-feature">
                <span class="dot">✓</span>
                <span>Medicine consumption trends &amp; vaccine forecasting</span>
            </div>
            <div class="hero-feature">
                <span class="dot">✓</span>
                <span>Secure, role-based access for every health worker</span>
            </div>
        </div>

        <div class="hero-footer">
            Department of Health · Province of Antique
        </div>
    </div>

    {{-- =================== RIGHT: LOGIN CARD =================== --}}
    <div class="login-panel">
        <div class="login-card">

            <div class="card-brand">
                <img src="{{ asset('images/rhu-icon.png') }}" alt="PHO">
            </div>

            <h2 class="card-title">Welcome back</h2>
            <p class="card-subtitle">Log in to manage your RHU inventory.</p>

            @if(session('error'))
                <div class="error-box">
                    <span>⚠️</span>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <form action="{{ route('login.submit') }}" method="POST" autocomplete="on">
                @csrf

                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-input"
                        value="{{ old('username') }}"
                        placeholder="Enter your username"
                        required
                        autofocus
                    >
                    @error('username')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Enter your password"
                            required
                        >
                        <button type="button" class="password-toggle" id="password-visibility-toggle"
                                onclick="togglePassword()" aria-controls="password" aria-label="Show password"
                                aria-pressed="false" title="Show password">
                        <img
                            id="togglePasswordIcon"
                            src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z'/%3E%3Ccircle cx='12' cy='12' r='3'/%3E%3C/svg%3E"
                            alt=""
                            aria-hidden="true"
                        >
                        </button>
                    </div>
                    @error('password')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn-submit">Sign In</button>
            </form>

            <div class="card-footer">
                © {{ date('Y') }} <strong>RHU Inventory System</strong><br>
                Department of Health – Province of Antique
            </div>
        </div>
    </div>

</div>

<script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const icon = document.getElementById('togglePasswordIcon');
        const toggle = document.getElementById('password-visibility-toggle');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.src = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94'/%3E%3Cpath d='M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19'/%3E%3Cline x1='1' y1='1' x2='23' y2='23'/%3E%3C/svg%3E";
        } else {
            passwordInput.type = 'password';
            icon.src = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z'/%3E%3Ccircle cx='12' cy='12' r='3'/%3E%3C/svg%3E";
        }
        const visible = passwordInput.type === 'text';
        toggle.setAttribute('aria-pressed', String(visible));
        toggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        toggle.title = visible ? 'Hide password' : 'Show password';
    }
</script>
@endsection
