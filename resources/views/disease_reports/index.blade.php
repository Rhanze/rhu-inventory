@extends('layouts.app')

@section('content')
@php
    $defaultDate = $periodType === 'monthly'
        ? today()->startOfMonth()->subDay()->toDateString()
        : today()->startOfWeek(\Carbon\Carbon::MONDAY)->subDay()->toDateString();
    $editingId = (int) old('report_id', 0);
    $statusLabels = ['ready' => 'Ready', 'insufficient_history' => 'Need at least four confirmed observations', 'latest_period_missing' => 'Latest completed period needs a confirmed report'];
@endphp
<style>
.disease-stack { display:flex;flex-direction:column;gap:20px;min-width:0; }
.disease-card { background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:24px; }
.disease-heading { font-size:24px;font-weight:700;margin:0 0 8px;color:#0f172a; }
.disease-muted { color:#64748b;font-size:14px; }
.disease-row { display:flex;gap:16px;flex-wrap:wrap;align-items:end; }
.disease-row label,.disease-form-label { display:block;font-size:14px;font-weight:600;margin-bottom:6px; }
.disease-control { padding:10px 12px;border:1px solid #94a3b8;border-radius:9px;max-width:100%;background:#fff;color:#0f172a; }
.disease-button { display:inline-block;padding:10px 16px;border:1px solid #0f766e;border-radius:9px;background:#0f766e;color:white;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none; }
.disease-secondary { color:#0f766e;background:white; }
.disease-table { width:100%;border-collapse:collapse;min-width:920px;text-align:left;font-size:14px; }
.disease-table th,.disease-table td { padding:12px;border-bottom:1px solid #e2e8f0;vertical-align:top; }
.disease-table th { background:#f8fafc;color:#475569; }
.disease-scroll { overflow-x:auto;max-width:100%; }
.disease-stat { flex:1;min-width:200px;padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px; }
.disease-stat strong { font-size:20px;display:block;color:#0f172a; }
</style>
<div class="disease-stack">
    <div class="disease-card">
        <h1 class="disease-heading">Disease reports and case forecasts</h1>
        <p class="disease-muted">Review reporting completeness, the linear case trend, and Holt's adaptive forecast for your RHU.</p>
        <div class="disease-row" style="margin-top:16px;">
            <button class="disease-button" type="button" onclick="openNewDiseaseReport()">Report disease</button>
            <a class="disease-button disease-secondary" href="{{ route('disease_reports.export', ['period_type' => $periodType, 'disease_id' => $selectedDiseaseId]) }}">Export reports CSV</a>
        </div>
    </div>
    @if($errors->any())
    <div class="disease-card" role="alert" style="border-color:#fca5a5;">
        <strong>Please review the report.</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
    @endif
    <section class="disease-card" aria-labelledby="disease-analysis-heading">
        <h2 id="disease-analysis-heading" style="font-size:20px;font-weight:700;">Disease trend analysis</h2>
        <form method="GET" action="{{ route('disease_reports.index') }}" class="disease-row" style="margin:16px 0;">
            <div><label for="disease-filter">Disease</label>
                <select class="disease-control" id="disease-filter" name="disease_id">
                    @foreach($diseases as $disease)<option value="{{ $disease->id }}" @selected((int) $selectedDiseaseId === $disease->id)>{{ $disease->name }}</option>@endforeach
                </select>
            </div>
            <div><label for="disease-period-filter">Reporting frequency</label>
                <select class="disease-control" id="disease-period-filter" name="period_type">
                    <option value="weekly" @selected($periodType === 'weekly')>Weekly (Monday to Sunday)</option>
                    <option value="monthly" @selected($periodType === 'monthly')>Monthly (calendar month)</option>
                </select>
            </div>
            <div><label for="disease-history-filter">History periods</label>
                <input class="disease-control" id="disease-history-filter" type="number" name="periods" min="4" max="52" value="{{ request('periods', config('disease_analysis.history_periods', 12)) }}" style="width:100px;">
            </div>
            <button class="disease-button" type="submit">Analyze</button>
        </form>
        @include('disease_reports._analysis')
    </section>
    @if(count($outbreakStatuses))
    <section class="disease-card" aria-labelledby="manual-outbreaks-heading">
        <h2 id="manual-outbreaks-heading" style="font-size:20px;font-weight:700;">Manually flagged outbreaks</h2>
        <p class="disease-muted">Trend estimates do not declare or close outbreaks. Closing a flag preserves the reported case totals.</p>
        @foreach($outbreakStatuses as $ob)
        <div class="disease-row" style="padding:16px 0;border-bottom:1px solid #e2e8f0;justify-content:space-between;">
            <div><strong>{{ $ob['disease_name'] }}</strong><p class="disease-muted">Flagged on report dated {{ $ob['start_date']->format('M d, Y') }}. {{ ucfirst($ob['period_type']) }} trend: {{ str_replace('_', ' ', $ob['trend']) }}.</p></div>
            <form method="POST" action="{{ route('disease_reports.close_outbreak', $ob['disease_id']) }}">
                @csrf
                <label class="disease-form-label" for="outbreak-note-{{ $ob['disease_id'] }}">Optional closing note</label>
                <input id="outbreak-note-{{ $ob['disease_id'] }}" class="disease-control" name="remarks" maxlength="500">
                <button class="disease-button disease-secondary" type="submit">Close flag</button>
            </form>
        </div>
        @endforeach
    </section>
    @endif
    <section class="disease-card">
        <h2 style="font-size:20px;font-weight:700;">{{ ucfirst($periodType) }} report history</h2>
        <p class="disease-muted">Needs review means the original period was not explicitly confirmed. Use Review / edit to verify it. Missing reports remain unknown; enter zero only when zero cases were actually reported.</p>
        <div class="disease-scroll">
            <table class="disease-table"><thead><tr><th>Report date</th><th>Disease</th><th>Reporting period</th><th>Cases</th><th>Data status</th><th>Outbreak flag</th><th>Remarks</th><th>Actions</th></tr></thead>
                <tbody>@forelse($reports as $report)
                <tr>
                    <td>{{ $report->report_date->format('M d, Y') }}</td><td>{{ $report->disease->name ?? 'Unknown' }}</td>
                    <td>{{ $report->period_start?->toDateString() ?? 'Unverified' }} to {{ $report->period_end?->toDateString() ?? 'Unverified' }}</td>
                    <td>{{ $report->case_count }}</td><td>{{ !$report->period_start ? 'Needs review' : ($report->is_complete ? 'Confirmed' : 'Provisional') }}</td>
                    <td>{{ $report->outbreak_closed_at ? 'Closed' : ($report->is_outbreak ? 'Flagged' : 'No flag') }}</td><td>{{ $report->remarks }}</td>
                    <td>
                        <button type="button" class="disease-button disease-secondary" data-report="{{ json_encode(['id' => $report->id, 'disease_id' => $report->disease_id, 'report_date' => $report->report_date->toDateString(), 'period_type' => $report->period_type, 'case_count' => $report->case_count, 'is_complete' => $report->is_complete, 'is_outbreak' => $report->is_outbreak, 'remarks' => $report->remarks]) }}" onclick="editDiseaseReport(this)">Review / edit</button>
                        <form method="POST" action="{{ route('disease_reports.destroy', $report->id) }}" style="margin-top:8px;">@csrf @method('DELETE')<button class="disease-button disease-secondary" type="submit">Delete</button></form>
                    </td>
                </tr>
                @empty<tr><td colspan="8">No reports for this disease and frequency.</td></tr>@endforelse</tbody>
            </table>
        </div>
        <div style="margin-top:16px;">{{ $reports->links() }}</div>
    </section>
</div>
<dialog id="disease-report-dialog" style="border:1px solid #cbd5e1;border-radius:18px;padding:24px;width:560px;max-width:95vw;max-height:90vh;overflow:auto;">
    <h2 id="disease-report-title" style="font-size:22px;font-weight:700;">Disease period report</h2>
    <form id="disease-report-form" method="POST" action="{{ $editingId ? route('disease_reports.update', $editingId) : route('disease_reports.store') }}">
        @csrf
        <input type="hidden" id="disease-report-method" name="_method" value="{{ $editingId ? 'PUT' : 'POST' }}">
        <input type="hidden" id="disease-report-id" name="report_id" value="{{ $editingId }}">
        <div style="margin:16px 0;"><label class="disease-form-label" for="disease-entry-id">Disease</label>
            <select id="disease-entry-id" name="disease_id" class="disease-control" required style="width:100%;">
                @foreach($diseases as $disease)<option value="{{ $disease->id }}" @selected((int) old('disease_id', $selectedDiseaseId) === $disease->id)>{{ $disease->name }}</option>@endforeach
            </select>
        </div>
        <div class="disease-row">
            <div><label class="disease-form-label" for="disease-entry-frequency">Reporting frequency</label>
                <select id="disease-entry-frequency" name="period_type" class="disease-control"><option value="weekly" @selected(old('period_type', $periodType) === 'weekly')>Weekly</option><option value="monthly" @selected(old('period_type', $periodType) === 'monthly')>Monthly</option></select>
            </div>
            <div><label class="disease-form-label" for="disease-entry-date">A date within the reporting period</label>
                <input id="disease-entry-date" type="date" class="disease-control" name="report_date" max="{{ today()->toDateString() }}" required value="{{ old('report_date', $defaultDate) }}">
            </div>
        </div>
        <p class="disease-muted">Weekly totals cover Monday through Sunday; monthly totals cover the calendar month containing the selected date.</p>
        <div style="margin:16px 0;"><label class="disease-form-label" for="disease-entry-cases">Total cases in that period</label><input id="disease-entry-cases" type="number" class="disease-control" name="case_count" required min="0" max="100000" value="{{ old('case_count') }}"></div>
        <input type="hidden" name="is_complete" value="0">
        <label style="display:flex;gap:8px;align-items:start;margin:16px 0;"><input id="disease-entry-complete" type="checkbox" name="is_complete" value="1" @checked(old('is_complete', false))><span>I confirm this is the complete total for the entire reporting period. Leave unchecked for provisional counts.</span></label>
        <p class="disease-muted">A period ending today or later cannot be confirmed yet.</p>
        <input type="hidden" name="is_outbreak" value="0">
        <label style="display:flex;gap:8px;margin:16px 0;"><input id="disease-entry-outbreak" type="checkbox" name="is_outbreak" value="1" @checked(old('is_outbreak'))><span>Manually flag this report as an outbreak</span></label>
        <label class="disease-form-label" for="disease-entry-remarks">Remarks</label><textarea id="disease-entry-remarks" class="disease-control" name="remarks" rows="3" maxlength="500" style="width:100%;">{{ old('remarks') }}</textarea>
        <div class="disease-row" style="margin-top:20px;justify-content:end;"><button type="button" class="disease-button disease-secondary" onclick="document.getElementById('disease-report-dialog').close()">Cancel</button><button type="submit" class="disease-button">Save report</button></div>
    </form>
</dialog>
<script>
function openNewDiseaseReport() {
    const form = document.getElementById('disease-report-form');
    form.reset();
    form.action = @json(route('disease_reports.store'));
    document.getElementById('disease-report-method').value = 'POST';
    document.getElementById('disease-report-id').value = '';
    document.getElementById('disease-entry-date').value = @json($defaultDate);
    document.getElementById('disease-entry-cases').value = '';
    document.getElementById('disease-entry-complete').checked = false;
    document.getElementById('disease-entry-outbreak').checked = false;
    document.getElementById('disease-entry-remarks').value = '';
    document.getElementById('disease-report-dialog').showModal();
}
function editDiseaseReport(button) {
    const report = JSON.parse(button.dataset.report);
    const form = document.getElementById('disease-report-form');
    form.action = @json(url('/disease-reports')) + '/' + report.id;
    document.getElementById('disease-report-method').value = 'PUT';
    document.getElementById('disease-report-id').value = report.id;
    document.getElementById('disease-entry-id').value = report.disease_id;
    document.getElementById('disease-entry-frequency').value = report.period_type;
    document.getElementById('disease-entry-date').value = report.report_date;
    document.getElementById('disease-entry-cases').value = report.case_count;
    document.getElementById('disease-entry-complete').checked = report.is_complete;
    document.getElementById('disease-entry-outbreak').checked = report.is_outbreak;
    document.getElementById('disease-entry-remarks').value = report.remarks || '';
    document.getElementById('disease-report-dialog').showModal();
}
@if($errors->any()) document.getElementById('disease-report-dialog').showModal(); @endif
</script>
@endsection
