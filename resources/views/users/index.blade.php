@extends('layouts.app')

@section('content')
@php
    $isRhuAdmin = (bool) session('health_unit_id');

    $roleBadges = [
        'admin'   => ['bg' => '#ede9fe', 'color' => '#6d28d9', 'border' => '#c4b5fd', 'label' => 'Admin'],
        'staff'   => ['bg' => '#dbeafe', 'color' => '#1e40af', 'border' => '#93c5fd', 'label' => 'Staff'],
        'midwife' => ['bg' => '#fce7f3', 'color' => '#9d174d', 'border' => '#f9a8d4', 'label' => 'Midwife'],
    ];
@endphp

<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Page header card --}}
    <div style="
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(12px);
        border-radius: 20px;
        padding: 24px 28px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    ">
        <div>
            <p style="font-size: 13px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #42ad8a; margin: 0 0 6px 0;">User Management</p>
            <h2 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0;">All User Accounts</h2>
        </div>

        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">

            {{-- Filters --}}
            <form method="GET" action="{{ route('users.index') }}" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                @if(request('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                @endif

                @if(is_null(session('health_unit_id')))
                    <select name="rhu_filter" style="
                        height: 40px; padding: 0 12px; font-size: 14px;
                        border: 2px solid #bec6cf; border-radius: 10px; outline: none;
                        background: white; color: #0f172a; appearance: auto;
                    " onchange="this.form.submit()">
                        <option value="">All RHUs</option>
                        @foreach($healthUnits as $rhu)
                            <option value="{{ $rhu->id }}" {{ request('rhu_filter') == $rhu->id ? 'selected' : '' }}>
                                {{ $rhu->name }}
                            </option>
                        @endforeach
                    </select>
                @endif

                <select name="role_filter" style="
                    height: 40px; padding: 0 12px; font-size: 14px;
                    border: 2px solid #bec6cf; border-radius: 10px; outline: none;
                    background: white; color: #0f172a; appearance: auto;
                " onchange="this.form.submit()">
                    <option value="">All Roles</option>
                    @foreach($roleBadges as $key => $meta)
                        <option value="{{ $key }}" {{ request('role_filter') === $key ? 'selected' : '' }}>
                            {{ $meta['label'] }}
                        </option>
                    @endforeach
                </select>
            </form>

            {{-- Add Account --}}
            <a href="{{ route('users.create') }}" style="
                display: inline-flex; align-items: center; gap: 6px;
                font-size: 14px; font-weight: 600; padding: 9px 16px;
                border-radius: 10px; background: #42ad8a; color: #ffffff;
                text-decoration: none; border: none; cursor: pointer;
                transition: all 0.2s;
            " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Account
            </a>
        </div>
    </div>

    {{-- Table card --}}
    <div style="
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(8px);
        border-radius: 20px;
        padding: 0;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        overflow: hidden;
    ">
        <table style="width:100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569;">#</th>
                    <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569;">Name</th>
                    <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569;">Username</th>
                    <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569;">Role</th>
                    <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569;">RHU</th>
                    <th style="padding: 14px 20px; text-align: center; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569;">Actions</th>
                </tr>
            </thead>

            <tbody>
                @forelse($users as $user)
                    @php
                        $isHighlighted = (string) request('highlight') === (string) $user->id;
                        $rowBg = $isHighlighted ? '#f1f5f9' : 'transparent';
                        $badge = $roleBadges[$user->role] ?? ['bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#cbd5e1', 'label' => ucfirst($user->role)];
                    @endphp

                    <tr id="user-{{ $user->id }}" style="background: {{ $rowBg }}; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; scroll-margin-top: 80px;"
                        onmouseover="if('{{ $rowBg }}'=='transparent') this.style.background='#f8fafc';"
                        onmouseout="if('{{ $rowBg }}'=='transparent') this.style.background='transparent';">
                        <td style="padding: 14px 20px; color: #334155;">{{ $loop->iteration + ($users->currentPage() - 1) * $users->perPage() }}</td>
                        <td style="padding: 14px 20px; font-weight: 600; color: #0f172a;">{{ $user->name }}</td>
                        <td style="padding: 14px 20px; color: #334155;">{{ $user->username }}</td>
                        <td style="padding: 14px 20px;">
                            <span style="
                                display:inline-block; padding: 3px 10px; border-radius: 999px;
                                font-size: 12px; font-weight: 600;
                                background: {{ $badge['bg'] }};
                                color: {{ $badge['color'] }};
                                border: 1px solid {{ $badge['border'] }};
                            ">{{ $badge['label'] }}</span>
                        </td>
                        <td style="padding: 14px 20px; color: #334155;">{{ $user->healthUnit->name ?? 'N/A' }}</td>
                        <td style="padding: 14px 20px;">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">

                                {{-- Edit --}}
                                <a href="{{ route('users.edit', $user->id) }}" title="Edit"
                                   style="
                                       width: 34px; height: 34px;
                                       display: inline-flex; align-items: center; justify-content: center;
                                       border-radius: 8px;
                                       background: #E8F7F4; color: #0f5c4b; border: 1px solid #42AD8A;
                                       text-decoration: none; transition: all 0.2s;
                                   "
                                   onmouseover="this.style.background='#d2efe6'" onmouseout="this.style.background='#E8F7F4'">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </a>

                                {{-- Delete --}}
                                <form action="{{ route('users.destroy', $user->id) }}" method="POST"
                                      onsubmit="return confirmDialog(event, 'Are you sure you want to delete this user?');"
                                      style="margin:0;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Delete"
                                            style="
                                                width: 34px; height: 34px;
                                                display: inline-flex; align-items: center; justify-content: center;
                                                border-radius: 8px;
                                                background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;
                                                cursor: pointer; transition: all 0.2s;
                                            "
                                            onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="3 6 5 6 21 6"/>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                            <line x1="10" y1="11" x2="10" y2="17"/>
                                            <line x1="14" y1="11" x2="14" y2="17"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 40px 20px; text-align: center; color: #64748b; border-bottom: 1px solid #f1f5f9;">
                            No user records found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if ($users->hasPages())
        <div style="
            background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
            border-radius: 16px; padding: 16px 24px; border: 1px solid rgba(255,255,255,0.4);
            box-shadow: 0 4px 12px rgba(0,0,0,0.04); display: flex; align-items: center;
            justify-content: center; gap: 10px; flex-wrap: wrap;
        ">
            @if ($users->onFirstPage())
                <span style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: default; user-select: none;">← Previous</span>
            @else
                <a href="{{ $users->previousPageUrl() }}" style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                    ← Previous
                </a>
            @endif

            @foreach ($users->getUrlRange(1, $users->lastPage()) as $page => $url)
                @if ($page == $users->currentPage())
                    <span style="font-size: 14px; font-weight: 600; padding: 8px 14px; border-radius: 8px; background: #42ad8a; color: #ffffff; border: 1px solid #42ad8a; cursor: default;">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" style="font-size: 14px; font-weight: 500; padding: 8px 14px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                       onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                        {{ $page }}
                    </a>
                @endif
            @endforeach

            @if ($users->hasMorePages())
                <a href="{{ $users->nextPageUrl() }}" style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                    Next →
                </a>
            @else
                <span style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: default; user-select: none;">Next →</span>
            @endif
        </div>
    @endif

</div>
@endsection