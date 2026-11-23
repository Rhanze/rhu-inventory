@php
    $selectedSource = old('stock_source', $record->stock_source ?? 'rhu');
    $selectedMedicine = old('medicine_id', $record->medicine_id ?? null);
@endphp
@foreach(['rhu' => $inventories, 'allocation' => $allocationInventories] as $source => $sourceInventories)
    @php
        $groups = $sourceInventories->filter(fn ($item) => $item->medicine)->groupBy('medicine_id');
        if (isset($record) && $source === ($record->stock_source ?: 'rhu') && !$groups->has($record->medicine_id)) {
            $groups->put($record->medicine_id, collect());
        }
    @endphp
    @foreach($groups as $medicineId => $batches)
        @php
            $medicine = $batches->first()?->medicine ?? $record->medicine ?? null;
            $availableToday = $batches->filter(fn ($batch) => !$batch->expiration_date || $batch->expiration_date->gte(today()))->sum('available_stock');
        @endphp
        <option value="{{ $medicineId }}" data-source="{{ $source }}"
                data-prescription="{{ $medicine?->requires_prescription ? 1 : 0 }}"
                data-unit="{{ $medicine?->unit ?? '' }}"
                data-pack-type="{{ $medicine?->pack_type ?? '' }}"
                data-pieces-per-pack="{{ $medicine?->pieces_per_pack ?? '' }}"
                @disabled($source !== $selectedSource) @if($source !== $selectedSource) hidden @endif
                @selected($source === $selectedSource && $selectedMedicine == $medicineId)>
            {{ $medicine?->medicine_name ?? 'Recorded medicine' }}{{ $medicine?->dosage ? ' - '.$medicine->dosage : '' }}
            ({{ $availableToday }} usable today)
        </option>
    @endforeach
@endforeach
