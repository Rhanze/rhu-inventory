@php
    $previousInventory = old('inventory_id');
@endphp
@foreach($inventories->filter(fn ($batch) => $batch->medicine)->groupBy('medicine_id') as $medicineId => $batches)
    @php
        $representative = $batches->first();
        $medicine = $representative->medicine;
        $available = $batches->filter(fn ($batch) => !$batch->expiration_date || $batch->expiration_date->gte(today()))->sum('available_stock');
        $selected = $previousInventory === null
            ? isset($allocation) && (int) $allocation->medicine_id === (int) $medicineId
            : $batches->contains('id', $previousInventory);
    @endphp
    <option value="{{ $representative->id }}" data-unit="{{ $medicine->unit }}"
            data-pack-type="{{ $medicine->pack_type }}" data-pieces-per-pack="{{ $medicine->pieces_per_pack }}"
            @selected($selected)>
        {{ $medicine->medicine_name }}{{ $medicine->dosage ? ' - '.$medicine->dosage : '' }}
        ({{ $available }} available now)
    </option>
@endforeach
