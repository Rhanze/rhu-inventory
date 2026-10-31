@extends('layouts.app')

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
        <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">User Management</p>
        <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">{{ $pageTitle }}</h2>
        <p style="font-size:14px; color:#475569; margin:6px 0 0 0;">{{ $subtitle }}</p>
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
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Full Name</label>
                <input type="text" name="name" value="{{ old('name') }}"
                       placeholder="Enter full name"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                @error('name')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Username --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Username</label>
                <input type="text" name="username" value="{{ old('username') }}"
                       placeholder="Enter username"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                @error('username')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Role --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Role</label>
                <select name="role" id="role-select" onchange="updateRoleHint(); toggleBarangayBox();"
                        style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box; appearance:auto;"
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
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
                <p style="margin-top:4px; font-size:12px; color:#64748b;">
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
                    <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Assigned RHU</label>
                    <input type="text"
                           value="{{ $ownRhu->name ?? 'Unknown RHU' }}{{ $ownRhu && $ownRhu->municipality ? ' (' . $ownRhu->municipality . ')' : '' }}"
                           readonly
                           style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #e2e8f0; border-radius:14px; background:#f8fafc; color:#64748b; box-sizing:border-box;">
                    <p style="margin-top:4px; font-size:12px; color:#64748b;">Locked to your RHU.</p>
                </div>
            @else
                <div>
                    <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">
                        Assigned RHU <span style="color:#dc2626;">*</span>
                    </label>
                    <select name="health_unit_id" id="rhu-select" onchange="updateRoleHint()"
                            style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box; appearance:auto;"
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
                        <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                    @enderror
                    <p style="margin-top:4px; font-size:12px; color:#64748b;">
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
                    <p id="role-hint-text" style="font-size:13px; color:#166534; margin:0; line-height:1.5;"></p>
                </div>
            @endif

            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- MIDWIFE BARANGAY ASSIGNMENT (shows only when role = midwife) --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}
            <div id="barangay-box" style="
                display: none;
                padding: 20px;
                border-radius: 14px;
                background: #FCE7F3;
                border: 1px solid #F9A8D4;
            ">
                <p style="margin: 0 0 4px 0; font-size: 14px; font-weight: 700; color: #9d174d;">Assigned Barangays</p>
                <p style="margin: 0 0 16px 0; font-size: 12px; color: #9d174d;">
                    Select the barangays this midwife covers. Mark one as <strong>primary</strong> — new patients
                    from that barangay will be auto-assigned to her.
                </p>

                @if(empty($barangayOptions))
                    <p style="margin: 0; font-size: 13px; color: #9d174d;">
                        No barangays available yet. Register at least one patient first, or assign barangays from the edit page.
                    </p>
                @else
                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; margin-bottom: 16px;">
                        @foreach($barangayOptions as $brgy)
                            <label style="
                                display: flex; align-items: center; gap: 8px;
                                padding: 10px 12px; background: white; border-radius: 10px;
                                border: 1px solid #F9A8D4; cursor: pointer; font-size: 13px; color: #0f172a;
                            ">
                                <input type="checkbox"
                                       name="barangays[]"
                                       value="{{ $brgy }}"
                                       class="barangay-checkbox"
                                       data-barangay="{{ $brgy }}"
                                       onchange="onBarangayToggle(this)"
                                       {{ in_array($brgy, old('barangays', [])) ? 'checked' : '' }}
                                       style="width:16px; height:16px; accent-color:#9d174d; cursor:pointer;">
                                {{ $brgy }}
                            </label>
                        @endforeach
                    </div>

                    <div id="primary-wrap" style="display:none;">
                        <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#9d174d;">
                            Primary Barangay
                        </label>
                        <select name="primary_barangay" id="primary-select"
                                style="width:100%; padding:10px 14px; font-size:14px; border:2px solid #F9A8D4; border-radius:10px; background:white; color:#0f172a; box-sizing:border-box;">
                            <option value="">— none —</option>
                        </select>
                        <p style="margin-top:4px; font-size:12px; color:#9d174d;">
                            Only one midwife can be primary per barangay. Selecting a new primary here will demote the previous one.
                        </p>
                    </div>
                @endif
            </div>

            {{-- Password --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Password</label>
                <input type="password" name="password"
                       placeholder="Enter password"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                @error('password')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Confirm Password --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Confirm Password</label>
                <input type="password" name="password_confirmation"
                       placeholder="Confirm password"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
            </div>

            {{-- Buttons --}}
            <div style="display:flex; gap:12px; margin-top:4px;">
                <button type="submit" style="
                    font-size:14px; font-weight:600; padding:10px 20px; border-radius:10px;
                    background:#42ad8a; color:#ffffff; border:none; cursor:pointer;
                    transition: background 0.2s;
                " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                    Create Account
                </button>

                <a href="{{ route('users.index') }}" style="
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

    let message = '';
    let bg = '#DCFCE7', border = '#86EFAC', color = '#166534';

    if (role === 'admin') {
        message = `This account will be an <strong>RHU Admin</strong> for <strong>${cleanRhu}</strong>. They can manage staff, inventory, and reports for that RHU.`;
        bg = '#EDE9FE'; border = '#C4B5FD'; color = '#6d28d9';
    } else if (role === 'staff') {
        message = `This account will be <strong>Staff</strong> at <strong>${cleanRhu}</strong>. They can handle inventory, dispensing, and transfers for that RHU.`;
        bg = '#DBEAFE'; border = '#93C5FD'; color = '#1e40af';
    } else if (role === 'midwife') {
        message = `This account will be a <strong>Midwife</strong> at <strong>${cleanRhu}</strong>. They can handle dispensing, disease reports, and immunization records.`;
        bg = '#FCE7F3'; border = '#F9A8D4'; color = '#9d174d';
    }

    if (message) {
        text.innerHTML = message;
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
// Show / hide the barangay assignment box based on selected role
function toggleBarangayBox() {
    var role = document.getElementById('role-select').value;
    var box  = document.getElementById('barangay-box');
    if (!box) return;
    box.style.display = (role === 'midwife') ? 'block' : 'none';
    if (role === 'midwife') rebuildPrimaryOptions();
}

// Rebuild the "primary barangay" dropdown from the currently-checked boxes
function rebuildPrimaryOptions() {
    var sel = document.getElementById('primary-select');
    var wrap = document.getElementById('primary-wrap');
    if (!sel || !wrap) return;

    var previously = sel.value;
    sel.innerHTML = '<option value="">— none —</option>';

    var anyChecked = false;
    document.querySelectorAll('.barangay-checkbox:checked').forEach(function (cb) {
        anyChecked = true;
        var opt = document.createElement('option');
        opt.value = cb.dataset.barangay;
        opt.textContent = cb.dataset.barangay;
        if (cb.dataset.barangay === previously) opt.selected = true;
        sel.appendChild(opt);
    });

    wrap.style.display = anyChecked ? 'block' : 'none';
}

function onBarangayToggle(checkbox) {
    rebuildPrimaryOptions();
}

document.addEventListener('DOMContentLoaded', function () {
    toggleBarangayBox();
    document.querySelectorAll('.barangay-checkbox').forEach(function (cb) {
        cb.addEventListener('change', rebuildPrimaryOptions);
    });
    rebuildPrimaryOptions();
});
</script>
@endsection