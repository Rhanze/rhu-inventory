@php
    $recordedBarangay = $member->barangay ?? null;
    $historicalArea = isset($member) && ! $barangays->containsStrict($recordedBarangay);
    $selectedBarangay = old('barangay', $historicalArea ? '' : $recordedBarangay);
@endphp
<select name="barangay" id="patient-barangay" @required(!$historicalArea) @disabled($barangays->isEmpty()) aria-describedby="patient-barangay-help">
    <option value="">{{ $historicalArea ? 'Keep recorded area' : ($barangays->isEmpty() ? 'No assigned barangays' : 'Select your assigned barangay') }}</option>
    @foreach($barangays as $area)
        <option value="{{ $area }}" @selected($selectedBarangay === $area)>{{ $area }}</option>
    @endforeach
</select>
@if($historicalArea)
    <p style="margin:.5rem 0 0;font-size:1rem;color:#475569;">Recorded area — {{ $recordedBarangay ?: 'Not recorded' }}. This remains part of the saved patient record.</p>
@endif
<p id="patient-barangay-help" style="margin:.5rem 0 0;font-size:1rem;color:#475569;">{{ $barangays->isEmpty() ? 'Ask your RHU administrator to assign a barangay before registering patients.' : 'Choose from the barangays assigned to your account.' }}</p>
