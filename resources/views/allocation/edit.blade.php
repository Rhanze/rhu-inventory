@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Allocation Management</p>
        <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">Edit Allocation</h2>
        <p style="font-size:14px; color:#475569; margin:6px 0 0 0;">Update the selected allocation record.</p>
    </div>

    {{-- Form Card --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius:20px; padding:28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <form action="{{ route('allocation.update', $allocation->id) }}" method="POST" style="display:flex; flex-direction:column; gap:20px;">
            @csrf
            @method('PUT')

            {{-- Inventory Item --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Inventory Item</label>
                <select name="inventory_id"
                        style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box; appearance:auto;"
                        onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                        onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                    <option value="">Select inventory</option>
                    @foreach($inventories as $inventory)
                        @php
                            // Preselect logic:
                            // 1. If validation re-populated old(inventory_id) → match by inventory row id
                            // 2. Else → match by medicine_id (first batch of the allocated medicine)
                            $isOldMatch       = (string) old('inventory_id') === (string) $inventory->id;
                            $isFallbackMatch  = old('inventory_id') === null
                                                && $inventory->medicine_id == $allocation->medicine_id
                                                && $inventory->health_unit_id == $allocation->health_unit_id;
                        @endphp
                        <option value="{{ $inventory->id }}" {{ ($isOldMatch || $isFallbackMatch) ? 'selected' : '' }}>
                            {{ $inventory->medicine->medicine_name ?? 'N/A' }}{{ $inventory->medicine->dosage ? ' - ' . $inventory->medicine->dosage : '' }}
                            (Available: {{ $inventory->available_stock }})
                        </option>
                    @endforeach
                </select>
                @error('inventory_id')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Destination (free text) --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Destination</label>
                <input type="text" name="destination" id="destination-input"
                       value="{{ old('destination', $allocation->destination) }}"
                       list="prior-destinations"
                       placeholder="e.g., Brgy Poblacion, Mobile Outreach Zone 4, Patient house visit"
                       autocomplete="off"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">

                {{-- Datalist of prior destinations --}}
                @if(isset($priorDestinations) && $priorDestinations->count())
                    <datalist id="prior-destinations">
                        @foreach($priorDestinations as $prior)
                            <option value="{{ $prior }}"></option>
                        @endforeach
                    </datalist>
                @endif

                <small style="color:#64748b; font-size:13px;">
                    💡 Type any destination — barangay name, outreach site, or purpose. Previous entries will appear as suggestions.
                </small>
                @error('destination')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Quantity Allocated --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Quantity Allocated</label>
                <input type="number" name="quantity_allocated" value="{{ old('quantity_allocated', $allocation->quantity_allocated) }}"
                       min="1"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                @error('quantity_allocated')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Date Allocated --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">Date Allocated</label>
                <input type="date" name="date_allocated"
                       value="{{ old('date_allocated', $allocation->date_allocated ? $allocation->date_allocated->format('Y-m-d') : '') }}"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                @error('date_allocated')
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
                    Update Allocation
                </button>

                <a href="{{ route('allocation.index') }}" style="
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