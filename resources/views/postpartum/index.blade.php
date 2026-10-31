@extends('layouts.app')

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header --}}
    <div style="background:rgba(255,255,255,0.9); backdrop-filter:blur(12px);
                border-radius:20px; padding:24px 28px;
                border:1px solid rgba(255,255,255,0.4);
                box-shadow:0 8px 20px rgba(0,0,0,0.06);
                display:flex; align-items:center; justify-content:space-between;
                flex-wrap:wrap; gap:16px;">
        <div>
            <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">
                Maternal Care
            </p>
            <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">
                Postpartum Visits
            </h2>
            <p style="font-size:15px; color:#64748b; margin:6px 0 0 0;">
                Track mother and newborn check-ups after delivery
            </p>
        </div>
        <button onclick="openModal()" style="
            padding:10px 18px; border-radius:10px;
            background:#42ad8a; color:#fff; border:1px solid #42ad8a;
            font-size:16px; font-weight:600; cursor:pointer;">
            + Record Visit
        </button>
    </div>

    @if(session('success'))
        <div style="background:#E8F7F4; border:1px solid #42ad8a; border-radius:12px; padding:14px 20px; color:#0f5c4b; font-size:16px;">
            ✓ {{ session('success') }}
        </div>
    @endif

    {{-- Stats --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px;">
        <div style="background:#fff; border-radius:16px; padding:20px; border:1px solid #e2e8f0;">
            <p style="font-size:13px; font-weight:700; color:#64748b; text-transform:uppercase; margin:0 0 4px 0;">Total Visits</p>
            <p style="font-size:32px; font-weight:700; color:#0f172a; margin:0;">{{ $stats['total'] }}</p>
        </div>
        <div style="background:#E8F7F4; border-radius:16px; padding:20px; border:1px solid #42ad8a;">
            <p style="font-size:13px; font-weight:700; color:#0f5c4b; text-transform:uppercase; margin:0 0 4px 0;">This Month</p>
            <p style="font-size:32px; font-weight:700; color:#0f5c4b; margin:0;">{{ $stats['this_month'] }}</p>
        </div>
        <div style="background:#FCE7F3; border-radius:16px; padding:20px; border:1px solid #F9A8D4;">
            <p style="font-size:13px; font-weight:700; color:#9D174D; text-transform:uppercase; margin:0 0 4px 0;">Mothers</p>
            <p style="font-size:32px; font-weight:700; color:#9D174D; margin:0;">{{ $stats['mothers'] }}</p>
        </div>
    </div>

    {{-- Filter --}}
    <div style="background:rgba(255,255,255,0.95); border-radius:20px;
                border:1px solid rgba(66,173,138,0.15); padding:20px 24px;
                box-shadow:0 8px 20px rgba(0,0,0,0.06);">
        <form method="GET" action="{{ route('postpartum.index') }}"
              style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div style="flex:2 1 240px;">
                <label style="display:block; font-size:13px; font-weight:700; color:#475569; margin-bottom:6px; text-transform:uppercase;">Search</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Patient name" style="width:100%; height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
            </div>
            <div style="flex:1 1 160px;">
                <label style="display:block; font-size:13px; font-weight:700; color:#475569; margin-bottom:6px; text-transform:uppercase;">Visit #</label>
                <select name="visit_number" style="width:100%; height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; background:#fff;">
                    <option value="">All</option>
                    @for($n = 1; $n <= 5; $n++)
                        <option value="{{ $n }}" {{ (string) $visitNumber === (string) $n ? 'selected' : '' }}>Visit {{ $n }}</option>
                    @endfor
                </select>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" style="height:44px; padding:0 22px; background:#42ad8a; color:#fff; border:none; border-radius:10px; font-weight:600; font-size:15px; cursor:pointer;">Filter</button>
                <a href="{{ route('postpartum.index') }}" style="height:44px; padding:0 22px; background:#fff; color:#334155; border:1px solid #e2e8f0; border-radius:10px; font-weight:600; font-size:15px; text-decoration:none; display:inline-flex; align-items:center;">Reset</a>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div style="background:rgba(255,255,255,0.95); border-radius:20px;
                border:1px solid rgba(66,173,138,0.15); overflow:hidden;
                box-shadow:0 8px 20px rgba(0,0,0,0.06);">
        <div style="overflow-x:auto;">
            <table style="width:100%; min-width:1000px; border-collapse:collapse; font-size:15px;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <th style="padding:14px 16px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Patient</th>
                        <th style="padding:14px 16px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Delivery Date</th>
                        <th style="padding:14px 16px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Place</th>
                        <th style="padding:14px 16px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Attended By</th>
                        <th style="padding:14px 16px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Visit Date</th>
                        <th style="padding:14px 16px; text-align:left; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Next Visit</th>
                        <th style="padding:14px 16px; text-align:center; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Visit #</th>
                        <th style="padding:14px 16px; text-align:center; font-size:13px; font-weight:700; text-transform:uppercase; color:#475569;">Actions</th>
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
                                    <span style="background:#FFEDD5; color:#9A3412; border:1px solid #FDBA74; padding:3px 10px; border-radius:999px; font-size:13px; font-weight:700;">
                                        {{ $v->next_visit_date->format('M d, Y') }}
                                    </span>
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>
                            <td style="padding:14px 16px; text-align:center;">
                                <span style="background:#E8F7F4; color:#0f5c4b; border:1px solid #42ad8a; padding:3px 10px; border-radius:999px; font-size:13px; font-weight:700;">
                                    {{ $v->visit_number }}
                                </span>
                            </td>
                            <td style="padding:14px 16px; text-align:center;">
                                <form action="{{ route('postpartum.destroy', $v->id) }}" method="POST" style="margin:0;"
                                      onsubmit="return confirmDialog(event, 'Delete this visit?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" style="background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:6px 12px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="padding:60px 20px; text-align:center; color:#64748b;">
                                <p style="font-size:32px; margin:0 0 8px 0;">👶</p>
                                <p style="font-size:16px; font-weight:600; color:#0f172a; margin:0;">No postpartum visits recorded</p>
                                <p style="font-size:14px; margin:6px 0 0 0;">Click "Record Visit" to add the first one.</p>
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
<div id="pp-modal" onclick="if(event.target===this)closeModal()"
     style="display:none; position:fixed; inset:0; z-index:99999;
            background:rgba(15,23,42,0.5); backdrop-filter:blur(2px);
            align-items:center; justify-content:center; padding:20px;">
    <div style="background:#fff; border-radius:20px; max-width:560px; width:100%;
                max-height:90vh; overflow-y:auto; padding:28px;
                box-shadow:0 20px 40px rgba(0,0,0,0.2);">
        <h3 style="font-size:20px; font-weight:700; color:#0f172a; margin:0 0 20px 0;">Record Postpartum Visit</h3>

        <form method="POST" action="{{ route('postpartum.store') }}" style="display:flex; flex-direction:column; gap:14px;">
            @csrf

            <div>
                <label style="display:block; font-size:14px; font-weight:600; margin-bottom:6px;">Patient <span style="color:#dc2626;">*</span></label>
                <select name="patient_id" required style="width:100%; height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; background:#fff; box-sizing:border-box;">
                    <option value="">— Select patient —</option>
                    @foreach($patients as $p)
                        <option value="{{ $p->id }}">{{ $p->full_name }} ({{ $p->age_display }})</option>
                    @endforeach
                </select>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label style="display:block; font-size:14px; font-weight:600; margin-bottom:6px;">Delivery Date <span style="color:#dc2626;">*</span></label>
                    <input type="date" name="delivery_date" required max="{{ now()->toDateString() }}" style="width:100%; height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:14px; font-weight:600; margin-bottom:6px;">Visit Date <span style="color:#dc2626;">*</span></label>
                    <input type="date" name="visit_date" required value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" style="width:100%; height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
                </div>
            </div>

            <div>
                <label style="display:block; font-size:14px; font-weight:600; margin-bottom:6px;">Next Visit Date</label>
                <input type="date" name="next_visit_date" min="{{ now()->addDay()->toDateString() }}" style="width:100%; height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label style="display:block; font-size:14px; font-weight:600; margin-bottom:6px;">Place of Delivery</label>
                    <input type="text" name="place_of_delivery" placeholder="e.g. RHU, Hospital, Home" style="width:100%; height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:14px; font-weight:600; margin-bottom:6px;">Attended By</label>
                    <input type="text" name="attended_by" placeholder="e.g. Dr. Cruz, Midwife Santos" style="width:100%; height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box;">
                </div>
            </div>

            <div>
                <label style="display:block; font-size:14px; font-weight:600; margin-bottom:6px;">Visit Number</label>
                <select name="visit_number" style="width:100%; height:44px; padding:0 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; background:#fff; box-sizing:border-box;">
                    <option value="1">Visit 1 (within 24h)</option>
                    <option value="2">Visit 2 (Day 3–7)</option>
                    <option value="3">Visit 3 (Week 2–4)</option>
                    <option value="4">Visit 4 (Week 6)</option>
                </select>
            </div>

            <div>
                <label style="display:block; font-size:14px; font-weight:600; margin-bottom:6px;">Remarks</label>
                <textarea name="remarks" rows="2" maxlength="2000" style="width:100%; padding:12px 14px; font-size:15px; border:2px solid #bec6cf; border-radius:10px; box-sizing:border-box; resize:vertical;"></textarea>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:8px;">
                <button type="button" onclick="closeModal()" style="padding:10px 20px; border-radius:10px; background:#fff; color:#334155; border:1px solid #e2e8f0; font-weight:600; font-size:15px; cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:10px 20px; border-radius:10px; background:#42ad8a; color:#fff; border:none; font-weight:700; font-size:15px; cursor:pointer;">Save Visit</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal()  { document.getElementById('pp-modal').style.display = 'flex'; document.body.style.overflow='hidden'; }
function closeModal() { document.getElementById('pp-modal').style.display = 'none'; document.body.style.overflow=''; }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
@endsection