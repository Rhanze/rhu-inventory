@extends('layouts.app')

@section('title', 'Create User Account')

@section('content')
@php
    $isRhuAdmin = (bool) session('health_unit_id');
    $roles      = $allowedRoles ?? ($isRhuAdmin ? ['staff', 'midwife'] : ['admin', 'staff']);
    $preselect  = request('role');
    if (!in_array($preselect, $roles, true)) { $preselect = null; }
    $roleLabels = ['admin' => 'Admin', 'staff' => 'Staff', 'midwife' => 'Midwife'];
    $pageTitle  = 'Add Account';
    $subtitle   = $isRhuAdmin
        ? 'Create a new staff or midwife account for your Rural Health Unit.'
        : 'Create a new admin or staff account and assign it to a Rural Health Unit.';

    $ownRhu = $isRhuAdmin ? $healthUnits->first() : null;
    $barangayOptions = $barangayOptions ?? [];
@endphp

<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <p style="font-size:1rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">User Management</p>
        <h2 style="font-size:1.5rem; font-weight:700; color:#0f172a; margin:0;">{{ $pageTitle }}</h2>
        <p style="font-size:1rem; color:#475569; margin:6px 0 0 0;">{{ $subtitle }}</p>
    </div>

    {{-- Form Card --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius:20px; padding:28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <form action="{{ route('users.store') }}" method="POST" style="display:flex; flex-direction:column; gap:20px;">
            @csrf

            {{-- Full Name --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:1rem; font-weight:600; color:#0f172a;">Full Name</label>
                <input type="text" name="name" value="{{ old('name') }}"
                       placeholder="Enter full name"
                       style="width:100%; padding:12px 16px; font-size:1rem; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                @error('name')
                    <p style="margin-top:4px; font-size:1rem; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Username --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:1rem; font-weight:600; color:#0f172a;">Username</label>
                <input type="text" name="username" value="{{ old('username') }}"
                       placeholder="Enter username"
                       style="width:100%; padding:12px 16px; font-size:1rem; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                @error('username')
                    <p style="margin-top:4px; font-size:1rem; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Role --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:1rem; font-weight:600; color:#0f172a;">Role</label>
                <select name="role" id="role-select" onchange="updateRoleHint(); toggleBarangayBox();"
                        style="width:100%; padding:12px 16px; font-size:1rem; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box; appearance:auto;"
                        onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                        onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <option value="">Select role</option>
                    @foreach($roles as $r)
                        <option value="{{ $r }}" {{ old('role', $preselect) === $r ? 'selected' : '' }}>
                            {{ $roleLabels[$r] ?? ucfirst($r) }}
                        </option>
                    @endforeach
                </select>
                @error('role')
                    <p style="margin-top:4px; font-size:1rem; color:#dc2626;">{{ $message }}</p>
                @enderror
                <p style="margin-top:4px; font-size:1rem; color:#64748b;">
                    @if($isRhuAdmin)
                        As RHU Admin you can create <strong>Staff</strong> and <strong>Midwife</strong> accounts for your RHU.
                    @else
                        As PHO Admin you can create <strong>Admin</strong> and <strong>Staff</strong> accounts.
                    @endif
                </p>
            </div>

            {{-- Assigned RHU --}}
            @if($isRhuAdmin)
                <input type="hidden" name="health_unit_id" value="{{ session('health_unit_id') }}">
                <div>
                    <label style="display:block; margin-bottom:6px; font-size:1rem; font-weight:600; color:#0f172a;">Assigned RHU</label>
                    <input type="text"
                           value="{{ $ownRhu->name ?? 'Unknown RHU' }}{{ $ownRhu && $ownRhu->municipality ? ' (' . $ownRhu->municipality . ')' : '' }}"
                           readonly
                           style="width:100%; padding:12px 16px; font-size:1rem; border:2px solid #e2e8f0; border-radius:14px; background:#f8fafc; color:#64748b; box-sizing:border-box;">
                    <p style="margin-top:4px; font-size:1rem; color:#64748b;">Locked to your RHU.</p>
                </div>
            @else
                <div>
                    <label style="display:block; margin-bottom:6px; font-size:1rem; font-weight:600; color:#0f172a;">
                        Assigned RHU <span style="color:#dc2626;">*</span>
                    </label>
                    <select name="health_unit_id" id="rhu-select" onchange="updateRoleHint()"
                            style="width:100%; padding:12px 16px; font-size:1rem; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box; appearance:auto;"
                            onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                            onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                        <option value="">Select RHU</option>
                        @foreach($healthUnits as $rhuOption)
                            <option value="{{ $rhuOption->id }}" {{ old('health_unit_id') == $rhuOption->id ? 'selected' : '' }}>
                                {{ $rhuOption->name }} ({{ $rhuOption->municipality }})
                            </option>
                        @endforeach
                    </select>
                    @error('health_unit_id')
                        <p style="margin-top:4px; font-size:1rem; color:#dc2626;">{{ $message }}</p>
                    @enderror
                    <p style="margin-top:4px; font-size:1rem; color:#64748b;">
                        Choose which RHU this user will belong to.
                    </p>
                </div>

                {{-- Dynamic hint --}}
                <div id="role-hint" style="
                    display: none;
                    padding: 14px 18px;
                    border-radius: 12px;
                    border: 1px solid #86EFAC;
                    background: #DCFCE7;
                ">
                    <p id="role-hint-text" style="font-size:1rem; color:#166534; margin:0; line-height:1.5;"></p>
                </div>
            @endif

            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- MIDWIFE BARANGAY ASSIGNMENT (shows only when role = midwife) --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}
            <div id="barangay-box" style="display:none;padding:20px;border-radius:14px;background:#f8fafc;border:1px solid #bec6cf;">
                <label for="barangay-names" style="display:block;font-size:1rem;font-weight:700;color:#0f5c4b;margin-bottom:8px;">Assigned Barangays</label>
                <p id="barangay-help" style="font-size:1rem;color:#0f5c4b;margin:0 0 12px;">Enter one barangay name per line using plain names. You can assign an area before registering any patients.</p>
                <textarea id="barangay-names" name="barangay_names" rows="4" maxlength="10100" aria-describedby="barangay-help" placeholder="Barangay San Jose&#10;Barangay Poblacion" style="width:100%;padding:10px 14px;font:inherit;font-size:1rem;border:2px solid #bec6cf;border-radius:10px;box-sizing:border-box;">{{ old('barangay_names', implode("\n", old('barangays', []))) }}</textarea>
                @error('barangay_names')<p style="color:#dc2626;font-size:1rem;">{{ $message }}</p>@enderror
                @error('barangays')<p style="color:#dc2626;font-size:1rem;">{{ $message }}</p>@enderror
                <label for="primary-barangay" style="display:block;font-size:1rem;font-weight:600;color:#0f5c4b;margin:14px 0 6px;">Primary Barangay (optional)</label>
                <input id="primary-barangay" name="primary_barangay" maxlength="100" list="known-barangays" value="{{ old('primary_barangay') }}" style="width:100%;padding:10px 14px;font-size:1rem;border:2px solid #bec6cf;border-radius:10px;box-sizing:border-box;">
                <datalist id="known-barangays">@foreach($barangayOptions as $brgy)<option value="{{ $brgy }}">@endforeach</datalist>
                <p style="font-size:1rem;color:#0f5c4b;margin:6px 0 0;">Use a name from the assigned list. This midwife becomes the primary contact for that barangay within this RHU.</p>
                @error('primary_barangay')<p style="color:#dc2626;font-size:1rem;">{{ $message }}</p>@enderror
            </div>

            {{-- Password --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:1rem; font-weight:600; color:#0f172a;">Password</label>
                <input type="password" name="password"
                       placeholder="Enter password"
                       style="width:100%; padding:12px 16px; font-size:1rem; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                @error('password')
                    <p style="margin-top:4px; font-size:1rem; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Confirm Password --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:1rem; font-weight:600; color:#0f172a;">Confirm Password</label>
                <input type="password" name="password_confirmation"
                       placeholder="Confirm password"
                       style="width:100%; padding:12px 16px; font-size:1rem; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
            </div>

            {{-- Buttons --}}
            <div style="display:flex; gap:12px; margin-top:4px;">
                <button type="submit" style="
                    font-size:1rem; font-weight:600; padding:10px 20px; border-radius:10px;
                    background:#42ad8a; color:#ffffff; border:none; cursor:pointer;
                    transition: background 0.2s;
                " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                    Create Account
                </button>

                <a href="{{ route('users.index') }}" style="
                    font-size:1rem; font-weight:600; padding:10px 20px; border-radius:10px;
                    background:#ffffff; color:#334155; border:1px solid #e2e8f0;
                    text-decoration:none; transition: background 0.2s;
                " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'">
                    Cancel
                </a>
            </div>
        </form>
    </div>

</div>

@if(!$isRhuAdmin)
<script>
function updateRoleHint() {
    const role    = document.getElementById('role-select').value;
    const rhuSel  = document.getElementById('rhu-select');
    const rhuName = rhuSel.options[rhuSel.selectedIndex]?.text || '';
    const hint    = document.getElementById('role-hint');
    const text    = document.getElementById('role-hint-text');

    if (!role || !rhuSel.value) {
        hint.style.display = 'none';
        return;
    }

    const cleanRhu = rhuName.split(' (')[0];

    let roleLabel, scopeLabel, description, bg, border, color;

    if (role === 'admin') {
        roleLabel = 'RHU Admin'; scopeLabel = ' for ';
        description = '. They can manage staff, inventory, and reports for that RHU.';
        bg = '#EDE9FE'; border = '#C4B5FD'; color = '#6d28d9';
    } else if (role === 'staff') {
        roleLabel = 'Staff'; scopeLabel = ' at ';
        description = '. They can handle inventory, dispensing, and transfers for that RHU.';
        bg = '#DBEAFE'; border = '#93C5FD'; color = '#1e40af';
    } else if (role === 'midwife') {
        roleLabel = 'Midwife'; scopeLabel = ' at ';
        description = '. They can handle dispensing, disease reports, and immunization records.';
        bg = '#f8fafc'; border = '#bec6cf'; color = '#0f5c4b';
    }

    if (roleLabel) {
        const roleName = document.createElement('strong');
        const facilityName = document.createElement('strong');
        roleName.textContent = roleLabel;
        facilityName.textContent = cleanRhu;
        const opening = { admin: 'This account will be an ', staff: 'This account will be ', midwife: 'This account will be a ' }[role];
        text.replaceChildren(opening, roleName, scopeLabel, facilityName, description);
        hint.style.background  = bg;
        hint.style.borderColor = border;
        text.style.color = color;
        hint.style.display = 'block';
    } else {
        hint.style.display = 'none';
    }
}
document.addEventListener('DOMContentLoaded', updateRoleHint);
</script>
@endif

<script>
function toggleBarangayBox() {
    var box = document.getElementById('barangay-box');
    if (box) box.style.display = document.getElementById('role-select').value === 'midwife' ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleBarangayBox);
</script>
@endsection
