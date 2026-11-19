@php
    $dispensingRoute = request()->routeIs('reports.dispensing') ? 'reports.dispensing' : 'dispensing.index';
    $missingBarangaySelected = request()->boolean('barangay_missing');
    $otherFilters = request()->except('barangay', 'barangay_missing', 'page');
@endphp
<section class="no-print" style="padding:18px 20px;background:#fff;border:1px solid #d6e5df;border-radius:16px;" aria-label="Dispensing record filters">
    <form method="GET" action="{{ url()->current() }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        @foreach($otherFilters as $filterName => $filterValue)
            @if(is_scalar($filterValue))
                <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
            @endif
        @endforeach
        <label for="dispensing-barangay-filter" style="display:flex;flex-direction:column;gap:6px;font-weight:600;color:#334155;">Barangay served
            <select id="dispensing-barangay-filter" name="barangay" style="min-height:44px;padding:8px 12px;border:1px solid #a6b7af;border-radius:8px;background:#fff;color:#0f172a;">
                <option value="">{{ session('role') === 'midwife' && ($dispensingScope ?? '') === 'area' ? 'All assigned barangays' : 'All barangays' }}</option>
                @if(request()->filled('barangay') && !$barangayFilterOptions->contains(fn ($area) => mb_strtolower($area) === mb_strtolower(trim(request('barangay')))))
                    <option value="{{ request('barangay') }}" selected>{{ request('barangay') }}</option>
                @endif
                @foreach($barangayFilterOptions as $filterBarangay)
                    <option value="{{ $filterBarangay }}" @selected(mb_strtolower(trim((string) request('barangay'))) === mb_strtolower($filterBarangay))>{{ $filterBarangay }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" style="min-height:44px;padding:10px 16px;border:1px solid #258663;border-radius:8px;background:#258663;color:#fff;font-weight:600;">Apply barangay filter</button>
        @if(!$missingBarangaySelected && (session('role') !== 'midwife' || ($dispensingScope ?? '') === 'personal'))
            <a href="{{ route($dispensingRoute, $otherFilters + ['barangay_missing' => 1]) }}" style="padding:10px;color:#0f5c4b;text-decoration:underline;">Show records without a barangay</a>
        @endif
        @if(request()->filled('barangay') || $missingBarangaySelected || ($selectedMedicine ?? null))
            <a href="{{ route($dispensingRoute, request()->except('barangay', 'barangay_missing', 'medicine_id', 'page')) }}" style="padding:10px;color:#0f5c4b;text-decoration:underline;">Clear barangay and medicine filters</a>
        @endif
    </form>
    @if($missingBarangaySelected)
        <p style="margin:12px 0 0;color:#475569;">Showing records without a barangay.</p>
    @endif
    @if($selectedMedicine ?? null)
        <p style="margin:12px 0 0;color:#475569;">Medicine: {{ $selectedMedicine->medicine_name }} {{ $selectedMedicine->dosage }} · {{ $selectedMedicine->unit ?: 'units' }}</p>
    @endif
    @error('barangay')<p role="alert" style="margin:8px 0 0;color:#b91c1c;">{{ $message }}</p>@enderror
</section>
