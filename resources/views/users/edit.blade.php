@extends('layouts.app')

@section('content')
@php
    $isRhuAdmin = (bool) session('health_unit_id');
    $roles      = $allowedRoles ?? ($isRhuAdmin ? ['staff', 'midwife'] : ['admin', 'staff', 'midwife']);
    if (!in_array($user->role, $roles, true)) { $roles[] = $user->role; }
    $roleLabels = ['admin' => 'Admin', 'staff' => 'Staff', 'midwife' => 'Midwife'];

    $barangayOptions   = $barangayOptions ?? [];
    $assignedBarangays = $assignedBarangays ?? [];
    $primaryBarangay   = $primaryBarangay ?? null;
@endphp

<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">User Management</p>
        <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">Edit User</h2>
        <p style="font-size:14px; color:#475569; margin:6px 0 0 0;">Update the selected user account.</p>
    </div>

    {{-- Form Card --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius:20px; padding:28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <form action="{{ route('users.update', $user->id) }}" method="POST" style="display:flex; flex-direction:column; gap:20px;">
            @csrf
            @method('PUT')

            {{-- Full Name --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Full Name</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}"
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
                <input type="text" name="username" value="{{ old('username', $user->username) }}"
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
                <select name="role" id="role-select" onchange="toggleBarangayBox()"
                        style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box; appearance:auto;"
                        onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                        onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    @foreach($roles as $r)
                        <option value="{{ $r }}" {{ old('role', $user->role) === $r ? 'selected' : '' }}>
                            {{ $roleLabels[$r] ?? ucfirst($r) }}
                        </option>
                    @endforeach
                </select>
                @error('role')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
                <p style="margin-top:4px; font-size:12px; color:#64748b;">
                    @if($isRhuAdmin)
                        As RHU Admin you can assign <strong>Staff</strong> or <strong>Midwife</strong> roles within your RHU.
                    @else
                        As PHO Admin you can assign any role.
                    @endif
                </p>
            </div>

            {{-- Assigned RHU --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Assigned RHU</label>
                @if($isRhuAdmin)
                    @php
                        $rhuName = optional($healthUnits->first())->name ?? ($user->healthUnit->name ?? 'N/A');
                    @endphp
                    <input type="text" value="{{ $rhuName }}" readonly
                           style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #e2e8f0; border-radius:14px; background:#f8fafc; color:#64748b; box-sizing:border-box;">
                    <input type="hidden" name="health_unit_id" value="{{ session('health_unit_id') }}">
                    <p style="margin-top:4px; font-size:12px; color:#64748b;">This user belongs to your current RHU.</p>
                @else
                    <select name="health_unit_id"
                            style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box; appearance:auto;"
                            onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                            onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                        @foreach($healthUnits as $rhu)
                            <option value="{{ $rhu->id }}" {{ (old('health_unit_id') ?? $user->health_unit_id) == $rhu->id ? 'selected' : '' }}>
                                {{ $rhu->name }} ({{ $rhu->municipality }})
                            </option>
                        @endforeach
                    </select>
                    <p style="margin-top:4px; font-size:12px; color:#64748b;">Choose which RHU this user will belong to.</p>
                @endif
                @error('health_unit_id')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- MIDWIFE BARANGAY ASSIGNMENT --}}
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
                        No barangays available yet. Register at least one patient first.
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
                                       {{ in_array($brgy, old('barangays', $assignedBarangays)) ? 'checked' : '' }}
                                       style="width:16px; height:16px; accent-color:#9d174d; cursor:pointer;">
                                {{ $brgy }}
                            </label>
                        @endforeach
                    </div>

                    <div id="primary-wrap">
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

            {{-- New Password --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">New Password</label>
                <input type="password" name="password"
                       placeholder="Leave blank if you do not want to change it"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                @error('password')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Confirm New Password --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Confirm New Password</label>
                <input type="password" name="password_confirmation"
                       placeholder="Confirm new password"
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
                    Update User
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

<script>
var EXISTING_PRIMARY = @json(old('primary_barangay', $primaryBarangay));

function toggleBarangayBox() {
    var role = document.getElementById('role-select').value;
    var box  = document.getElementById('barangay-box');
    if (!box) return;
    box.style.display = (role === 'midwife') ? 'block' : 'none';
    if (role === 'midwife') rebuildPrimaryOptions();
}

function rebuildPrimaryOptions() {
    var sel = document.getElementById('primary-select');
    var wrap = document.getElementById('primary-wrap');
    if (!sel || !wrap) return;

    var previously = sel.value || EXISTING_PRIMARY || '';
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

document.addEventListener('DOMContentLoaded', function () {
    toggleBarangayBox();
    document.querySelectorAll('.barangay-checkbox').forEach(function (cb) {
        cb.addEventListener('change', rebuildPrimaryOptions);
    });
    rebuildPrimaryOptions();
});
</script>
@endsection