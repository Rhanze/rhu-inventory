<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Batch Inventory Report</title>
    <style>
        body { margin: 1rem; color: #111827; font: 11pt/1.45 Arial, sans-serif; }
        h1 { margin: 0; font-size: 1.45rem; }
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: .35rem .45rem; border-bottom: 1px solid #94a3b8; text-align: start; overflow-wrap: anywhere; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        .quantity { text-align: end; }
        .table-scroll { overflow-x: auto; }
        @media print { body { margin: 0; } .print-controls { display: none; } .table-scroll { overflow: visible; } }
    </style>
</head>
<body>
    <h1>Batch Inventory Report</h1>
    <p>All batches matching the inventory filters. Generated {{ now()->format('M j, Y g:i A T') }}.</p>
    <p class="print-controls"><button type="button" onclick="window.print()">Print report</button> <a href="{{ route('inventory.index', request()->except('print', 'page')) }}">Return to inventory</a></p>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th scope="col">Item</th><th scope="col">Facility</th><th scope="col">Batch</th><th scope="col">Expiry</th>
                    <th scope="col" class="quantity">Book balance</th><th scope="col" class="quantity">Usable stock</th><th scope="col" class="quantity">Allocated</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inventories as $batch)
                    <tr>
                        <td>{{ $batch->medicine?->full_label ?? 'Archived item' }}</td>
                        <td>{{ $batch->healthUnit?->name }}</td>
                        <td>{{ $batch->batch_no ?: 'Unrecorded' }}</td>
                        <td>{{ $batch->expiration_date?->format('Y-m-d') ?? 'Unrecorded' }}</td>
                        <td class="quantity">{{ number_format($batch->available_stock) }}</td>
                        <td class="quantity">{{ number_format($batch->medicine && (! $batch->expiration_date || $batch->expiration_date->gte(today())) ? $batch->available_stock : 0) }}</td>
                        <td class="quantity">{{ number_format($batch->allocated_stock) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">No batches match the filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
