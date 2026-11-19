@php($selectedSource = old('stock_source', $record->stock_source ?? 'rhu'))
@foreach(['rhu' => $inventories, 'allocation' => $allocationInventories] as $source => $sourceInventories)
    @foreach($sourceInventories as $inventory)
        @php($expiry = $inventory->expiration_date?->format('M d, Y'))
        <option value="{{ $inventory->id }}" data-source="{{ $source }}"
                data-prescription="{{ $inventory->medicine?->requires_prescription ? 1 : 0 }}"
                data-unit="{{ $inventory->medicine->unit ?? '' }}"
                data-pack-type="{{ $inventory->pack_type ?? $inventory->medicine->pack_type ?? '' }}"
                data-pieces-per-pack="{{ $inventory->pieces_per_pack ?? $inventory->medicine->pieces_per_pack ?? '' }}"
                data-batch="{{ $inventory->batch_no ?? '' }}" data-expiry="{{ $expiry ?? '' }}"
                data-stock="{{ $inventory->available_stock }}"
                @disabled($source !== $selectedSource) @if($source !== $selectedSource) hidden @endif
                @selected($source === $selectedSource && ($selectedInventoryId ?? old('inventory_id')) == $inventory->id)>
            [{{ $inventory->batch_no ?? 'NO BATCH' }}]
            | {{ $inventory->medicine->medicine_name ?? 'N/A' }}{{ $inventory->medicine?->dosage ? ' - '.$inventory->medicine->dosage : '' }}
            (Available: {{ $inventory->available_stock }}) @if($expiry) · Exp: {{ $expiry }} @endif
        </option>
    @endforeach
@endforeach
