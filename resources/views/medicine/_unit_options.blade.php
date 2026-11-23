@php
    $selectedUnit = old('unit', $currentUnit);
    $units = ['Tablet', 'Capsule', 'piece', 'stub', 'sachet', 'box', 'vial', 'ampule', 'bottle'];
@endphp
<option value="">— Select —</option>
@if($selectedUnit && !in_array($selectedUnit, $units, true))
    <option value="{{ $selectedUnit }}" selected>{{ $selectedUnit }}</option>
@endif
@foreach($units as $unit)
    <option value="{{ $unit }}" @selected($selectedUnit === $unit)>{{ $unit }}</option>
@endforeach
