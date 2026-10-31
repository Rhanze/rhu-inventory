@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">PHO Allocation</p>
        <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">Allocate Stock to RHU</h2>
        <p style="font-size:14px; color:#475569; margin:6px 0 0 0;">Send medicines directly from the Provincial Health Office to a Rural Health Unit.</p>
    </div>

    {{-- Form Card --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius:20px; padding:28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
    ">
        <form action="{{ route('allocation.province.store') }}" method="POST" style="display:flex; flex-direction:column; gap:20px;">
            @csrf

            {{-- Medicine (searchable autocomplete) --}}
            <div style="position: relative;">
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">
                    Medicine / Vaccine <span style="color:#dc2626;">*</span>
                </label>
                <input type="text"
                       id="medicine-search"
                       name="medicine_search"
                       placeholder="Type to search medicine..."
                       autocomplete="off"
                       value="{{ old('medicine_search') }}"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">

                {{-- Hidden input to hold the actual medicine_id --}}
                <input type="hidden" name="medicine_id" id="medicine-id" value="{{ old('medicine_id') }}">

                {{-- Dropdown container --}}
                <div id="medicine-dropdown" style="
                    display: none;
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    background: white;
                    border: 2px solid #42ad8a;
                    border-radius: 0 0 14px 14px;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
                    z-index: 100;
                    max-height: 240px;
                    overflow-y: auto;
                "></div>
            </div>

            @error('medicine_id')
                <p style="margin-top:-12px; font-size:13px; color:#dc2626;">{{ $message }}</p>
            @enderror

            {{-- Destination RHU --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">
                    Destination RHU <span style="color:#dc2626;">*</span>
                </label>
                <select name="destination_health_unit_id"
                        style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box; appearance:auto;"
                        onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                        onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'"
                        required>
                    <option value="">Select RHU</option>
                    @foreach($healthUnits as $rhu)
                        <option value="{{ $rhu->id }}" {{ old('destination_health_unit_id') == $rhu->id ? 'selected' : '' }}>
                            {{ $rhu->name }}
                        </option>
                    @endforeach
                </select>
                @error('destination_health_unit_id')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Quantity --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">
                    Quantity to Allocate <span style="color:#dc2626;">*</span>
                </label>
                <input type="number" name="quantity_allocated" value="{{ old('quantity_allocated') }}" min="1"
                       placeholder="Enter quantity"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'"
                       required>
                @error('quantity_allocated')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Batch No. (optional) --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">
                    Batch No.
                    <span style="font-weight:400; color:#64748b; font-size:13px;">(optional)</span>
                </label>
                <input type="text" name="batch_no" value="{{ old('batch_no') }}"
                       placeholder="Enter batch or lot number"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                <small style="color:#64748b; font-size:13px;">
                    💡 Different batches get separate inventory rows at the destination RHU.
                </small>
                @error('batch_no')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Expiration Date (optional) --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">
                    Expiration Date
                    <span style="font-weight:400; color:#64748b; font-size:13px;">(optional)</span>
                </label>
                <input type="date" name="expiration_date" value="{{ old('expiration_date') }}"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'">
                @error('expiration_date')
                    <p style="margin-top:4px; font-size:13px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Date of Allocation --}}
            <div>
                <label style="display:block; margin-bottom:6px; font-size:14px; font-weight:600; color:#0f172a;">
                    Date of Allocation <span style="color:#dc2626;">*</span>
                </label>
                <input type="date" name="date_allocated" value="{{ old('date_allocated', date('Y-m-d')) }}"
                       style="width:100%; padding:12px 16px; font-size:14px; border:2px solid #bec6cf; border-radius:14px; outline:none; background:white; color:#0f172a; box-sizing:border-box;"
                       onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                       onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'"
                       required>
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
                    Allocate to RHU
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

{{-- Autocomplete JavaScript --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('medicine-search');
    const hiddenInput = document.getElementById('medicine-id');
    const dropdown    = document.getElementById('medicine-dropdown');
    let abortController = null;
    let selectedIndex = -1;

    searchInput.addEventListener('input', function () {
        const query = this.value.trim();
        if (query.length < 2) {
            closeDropdown();
            return;
        }

        if (abortController) abortController.abort();

        abortController = new AbortController();
        fetch(`/stock-logs/autocomplete?q=${encodeURIComponent(query)}`, {
            signal: abortController.signal
        })
        .then(response => response.json())
        .then(data => {
            if (data.length === 0) {
                closeDropdown();
                return;
            }
            renderSuggestions(data);
        })
        .catch(err => {
            if (err.name !== 'AbortError') {
                console.error(err);
                closeDropdown();
            }
        });
    });

    function renderSuggestions(items) {
        dropdown.innerHTML = '';
        selectedIndex = -1;

        items.forEach((item, idx) => {
            const div = document.createElement('div');
            div.className = 'autocomplete-item';
            div.dataset.id = item.id;
            div.dataset.text = item.text;
            div.style.cssText = 'padding:10px 16px; cursor:pointer; font-size:14px; color:#0f172a; transition:background 0.15s;';
            div.textContent = item.text;

            div.addEventListener('mouseenter', () => {
                Array.from(dropdown.children).forEach(c => c.style.background = 'white');
                div.style.background = '#E8F7F4';
                selectedIndex = idx;
            });

            div.addEventListener('click', () => selectItem(item));

            dropdown.appendChild(div);
        });

        dropdown.style.display = 'block';
    }

    function selectItem(item) {
        searchInput.value = item.text;
        hiddenInput.value = item.id;
        closeDropdown();
    }

    function closeDropdown() {
        dropdown.style.display = 'none';
        dropdown.innerHTML = '';
        selectedIndex = -1;
    }

    // Keyboard navigation
    searchInput.addEventListener('keydown', function (e) {
        const items = dropdown.querySelectorAll('.autocomplete-item');
        if (!items.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
            highlightItem(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, 0);
            highlightItem(items);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && items[selectedIndex]) {
                const item = items[selectedIndex];
                const id = item.dataset.id;
                const text = item.dataset.text;
                selectItem({ id, text });
            }
        } else if (e.key === 'Escape') {
            closeDropdown();
        }
    });

    function highlightItem(items) {
        items.forEach((item, idx) => {
            if (idx === selectedIndex) {
                item.style.background = '#E8F7F4';
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.style.background = 'white';
            }
        });
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            closeDropdown();
        }
    });
});
</script>

<style>
    .autocomplete-item:hover {
        background: #E8F7F4;
    }
</style>
@endsection