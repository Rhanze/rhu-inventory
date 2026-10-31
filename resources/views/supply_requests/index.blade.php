@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius: 20px; padding: 24px 28px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 16px;">
        <div>
            <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">
                Supply
            </p>
            <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">
                Supply Requests
            </h2>
            <p style="font-size:14px; color:#475569; margin:6px 0 0 0;">
                Request stock from your RHU for immunization drives, prenatal clinics, and outreach activities.
            </p>
        </div>

        <a href="{{ route('supply_requests.create') }}" style="
            display:inline-flex; align-items:center; gap:6px;
            font-size:14px; font-weight:600; padding:9px 18px; border-radius:10px;
            background:#42ad8a; color:#ffffff; text-decoration:none;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Request
        </a>
    </div>

    @if(session('success'))
        <div style="background:#E8F7F4; border:1px solid #42ad8a; border-radius:12px; padding:14px 20px; color:#0f5c4b; font-size:16px;">
            ✓ {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div style="background:#FCEBEB; border:1px solid #F7C1C1; border-radius:12px; padding:14px 20px; color:#991b1b; font-size:16px;">
            ✗ {{ session('error') }}
        </div>
    @endif

    {{-- Stat cards --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px;">
        <div style="background:#FEF3C7; border:1px solid #FAC775; border-radius:16px; padding:20px; text-align:center;">
            <p style="font-size:13px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#854F0B; margin:0 0 4px 0;">Pending</p>
            <p style="font-size:32px; font-weight:700; color:#633806; margin:0; line-height:1.1;">{{ $counts['pending'] }}</p>
        </div>
        <div style="background:#EFF6FF; border:1px solid #93C5FD; border-radius:16px; padding:20px; text-align:center;">
            <p style="font-size:13px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#1E40AF; margin:0 0 4px 0;">Approved</p>
            <p style="font-size:32px; font-weight:700; color:#1E40AF; margin:0; line-height:1.1;">{{ $counts['approved'] }}</p>
        </div>
        <div style="background:#E8F7F4; border:1px solid #42ad8a; border-radius:16px; padding:20px; text-align:center;">
            <p style="font-size:13px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#0f5c4b; margin:0 0 4px 0;">Received</p>
            <p style="font-size:32px; font-weight:700; color:#0f5c4b; margin:0; line-height:1.1;">{{ $counts['received'] }}</p>
        </div>
        <div style="background:#FCEBEB; border:1px solid #F7C1C1; border-radius:16px; padding:20px; text-align:center;">
            <p style="font-size:13px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#991b1b; margin:0 0 4px 0;">Rejected</p>
            <p style="font-size:32px; font-weight:700; color:#991b1b; margin:0; line-height:1.1;">{{ $counts['rejected'] }}</p>
        </div>
    </div>

    {{-- Filter --}}
    <div style="
        background: rgba(255,255,255,0.95); border-radius: 20px;
        border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        padding: 20px 24px;">
        <form method="GET" action="{{ route('supply_requests.index') }}"
              style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div style="flex:2 1 240px;">
                <label style="display:block; font-size:13px; font-weight:700; color:#475569; margin-bottom:6px; text-transform:uppercase;">Search Medicine</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Type medicine name..."
                       style="width:100%; height:44px; padding:0 14px; font-size:15px;
                              border:2px solid #bec6cf; border-radius:10px;
                              box-sizing:border-box; outline:none;">
            </div>
            <div style="flex:1 1 180px;">
                <label style="display:block; font-size:13px; font-weight:700; color:#475569; margin-bottom:6px; text-transform:uppercase;">Status</label>
                <select name="status" style="width:100%; height:44px; padding:0 14px; font-size:15px;
                                             border:2px solid #bec6cf; border-radius:10px; background:#fff;">
                    <option value="">All</option>
                    <option value="pending"  {{ $status === 'pending'  ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="received" {{ $status === 'received' ? 'selected' : '' }}>Received</option>
                    <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" style="height:44px; padding:0 22px; background:#42ad8a; color:#fff; border:none; border-radius:10px; font-weight:600; font-size:15px; cursor:pointer;">
                    Filter
                </button>
                <a href="{{ route('supply_requests.index') }}" style="height:44px; padding:0 22px; background:#fff; color:#334155; border:1px solid #e2e8f0; border-radius:10px; font-weight:600; font-size:15px; text-decoration:none; display:inline-flex; align-items:center;">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div style="
        background: rgba(255,255,255,0.95); border-radius: 20px;
        border: 1px solid rgba(66,173,138,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        overflow: hidden;">
        <div style="overflow-x:auto;">
            <table style="width:100%; min-width:900px; border-collapse:collapse; font-size:15px;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <th style="padding:14px 18px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">#</th>
                        <th style="padding:14px 18px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Medicine</th>
                        <th style="padding:14px 18px; text-align:right; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Qty</th>
                        <th style="padding:14px 18px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Purpose</th>
                        <th style="padding:14px 18px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Requested</th>
                        <th style="padding:14px 18px; text-align:center; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Status</th>
                        <th style="padding:14px 18px; text-align:center; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                        @php
                            $statusStyle = match($req->status) {
                                'approved' => ['bg' => '#EFF6FF', 'text' => '#1E40AF', 'border' => '#93C5FD', 'label' => 'Approved'],
                                'received' => ['bg' => '#E8F7F4', 'text' => '#0f5c4b', 'border' => '#42ad8a', 'label' => 'Received'],
                                'rejected' => ['bg' => '#FCEBEB', 'text' => '#991b1b', 'border' => '#F7C1C1', 'label' => 'Rejected'],
                                default    => ['bg' => '#FEF3C7', 'text' => '#92400E', 'border' => '#FAC775', 'label' => 'Pending'],
                            };
                            $med  = $req->medicine;
                            $unit = $med->unit ?? 'unit';
                        @endphp
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:14px 18px; color:#94a3b8; font-weight:600;">
                                {{ $loop->iteration + ($requests->currentPage() - 1) * $requests->perPage() }}
                            </td>
                            <td style="padding:14px 18px; font-weight:600; color:#0f172a;">
                                {{ $med->medicine_name ?? 'N/A' }}
                                @if($med && $med->dosage)
                                    <div style="font-size:13px; color:#64748b; font-weight:400;">{{ $med->dosage }}</div>
                                @endif
                            </td>
                            <td style="padding:14px 18px; text-align:right; font-weight:700; color:#0f5c4b;">
                                {{ $req->quantity_allocated }}
                                <span style="font-weight:400; color:#64748b;">
                                    {{ $req->quantity_allocated == 1 ? $unit : $unit . 's' }}
                                </span>
                            </td>
                            <td style="padding:14px 18px; color:#334155;">
                                {{ $req->destination ?? '—' }}
                            </td>
                            <td style="padding:14px 18px; color:#334155; white-space:nowrap;">
                                {{ \Carbon\Carbon::parse($req->created_at ?? $req->date_allocated)->format('M d, Y') }}
                                <div style="font-size:13px; color:#64748b; margin-top:2px;">
                                    {{ \Carbon\Carbon::parse($req->created_at ?? $req->date_allocated)->format('g:i A') }}
                                </div>
                            </td>
                            <td style="padding:14px 18px; text-align:center;">
                                <span style="
                                    display:inline-block; padding:4px 12px; border-radius:999px;
                                    font-size:13px; font-weight:700;
                                    background:{{ $statusStyle['bg'] }};
                                    color:{{ $statusStyle['text'] }};
                                    border:1px solid {{ $statusStyle['border'] }};">
                                    {{ $statusStyle['label'] }}
                                </span>
                            </td>
                            <td style="padding:14px 18px; text-align:center;">
                                @if($req->status === 'pending')
                                    <form action="{{ route('supply_requests.destroy', $req->id) }}" method="POST"
                                          onsubmit="return confirmDialog(event, 'Cancel this request?');"
                                          style="margin:0; display:inline;">
                                        @csrf @method('DELETE')
                                        <button type="submit" style="
                                            background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;
                                            padding:6px 14px; border-radius:8px;
                                            font-size:13px; font-weight:600; cursor:pointer;">
                                            Cancel
                                        </button>
                                    </form>
                                @else
                                    <span style="color:#94a3b8; font-size:13px;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding:60px 20px; text-align:center; color:#64748b;">
                                <p style="font-size:32px; margin:0 0 8px 0;">📦</p>
                                <p style="font-size:16px; font-weight:600; color:#0f172a; margin:0;">No supply requests yet</p>
                                <p style="font-size:14px; margin:6px 0 0 0;">Click "New Request" to ask your RHU for stock.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($requests->hasPages())
        <div style="display:flex; justify-content:center;">{{ $requests->links() }}</div>
    @endif
</div>
@endsection