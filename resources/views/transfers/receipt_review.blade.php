@extends('layouts.app')

@section('title', 'Transfer Receipt Review')

@section('content')
<section style="max-width:44rem;padding:clamp(1rem,4vw,2rem);border-radius:1rem;background:#fff;">
    <a href="{{ route('transfers.index', ['receipt_review' => 1]) }}" style="color:#0f5c4b;text-decoration:underline;">Receipt reviews</a>
    <h1 style="font-size:1.5rem;font-weight:700;margin:.75rem 0;">Review delivery for transfer #{{ $transfer->id }}</h1>
    <p>{{ $transfer->medicine?->medicine_name ?? 'Inventory item' }} from {{ $transfer->fromHealthUnit?->name ?? 'the supplying facility' }}</p>
    <p style="margin:.5rem 0;">Expected quantity {{ $transfer->formatQuantity($transfer->quantity) }}.</p>
    @if($transfer->receiptReview)
        @php($review = $transfer->receiptReview)
        <p>{{ $review->statusLabel() }}</p>
        <p>Verified quantity {{ number_format($review->received_quantity) }} {{ $transfer->base_unit }}.</p>
        @if(!$transfer->auto_completed && !$review->matchesQuantity())
            <p>Posted at receipt {{ number_format(min($review->received_quantity, $review->expected_quantity)) }} {{ $transfer->base_unit }}.</p>
        @endif
        <p>Evidence reference {{ $review->evidence_reference }}</p>
        <p>Reviewed {{ $review->reviewed_at->format('M j, Y g:i A') }}.</p>
        @if($review->resolution)
            <h2 style="font-size:1.15rem;font-weight:700;margin:1rem 0 .35rem;">Resolution</h2>
            <p>{{ $review->resolution->stock_log_id ? 'Stock adjustment log #' . $review->resolution->stock_log_id : 'No additional adjustment required' }}</p>
            <p style="white-space:pre-wrap;overflow-wrap:anywhere;">{{ $review->resolution->reason }}</p>
            <p>Recorded {{ $review->resolution->resolved_at->format('M j, Y g:i A') }} by account #{{ $review->resolution->resolved_by }}.</p>
        @elseif(!$review->matchesQuantity() && session('role') === 'admin')
            @php($canLinkAdjustment = $transfer->auto_completed || $review->received_quantity > $review->expected_quantity)
            <h2 style="font-size:1.15rem;font-weight:700;margin:1rem 0 .35rem;">Resolve discrepancy</h2>
            @if($canLinkAdjustment)
                <p style="margin:.5rem 0;">Verify physical stock and the receiving evidence. If inventory needs correcting, post an inventory adjustment before linking its stock log.</p>
            @else
                <p style="margin:.5rem 0;">The shortage is reflected in receiving stock. Reconcile the difference with the supplying facility and record your evidence.</p>
            @endif
            <form action="{{ route('transfers.receipt_review.resolve', $transfer->id) }}" method="post" style="display:grid;gap:.65rem;max-width:26rem;">
                @csrf
                <label for="receipt-decision">Resolution</label>
                <select id="receipt-decision" name="decision" required>
                    <option value="">Choose a resolution</option>
                    @if($canLinkAdjustment)
                        <option value="adjustment" @selected(old('decision') === 'adjustment')>Link a posted inventory adjustment</option>
                    @endif
                    <option value="no_adjustment" @selected(old('decision') === 'no_adjustment')>Document why no additional adjustment is required</option>
                </select>
                @error('decision')<p role="alert" style="color:#991b1b;">{{ $message }}</p>@enderror
                @if($canLinkAdjustment)
                    <label for="receipt-stock-log">Adjustment stock log ID</label>
                    <input id="receipt-stock-log" name="stock_log_id" type="number" min="1" value="{{ old('stock_log_id') }}" aria-describedby="receipt-stock-log-help">
                    <p id="receipt-stock-log-help" style="color:#475569;">Required when linking an adjustment. In Stock Logs, open the adjustment or use its CSV ID.</p>
                    @error('stock_log_id')<p role="alert" style="color:#991b1b;">{{ $message }}</p>@enderror
                @endif
                <label for="receipt-reason">Resolution evidence and reason</label>
                <textarea id="receipt-reason" name="reason" maxlength="500" rows="3" required>{{ old('reason') }}</textarea>
                @error('reason')<p role="alert" style="color:#991b1b;">{{ $message }}</p>@enderror
                <button type="submit" style="background:#0f5c4b;color:#fff;padding:.65rem 1rem;border-radius:.5rem;justify-self:start;">Save resolution</button>
            </form>
        @endif
    @else
        <p style="margin:.75rem 0;">Check what arrived using receiving records or other documented evidence. This review records your finding and leaves inventory balances as recorded.</p>
        <form action="{{ route('transfers.receipt_review.store', $transfer->id) }}" method="post" style="display:grid;gap:.65rem;max-width:26rem;">
            @csrf
            <label for="receipt-quantity">Quantity received in {{ $transfer->base_unit ?: 'base units' }}</label>
            <input id="receipt-quantity" name="received_quantity" type="number" min="0" max="2147483647" required value="{{ old('received_quantity') }}">
            @error('received_quantity')<p role="alert" style="color:#991b1b;">{{ $message }}</p>@enderror
            <label for="receipt-evidence">Receiving document or evidence reference</label>
            <input id="receipt-evidence" name="evidence_reference" maxlength="120" required value="{{ old('evidence_reference') }}" aria-describedby="receipt-evidence-help">
            <p id="receipt-evidence-help" style="color:#475569;">Use a reference number or record location. Keep personal details out of this field.</p>
            @error('evidence_reference')<p role="alert" style="color:#991b1b;">{{ $message }}</p>@enderror
            <button type="submit" style="background:#0f5c4b;color:#fff;padding:.65rem 1rem;border-radius:.5rem;justify-self:start;">Save receipt review</button>
        </form>
    @endif
</section>
@endsection
