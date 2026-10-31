@extends('layouts.public')

@section('content')
<style>
    .portal-entry { max-width:1000px;margin:24px auto 48px; }
    .portal-entry h2 { font-size:28px;line-height:1.3;margin:0 0 12px; }
    .portal-entry-intro { color:#475569;line-height:1.6;margin:0 0 28px; }
    .portal-entry-options { display:grid;grid-template-columns:1.2fr 1fr;gap:20px; }
    .portal-entry-card { display:flex;flex-direction:column;background:#fff;border:1px solid #cbded5;border-radius:12px;padding:28px; }
    .portal-entry-card h3 { font-size:22px;margin:0 0 12px; }
    .portal-entry-card p { color:#475569;line-height:1.6;margin:0 0 16px; }
    .portal-entry-label { color:#0f5c4b;font-size:13px;font-weight:700;letter-spacing:.04em;margin-bottom:10px; }
    .portal-entry-action { display:block;text-align:center;padding:12px 16px;border:1px solid #0f5c4b;border-radius:8px;background:#0f5c4b;color:#fff;text-decoration:none;font-weight:600;margin-top:auto; }
    .portal-entry-action-secondary { background:#fff;color:#0f5c4b; }
    .portal-entry-action:hover { background:#104c40;color:#fff; }
    .portal-entry-action:focus-visible { outline:3px solid #0f766e;outline-offset:4px; }
    .portal-entry-card .portal-entry-note { font-size:14px; }
    @media(max-width:700px) {
        .portal-entry-options { grid-template-columns:1fr; }
        .portal-entry-card { padding:22px; }
        .portal-entry h2 { font-size:24px; }
    }
</style>
<section class="portal-entry" aria-labelledby="portal-entry-heading">
    <h2 id="portal-entry-heading">Welcome to the Supply Portal</h2>
    <p class="portal-entry-intro">Check medicine availability or sign in to manage health services and supplies.</p>
    <div class="portal-entry-options">
        <article class="portal-entry-card">
            <span class="portal-entry-label">FOR THE PUBLIC</span>
            <h3>Find medicine availability</h3>
            <p>Search medicines and vaccines, choose your RHU, and check available stock before you visit.</p>
            <p class="portal-entry-note">No account or login required.</p>
            <a class="portal-entry-action" href="{{ route('public.home.citizen') }}">Search medicines</a>
        </article>
        <article class="portal-entry-card">
            <span class="portal-entry-label">FOR AUTHORIZED HEALTH PERSONNEL</span>
            <h3>Management portal</h3>
            <p>Administrators, RHU staff, and midwives can access their assigned dashboard and permitted work.</p>
            <p class="portal-entry-note">An active staff account is required. Public visitors cannot manage inventory or patient records.</p>
            @if(session('user_id'))
                <a class="portal-entry-action portal-entry-action-secondary" href="{{ route('dashboard') }}">Open your dashboard</a>
            @else
                <a class="portal-entry-action portal-entry-action-secondary" href="{{ route('login') }}">Staff login</a>
            @endif
        </article>
    </div>
</section>
@endsection
