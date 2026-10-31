@extends('layouts.app')

@section('content')
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
            <p style="font-size: 13px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #42ad8a; margin: 0 0 6px 0;">RHU Management</p>
            <h2 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0;">View and manage Rural Health Units</h2>
        </div>

        <a href="{{ route('rhu.create') }}" style="
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
            Add RHU
        </a>
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
                    <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569;">Municipality</th>
                    <th style="padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569;">Address</th>
                    <th style="padding: 14px 20px; text-align: center; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #475569;">Actions</th>
                </tr>
            </thead>

            <tbody>
                @forelse($healthUnits as $unit)
                    @php
                        $isHighlighted = (string) request('highlight') === (string) $unit->id;
                        $rowBg = $isHighlighted ? '#f1f5f9' : 'transparent';
                    @endphp

                    <tr id="rhu-{{ $unit->id }}" style="background: {{ $rowBg }}; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; scroll-margin-top: 80px;"
                        onmouseover="if('{{ $rowBg }}'=='transparent') this.style.background='#f8fafc';" 
                        onmouseout="if('{{ $rowBg }}'=='transparent') this.style.background='transparent';">
                        <td style="padding: 14px 20px; color: #334155;">{{ $loop->iteration + ($healthUnits->currentPage() - 1) * $healthUnits->perPage() }}</td>
                        <td style="padding: 14px 20px; font-weight: 600; color: #0f172a;">{{ $unit->name }}</td>
                        <td style="padding: 14px 20px; color: #334155;">{{ $unit->municipality }}</td>
                        <td style="padding: 14px 20px; color: #334155;">{{ $unit->address }}</td>
                        <td style="padding: 14px 20px;">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <a href="{{ route('rhu.edit', $unit->id) }}" style="
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

                                <form action="{{ route('rhu.destroy', $unit->id) }}" method="POST"
                                      onsubmit="return confirm('Are you sure you want to delete this RHU?');">
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
                        <td colspan="5" style="padding: 40px 20px; text-align: center; color: #64748b; border-bottom: 1px solid #f1f5f9;">
                            No RHU records found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if ($healthUnits->hasPages())
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
            {{-- Previous Page Link --}}
            @if ($healthUnits->onFirstPage())
                <span style="
                    font-size: 14px; font-weight: 500; padding: 8px 16px;
                    border-radius: 8px; background: #f1f5f9; color: #94a3b8;
                    border: 1px solid #e2e8f0; cursor: default; user-select: none;
                ">← Previous</span>
            @else
                <a href="{{ $healthUnits->previousPageUrl() }}" style="
                    font-size: 14px; font-weight: 500; padding: 8px 16px;
                    border-radius: 8px; background: #ffffff; color: #334155;
                    border: 1px solid #e2e8f0; text-decoration: none;
                    transition: all 0.2s;
                " onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" 
                   onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                    ← Previous
                </a>
            @endif

            {{-- Page Numbers --}}
            @foreach ($healthUnits->getUrlRange(1, $healthUnits->lastPage()) as $page => $url)
                @if ($page == $healthUnits->currentPage())
                    <span style="
                        font-size: 14px; font-weight: 600; padding: 8px 14px;
                        border-radius: 8px; background: #42ad8a; color: #ffffff;
                        border: 1px solid #42ad8a; cursor: default;
                    ">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" style="
                        font-size: 14px; font-weight: 500; padding: 8px 14px;
                        border-radius: 8px; background: #ffffff; color: #334155;
                        border: 1px solid #e2e8f0; text-decoration: none;
                        transition: all 0.2s;
                    " onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" 
                       onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                        {{ $page }}
                    </a>
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($healthUnits->hasMorePages())
                <a href="{{ $healthUnits->nextPageUrl() }}" style="
                    font-size: 14px; font-weight: 500; padding: 8px 16px;
                    border-radius: 8px; background: #ffffff; color: #334155;
                    border: 1px solid #e2e8f0; text-decoration: none;
                    transition: all 0.2s;
                " onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#42ad8a';" 
                   onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e2e8f0';">
                    Next →
                </a>
            @else
                <span style="
                    font-size: 14px; font-weight: 500; padding: 8px 16px;
                    border-radius: 8px; background: #f1f5f9; color: #94a3b8;
                    border: 1px solid #e2e8f0; cursor: default; user-select: none;
                ">Next →</span>
            @endif
        </div>
    @endif

</div>
@endsection