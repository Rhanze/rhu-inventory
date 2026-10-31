@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Barangay Management</p>
        <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">Add Barangay</h2>
        <p style="font-size:14px; color:#475569; margin:6px 0 0 0;">Create a new barangay under your RHU.</p>
    </div>

    {{-- Form Card --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius:20px; padding:28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <form action="{{ route('barangay.store') }}" method="POST" style="display:flex; flex-direction:column; gap:20px;">
            @csrf

            {{-- Assigned RHU (read‑only) --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Assigned RHU</label>
                <input type="text" value="{{ $healthUnit->name ?? 'N/A' }}"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #e2e8f0; border-radius:14px; background:#f8fafc; color:#64748b; box-sizing:border-box;"
                       readonly>
            </div>

            {{-- Barangay Name --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">BHS Name</label>
                <input type="text" name="barangay_name" value="{{ old('barangay_name') }}"
                       placeholder="Enter BHS name"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                @error('barangay_name')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Buttons --}}
            <div style="display:flex; gap:12px; margin-top:4px;">
                <button type="submit" style="
                    font-size:14px; font-weight:600; padding:10px 20px; border-radius:10px;
                    background:#42ad8a; color:#ffffff; border:none; cursor:pointer;
                    transition: background 0.2s;
                " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                    Save BHS
                </button>

                <a href="{{ route('barangay.index') }}" style="
                    font-size:14px; font-weight:600; padding:10px 20px; border-radius:10px;
                    background:#ffffff; color:#334155; border:1px solid #e2e8f0;
                    text-decoration:none; transition: background 0.2s;
                " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'">
                    Cancel
                </a>
            </div>
        </form>
    </div>

</div>
@endsection