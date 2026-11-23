@extends('layouts.app')

@section('title', 'Postpartum Care')

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
                Postpartum Visits
            </h2>
            <p style="font-size:1.0625rem; color:#64748b; margin:6px 0 0 0;">
                Track mother and newborn check-ups after delivery
            </p>
        </div>
        <div class="no-print" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            @if(\App\Support\ClinicalAudit::archiveVisible())
                <a href="{{ route('postpartum.index', $archived ? [] : ['archived' => 1]) }}" style="padding:10px 14px;color:#0f5c4b;text-decoration:underline;">
                    {{ $archived ? 'Current records' : 'Voided records' }}
                </a>
            @endif
            @unless($archived)
            <button type="button" aria-haspopup="dialog" aria-controls="pp-modal" onclick="openModal()" style="
            padding:10px 18px; border-radius:10px;
            background:#0f5c4b; color:#fff; border:1px solid #0f5c4b;
            font-size:1.0625rem; font-weight:600; cursor:pointer;">
            + Record Visit
        </button>
            @include('imports._link', ['section' => 'postpartum'])
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
            <p style="font-size:1.0625rem; font-weight:700; color:#64748b; text-transform:uppercase; margin:0 0 4px 0;">Total Visits</p>
            <p style="font-size:2rem; font-weight:700; color:#0f172a; margin:0;">{{ $stats['total'] }}</p>
        </div>
        <div style="background:#E8F7F4; border-radius:16px; padding:20px; border:1px solid #42ad8a;">
            <p style="font-size:1.0625rem; font-weight:700; color:#0f5c4b; text-transform:uppercase; margin:0 0 4px 0;">This Month</p>
            <p style="font-size:2rem; font-weight:700; color:#0f5c4b; margin:0;">{{ $stats['this_month'] }}</p>
        </div>
        <div style="background:#FCE7F3; border-radius:16px; padding:20px; border:1px solid #F9A8D4;">
            <p style="font-size:1.0625rem; font-weight:700; color:#9D174D; text-transform:uppercase; margin:0 0 4px 0;">Mothers</p>
            <p style="font-size:2rem; font-weight:700; color:#9D174D; margin:0;">{{ $stats['mothers'] }}</p>
        </div>
    </div>

    {{-- Filter --}}
    <div class="mw-page-card" style="background:rgba(255,255,255,0.95); border-radius:20px;
                border:1px solid rgba(66,173,138,0.15); padding:20px 24px;
                box-shadow:0 8px 20px rgba(0,0,0,0.06);">
        <form method="GET" action="{{ route('postpartum.index') }}"
              style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div style="flex:2 1 240px;">
                @if($archived)<input type="hidden" name="archived" value="1">@endif
                <label for="postpartum-filter-search" style="display:block; font-size:1.0625rem; font-weight:700; color:#475569; margin-bottom:6px; text-transform:uppercase;">Search</label>
                <input id="postpartum-filter-search" type="text" name="search" value="{{ $search }}" placeholder="Patient name" style="width:100%; min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
            </div>
            <div style="flex:1 1 160px;">
                <label for="postpartum-filter-visit_number" style="display:block; font-size:1.0625rem; font-weight:700; color:#475569; margin-bottom:6px; text-transform:uppercase;">Visit #</label>
                <select id="postpartum-filter-visit_number" name="visit_number" style="width:100%; min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; background:#fff;">
                    <option value="">All</option>
                    @for($n = 1; $n <= 5; $n++)
                        <option value="{{ $n }}" {{ (string) $visitNumber === (string) $n ? 'selected' : '' }}>Visit {{ $n }}</option>
                    @endfor
                </select>
            </div>
            <div style="display:flex; gap:8px;flex-wrap:wrap;">
                <button type="submit" style="min-height:2.75rem;height:auto; padding:0 22px; background:#0f5c4b; color:#fff; border:none; border-radius:10px; font-weight:600; font-size:1.0625rem; cursor:pointer;">Filter</button>
                <a href="{{ route('postpartum.index') }}" style="min-height:2.75rem;height:auto; padding:0 22px; background:#fff; color:#334155; border:1px solid #e2e8f0; border-radius:10px; font-weight:600; font-size:1.0625rem; text-decoration:none; display:inline-flex; align-items:center;">Reset</a>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div style="background:rgba(255,255,255,0.95); border-radius:20px;
                border:1px solid rgba(66,173,138,0.15); overflow:hidden;
                box-shadow:0 8px 20px rgba(0,0,0,0.06);">
        <div style="overflow-x:auto;">
            <table class="mw-mobile-records" style="width:100%; min-width:1000px; border-collapse:collapse; font-size:1.0625rem;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:700; text-transform:uppercase; color:#475569;">Patient</th>
                        <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:700; text-transform:uppercase; color:#475569;">Delivery Date</th>
                        <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:700; text-transform:uppercase; color:#475569;">Place</th>
                        <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:700; text-transform:uppercase; color:#475569;">Attended By</th>
                        <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:700; text-transform:uppercase; color:#475569;">Visit Date</th>
                        <th style="padding:14px 16px; text-align:left; font-size:1.0625rem; font-weight:700; text-transform:uppercase; color:#475569;">Next Visit</th>
                        <th style="padding:14px 16px; text-align:center; font-size:1.0625rem; font-weight:700; text-transform:uppercase; color:#475569;">Visit #</th>
                        <th style="padding:14px 16px; text-align:center; font-size:1.0625rem; font-weight:700; text-transform:uppercase; color:#475569;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($visits as $v)
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:14px 16px; font-weight:600; color:#0f172a;">{{ $v->patient?->full_name ?? '—' }}</td>
                            <td style="padding:14px 16px; color:#334155; white-space:nowrap;">{{ $v->delivery_date->format('M d, Y') }}</td>
                            <td style="padding:14px 16px; color:#334155;">{{ $v->place_of_delivery ?? '—' }}</td>
                            <td style="padding:14px 16px; color:#334155;">{{ $v->attended_by ?? '—' }}</td>
                            <td style="padding:14px 16px; color:#334155; white-space:nowrap;">{{ $v->visit_date->format('M d, Y') }}</td>
                            <td style="padding:14px 16px; white-space:nowrap;">
                                @if($v->next_visit_date)
                                    <span style="background:#FFEDD5; color:#9A3412; border:1px solid #FDBA74; padding:3px 10px; border-radius:999px; font-size:1.0625rem; font-weight:700;">
                                        {{ $v->next_visit_date->format('M d, Y') }}
                                    </span>
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>
                            <td style="padding:14px 16px; text-align:center;">
                                <span style="background:#E8F7F4; color:#0f5c4b; border:1px solid #42ad8a; padding:3px 10px; border-radius:999px; font-size:1.0625rem; font-weight:700;">
                                    {{ $v->visit_number }}
                                </span>
                            </td>
                            <td style="padding:14px 16px; text-align:center;">
                                @if($archived)
                    <form action="{{ route('postpartum.restore', $v->id) }}" method="POST" style="margin:0;" onsubmit="return confirmDialog(event, 'Restore this clinical record?');">
                        @csrf
                        <button type="submit" style="color:#0f5c4b;text-decoration:underline;">Restore</button>
                    </form>
                                @else
                                <form action="{{ route('postpartum.destroy', $v->id) }}" method="POST" style="margin:0;"
                                      onsubmit="return confirmDialog(event, 'Void this clinical record and retain it in the archive?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" style="background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:6px 12px; border-radius:8px; font-size:1.0625rem; font-weight:600; cursor:pointer;">Void</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="padding:60px 20px; text-align:center; color:#64748b;">
                                <p style="font-size:2rem; margin:0 0 8px 0;">👶</p>
                                <p style="font-size:1.0625rem; font-weight:600; color:#0f172a; margin:0;">{{ $archived ? 'No voided records match these filters.' : 'No postpartum visits recorded' }}</p>
                                @unless($archived)
                                <p style="font-size:1.0625rem; margin:6px 0 0 0;">Click "Record Visit" to add the first one.</p>
                                @endunless
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($visits->hasPages())
        <div style="background:rgba(255,255,255,0.95); border-radius:16px; padding:16px; border:1px solid rgba(66,173,138,0.15); display:flex; justify-content:center;">
            {{ $visits->links() }}
        </div>
    @endif
