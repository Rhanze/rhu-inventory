@php
    $receiptDialogId = 'transfer-receipt-'.$transfer->id.($receiptContext ?? '');
    $retryingReceipt = (int) old('receipt_for') === (int) $transfer->id;
    $receiptError = $retryingReceipt ? $errors->first() : null;
@endphp

@once
    <style>
        .transfer-receipt-dialog {
            position: fixed;
            inset: 0;
            width: min(30rem, calc(100% - 2rem));
            max-height: calc(100dvh - 2rem);
            margin: auto;
            padding: clamp(1rem, 4vw, 1.5rem);
            overflow: auto;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 20px 55px rgba(15, 23, 42, .22);
            color: #0f172a;
            text-align: left;
            white-space: normal;
            overflow-wrap: anywhere;
            cursor: default;
        }
        .transfer-receipt-dialog::backdrop { background: rgba(15, 23, 42, .48); }
        .transfer-receipt-dialog form, .transfer-receipt-field { display: grid; grid-template-columns: minmax(0, 1fr); }
        .transfer-receipt-dialog form { gap: 1rem; margin: 0; }
        .transfer-receipt-field { gap: .35rem; }
        .transfer-receipt-dialog h3 { margin: 0 0 .3rem; font-size: 1.25rem; font-weight: 700; }
        .transfer-receipt-summary, .transfer-receipt-hint { margin: 0; color: #475569; font-size: .9375rem; }
        .transfer-receipt-field label { font-weight: 600; }
        .transfer-receipt-field input {
            width: 100%;
            min-width: 0;
            min-height: 44px;
            padding: .6rem .75rem;
            border: 1px solid #94a3b8;
            border-radius: 8px;
            background: #fff;
            color: #0f172a;
            font: inherit;
        }
        .transfer-receipt-field input[aria-invalid="true"] { border-color: #b91c1c; }
        .transfer-receipt-error { margin: 0; padding: .65rem .8rem; border-radius: 8px; background: #fef2f2; color: #991b1b; }
        .transfer-receipt-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: .6rem; }
        .transfer-receipt-primary, .transfer-receipt-dismiss {
            min-height: 40px;
            padding: .45rem .75rem;
            border: 1px solid transparent;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        .transfer-receipt-primary { background: #0f5c4b; color: #fff; }
        .transfer-receipt-primary:hover { background: #0b483b; }
        .transfer-receipt-dismiss { border-color: #cbd5e1; background: #fff; color: #334155; }
        .transfer-receipt-dismiss:hover { background: #f1f5f9; }
        .transfer-receipt-dialog button { min-height: 44px; }
        .transfer-receipt-dialog :is(button, input):focus-visible, .transfer-receipt-primary:focus-visible {
            outline: 2px solid #0f766e;
            outline-offset: 2px;
        }
    </style>
@endonce

<button type="button" class="transfer-receipt-primary" aria-haspopup="dialog" aria-controls="{{ $receiptDialogId }}"
        onclick="document.getElementById(this.getAttribute('aria-controls')).showModal()">
    Record receipt
</button>

<dialog id="{{ $receiptDialogId }}" class="transfer-receipt-dialog"
        aria-labelledby="{{ $receiptDialogId }}-title" aria-describedby="{{ $receiptDialogId }}-summary">
    <form method="POST" action="{{ route('transfers.receive', $transfer->id) }}">
        @csrf
        <input type="hidden" name="receipt_for" value="{{ $transfer->id }}">
        <div>
            <h3 id="{{ $receiptDialogId }}-title">Record receipt</h3>
            <p id="{{ $receiptDialogId }}-summary" class="transfer-receipt-summary">
                {{ $transfer->medicine_label }} · Transfer #{{ $transfer->id }}
            </p>
        </div>

        @if($receiptError)
            <p role="alert" class="transfer-receipt-error">{{ $receiptError }}</p>
        @endif

        <div class="transfer-receipt-field">
            <label for="{{ $receiptDialogId }}-count">Count received ({{ $transfer->base_unit }})</label>
            <input id="{{ $receiptDialogId }}-count" name="received_quantity" type="number" min="0" max="2147483647" required autofocus
                   aria-describedby="{{ $receiptDialogId }}-expected" @if($retryingReceipt && $errors->has('received_quantity')) aria-invalid="true" @endif
                   value="{{ $retryingReceipt ? old('received_quantity') : null }}">
            <p id="{{ $receiptDialogId }}-expected" class="transfer-receipt-hint">Expected quantity {{ number_format($transfer->quantity) }}</p>
        </div>

        <div class="transfer-receipt-field">
            <label for="{{ $receiptDialogId }}-evidence">Evidence reference</label>
            <input id="{{ $receiptDialogId }}-evidence" name="evidence_reference" type="text" maxlength="120"
                   aria-describedby="{{ $receiptDialogId }}-evidence-hint" @if($retryingReceipt && $errors->has('evidence_reference')) aria-invalid="true" @endif
                   value="{{ $retryingReceipt ? old('evidence_reference') : null }}">
            <p id="{{ $receiptDialogId }}-evidence-hint" class="transfer-receipt-hint">Required when the count differs from the expected quantity.</p>
        </div>

        <p class="transfer-receipt-hint">Recording the receipt updates stock using the count entered.</p>
        <div class="transfer-receipt-actions">
            <button type="button" class="transfer-receipt-dismiss" onclick="this.closest('dialog').close()">Close</button>
            <button type="submit" class="transfer-receipt-primary">Record receipt</button>
        </div>
    </form>
</dialog>

@if($receiptError)
    <script>
        if (!document.querySelector('.transfer-receipt-dialog[open]')) {
            document.getElementById({{ Illuminate\Support\Js::from($receiptDialogId) }}).showModal();
        }
    </script>
@endif
