@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Inventory Management</p>
        <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">Add Inventory Record</h2>
    </div>

    {{-- Form Card --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius:20px; padding:28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <form action="{{ route('inventory.store') }}" method="POST" style="display:flex; flex-direction:column; gap:18px;">
            @csrf

            {{-- 1. Medicine / Vaccine --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Medicine / Vaccine <span style="color:#dc2626;">*</span></label>
                <select name="medicine_id" style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box; appearance:auto;" required>
                    <option value="">Select medicine</option>
                    @foreach($medicines as $med)
                        <option value="{{ $med->id }}" {{ old('medicine_id') == $med->id ? 'selected' : '' }}>
                            {{ $med->medicine_name }} {{ $med->dosage ? '- ' . $med->dosage : '' }}
                        </option>
                    @endforeach
                </select>
                @error('medicine_id')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- 2. Batch Number --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Batch Number</label>
                <input type="text" name="batch_no" id="batch_no" value="{{ old('batch_no') }}"
                       placeholder="e.g., BATCH-2024-001"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                @error('batch_no')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- 3. Storage Location --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Storage Location</label>
                <input type="text" name="storage_location" id="storage_location" value="{{ old('storage_location') }}"
                       placeholder="e.g., Cold Room A, Shelf 3B, Refrigerator 2"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                <small style="color:#64748b; font-size:13px;">Where is this batch physically stored?</small>
                @error('storage_location')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- 4. Pack Type + Pieces per Pack --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">
                        Pack Type
                        <span style="font-weight:400; color:#64748b; font-size:12px;">(optional)</span>
                    </label>
                    <input type="text" name="pack_type" value="{{ old('pack_type') }}"
                           placeholder="e.g., Stub, Box, Tray"
                           style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                           onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                           onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <small style="color:#64748b; font-size:13px;">How this batch is wrapped.</small>
                    @error('pack_type')
                        <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">
                        Pieces per Pack
                        <span style="font-weight:400; color:#64748b; font-size:12px;">(optional)</span>
                    </label>
                    <input type="number" name="pieces_per_pack" value="{{ old('pieces_per_pack') }}" min="1"
                           placeholder="e.g., 10"
                           style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                           onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                           onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <small style="color:#64748b; font-size:13px;">How many pieces fit in one pack.</small>
                    @error('pieces_per_pack')
                        <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- 5. Expiration Date --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Expiration Date</label>
                <input type="date" name="expiration_date" value="{{ old('expiration_date') }}"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                <small style="color:#64748b; font-size:13px;">Leave blank if not applicable.</small>
                @error('expiration_date')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- 6. Available Stock + Allocated Stock --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Available Stock <span style="color:#dc2626;">*</span></label>
                    <input type="number" name="available_stock" value="{{ old('available_stock', 0) }}" min="0"
                           style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                           onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                           onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'" required>
                    @error('available_stock')
                        <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Allocated Stock <span style="color:#dc2626;">*</span></label>
                    <input type="number" name="allocated_stock" value="{{ old('allocated_stock', 0) }}" min="0"
                           style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                           onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                           onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'" required>
                    @error('allocated_stock')
                        <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Buttons --}}
            <div style="display:flex; gap:12px; margin-top:8px;">
                <button type="submit" style="
                    font-size:14px; font-weight:600; padding:10px 20px; border-radius:10px;
                    background:#42ad8a; color:#ffffff; border:none; cursor:pointer;
                    transition: background 0.2s;
                " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                    Save Inventory Record
                </button>

                <a href="{{ route('inventory.index') }}" style="
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