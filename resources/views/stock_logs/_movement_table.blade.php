<div class="inventory-history-scroll" tabindex="0" role="region" aria-label="Historical inventory transactions">
    <table>
        <thead><tr><th>Transaction date</th><th>Medicine</th><th>Transaction / reference</th><th>Quantity</th><th>Status</th><th>Batch</th><th>Facility / destination</th><th>Recorded by</th><th>Notes</th></tr></thead>
        <tbody>
        @forelse($movements as $movement)
            <tr id="{{ $movement->event_source === 'log' ? 'stock-log-' : 'stock-movement-' }}{{ $movement->event_id }}">
                <td>{{ \Carbon\Carbon::parse($movement->stock_effective_date ?? $movement->date_added)->format('M d, Y') }}
                    @if($movement->recorded_at)<small>Entered {{ \Carbon\Carbon::parse($movement->recorded_at)->format('M d, Y g:i A') }}</small>@endif
                </td>
                <td><strong>{{ $movement->medicine_name ?? 'Medicine no longer available' }}</strong>@if($movement->dosage)<small>{{ $movement->dosage }}</small>@endif</td>
                <td>{{ $movement->event_label }}<small>{{ $movement->mechanism }}</small></td>
                <td class="history-count">{{ $movement->direction === 'release' ? '-' : ($movement->direction === 'addition' ? '+' : '') }}{{ number_format($movement->quantity_added) }} {{ $movement->unit ?: 'units' }}</td>
                <td><span class="inventory-history-status">{{ $movement->status_label }}</span></td>
                <td>{{ $movement->batch_no ?: 'Not recorded' }}</td>
                <td>{{ $movement->facility_name ?? 'Not recorded' }}
                    @if($movement->source_name)<small>From: {{ $movement->source_name }}</small>@endif
                    @if($movement->destination_name)<small>To: {{ $movement->destination_name }}</small>@endif
                    @if($movement->storage_location)<small>Storage: {{ $movement->storage_location }}</small>@endif
                </td>
                <td>{{ $movement->actor_name ?: ($movement->added_by ? 'User #'.$movement->added_by : 'Not recorded') }}</td>
                <td>{{ $movement->remarks ?: '-' }}
                    @if($movement->method)<small>{{ $movement->method }}</small>@endif
                    @if($movement->event_source === 'ledger')<small>Available change: {{ $movement->available_delta > 0 ? '+' : '' }}{{ number_format($movement->available_delta) }}; reserved change: {{ $movement->allocated_delta > 0 ? '+' : '' }}{{ number_format($movement->allocated_delta) }}</small>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9">No stock movements match these filters.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
