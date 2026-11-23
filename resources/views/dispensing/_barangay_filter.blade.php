@php
    $dispensingRoute = request()->routeIs('reports.dispensing') ? 'reports.dispensing' : 'dispensing.index';
    $missingBarangaySelected = request()->boolean('barangay_missing');
    $otherFilters = request()->except('barangay', 'barangay_missing', 'page');
    $selectedBarangay = trim((string) request('barangay'));
    $selectedBarangayKey = mb_strtolower($selectedBarangay);
    $assignedBarangaysOnly = session('role') === 'midwife' && ($dispensingScope ?? '') === 'area';
@endphp
<select id="dispensing-barangay-filter" data-native-select aria-label="Filter by barangay"
        onchange="window.location.assign(this.value)"
        style="min-height:2.75rem;max-width:min(13rem,100%);padding:0 12px;border:2px solid #bec6cf;border-radius:10px;background:#fff;color:#0f172a;font-size:1rem;appearance:auto;">
    <option value="{{ route($dispensingRoute, $otherFilters) }}" @selected($selectedBarangay === '' && !$missingBarangaySelected)>
        {{ $assignedBarangaysOnly ? 'All assigned barangays' : 'All barangays' }}
    </option>
    @if($selectedBarangay !== '' && !$barangayFilterOptions->contains(fn ($area) => mb_strtolower($area) === $selectedBarangayKey))
        <option value="{{ route($dispensingRoute, $otherFilters + ['barangay' => $selectedBarangay]) }}" selected>{{ $selectedBarangay }}</option>
    @endif
    @foreach($barangayFilterOptions as $filterBarangay)
        <option value="{{ route($dispensingRoute, $otherFilters + ['barangay' => $filterBarangay]) }}" @selected($selectedBarangayKey === mb_strtolower($filterBarangay))>{{ $filterBarangay }}</option>
    @endforeach
    @if(!$assignedBarangaysOnly)
        <option value="{{ route($dispensingRoute, $otherFilters + ['barangay_missing' => 1]) }}" @selected($missingBarangaySelected)>No barangay recorded</option>
    @endif
</select>
