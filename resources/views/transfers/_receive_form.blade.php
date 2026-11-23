@php($retryingReceipt = (int) old('receipt_for') === (int) $transfer->id)
<form method="POST" action="{{ route('transfers.receive', $transfer->id) }}"
      style="display:grid; gap:.4rem; margin:0; text-align:left; min-width:13rem;"
      onsubmit="return confirmDialog(event, 'Post the counted receipt and update stock?');">
    @csrf
    <input type="hidden" name="receipt_for" value="{{ $transfer->id }}">
    @if(($showReceiptErrors ?? false) && $retryingReceipt && $errors->any())
        <p role="alert" style="margin:0; color:#991b1b; white-space:normal;">{{ $errors->first() }}</p>
    @endif
    <label for="receipt-count-{{ $transfer->id }}">Count received ({{ $transfer->base_unit }})</label>
    <input id="receipt-count-{{ $transfer->id }}" name="received_quantity" type="number" min="0" max="2147483647" required
           value="{{ $retryingReceipt ? old('received_quantity') : null }}">
    <label for="receipt-evidence-{{ $transfer->id }}">Evidence reference if count differs</label>
    <input id="receipt-evidence-{{ $transfer->id }}" name="evidence_reference" type="text" maxlength="120"
           value="{{ $retryingReceipt ? old('evidence_reference') : null }}">
    <button type="submit" style="padding:6px 12px; border-radius:8px; background:#0f5c4b; color:#fff; border:none; font-weight:600; cursor:pointer;">
        Record receipt
    </button>
</form>
