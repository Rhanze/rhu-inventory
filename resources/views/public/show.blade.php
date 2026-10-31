@extends('layouts.public')

@section('content')
<div class="stack">

    {{-- Font scale override + mobile tweaks --}}
    <style>
        [style*="font-size:11px"] { font-size: 13px !important; }
        [style*="font-size: 11px"] { font-size: 13px !important; }
        [style*="font-size:12px"] { font-size: 14px !important; }
        [style*="font-size: 12px"] { font-size: 14px !important; }
        [style*="font-size:13px"] { font-size: 15px !important; }
        [style*="font-size: 13px"] { font-size: 15px !important; }
        [style*="font-size:14px"] { font-size: 16px !important; }
        [style*="font-size: 14px"] { font-size: 16px !important; }
        [style*="font-size:15px"] { font-size: 17px !important; }
        [style*="font-size: 15px"] { font-size: 17px !important; }
        [style*="font-size:16px"] { font-size: 18px !important; }
        [style*="font-size: 16px"] { font-size: 18px !important; }
        [style*="font-size:18px"] { font-size: 20px !important; }
        [style*="font-size: 18px"] { font-size: 20px !important; }
        [style*="font-size:20px"] { font-size: 22px !important; }
        [style*="font-size: 20px"] { font-size: 22px !important; }

        @media (max-width: 768px) {
            .medicine-header {
                flex-direction: column !important;
                gap: 20px !important;
            }
            .medicine-info-card {
                max-width: none !important;
                width: 100% !important;
            }
            .availability-card {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 12px !important;
            }
            .availability-tags {
                width: 100% !important;
                justify-content: flex-start !important;
            }
            .medicine-title {
                font-size: clamp(28px, 7vw, 42px) !important;
            }
            .availability-title {
                font-size: 26px !important;
            }
        }
    </style>

    @if(isset($medicine))

        <div style="
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(255,255,255,0.4);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            box-sizing: border-box;
        ">
            <a href="{{ route('public.home.citizen', array_filter(['q' => $q ?? null, 'health_unit_id' => $healthUnitId ?? null])) }}" style="
                display: inline-flex;
                align-items: center;
                gap: 6px;
                font-size: 22px;
                font-weight: 500;
                color: #42ad8a;
                text-decoration: none;
                margin-bottom: 20px;
            ">
                ← Back to Search
            </a>

            <div class="medicine-header" style="display: flex; flex-wrap: wrap; gap: 24px; justify-content: space-between; align-items: flex-start;">

                <div style="flex: 1; min-width: 260px;">
                    <h1 class="medicine-title" style="
                        font-size: clamp(30px, 6vw, 44px);
                        font-weight: 800;
                        color: #0f172a;
                        margin: 0 0 6px 0;
                        line-height: 1.2;
                    ">{{ $medicine->medicine_name }}</h1>

                    <p style="
                        font-size: 18px;
                        color: #475569;
                        line-height: 1.7;
                        margin-top: 20px;
                    ">{{ $medicine->description ?: 'No description available.' }}</p>
                </div>

                <div class="medicine-info-card" style="
                    background: #E8F7F4;
                    border: 1px solid #42AD8A;
                    border-radius: 18px;
                    padding: 24px 20px;
                    min-width: 240px;
                    max-width: 300px;
                    width: 100%;
                    box-sizing: border-box;
                ">
                    <p style="
                        font-size: 16px;
                        font-weight: 700;
                        letter-spacing: 0.04em;
                        text-transform: uppercase;
                        color: #0f5c4b;
                        margin: 0 0 20px 0;
                        border-bottom: 1px solid rgba(66,173,138,0.3);
                        padding-bottom: 10px;
                    ">Medicine Info</p>

                    <div style="margin-bottom: 16px;">
                        <span style="font-size: 16px; font-weight: 600; color: #1a6e5c; display: block; margin-bottom: 4px;">Dosage</span>
                        <span style="font-size: 20px; font-weight: 700; color: #0f172a;">{{ $medicine->dosage }}</span>
                    </div>

                    <div style="margin-bottom: 16px; border-top: 1px solid rgba(66,173,138,0.15); padding-top: 12px;">
                        <span style="font-size: 16px; font-weight: 600; color: #1a6e5c; display: block; margin-bottom: 4px;">Category</span>
                        <span style="font-size: 20px; font-weight: 700; color: #0f172a;">{{ ucfirst($medicine->category) }}</span>
                    </div>

                    <div style="border-top: 1px solid rgba(66,173,138,0.15); padding-top: 12px;">
                        <span style="font-size: 16px; font-weight: 600; color: #1a6e5c; display: block; margin-bottom: 6px;">Prescription</span>
                        @if($medicine->requires_prescription)
                            <span style="
                                display: inline-block;
                                font-size: 16px;
                                font-weight: 700;
                                padding: 6px 16px;
                                border-radius: 999px;
                                background: #faeeda;
                                color: #854f0b;
                                border: 1px solid #fac775;
                            ">Required</span>
                        @else
                            <span style="
                                display: inline-block;
                                font-size: 16px;
                                font-weight: 700;
                                padding: 6px 16px;
                                border-radius: 999px;
                                background: #eaf3de;
                                color: #3b6d11;
                                border: 1px solid #c0dd97;
                            ">Not Required</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div style="
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(8px);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(255,255,255,0.4);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            box-sizing: border-box;
        ">
            <h2 class="availability-title" style="
                font-size: clamp(24px, 5vw, 30px);
                font-weight: 700;
                color: #0f172a;
                margin: 0 0 6px 0;
            ">Availability in your local RHU</h2>
            <p style="
                font-size: 18px;
                color: #64748b;
                margin: 0 0 24px 0;
            ">Current stock visibility in your local RHU</p>

            @if(isset($availability) && $availability->count() > 0)
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    @foreach($availability as $item)
                        <div class="availability-card" style="
                            display: flex;
                            flex-wrap: wrap;
                            align-items: flex-start;
                            justify-content: space-between;
                            gap: 16px;
                            background: #ffffff;
                            border: 1px solid #e2e8f0;
                            border-radius: 16px;
                            padding: 20px;
                            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
                            transition: all 0.25s ease;
                            box-sizing: border-box;
                        "
                        onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 24px rgba(0,0,0,0.1)'; this.style.borderColor='#42ad8a';"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 6px rgba(0,0,0,0.04)'; this.style.borderColor='#e2e8f0';"
                        >
                            <div style="flex: 1;">
                                <h3 style="font-size: 20px; font-weight: 700; color: #0f172a; margin: 0;">{{ $item->name }}</h3>
                                <p style="font-size: 16px; color: #64748b; margin: 3px 0 0 0;">{{ $item->municipality }}</p>
                                <p style="font-size: 15px; color: #64748b; margin: 2px 0 0 0; line-height: 1.5;">{{ $item->address }}</p>
                            </div>

                            <div class="availability-tags" style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
                                <span style="
                                    font-size: 14px;
                                    font-weight: 600;
                                    padding: 5px 12px;
                                    border-radius: 999px;
                                    background: #e6f7f2;
                                    color: #1a5c4a;
                                    border: 0.5px solid #b2d8c9;
                                ">Available: {{ $item->total_available }}</span>

                                <span style="
                                    font-size: 14px;
                                    font-weight: 600;
                                    padding: 5px 12px;
                                    border-radius: 999px;
                                    background: #fcebeb;
                                    color: #a32d2d;
                                    border: 0.5px solid #f7c1c1;
                                ">Nearest Exp.: {{ $item->nearest_expiration ?? 'N/A' }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div style="
                    text-align: center;
                    padding: 40px 20px;
                    background: #f8fafc;
                    border-radius: 16px;
                    border: 1px dashed #cbd5e1;
                ">
                    <div style="font-size: 40px; margin-bottom: 8px;">📦</div>
                    <h3 style="font-size: 22px; font-weight: 600; color: #0f172a; margin: 0 0 6px 0;">No stock records found</h3>
                    <p style="font-size: 16px; color: #64748b; margin: 0;">
                        This medicine isn't currently listed in this RHU's inventory.
                    </p>
                </div>
            @endif
        </div>
    @else
        <div style="
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(255,255,255,0.4);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            text-align: center;
        ">
            <div style="font-size: 40px; margin-bottom: 8px;">⚠️</div>
            <h2 style="font-size: 26px; font-weight: 700; color: #0f172a; margin-bottom: 12px;">Medicine information unavailable</h2>
            <p style="color: #64748b; font-size: 16px; margin-bottom: 20px;">We could not load the requested medicine. Please try a new search.</p>
            <a href="{{ route('public.home.citizen') }}" class="btn-soft" style="display:inline-block; font-size: 16px;">Back to Search</a>
        </div>
    @endif

</div>
@endsection