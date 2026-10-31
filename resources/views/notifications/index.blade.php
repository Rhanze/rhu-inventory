@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header Card with tabs beside title + actions --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
        display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;
    ">
        <div style="display:flex; align-items:center; gap:24px; flex-wrap:wrap;">
            <div>
                <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">Notifications</p>
                <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0; display:flex; align-items:center; gap:8px;">
                    Notifications
                    @if($unreadCount > 0)
                        <span style="font-size:14px; font-weight:600; background:#dc2626; color:white; padding:2px 12px; border-radius:999px;">
                            {{ $unreadCount }} unread
                        </span>
                    @endif
                </h2>
            </div>

            <div style="background: #f1f5f9; border-radius: 12px; padding: 4px; display: inline-flex; gap: 4px;">
                @php
                    $tabs = [
                        'active'   => ['label' => 'Active',   'count' => $unreadCount],
                        'archived' => ['label' => 'Archived', 'count' => $archivedCount],
                        'all'      => ['label' => 'All',      'count' => null],
                    ];
                @endphp
                @foreach($tabs as $key => $tab)
                    @php $isActive = $view === $key; @endphp
                    <a href="{{ route('notifications.index', ['view' => $key]) }}" style="
                        display: inline-flex; align-items: center; gap: 8px;
                        padding: 8px 18px; border-radius: 9px;
                        font-size: 14px; font-weight: 600; text-decoration: none;
                        @if($isActive) background: #42ad8a; color: #ffffff; box-shadow: 0 2px 6px rgba(66,173,138,0.25);
                        @else background: transparent; color: #475569; @endif
                    "
                    @if(!$isActive)
                        onmouseover="this.style.background='rgba(255,255,255,0.7)'; this.style.color='#0f5c4b';"
                        onmouseout="this.style.background='transparent'; this.style.color='#475569';"
                    @endif
                    >
                        @if($key === 'archived')
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/>
                            </svg>
                        @elseif($key === 'active')
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                            </svg>
                        @else
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
                                <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                            </svg>
                        @endif
                        {{ $tab['label'] }}
                        @if($key === 'archived' && $archivedCount > 0)
                            <span style="
                                font-size:11px; font-weight:700;
                                @if($isActive) background:rgba(255,255,255,0.25); color:white;
                                @else background:#e2e8f0; color:#475569; @endif
                                padding:1px 8px; border-radius:999px;
                            ">{{ $archivedCount }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            @if($unreadCount > 0)
                <button onclick="markAllRead()" style="
                    font-size:14px; font-weight:600; padding:8px 16px; border-radius:10px;
                    background:#42ad8a; color:white; border:none; cursor:pointer;
                " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                    Mark All Read
                </button>
            @endif

            @if($view === 'archived' && $notifications->count() > 0)
                <button onclick="deleteAllArchived()" style="
                    font-size:14px; font-weight:600; padding:8px 16px; border-radius:10px;
                    background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; cursor:pointer;
                " onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
                    🗑️ Delete All Archived
                </button>
            @elseif($notifications->count() > 0)
                <button onclick="deleteAllNotifications()" style="
                    font-size:14px; font-weight:600; padding:8px 16px; border-radius:10px;
                    background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; cursor:pointer;
                " onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
                    🗑️ Delete All
                </button>
            @endif

            <a href="{{ route('dashboard') }}" style="
                font-size:14px; font-weight:600; padding:8px 16px; border-radius:10px;
                background:#ffffff; color:#334155; border:1px solid #e2e8f0; text-decoration:none;
            " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'">
                Back
            </a>
        </div>
    </div>

    {{-- Notification List --}}
    <div style="
        background: rgba(255,255,255,0.9);
        border-radius:20px; padding: 0;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        overflow: hidden;
    ">
        @php
            $huId         = session('health_unit_id');
            $isPHOViewer  = is_null($huId);
            $phoId        = $isPHOViewer ? \App\Models\HealthUnit::phoId() : null;
            $viewerUnitId = $isPHOViewer ? $phoId : $huId;
            $declineLabel = $isPHOViewer ? 'Reject' : 'Decline';
            $canApproveRequests = in_array(session('role'), ['admin', 'staff']) && !is_null($huId);
            $isRecipientViewer = session('user_id');
        @endphp

        @forelse($notifications as $notification)
            @php
                $severityColors = [
                    'info'    => 'background:#E8F7F4; border-left:4px solid #42ad8a;',
                    'warning' => 'background:#FEFCE8; border-left:4px solid #eab308;',
                    'danger'  => 'background:#FEF2F2; border-left:4px solid #dc2626;',
                ];
                $bgStyle = $severityColors[$notification->severity] ?? $severityColors['info'];

                if ($notification->is_read)      $bgStyle = 'background:#f8fafc; border-left:4px solid #94a3b8;';
                if ($notification->archived_at)  $bgStyle = 'background:#f8fafc; border-left:4px solid #cbd5e1; opacity:0.85;';

                $t          = $notification->transfer;
                $isPending  = $t && $t->status === 'pending';
                $isAccepted = $t && $t->status === 'accepted';

                $showAccept = !$notification->archived_at
                              && $t && $isPending
                              && $notification->type === 'transfer_request'
                              && $viewerUnitId !== null
                              && $t->from_health_unit_id == $viewerUnitId;

                $showReceive = !$notification->archived_at
                               && $t && $isAccepted
                               && $notification->type === 'transfer_accepted'
                               && $viewerUnitId !== null
                               && $t->to_health_unit_id == $viewerUnitId;

                $viewerOwnsBatch = $isPHOViewer || ($t && $t->from_health_unit_id == $huId);

                $showTransferInfo = $t
                                    && $t->batch_no
                                    && $viewerOwnsBatch
                                    && in_array($notification->type, [
                                        'transfer_accepted',
                                        'transfer_received',
                                        'transfer_auto_completed',
                                        'pho_directive',
                                    ]);

                // ═══ Supply request approve/reject controls (RHU admin/staff only) ═══
                $showSupplyControls = $canApproveRequests
                                    && $notification->type === 'supply_request_pending'
                                    && $notification->allocation_id
                                    && !$notification->is_read
                                    && !$notification->archived_at;

                // ═══ Allocation receipt confirmation (recipient only) ═══
                $showAllocationReceipt = $notification->type === 'allocation_to_user'
                                    && $notification->allocation_id
                                    && $notification->user_id == $isRecipientViewer
                                    && !$notification->is_read
                                    && !$notification->archived_at;
            @endphp

            <div id="notification-{{ $notification->id }}" style="
                padding: 16px 24px; border-bottom: 1px solid #f1f5f9;
                transition: background 0.2s, opacity 0.3s ease;
                {{ $bgStyle }}
                @if(!$notification->is_read && !$notification->archived_at) cursor:pointer; @endif
            " onclick="{{ (!$notification->is_read && !$notification->archived_at) ? 'markRead(' . $notification->id . ')' : '' }}">
                <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px;">
                    <div style="flex:1;">
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <span style="font-weight:600; color:#0f172a;">{{ $notification->title }}</span>
                            @if(!$notification->is_read && !$notification->archived_at)
                                <span style="font-size:10px; font-weight:600; background:#42ad8a; color:white; padding:1px 8px; border-radius:999px;">NEW</span>
                            @endif
                            @if($notification->archived_at)
                                <span style="font-size:10px; font-weight:600; background:#cbd5e1; color:#475569; padding:1px 8px; border-radius:999px;">📦 Archived</span>
                            @endif
                            <span style="font-size:12px; color:#94a3b8;">{{ $notification->created_at->diffForHumans() }}</span>
                        </div>
                        <p style="margin:4px 0 0 0; color:#334155; font-size:14px;">{{ $notification->message }}</p>

                        {{-- Batch / pack info — visible only to the source RHU (or PHO) --}}
                        @if($showTransferInfo)
                            <div style="margin-top:10px; font-size:13px; color:#0f5c4b; background:#E8F7F4; border-radius:8px; padding:8px 12px; display:inline-block;">
                                <strong>Batch:</strong> {{ $t->batch_no }}
                                @if($t->pack_type && $t->pieces_per_pack)
                                    &nbsp;·&nbsp; <strong>Packaging:</strong> {{ $t->pack_type }} · {{ $t->pieces_per_pack }} pcs/pack
                                @endif
                                &nbsp;·&nbsp; <strong>Total:</strong> {{ $t->formatQuantity($t->quantity) }}
                            </div>
                        @endif

                        {{-- Prefer explicit ->link, fall back to inventory --}}
                        @php
                            $targetUrl = $notification->link
                                ?? ($notification->inventory_id
                                    ? route('inventory.index', ['highlight' => $notification->inventory_id])
                                    : null);
                            $targetLabel = $notification->inventory_id ? 'View Inventory →' : 'Open →';
                        @endphp
                        @if($targetUrl)
                            <a href="{{ $targetUrl }}" style="
                                display:inline-block; margin-top:6px; font-size:13px; color:#42ad8a;
                                text-decoration:none; font-weight:500;
                            " onclick="event.stopPropagation();">
                                {{ $targetLabel }}
                            </a>
                        @endif

                        {{-- ═══ NEW — Allocation to me: Confirm Receipt ═══ --}}
                        @if($showAllocationReceipt)
                            <div style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap;" onclick="event.stopPropagation();">
                                <form method="POST"
                                      action="{{ route('allocation.mark_received', $notification->allocation_id) }}"
                                      style="display:inline;"
                                      onsubmit="return confirm('Confirm you have physically received this stock?');">
                                    @csrf
                                    <button type="submit" style="
                                        display:inline-flex; align-items:center; gap:6px;
                                        padding:8px 16px; border-radius:10px;
                                        background:#42ad8a; color:#fff; border:none;
                                        font-size:14px; font-weight:600; cursor:pointer;">
                                        ✓ Confirm Receipt
                                    </button>
                                </form>
                            </div>
                        @endif

                        {{-- ═══ Supply Request: Approve / Reject ═══ --}}
                        @if($showSupplyControls)
                            <div style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap;" onclick="event.stopPropagation();">
                                <form method="POST"
                                      action="{{ route('allocation.approve_supply_request', $notification->allocation_id) }}"
                                      style="display:inline;"
                                      onsubmit="return confirm('Approve this supply request? Stock will be reserved for the midwife.');">
                                    @csrf
                                    <button type="submit" style="
                                        display:inline-flex; align-items:center; gap:6px;
                                        padding:8px 16px; border-radius:10px;
                                        background:#42ad8a; color:#fff; border:none;
                                        font-size:14px; font-weight:600; cursor:pointer;
                                    " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                                        ✓ Approve Request
                                    </button>
                                </form>

                                <button type="button"
                                        onclick="rejectSupplyRequest({{ $notification->allocation_id }})"
                                        style="
                                            display:inline-flex; align-items:center; gap:6px;
                                            padding:8px 16px; border-radius:10px;
                                            background:#fee2e2; color:#991b1b;
                                            border:1px solid #fca5a5; font-size:14px; font-weight:600; cursor:pointer;
                                        " onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
                                        ✗ Reject
                                    </button>
                            </div>
                        @endif

                        {{-- Inline Accept / Decline-Reject / Transfer Received buttons --}}
                        @if($showAccept || $showReceive)
                            <div style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap;" onclick="event.stopPropagation();">
                                @if($showAccept)
                                    <form method="POST" action="{{ route('transfers.accept', $t->id) }}" style="display:inline;"
                                          onsubmit="return confirm('Accept this transfer request? A batch will be auto-reserved (FIFO).');">
                                        @csrf
                                        <button type="submit" style="
                                            padding:8px 16px; border-radius:10px; background:#42ad8a; color:#fff;
                                            border:none; font-size:14px; font-weight:600; cursor:pointer;
                                        " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                                            ✓ Accept Transfer
                                        </button>
                                    </form>

                                    <button type="button"
                                            onclick="openRejectFromNotification({{ $t->id }}, '{{ $declineLabel }}')"
                                            style="
                                                padding:8px 16px; border-radius:10px; background:#fee2e2; color:#991b1b;
                                                border:1px solid #fca5a5; font-size:14px; font-weight:600; cursor:pointer;
                                            " onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
                                        ✗ {{ $declineLabel }}
                                    </button>
                                @endif

                                @if($showReceive)
                                    <form method="POST" action="{{ route('transfers.receive', $t->id) }}" style="display:inline;"
                                          onsubmit="return confirm('Confirm you have physically received these items? Stock will move immediately between RHUs.');">
                                        @csrf
                                        <button type="submit" style="
                                            padding:8px 16px; border-radius:10px; background:#0f5c4b; color:#fff;
                                            border:none; font-size:14px; font-weight:600; cursor:pointer;
                                        " onmouseover="this.style.background='#0b4638'" onmouseout="this.style.background='#0f5c4b'">
                                            📦 Transfer Received
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        @if(!$notification->is_read && !$notification->archived_at)
                            <button onclick="event.stopPropagation(); markRead({{ $notification->id }})" style="
                                font-size:12px; font-weight:600; padding:4px 10px; border-radius:8px;
                                background:#ffffff; color:#334155; border:1px solid #e2e8f0;
                                cursor:pointer; white-space:nowrap;
                            " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'">
                                Mark Read
                            </button>
                        @endif

                        @if($notification->archived_at)
                            <button onclick="event.stopPropagation(); unarchiveNotification({{ $notification->id }})"
                                    title="Move back to Active"
                                    style="
                                        font-size:12px; font-weight:600; padding:4px 10px; border-radius:8px;
                                        background:#E8F7F4; color:#0f5c4b; border:1px solid #42AD8A;
                                        cursor:pointer; white-space:nowrap;
                                        display:inline-flex; align-items:center; gap:4px;
                                    "
                                    onmouseover="this.style.background='#d2efe6'" onmouseout="this.style.background='#E8F7F4'">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="1 4 1 10 7 10"/>
                                    <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
                                </svg>
                                Unarchive
                            </button>
                        @else
                            <button onclick="event.stopPropagation(); archiveNotification({{ $notification->id }})"
                                    title="Archive"
                                    style="
                                        font-size:12px; font-weight:600; padding:4px 10px; border-radius:8px;
                                        background:#f1f5f9; color:#475569; border:1px solid #cbd5e1;
                                        cursor:pointer; white-space:nowrap;
                                        display:inline-flex; align-items:center; gap:4px;
                                    "
                                    onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="21 8 21 21 3 21 3 8"/>
                                    <rect x="1" y="3" width="22" height="5"/>
                                    <line x1="10" y1="12" x2="14" y2="12"/>
                                </svg>
                                Archive
                            </button>
                        @endif

                        <button onclick="event.stopPropagation(); deleteNotification({{ $notification->id }})"
                                title="Delete"
                                style="
                                    font-size:12px; font-weight:600; padding:4px 10px; border-radius:8px;
                                    background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;
                                    cursor:pointer; white-space:nowrap;
                                " onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
                            🗑️
                        </button>
                    </div>
                </div>
            </div>
        @empty
            @php
                $viewerRole   = session('role');
                $viewerUnitId = session('health_unit_id');
                $isMidwife    = $viewerRole === 'midwife';
                $isPHO        = in_array($viewerRole, ['admin', 'staff']) && is_null($viewerUnitId);
                $isRhuAdmin   = in_array($viewerRole, ['admin', 'staff']) && !is_null($viewerUnitId);
            @endphp

            <div style="padding: 40px 20px; text-align: center; color: #64748b;">
                @if($view === 'archived')
                    <p style="font-size:16px; margin:0;">📦 No archived notifications</p>
                    <p style="font-size:14px; margin:4px 0 0 0;">Notifications you archive will appear here.</p>

                @elseif($view === 'all')
                    <p style="font-size:16px; margin:0;">No notifications</p>
                    <p style="font-size:14px; margin:4px 0 0 0;">You have no active or archived notifications.</p>

                @else
                    {{-- Active view, no rows --}}
                    @if($isMidwife)
                        <p style="font-size:16px; margin:0;">🎉 You're all caught up</p>
                        <p style="font-size:14px; margin:4px 0 0 0;">
                            No overdue vaccine doses, low-stock alerts, or disease outbreaks at your RHU right now.
                        </p>

                    @elseif($isPHO)
                        <p style="font-size:16px; margin:0;">🎉 No active notifications</p>
                        <p style="font-size:14px; margin:4px 0 0 0;">
                            No provincial alerts, supply requests, or outbreak flags awaiting review.
                        </p>

                    @elseif($isRhuAdmin)
                        <p style="font-size:16px; margin:0;">🎉 All clear</p>
                        <p style="font-size:14px; margin:4px 0 0 0;">
                            All inventory is well stocked, no batches are expiring soon, and no transfers need action.
                        </p>

                    @else
                        <p style="font-size:16px; margin:0;">🎉 No notifications</p>
                        <p style="font-size:14px; margin:4px 0 0 0;">
                            Nothing needs your attention right now.
                        </p>
                    @endif
                @endif
            </div>
        @endforelse
    </div>

