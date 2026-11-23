@extends('layouts.app')

@section('title', 'Family Planning')

@section('content')
<link rel="stylesheet" href="{{ asset('css/clinical-dialog.css') }}">
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header --}}
    <div class="mw-page-card" style="background:rgba(255,255,255,0.9); backdrop-filter:blur(12px);
                border-radius:20px; padding:24px 28px;
                border:1px solid rgba(255,255,255,0.4);
                box-shadow:0 8px 20px rgba(0,0,0,0.06);
                display:flex; align-items:center; justify-content:space-between;
                flex-wrap:wrap; gap:16px;">
        <div>
            <p style="font-size:1.0625rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">
                Maternal Care
            </p>
            <h2 style="font-size:1.5rem; font-weight:700; color:#0f172a; margin:0;">
                Family Planning
            </h2>
            <p style="font-size:1.0625rem; color:#64748b; margin:6px 0 0 0;">
                Track clients, methods, and next supply dates
            </p>
        </div>
        <div class="no-print" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            @if(\App\Support\ClinicalAudit::archiveVisible())
                <a href="{{ route('family_planning.index', $archived ? [] : ['archived' => 1]) }}" style="padding:10px 14px;color:#0f5c4b;text-decoration:underline;">
                    {{ $archived ? 'Current records' : 'Voided records' }}
                </a>
            @endif
            @unless($archived)
            <button type="button" aria-haspopup="dialog" aria-controls="fp-modal" onclick="openModal()" style="
            padding:10px 18px; border-radius:10px;
            background:#0f5c4b; color:#fff; border:1px solid #0f5c4b;
            font-size:1.0625rem; font-weight:600; cursor:pointer;">
            + New Record
        </button>
            @include('imports._link', ['section' => 'family_planning'])
            @endunless
        </div>
    </div>

    @if(session('success'))
        <div style="background:#E8F7F4; border:1px solid #42ad8a; border-radius:12px; padding:14px 20px; color:#0f5c4b; font-size:1.0625rem;">
            ✓ {{ session('success') }}
        </div>
    @endif

    {{-- Stats --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px;">
        <div style="background:#fff; border-radius:16px; padding:20px; border:1px solid #e2e8f0;">
            <p style="font-size:1.0625rem; font-weight:700; color:#64748b; text-transform:uppercase; margin:0 0 4px 0;">Total Clients</p>
            <p style="font-size:2rem; font-weight:700; color:#0f172a; margin:0;">{{ $stats['total'] }}</p>
        </div>
        <div style="background:#E8F7F4; border-radius:16px; padding:20px; border:1px solid #42ad8a;">
            <p style="font-size:1.0625rem; font-weight:700; color:#0f5c4b; text-transform:uppercase; margin:0 0 4px 0;">This Month</p>
            <p style="font-size:2rem; font-weight:700; color:#0f5c4b; margin:0;">{{ $stats['this_month'] }}</p>
        </div>
        <div style="background:#FEF3C7; border-radius:16px; padding:20px; border:1px solid #FAC775;">
            <p style="font-size:1.0625rem; font-weight:700; color:#854F0B; text-transform:uppercase; margin:0 0 4px 0;">Due for Supply</p>
            <p style="font-size:2rem; font-weight:700; color:#633806; margin:0;">{{ $stats['due_for_supply'] }}</p>
            <p style="font-size:1.0625rem; color:#854F0B; margin:4px 0 0 0;">next 30 days</p>
        </div>
    </div>

    {{-- Filter --}}
    <div class="mw-page-card" style="background:rgba(255,255,255,0.95); border-radius:20px;
                border:1px solid rgba(66,173,138,0.15); padding:20px 24px;
                box-shadow:0 8px 20px rgba(0,0,0,0.06);">
        <form method="GET" action="{{ route('family_planning.index') }}"
              style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div style="flex:2 1 240px;">
                @if($archived)<input type="hidden" name="archived" value="1">@endif
                <label for="family-planning-filter-search" style="display:block; font-size:1.0625rem; font-weight:700; color:#475569; margin-bottom:6px; text-transform:uppercase;">Search</label>
                <input id="family-planning-filter-search" type="text" name="search" value="{{ $search }}" placeholder="Client name" style="width:100%; min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
            </div>
            <div style="flex:1 1 180px;">
                <label for="family-planning-filter-method" style="display:block; font-size:1.0625rem; font-weight:700; color:#475569; margin-bottom:6px; text-transform:uppercase;">Method</label>
                <select id="family-planning-filter-method" name="method" style="width:100%; min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; background:#fff;">
                    <option value="">All methods</option>
                    @foreach($methods as $m)
                        <option value="{{ $m }}" {{ $method === $m ? 'selected' : '' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:1 1 160px;">
                <label for="family-planning-filter-supply" style="display:block; font-size:1.0625rem; font-weight:700; color:#475569; margin-bottom:6px; text-transform:uppercase;">Supply</label>
                <select id="family-planning-filter-supply" name="supply" style="width:100%; min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; background:#fff;">
                    <option value="">Any</option>
                    <option value="due" {{ $supplyFilter === 'due' ? 'selected' : '' }}>Due in 30 days</option>
                </select>
            </div>
            <div style="display:flex; gap:8px;flex-wrap:wrap;">
                <button type="submit" style="min-height:2.75rem;height:auto; padding:0 22px; background:#0f5c4b; color:#fff; border:none; border-radius:10px; font-weight:600; font-size:1.0625rem; cursor:pointer;">Filter</button>
                <a href="{{ route('family_planning.index') }}" style="min-height:2.75rem;height:auto; padding:0 22px; background:#fff; color:#334155; border:1px solid #e2e8f0; border-radius:10px; font-weight:600; font-size:1.0625rem; text-decoration:none; display:inline-flex; align-items:center;">Reset</a>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div style="background:rgba(255,255,255,0.95); border-radius:20px;
                border:1px solid rgba(66,173,138,0.15); overflow:hidden;
                box-shadow:0 8px 20px rgba(0,0,0,0.06);">
        <div style="overflow-x:auto;">
            <table class="mw-mobile-records" style="width:100%; min-width:800px; border-collapse:collapse; font-size:1.0625rem;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:700; text-transform:uppercase; color:#475569;">Client</th>
                        <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:700; text-transform:uppercase; color:#475569;">Method</th>
                        <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:700; text-transform:uppercase; color:#475569;">Date Accepted</th>
                        <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:700; text-transform:uppercase; color:#475569;">Next Supply</th>
                        <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:700; text-transform:uppercase; color:#475569;">Remarks</th>
                        <th style="padding:14px 16px; text-align:center; font-size:1.0625rem; font-weight:700; text-transform:uppercase; color:#475569;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $r)
                        @php
                            $dueSoon = $r->next_supply_date
                                && $r->next_supply_date->isBetween(now(), now()->addDays(30));
                        @endphp
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:14px 16px; font-weight:600; color:#0f172a;">{{ $r->patient?->full_name ?? '—' }}</td>
                            <td style="padding:14px 16px;">
                                <span style="background:#E8F7F4; color:#0f5c4b; border:1px solid #42ad8a; padding:3px 10px; border-radius:999px; font-size:1.0625rem; font-weight:700;">
                                    {{ $r->method }}
                                </span>
                            </td>
                            <td style="padding:14px 16px; color:#334155; white-space:nowrap;">{{ $r->date_accepted->format('M d, Y') }}</td>
                            <td style="padding:14px 16px; color:#334155; white-space:nowrap;">
                                @if($r->next_supply_date)
                                    {{ $r->next_supply_date->format('M d, Y') }}
                                    @if($dueSoon)
                                        <span style="margin-left:6px; background:#FEF3C7; color:#92400E; border:1px solid #FAC775; padding:2px 8px; border-radius:999px; font-size:1.0625rem; font-weight:700;">Due</span>
                                    @endif
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>
                            <td style="padding:14px 16px; color:#64748b; font-size:1.0625rem;">{{ $r->remarks ?? '—' }}</td>
                            <td style="padding:14px 16px; text-align:center;">
                                @if($archived)
                    <form action="{{ route('family_planning.restore', $r->id) }}" method="POST" style="margin:0;" onsubmit="return confirmDialog(event, 'Restore this clinical record?');">
                        @csrf
                        <button type="submit" style="color:#0f5c4b;text-decoration:underline;">Restore</button>
                    </form>
                                @else
                                <form action="{{ route('family_planning.destroy', $r->id) }}" method="POST" style="margin:0;"
                                      onsubmit="return confirmDialog(event, 'Void this clinical record and retain it in the archive?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" style="background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:6px 12px; border-radius:8px; font-size:1.0625rem; font-weight:600; cursor:pointer;">Void</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:60px 20px; text-align:center; color:#64748b;">
                                <p style="font-size:2rem; margin:0 0 8px 0;">👨‍👩‍👧</p>
                                <p style="font-size:1.0625rem; font-weight:600; color:#0f172a; margin:0;">{{ $archived ? 'No voided records match these filters.' : 'No family planning records' }}</p>
                                @unless($archived)
                                <p style="font-size:1.0625rem; margin:6px 0 0 0;">Click "New Record" to add the first one.</p>
                                @endunless
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($records->hasPages())
        <div style="background:rgba(255,255,255,0.95); border-radius:16px; padding:16px; border:1px solid rgba(66,173,138,0.15); display:flex; justify-content:center;">
            {{ $records->links() }}
        </div>
    @endif
</div>

{{-- Modal --}}
<dialog id="fp-modal" class="clinical-dialog" aria-labelledby="fp-modal-title">
        <h3 id="fp-modal-title" tabindex="-1" autofocus style="font-size:1.25rem; font-weight:700; color:#0f172a; margin:0 0 20px 0;">New Family Planning Record</h3>

        @if($errors->any())
            <div id="family-planning-errors" role="alert" tabindex="-1" style="background:#FCEBEB; border:1px solid #F7C1C1; border-radius:12px; padding:14px 20px; margin-bottom:16px; color:#991b1b;">
                <p style="font-weight:700; margin:0 0 6px;">Please correct the following:</p>
                <ul style="margin:0; padding-left:20px;">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('family_planning.store') }}" style="display:flex; flex-direction:column; gap:14px;">
            @csrf

            <div>
                <label for="family-planning-field-patient_id" style="display:block; font-size:1.0625rem; font-weight:600; margin-bottom:6px;">Client <span style="color:#dc2626;">*</span></label>
                <select id="family-planning-field-patient_id" name="patient_id" required style="width:100%; min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; background:#fff; box-sizing:border-box;">
                    <option value="">— Select patient —</option>
                    @foreach($patients as $p)
                        <option value="{{ $p->id }}" @selected((string) old('patient_id') === (string) $p->id)>{{ $p->full_name }} ({{ $p->age_display }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="family-planning-field-method" style="display:block; font-size:1.0625rem; font-weight:600; margin-bottom:6px;">Method <span style="color:#dc2626;">*</span></label>
                <select id="family-planning-field-method" name="method" required style="width:100%; min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; background:#fff; box-sizing:border-box;">
                    <option value="">— Select method —</option>
                    @foreach($commonMethods as $m)
                        <option value="{{ $m }}" @selected(old('method') === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mw-form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label for="family-planning-field-date_accepted" style="display:block; font-size:1.0625rem; font-weight:600; margin-bottom:6px;">Date Accepted <span style="color:#dc2626;">*</span></label>
                    <input id="family-planning-field-date_accepted" type="date" name="date_accepted" required value="{{ old('date_accepted', now()->toDateString()) }}" max="{{ now()->toDateString() }}" style="width:100%; min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
                </div>
                <div>
                    <label for="family-planning-field-next_supply_date" style="display:block; font-size:1.0625rem; font-weight:600; margin-bottom:6px;">Next Supply Date</label>
                    <input id="family-planning-field-next_supply_date" type="date" name="next_supply_date" value="{{ old('next_supply_date') }}" style="width:100%; min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
                </div>
            </div>

            <div>
                <label for="family-planning-field-remarks" style="display:block; font-size:1.0625rem; font-weight:600; margin-bottom:6px;">Remarks</label>
                <textarea id="family-planning-field-remarks" name="remarks" rows="2" maxlength="2000" style="width:100%; padding:12px 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box; resize:vertical;">{{ old('remarks') }}</textarea>
            </div>

            <div style="display:flex; flex-wrap:wrap; gap:10px; justify-content:flex-end; margin-top:8px;">
                <button type="button" onclick="closeModal()" style="padding:10px 20px; border-radius:10px; background:#fff; color:#334155; border:1px solid #e2e8f0; font-weight:600; font-size:1.0625rem; cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:10px 20px; border-radius:10px; background:#0f5c4b; color:#fff; border:none; font-weight:700; font-size:1.0625rem; cursor:pointer;">Save Record</button>
            </div>
        </form>
</dialog>

<script>
function openModal() { document.getElementById('fp-modal').showModal(); }
function closeModal() { document.getElementById('fp-modal').close(); }

@if($errors->any())
openModal();
document.getElementById('family-planning-errors').focus();
@endif
</script>
@endsection