</div>

{{-- Modal --}}
<dialog id="pp-modal" class="clinical-dialog" aria-labelledby="pp-modal-title">
        <h3 id="pp-modal-title" tabindex="-1" autofocus style="font-size:1.25rem; font-weight:700; color:#0f172a; margin:0 0 20px 0;">Record Postpartum Visit</h3>

        @if($errors->any())
            <div id="postpartum-errors" role="alert" tabindex="-1" style="background:#FCEBEB; border:1px solid #F7C1C1; border-radius:12px; padding:14px 20px; margin-bottom:16px; color:#991b1b;">
                <p style="font-weight:700; margin:0 0 6px;">Please correct the following:</p>
                <ul style="margin:0; padding-left:20px;">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('postpartum.store') }}" style="display:flex; flex-direction:column; gap:14px;">
            @csrf

            <div>
                <label for="postpartum-field-patient_id" style="display:block; font-size:1.0625rem; font-weight:600; margin-bottom:6px;">Patient <span style="color:#dc2626;">*</span></label>
                <select id="postpartum-field-patient_id" name="patient_id" required style="width:100%; min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; background:#fff; box-sizing:border-box;">
                    <option value="">— Select patient —</option>
                    @foreach($patients as $p)
                        <option value="{{ $p->id }}" @selected((string) old('patient_id') === (string) $p->id)>{{ $p->full_name }} ({{ $p->age_display }})</option>
                    @endforeach
                </select>
            </div>

            <div class="mw-form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label for="postpartum-field-delivery_date" style="display:block; font-size:1.0625rem; font-weight:600; margin-bottom:6px;">Delivery Date <span style="color:#dc2626;">*</span></label>
                    <input id="postpartum-field-delivery_date" type="date" name="delivery_date" value="{{ old('delivery_date') }}" required max="{{ now()->toDateString() }}" style="width:100%; min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
                </div>
                <div>
                    <label for="postpartum-visit-date" style="display:block; font-size:1.0625rem; font-weight:600; margin-bottom:6px;">Visit Date <span style="color:#dc2626;">*</span></label>
                    <input type="date" id="postpartum-visit-date" name="visit_date" required value="{{ old('visit_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" style="width:100%; min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
                </div>
            </div>

            <div>
                <label for="postpartum-next-visit-date" style="display:block; font-size:1.0625rem; font-weight:600; margin-bottom:6px;">Next Visit Date</label>
                <input type="date" id="postpartum-next-visit-date" name="next_visit_date" value="{{ old('next_visit_date') }}" style="width:100%; min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
            </div>

            <div class="mw-form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label for="postpartum-field-place_of_delivery" style="display:block; font-size:1.0625rem; font-weight:600; margin-bottom:6px;">Place of Delivery</label>
                    <input id="postpartum-field-place_of_delivery" type="text" name="place_of_delivery" value="{{ old('place_of_delivery') }}" maxlength="100" placeholder="e.g. RHU, Hospital, Home" style="width:100%; min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
                </div>
                <div>
                    <label for="postpartum-field-attended_by" style="display:block; font-size:1.0625rem; font-weight:600; margin-bottom:6px;">Attended By</label>
                    <input id="postpartum-field-attended_by" type="text" name="attended_by" value="{{ old('attended_by') }}" maxlength="100" placeholder="e.g. Dr. Cruz, Midwife Santos" style="width:100%; min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
                </div>
            </div>

            <div>
                <label for="postpartum-field-visit_number" style="display:block; font-size:1.0625rem; font-weight:600; margin-bottom:6px;">Visit Number</label>
                <select id="postpartum-field-visit_number" name="visit_number" style="width:100%; min-height:2.75rem;height:auto; padding:0 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; background:#fff; box-sizing:border-box;">
                    <option value="1" @selected((string) old('visit_number', 1) === '1')>Visit 1 (within 24h)</option>
                    <option value="2" @selected((string) old('visit_number', 1) === '2')>Visit 2 (Day 3–7)</option>
                    <option value="3" @selected((string) old('visit_number', 1) === '3')>Visit 3 (Week 2–4)</option>
                    <option value="4" @selected((string) old('visit_number', 1) === '4')>Visit 4 (Week 6)</option>
                </select>
            </div>

            <div>
                <label for="postpartum-field-remarks" style="display:block; font-size:1.0625rem; font-weight:600; margin-bottom:6px;">Remarks</label>
                <textarea id="postpartum-field-remarks" name="remarks" rows="2" maxlength="2000" style="width:100%; padding:12px 14px; font-size:1.0625rem; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box; resize:vertical;">{{ old('remarks') }}</textarea>
            </div>

            <div style="display:flex; flex-wrap:wrap; gap:10px; justify-content:flex-end; margin-top:8px;">
                <button type="button" onclick="closeModal()" style="padding:10px 20px; border-radius:10px; background:#fff; color:#334155; border:1px solid #e2e8f0; font-weight:600; font-size:1.0625rem; cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:10px 20px; border-radius:10px; background:#0f5c4b; color:#fff; border:none; font-weight:700; font-size:1.0625rem; cursor:pointer;">Save Visit</button>
            </div>
        </form>
</dialog>

<script>
function openModal() { document.getElementById('pp-modal').showModal(); }
function closeModal() { document.getElementById('pp-modal').close(); }

function updateNextVisitMinimum() {
    const visit = document.getElementById('postpartum-visit-date');
    const next = document.getElementById('postpartum-next-visit-date');
    if (!visit.value) { next.removeAttribute('min'); return; }
    const day = new Date(visit.value + 'T12:00:00');
    day.setDate(day.getDate() + 1);
    next.min = `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`;
}
document.getElementById('postpartum-visit-date').addEventListener('input', updateNextVisitMinimum);
updateNextVisitMinimum();
@if($errors->any())
openModal();
document.getElementById('postpartum-errors').focus();
@endif
</script>
@endsection
