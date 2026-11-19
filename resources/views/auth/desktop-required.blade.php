@extends('layouts.guest')

@section('title', 'Desktop required')

@section('content')
<main class="page-center">
    <div class="card">
        <div class="logo-placeholder">
            <img src="{{ asset('images/rhu-icon.png') }}" alt="PHO">
        </div>
        <h1 class="title">Please use a desktop or laptop</h1>
        <p class="subtitle">{{ \App\Support\ManagementDeviceAccess::MOBILE_MESSAGE }}</p>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit">Sign out</button>
        </form>
        <a class="back-link" style="margin: 24px 0 0; text-align: center;" href="{{ route('public.home.citizen') }}">Browse public medicine availability</a>
    </div>
</main>
@endsection
