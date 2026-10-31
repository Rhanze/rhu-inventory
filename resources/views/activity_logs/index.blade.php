{{-- resources/views/activity_logs/index.blade.php --}}
@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Search & Actions Card (hidden when printing) --}}
    <div class="no-print" style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:20px 24px; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
        display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;
    ">
        <form method="GET" action="{{ route('activity_logs.index') }}" style="display:flex; align-items:center; gap:8px; flex:1; max-width:500px;">
            <input
                type="text"
                name="search"
                value="{{ $search ?? '' }}"
                placeholder="Search user, module, description..."
                style="flex:1; min-width:200px; height:40px; padding:0 14px; font-size:14px; border:2px solid #bec6cf; border-radius:10px; outline:none; background:white; color:#0f172a;"
                onfocus="this.style.borderColor='#42ad8a'; this.style.boxShadow='0 0 0 3px rgba(66,173,138,0.1)'"
                onblur="this.style.borderColor='#bec6cf'; this.style.boxShadow='none'"
            >
            <button type="submit" style="
                height:40px; padding:0 18px; background:#42ad8a; color:white; border:none; border-radius:10px; font-weight:600; font-size:14px; cursor:pointer; transition:background 0.2s;
            " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                Search
            </button>
        </form>

        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('activity_logs.export', ['search' => request('search')]) }}" style="
                display:inline-flex; align-items:center; gap:6px; font-size:14px; font-weight:500;
                padding:8px 16px; border-radius:10px; background:#ffffff; color:#334155;
                border:1px solid #e2e8f0; text-decoration:none; transition:all 0.2s;
            " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'">
                <img src="{{ asset('images/icons/csv.png') }}" alt="CSV" style="width:18px;height:18px;object-fit:contain;">
                Export CSV
            </a>

            <button onclick="window.print()" style="
                display:inline-flex; align-items:center; gap:6px; font-size:14px; font-weight:500;
                padding:8px 16px; border-radius:10px; background:#ffffff; color:#334155;
                border:1px solid #e2e8f0; cursor:pointer; transition:all 0.2s;
            " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'">
                <img src="{{ asset('images/icons/print.png') }}" alt="Print" style="width:18px;height:18px;object-fit:contain;">
                Print
            </button>
        </div>
    </div>

    {{-- Notice about auto-deletion --}}
    <div class="no-print" style="
        background: #E8F7F4; border: 1px solid #42AD8A;
        border-radius: 12px; padding: 10px 16px;
        display: flex; align-items: center; gap: 8px;
        font-size: 13px; color: #0f5c4b;
    ">
        <span style="font-size: 16px;">ℹ️</span>
        <span>Activity logs older than <strong>30 days</strong> are automatically deleted. Only recent actions are shown here.</span>
    </div>

    {{-- Table card – SCROLLABLE CONTAINER --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius:20px; padding:0; border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
        max-height: 600px; overflow-y: auto;
    ">
        <table style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead>
                <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; position:sticky; top:0; z-index:2;">
                    <th style="padding:14px 20px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">#</th>
                    <th style="padding:14px 20px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">User</th>
                    <th style="padding:14px 20px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">Section</th>
                    <th style="padding:14px 20px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">Action</th>
                    <th style="padding:14px 20px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">Description</th>
                    <th style="padding:14px 20px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#475569;">Date & Time</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    @php
                        $actionClasses = match(strtolower($log->action)) {
                            'create' => 'background:#EAF3DE; color:#3B6D11; border:1px solid #C0DD97;',
                            'update' => 'background:#E6F1FB; color:#185FA5; border:1px solid #B5D4F4;',
                            'delete' => 'background:#FCEBEB; color:#A32D2D; border:1px solid #F7C1C1;',
                            'login'  => 'background:#E8F7F4; color:#0f5c4b; border:1px solid #42AD8A;',
                            'logout' => 'background:#f8fafc; color:#475569; border:1px solid #e2e8f0;',
                            default  => 'background:#FAEEDA; color:#854F0B; border:1px solid #FAC775;',
                        };
                    @endphp

                    <tr style="border-bottom:1px solid #f1f5f9; transition:background 0.2s;"
                        onmouseover="this.style.background='#f8fafc';" onmouseout="this.style.background='transparent';">
                        <td style="padding:14px 20px; color:#334155;">{{ $logs->firstItem() + $loop->index }}</td>
                        <td style="padding:14px 20px; font-weight:600; color:#0f172a;">{{ $log->user->name ?? 'Unknown User' }}</td>
                        <td style="padding:14px 20px; color:#334155;">{{ $log->module }}</td>
                        <td style="padding:14px 20px;">
                            <span style="display:inline-block; font-size:12px; font-weight:600; padding:3px 10px; border-radius:999px; {{ $actionClasses }}">
                                {{ $log->action }}
                            </span>
                        </td>
                        <td style="padding:14px 20px; color:#334155;">{{ $log->description }}</td>
                        <td style="padding:14px 20px; color:#334155;">
                            {{ $log->created_at ? \Carbon\Carbon::parse($log->created_at)->format('M d, Y | h:i A') : 'N/A' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding:40px 20px; text-align:center; color:#64748b; border-bottom:1px solid #f1f5f9;">
                            No activity logs found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination (hidden when printing) --}}
    @if ($logs->hasPages())
        <div class="no-print" style="
            background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
            border-radius:16px; padding:16px 24px; border:1px solid rgba(255,255,255,0.4);
            box-shadow:0 4px 12px rgba(0,0,0,0.04);
            display:flex; align-items:center; justify-content:center; gap:10px; flex-wrap:wrap;
        ">
            @if ($logs->onFirstPage())
                <span style="font-size:14px; font-weight:500; padding:8px 16px; border-radius:8px; background:#f1f5f9; color:#94a3b8; border:1px solid #e2e8f0; cursor:default; user-select:none;">← Previous</span>
            @else
                <a href="{{ $logs->previousPageUrl() }}" style="font-size:14px; font-weight:500; padding:8px 16px; border-radius:8px; background:#ffffff; color:#334155; border:1px solid #e2e8f0; text-decoration:none; transition:all 0.2s;"
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                    ← Previous
                </a>
            @endif

            @foreach ($logs->getUrlRange(1, $logs->lastPage()) as $page => $url)
                @if ($page == $logs->currentPage())
                    <span style="font-size:14px; font-weight:600; padding:8px 14px; border-radius:8px; background:#42ad8a; color:#ffffff; border:1px solid #42ad8a; cursor:default;">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" style="font-size:14px; font-weight:500; padding:8px 14px; border-radius:8px; background:#ffffff; color:#334155; border:1px solid #e2e8f0; text-decoration:none; transition:all 0.2s;"
                       onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                        {{ $page }}
                    </a>
                @endif
            @endforeach

            @if ($logs->hasMorePages())
                <a href="{{ $logs->nextPageUrl() }}" style="font-size:14px; font-weight:500; padding:8px 16px; border-radius:8px; background:#ffffff; color:#334155; border:1px solid #e2e8f0; text-decoration:none; transition:all 0.2s;"
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                    Next →
                </a>
            @else
                <span style="font-size:14px; font-weight:500; padding:8px 16px; border-radius:8px; background:#f1f5f9; color:#94a3b8; border:1px solid #e2e8f0; cursor:default; user-select:none;">Next →</span>
            @endif
        </div>
    @endif
</div>
@endsection