{{-- resources/views/barangay/index.blade.php --}}
@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header Card with actions --}}
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
            <p style="font-size: 13px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #42ad8a; margin: 0 0 6px 0;">BHS Management</p>
            <h2 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0;">Barangay Health Stations</h2>
        </div>

        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">

            {{-- Add BHS --}}
            <a href="{{ route('barangay.create') }}" style="
                display: inline-flex;
                align-items: center;
                gap: 6px;
                font-size: 14px;
                font-weight: 600;
                padding: 9px 16px;
                border-radius: 10px;
                background: #42ad8a;
                color: #ffffff;
                text-decoration: none;
                border: none;
                cursor: pointer;
                transition: all 0.2s;
            " onmouseover="this.style.background='#369f7d'" onmouseout="this.style.background='#42ad8a'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add BHS
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
                    <th class="no-print" style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569;">#</th>
                    <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569;">BHS Name</th>
                    <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569;">RHU Name</th>
                    {{-- RHU Address column removed --}}
                    <th class="no-print" style="padding: 14px 20px; text-align: center; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569;">Actions</th>
                </tr>
            </thead>

            <tbody>
                @forelse($barangays as $barangay)
                    @php
                        $isHighlighted = (string) request('highlight') === (string) $barangay->id;
                        $rowBg = $isHighlighted ? '#f1f5f9' : 'transparent';
                        $rhu = $barangay->healthUnit;
                    @endphp
                    <tr id="barangay-{{ $barangay->id }}" style="background: {{ $rowBg }}; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; scroll-margin-top: 80px;"
                        onmouseover="if('{{ $rowBg }}'=='transparent') this.style.background='#f8fafc';" 
                        onmouseout="if('{{ $rowBg }}'=='transparent') this.style.background='transparent';">
                        <td class="no-print" style="padding: 14px 20px; color: #334155;">{{ $loop->iteration + ($barangays->currentPage() - 1) * $barangays->perPage() }}</td>
                        <td style="padding: 14px 20px; font-weight: 600; color: #0f172a;">{{ $barangay->barangay_name }}</td>
                        <td style="padding: 14px 20px; color: #0f172a; font-weight: 500;">{{ $rhu->name ?? 'N/A' }}</td>
                        <td class="no-print" style="padding: 14px 20px;">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <a href="{{ route('barangay.edit', $barangay->id) }}" style="
                                    font-size: 13px;
                                    font-weight: 600;
                                    padding: 6px 12px;
                                    border-radius: 8px;
                                    background: #E8F7F4;
                                    color: #0f5c4b;
                                    border: 1px solid #42AD8A;
                                    text-decoration: none;
                                    transition: all 0.2s;
                                " onmouseover="this.style.background='#d2efe6'" onmouseout="this.style.background='#E8F7F4'">
                                    Edit
                                </a>

                                <form action="{{ route('barangay.destroy', $barangay->id) }}" method="POST"
                                      onsubmit="return confirm('Are you sure you want to delete this BHS?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="
                                        font-size: 13px;
                                        font-weight: 600;
                                        padding: 6px 12px;
                                        border-radius: 8px;
                                        background: #fee2e2;
                                        color: #991b1b;
                                        border: 1px solid #fca5a5;
                                        cursor: pointer;
                                        transition: all 0.2s;
                                    " onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="padding: 40px 20px; text-align: center; color: #64748b; border-bottom: 1px solid #f1f5f9;">
                            No Barangay Health Station records found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if ($barangays->hasPages())
        <div style="
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(8px);
            border-radius: 16px;
            padding: 16px 24px;
            border: 1px solid rgba(255,255,255,0.4);
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        ">
            @if ($barangays->onFirstPage())
                <span style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: default; user-select: none;">← Previous</span>
            @else
                <a href="{{ $barangays->previousPageUrl() }}" style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                    ← Previous
                </a>
            @endif

            @foreach ($barangays->getUrlRange(1, $barangays->lastPage()) as $page => $url)
                @if ($page == $barangays->currentPage())
                    <span style="font-size: 14px; font-weight: 600; padding: 8px 14px; border-radius: 8px; background: #42ad8a; color: #ffffff; border: 1px solid #42ad8a; cursor: default;">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" style="font-size: 14px; font-weight: 500; padding: 8px 14px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
                       onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                        {{ $page }}
                    </a>
                @endif
            @endforeach

            @if ($barangays->hasMorePages())
                <a href="{{ $barangays->nextPageUrl() }}" style="font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s;"
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