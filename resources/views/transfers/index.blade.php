@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
        display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;
    ">
        <div>
            <p style="font-size:1.0625rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">
                {{ $isPHO ? 'Distribution' : 'Transfers' }}
            </p>
            <h2 style="font-size:1.5rem; font-weight:700; color:#0f172a; margin:0;">
                {{ $isPHO ? 'All Transfer Activity' : 'Stock Transfers' }}
            </h2>
        </div>

        <div style="display:flex; gap:8px;flex-wrap:wrap;">
            @if($isPHO)
                <a href="{{ route('transfers.distribute') }}" style="
                    padding:10px 18px; border-radius:10px; background:#42ad8a; color:white;
                    font-size:1.0625rem; font-weight:600; text-decoration:none;">
                    Distribute
                </a>
            @else
                <a href="{{ route('transfers.create') }}" style="
                    padding:10px 18px; border-radius:10px; background:#42ad8a; color:white;
                    font-size:1.0625rem; font-weight:600; text-decoration:none;">
                    + Transfer
                </a>
            @endif
            @include('imports._link', ['section' => session('health_unit_id') ? 'transfers' : 'distribution'])
        </div>
    </div>

    @if(session('success'))
        <div style="background:#eaf3de; border:1px solid #c0dd97; border-radius:12px; padding:14px 20px; color:#3b6d11; font-size:1.0625rem;">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div style="background:#fcebeb; border:1px solid #f7c1c1; border-radius:12px; padding:14px 20px; color:#a32d2d; font-size:1.0625rem;">
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <p role="alert" style="margin:0; background:#fcebeb; border:1px solid #f7c1c1; border-radius:12px; padding:14px 20px; color:#a32d2d;">
            {{ $errors->first() }}
        </p>
    @endif

    <div style="background:rgba(255,255,255,0.95); border-radius:20px; border:1px solid rgba(66,173,138,0.15); box-shadow:0 8px 20px rgba(0,0,0,0.06); overflow:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:1.0625rem; min-width:1150px;">
            <thead>
                <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                    <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:600; text-transform:uppercase; color:#475569;">#</th>
                    <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:600; text-transform:uppercase; color:#475569;">From → To</th>
                    <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:600; text-transform:uppercase; color:#475569;">Medicine</th>
                    <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:600; text-transform:uppercase; color:#475569;">
                        Batch # <span style="font-weight:400; text-transform:none; color:#94a3b8;">(source only)</span>
                    </th>
                    <th style="padding:14px 16px; text-align:center; font-size:1.0625rem; font-weight:600; text-transform:uppercase; color:#475569;">Qty</th>
                    <th style="padding:14px 16px; text-align:center; font-size:1.0625rem; font-weight:600; text-transform:uppercase; color:#475569;">Type</th>
                    <th style="padding:14px 16px; text-align:center; font-size:1.0625rem; font-weight:600; text-transform:uppercase; color:#475569;">Status</th>
                    <th style="padding:14px 16px; text-align:center; font-size:1.0625rem; font-weight:600; text-transform:uppercase; color:#475569;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $t)
                    @php
                        $huId = session('health_unit_id');
                        $statusColors = [
                            'pending'   => 'background:#FEFCE8; color:#854d0e; border:1px solid #fde047;',
                            'accepted'  => 'background:#EFF6FF; color:#1e40af; border:1px solid #93c5fd;',
                            'approved'  => 'background:#EFF6FF; color:#1e40af; border:1px solid #93c5fd;',
                            'rejected'  => 'background:#FEF2F2; color:#991b1b; border:1px solid #fca5a5;',
                            'received'  => 'background:#E8F7F4; color:#0f5c4b; border:1px solid #42ad8a;',
                            'completed' => 'background:#E8F7F4; color:#0f5c4b; border:1px solid #42ad8a;',
                            'cancelled' => 'background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1;',
                        ];
                        $statusStyle = $statusColors[$t->status] ?? $statusColors['pending'];

                        $isPhoAdmin = $isPHO && session('role') === 'admin';
                        $canApprove = $isPhoAdmin && $t->isAwaitingPhoReview();
                        $canAccept = !$isPHO
                                     && $t->from_health_unit_id == $huId
                                     && $t->isAwaitingSourceResponse();

                        $canReceive = !$isPHO
                                      && $t->to_health_unit_id == $huId
                                      && $t->status === 'accepted'
                                      && $t->inventory_id !== null;

                        $canCancel = ($isPhoAdmin || (!$isPHO && $t->to_health_unit_id == $huId))
                                     && in_array($t->status, ['pending', 'accepted']);

                        $canSeeBatch = $isPHO || $t->from_health_unit_id == $huId;
                        $statusLabel = match (true) {
                            $t->isAwaitingPhoReview() => 'Awaiting PHO approval',
                            $t->isAwaitingSourceResponse() => 'Awaiting source acceptance',
                            $t->pho_review_status === \App\Models\TransferRequest::PHO_REVIEW_REJECTED => 'Rejected by PHO',
                            default => ucfirst($t->status),
                        };
                    @endphp
                    <tr id="transfer-{{ $t->id }}" style="border-bottom:1px solid #f1f5f9;scroll-margin-top:24px;">
                        <td style="padding:14px 16px; color:#94a3b8;">{{ $t->id }}</td>

                        <td style="padding:14px 16px; color:#0f172a;">
                            <strong>{{ $t->fromHealthUnit->name ?? 'N/A' }}</strong>
                            <span style="color:#64748b;"> → </span>
                            <strong>{{ $t->toHealthUnit->name ?? 'N/A' }}</strong>
                        </td>

                        {{-- Medicine cell with snapshot --}}
                        <td style="padding:14px 16px; color:#0f172a;">
                            <div style="font-weight:600;">
                                {{ $t->medicine->medicine_name ?? 'N/A' }}
                                @if($t->dosage)
                                    <span>{{ $t->dosage }}</span>
                                @endif
                                @if($t->dosage_form)
                                    <span style="color:#64748b; font-weight:400;"> ({{ $t->dosage_form }})</span>
                                @endif
                            </div>
                            <div style="font-size:1.0625rem; color:#64748b; margin-top:2px;">
                                @if($t->category)
                                    {{ $t->category_label }}
                                @endif
                                @if($t->pack_type && $t->pieces_per_pack)
                                    · {{ $t->pieces_per_pack }} pcs / {{ $t->pack_type }}
                                @endif
                            </div>
                            @if(filled($t->notes))
                                <p style="margin:6px 0 0; color:#475569; overflow-wrap:anywhere;"><strong>Notes</strong> {{ $t->notes }}</p>
                            @endif
                        </td>

                        <td style="padding:14px 16px; color:#0f172a;">
                            @if($canSeeBatch && $t->batch_no)
                                <span style="font-family:monospace; font-size:1.0625rem; background:#f1f5f9; padding:3px 8px; border-radius:6px;">
                                    {{ $t->batch_no }}
                                </span>
                            @elseif($canSeeBatch)
                                <span style="color:#94a3b8;">—</span>
                            @else
                                <span style="color:#cbd5e1; font-size:1.0625rem;" title="Only the source RHU can view this batch">🔒</span>
                            @endif
                        </td>

                        <td style="padding:14px 16px; text-align:center; font-weight:600;">
                            <div>
                                @if($t->pack_type && $t->pieces_per_pack)
                                    {{ $t->formatQuantity($t->quantity) }}
                                @else
                                    {{ $t->quantity }} {{ $t->quantity == 1 ? 'unit' : 'units' }}
                                @endif
                            </div>
                            <div style="font-size:1.0625rem; color:#94a3b8; font-weight:400;">
                                {{ $t->quantity }} pieces
                            </div>
                        </td>

                        <td style="padding:14px 16px; text-align:center; font-size:1.0625rem;">
                            <span style="padding:3px 10px; border-radius:999px; background:#f1f5f9; color:#334155; white-space:nowrap;">
                                {{ $t->initiator_label }}
                            </span>
                        </td>

                        <td style="padding:14px 16px; text-align:center;">
                            <span style="padding:4px 12px; border-radius:999px; font-size:1.0625rem; font-weight:600; {{ $statusStyle }} white-space:nowrap;">
                                {{ $statusLabel }}
                                @if($t->auto_completed)
                                    <span title="Auto-completed after 7 days" style="font-size:1.0625rem;"> ⚙</span>
                                @endif
                            </span>
                            @if($isPhoAdmin && $t->pho_reviewed_at)
                                <p style="margin:6px 0 0; color:#475569;">
                                    Reviewed by {{ $t->phoReviewer?->name ?? 'PHO' }}<br>
                                    {{ $t->pho_reviewed_at->format('M j, Y g:i A') }}
                                </p>
                            @endif
                            @if($t->status === 'rejected' && filled($t->rejection_reason))
                                <p style="margin:6px 0 0; color:#475569; overflow-wrap:anywhere;">{{ $t->rejection_reason }}</p>
                            @endif
                        </td>

                        {{-- ACTIONS — text buttons, single line --}}
                        <td style="padding:14px 16px; text-align:center; white-space:nowrap;">
                            <div style="display:inline-flex; gap:6px; justify-content:center; align-items:center; flex-wrap:nowrap;">

                                @if($canApprove || $canAccept)
                                    <form method="POST" action="{{ route($canApprove ? 'transfers.pho_approve' : 'transfers.accept', $t->id) }}"
                                          style="display:inline; margin:0;">
                                        @csrf
                                        <button type="submit"
                                                style="
                                                    padding:6px 12px; border-radius:8px;
                                                    background:#42ad8a; color:#ffffff; border:none;
                                                    font-size:1.0625rem; font-weight:600; cursor:pointer;
                                                    white-space:nowrap; line-height:1.2;
                                                    transition: background 0.2s;
                                                "
                                                onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                                            {{ $canApprove ? 'Approve' : 'Accept' }}
                                        </button>
                                    </form>

                                    <button type="button" data-reject-url="{{ route($canApprove ? 'transfers.pho_reject' : 'transfers.reject', $t->id) }}" onclick="showReject(this)"
                                            style="
                                                padding:6px 12px; border-radius:8px;
                                                background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;
                                                font-size:1.0625rem; font-weight:600; cursor:pointer;
                                                white-space:nowrap; line-height:1.2;
                                                transition: background 0.2s;
                                            "
                                            onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
                                        Reject
                                    </button>
                                @endif

                                @if($canReceive)
                                    <form method="POST" action="{{ route('transfers.receive', $t->id) }}"
                                          style="display:inline; margin:0;"
                                          onsubmit="return confirm('Confirm you have physically received these items? Stock will move immediately.');">
                                        @csrf
                                        <button type="submit"
                                                style="
                                                    padding:6px 12px; border-radius:8px;
                                                    background:#0f5c4b; color:#ffffff; border:none;
                                                    font-size:1.0625rem; font-weight:600; cursor:pointer;
                                                    white-space:nowrap; line-height:1.2;
                                                    transition: background 0.2s;
                                                "
                                                onmouseover="this.style.background='#0a4437'" onmouseout="this.style.background='#0f5c4b'">
                                            Receive
                                        </button>
                                    </form>
                                @endif

                                @if($canCancel)
                                    <form method="POST" action="{{ route('transfers.cancel', $t->id) }}"
                                          style="display:inline; margin:0;"
                                          onsubmit="return confirm('Cancel this transfer?');">
                                        @csrf
                                        <button type="submit"
                                                style="
                                                    padding:6px 12px; border-radius:8px;
                                                    background:#f1f5f9; color:#475569; border:1px solid #cbd5e1;
                                                    font-size:1.0625rem; font-weight:600; cursor:pointer;
                                                    white-space:nowrap; line-height:1.2;
                                                    transition: background 0.2s;
                                                "
                                                onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                                            Cancel
                                        </button>
                                    </form>
                                @endif

                                @if(!$canApprove && !$canAccept && !$canReceive && !$canCancel)
                                    <span style="color:#cbd5e1; font-size:1.0625rem;">—</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="padding:40px; text-align:center; color:#94a3b8;">
                            No transfer requests found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $transfers->links() }}
