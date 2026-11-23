@if($selectedMedicine ?? null)
    <p class="no-print" style="margin:0;color:#475569;">
        Medicine: {{ $selectedMedicine->medicine_name }} {{ $selectedMedicine->dosage }} · {{ $selectedMedicine->unit ?: 'units' }}
        <a href="{{ route(request()->route()->getName(), request()->except('medicine_id', 'page')) }}" style="margin-inline-start:8px;color:#0f5c4b;text-decoration:underline;">Clear medicine filter</a>
    </p>
@endif
@error('barangay')<p role="alert" style="margin:0;color:#b91c1c;">{{ $message }}</p>@enderror
