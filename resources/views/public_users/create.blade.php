@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
        display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
        <div>
            <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Patient Registry</p>
            <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">Register New Patient</h2>
            <p style="font-size:14px; color:#475569; margin:6px 0 0 0;">Register a resident to this RHU.</p>
        </div>
        <a href="{{ route('public_users.index') }}" style="
            padding:10px 18px; border-radius:10px; background:white; color:#334155;
            border:1px solid #e2e8f0; font-size:16px; font-weight:600; text-decoration:none;">
            ← Back
        </a>
    </div>

    @if($errors->any())
        <div style="background:#fcebeb; border:1px solid #f7c1c1; border-radius:12px; padding:14px 20px; color:#a32d2d; font-size:16px;">
            <strong>Please fix the following:</strong>
            <ul style="margin:6px 0 0 0; padding-left:20px;">
                @foreach($errors->all() as $err) <li>{{ $err }}</li> @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('public_users.store') }}" method="POST">
        @csrf

        <style>
            .form-card input, .form-card select, .form-card textarea {
                width:100%; padding:12px 16px; font-size:16px;
                border:2px solid #bec6cf; border-radius:12px;
                background:white; color:#0f172a; box-sizing:border-box; outline:none;
            }
            .form-card input:focus, .form-card select:focus, .form-card textarea:focus {
                border-color:#42ad8a; box-shadow:0 0 0 3px rgba(66,173,138,0.1);
            }
            .form-card label {
                display:block; font-size:14px; font-weight:600; color:#0f172a; margin-bottom:6px;
            }
            .req { color:#dc2626; }
        </style>

        {{-- Basic info --}}
        <div class="form-card" style="
            background:rgba(255,255,255,0.95); border-radius:20px;
            border:1px solid rgba(66,173,138,0.15); box-shadow:0 8px 20px rgba(0,0,0,0.06);
            padding:28px; margin-bottom:20px;">
            <p style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0 0 16px 0;">
                Basic Information
            </p>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label>First Name <span class="req">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" required placeholder="First name">
                </div>
                <div>
                    <label>Last Name <span class="req">*</span></label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" required placeholder="Last name">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label>Birth Date <span class="req">*</span></label>
                    <input type="date" name="birth_date" value="{{ old('birth_date') }}"
                           max="{{ now()->toDateString() }}" required>
                </div>
                <div>
                    <label>PhilHealth ID <span style="color:#64748b; font-weight:400;">(optional)</span></label>
                    <input type="text" name="philhealth_id" value="{{ old('philhealth_id') }}" placeholder="Leave blank if none">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label>Barangay <span class="req">*</span></label>
                    <input type="text" name="barangay" value="{{ old('barangay') }}" required
                           placeholder="e.g. Poblacion"
                           list="barangay-list">
                    <datalist id="barangay-list">
                        @foreach($barangays as $b)
                            <option value="{{ $b }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <div>
                    <label>Contact Number</label>
                    <input type="text" name="contact_number" value="{{ old('contact_number') }}" placeholder="e.g. 09171234567">
                </div>
            </div>

            <div>
                <label>Address <span class="req">*</span></label>
                <input type="text" name="address" value="{{ old('address') }}" required placeholder="House no., street, purok">
            </div>
        </div>

        {{-- FHSIS / Maternal --}}
        <div class="form-card" style="
            background:rgba(255,255,255,0.95); border-radius:20px;
            border:1px solid rgba(66,173,138,0.15); box-shadow:0 8px 20px rgba(0,0,0,0.06);
            padding:28px; margin-bottom:20px;">
            <p style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0 0 16px 0;">
                FHSIS / Maternal Health <span style="color:#64748b; font-weight:400; text-transform:none; letter-spacing:normal;">(all optional)</span>
            </p>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label>Household ID</label>
                    <input type="text" name="household_id" value="{{ old('household_id') }}" placeholder="e.g. HH-0001">
                </div>
                <div>
                    <label>Family Serial No.</label>
                    <input type="text" name="family_serial_no" value="{{ old('family_serial_no') }}" placeholder="e.g. 001">
                </div>
            </div>

            <div style="display:flex; align-items:center; gap:10px; padding:12px 16px; background:#f8fafc; border-radius:12px; margin-bottom:16px;">
                <input type="checkbox" name="is_pregnant" id="is_pregnant" value="1"
                       {{ old('is_pregnant') ? 'checked' : '' }}
                       style="width:20px; height:20px; accent-color:#42ad8a; cursor:pointer;">
                <label for="is_pregnant" style="margin:0; cursor:pointer;">Currently pregnant</label>
            </div>

            <div id="pregnancy-fields" style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:16px;">
                <div>
                    <label>LMP</label>
                    <input type="date" name="lmp" value="{{ old('lmp') }}" max="{{ now()->toDateString() }}">
                </div>
                <div>
                    <label>Gravida</label>
                    <input type="number" name="gravida" value="{{ old('gravida') }}" min="0" max="30">
                </div>
                <div>
                    <label>Para</label>
                    <input type="number" name="para" value="{{ old('para') }}" min="0" max="30">
                </div>
                <div>
                    <label>Risk Codes</label>
                    <input type="text" name="risk_codes" value="{{ old('risk_codes') }}" placeholder="e.g. A1,B2">
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <a href="{{ route('public_users.index') }}" style="
                padding:10px 20px; border-radius:10px; background:white; color:#334155;
                border:1px solid #e2e8f0; font-size:16px; font-weight:600; text-decoration:none;">
                Cancel
            </a>
            <button type="submit" style="
                padding:10px 20px; border-radius:10px; background:#42ad8a; color:white;
                font-size:16px; font-weight:600; border:none; cursor:pointer;">
                Save Patient
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chk = document.getElementById('is_pregnant');
    const box = document.getElementById('pregnancy-fields');
    function toggle() {
        box.style.opacity = chk.checked ? '1' : '0.4';
        box.querySelectorAll('input').forEach(i => { i.disabled = !chk.checked; });
    }
    chk.addEventListener('change', toggle);
    toggle();
});
</script>
@endsection