</div>

{{-- Reject Modal --}}
<style>
    #reject-modal::backdrop { background:rgba(0,0,0,0.5); }
</style>
<dialog id="reject-modal" aria-labelledby="reject-modal-title" style="background:white; border:1px solid #e2e8f0; border-radius:20px; padding:24px; width:min(440px, calc(100% - 32px));">
    <h3 id="reject-modal-title" style="margin:0 0 12px 0; font-size:1.125rem; color:#0f172a;">Reject Transfer Request</h3>
    <form id="reject-form" method="POST">
        @csrf
        <label for="reject-reason" style="display:block; font-size:1.0625rem; font-weight:600; color:#0f172a; margin-bottom:6px;">Reason (optional)</label>
        <textarea id="reject-reason" name="rejection_reason" rows="3" maxlength="500"
                  style="width:100%; padding:12px; border:2px solid #bec6cf; border-radius:12px; font-size:1.0625rem; box-sizing:border-box;"></textarea>
        <div style="display:flex; gap:10px; margin-top:16px;">
            <button type="submit" style="flex:1; padding:12px; background:#dc2626; color:white; border:none; border-radius:12px; font-weight:600; font-size:1.0625rem; cursor:pointer;">Reject</button>
            <button type="button" onclick="this.closest('dialog').close()"
                    style="flex:1; padding:12px; background:white; color:#334155; border:1px solid #e2e8f0; border-radius:12px; font-weight:600; font-size:1.0625rem; cursor:pointer;">Cancel</button>
        </div>
    </form>
</dialog>

<script>
function showReject(button) {
    const form = document.getElementById('reject-form');
    form.reset();
    form.action = button.dataset.rejectUrl;
    document.getElementById('reject-modal').showModal();
}
</script>
@endsection