</div>

<script>
function markRead(id) {
    fetch(`/notifications/mark-read/${id}`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    })
    .then(response => response.json())
    .then(data => { if (data.success) location.reload(); });
}

function markAllRead() {
    if (!confirm('Mark all notifications as read?')) return;
    fetch('/notifications/mark-all-read', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    })
    .then(response => response.json())
    .then(data => { if (data.success) location.reload(); });
}

function deleteNotification(id) {
    if (!confirm('Delete this notification?')) return;
    fetch(`/notifications/delete/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const el = document.getElementById('notification-' + id);
            if (el) { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }
        } else {
            alert(data.message || 'Unable to delete notification.');
        }
    });
}

function deleteAllNotifications() {
    if (!confirm('Delete ALL notifications? This cannot be undone.')) return;
    fetch('/notifications/delete-all', {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) location.reload();
        else alert(data.message || 'Unable to delete notifications.');
    });
}

function archiveNotification(id) {
    fetch(`/notifications/archive/${id}`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const el = document.getElementById('notification-' + id);
            if (el) { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }
        } else {
            alert(data.message || 'Unable to archive notification.');
        }
    });
}

function unarchiveNotification(id) {
    fetch(`/notifications/unarchive/${id}`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const el = document.getElementById('notification-' + id);
            if (el) { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }
        } else {
            alert(data.message || 'Unable to unarchive notification.');
        }
    });
}

function deleteAllArchived() {
    if (!confirm('Delete ALL archived notifications? This cannot be undone.')) return;
    fetch('/notifications/delete-archived', {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) location.reload();
        else alert(data.message || 'Unable to delete archived notifications.');
    });
}

// ═══ Decline (RHU) / Reject (PHO) from notification card ═══
function openRejectFromNotification(id, label) {
    const verb = (label || 'Reject').toLowerCase();
    const reason = prompt(`Reason to ${verb} this transfer (optional):`);
    if (reason === null) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/transfers/' + id + '/reject';
    form.innerHTML = '@csrf<input type="hidden" name="rejection_reason" value="' + reason.replace(/"/g, '&quot;') + '">';
    document.body.appendChild(form);
    form.submit();
}

// ═══ Reject a pending supply request from the notification card ═══
function rejectSupplyRequest(allocationId) {
    const reason = prompt('Reason for rejecting this request (optional):');
    if (reason === null) return;   // user cancelled

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ url("/allocation") }}/' + allocationId + '/reject-supply-request';
    form.innerHTML = '@csrf<input type="hidden" name="rejection_reason" value="' +
                     reason.replace(/"/g, '&quot;') + '">';
    document.body.appendChild(form);
    form.submit();
}
</script>
@endsection