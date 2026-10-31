<?php

namespace App\Http\Controllers;

use App\Helpers\ActivityLogger;
use App\Models\Disease;
use App\Models\DiseaseReport;
use App\Models\HealthUnit;
use App\Services\DiseaseAnalysisService;
use App\Services\DiseasePeriodService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiseaseReportController extends Controller
{
    private function unitId(): int
    {
        abort_unless(session('user_id') && in_array(session('role'), ['admin', 'staff', 'midwife']), 403);
        abort_unless(session('health_unit_id'), 403, 'Disease reporting requires an assigned RHU.');

        return (int) session('health_unit_id');
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'period_type' => 'nullable|in:weekly,monthly',
            'disease_id' => 'nullable|integer|exists:diseases,id',
            'periods' => 'nullable|integer|min:4|max:52',
        ]);
    }

    public function index(Request $request)
    {
        $unitId = $this->unitId();
        $filters = $this->filters($request);
        $periodType = $filters['period_type'] ?? 'weekly';
        $diseases = Disease::orderBy('name')->get();
        $selectedDiseaseId = $filters['disease_id'] ?? DiseaseReport::where('health_unit_id', $unitId)->orderByDesc('report_date')->value('disease_id') ?? $diseases->first()?->id;
        $analysis = $selectedDiseaseId ? app(DiseaseAnalysisService::class)->analyze((int) $selectedDiseaseId, $unitId, $periodType, $filters['periods'] ?? config('disease_analysis.history_periods', 12)) : null;
        $reports = DiseaseReport::with(['disease', 'reportedByUser'])->where('health_unit_id', $unitId)
            ->where('period_type', $periodType)->when($selectedDiseaseId, fn ($q) => $q->where('disease_id', $selectedDiseaseId))
            ->orderByDesc('report_date')->orderByDesc('id')->paginate(30)->withQueryString();
        $outbreakStatuses = $this->getOpenOutbreaks($unitId);

        return view('disease_reports.index', compact('reports', 'diseases', 'outbreakStatuses', 'analysis', 'periodType', 'selectedDiseaseId'));
    }

    private function getOpenOutbreaks(int $unitId): array
    {
        $latest = DiseaseReport::with('disease')->where('health_unit_id', $unitId)->whereDate('report_date', '<=', today())
            ->where('is_outbreak', true)->whereNull('outbreak_closed_at')->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('disease_reports as newer')
                    ->whereColumn('newer.health_unit_id', 'disease_reports.health_unit_id')
                    ->whereColumn('newer.disease_id', 'disease_reports.disease_id')->whereDate('newer.report_date', '<=', today())
                    ->where(fn ($date) => $date->whereColumn('newer.report_date', '>', 'disease_reports.report_date')
                        ->orWhere(fn ($tie) => $tie->whereColumn('newer.report_date', 'disease_reports.report_date')->whereColumn('newer.id', '>', 'disease_reports.id')));
            })->get();

        return $latest->map(function ($report) use ($unitId) {
            $analysis = app(DiseaseAnalysisService::class)->analyze($report->disease_id, $unitId, $report->period_type);
            $observed = array_values(array_filter(array_column($analysis['periods'], 'cases'), fn ($v) => $v !== null));

            return ['disease_id' => $report->disease_id, 'disease_name' => $report->disease->name ?? 'Unknown disease',
                'category' => $report->disease->category ?? null, 'start_date' => $report->report_date,
                'weeks_open' => max(1, (int) ceil($report->report_date->diffInDays(now()) / 7)),
                'baseline' => $analysis['baseline'] === null ? 'N/A' : round($analysis['baseline'], 1),
                'this_week' => $analysis['periods'][count($analysis['periods']) - 1]['cases'] ?? 'N/A',
                'peak' => count($observed) ? max($observed) : 'N/A', 'trend' => $analysis['direction'],
                'period_type' => $report->period_type, 'suggest_close' => false];
        })->all();
    }

    public function store(Request $request)
    {
        return $this->saveReport($request);
    }

    public function update(Request $request, int $id)
    {
        $report = DiseaseReport::where('health_unit_id', $this->unitId())->findOrFail($id);

        return $this->saveReport($request, $report);
    }

    private function saveReport(Request $request, ?DiseaseReport $existing = null)
    {
        $unitId = $this->unitId();
        $data = $request->validate([
            'disease_id' => 'required|integer|exists:diseases,id',
            'report_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'period_type' => 'required|in:weekly,monthly', 'case_count' => 'required|integer|min:0|max:100000',
            'is_complete' => 'required|boolean', 'is_outbreak' => 'nullable|boolean', 'remarks' => 'nullable|string|max:500',
        ]);
        $bounds = app(DiseasePeriodService::class)->bounds($data['report_date'], $data['period_type']);
        if ($request->boolean('is_complete') && $bounds['end'] >= today()->toDateString()) {
            throw ValidationException::withMessages(['is_complete' => 'This period is still in progress. Save it as provisional until the entire period has ended.']);
        }
        $report = DB::transaction(function () use ($data, $bounds, $unitId, $existing, $request) {
            HealthUnit::whereKey($unitId)->lockForUpdate()->firstOrFail();
            $duplicate = DiseaseReport::where('health_unit_id', $unitId)->where('disease_id', $data['disease_id'])->where('period_type', $data['period_type'])
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                ->where(fn ($q) => $q->where('period_start', $bounds['start'])->orWhere(fn ($legacy) => $legacy->whereNull('period_start')->whereDate('report_date', '>=', $bounds['start'])->whereDate('report_date', '<=', $bounds['end'])))
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['disease_id' => 'A report already covers this disease and period. Review or edit the existing report; do not add the same period again.']);
            }
            $report = $existing ? DiseaseReport::whereKey($existing->id)->lockForUpdate()->firstOrFail() : new DiseaseReport;
            $report->fill($data + ['health_unit_id' => $unitId]);
            $report->period_start = $bounds['start'];
            $report->period_end = $bounds['end'];
            $report->is_complete = $request->boolean('is_complete');
            $report->is_outbreak = $request->boolean('is_outbreak');
            $report->reported_by = session('user_id');
            $report->save();
            ActivityLogger::log('Disease Reports', $existing ? 'Update' : 'Create', 'Saved report #'.$report->id.' for '.$data['period_type'].' period '.$bounds['start'].' through '.$bounds['end'].'.');

            return $report;
        });
        if ($report->is_outbreak && ! $report->outbreak_closed_at) {
            app(NotificationService::class)->notifyDiseaseOutbreak($report->fresh('disease'));
        }

        return redirect()->route('disease_reports.index', ['disease_id' => $report->disease_id, 'period_type' => $report->period_type])->with('success', 'Disease report saved. Only confirmed, completed periods enter the models.');
    }

    public function closeOutbreak(Request $request, int $diseaseId)
    {
        $unitId = $this->unitId();
        $request->validate(['remarks' => 'nullable|string|max:500']);
        DB::transaction(function () use ($unitId, $diseaseId, $request) {
            HealthUnit::whereKey($unitId)->lockForUpdate()->firstOrFail();
            $report = DiseaseReport::where('health_unit_id', $unitId)->where('disease_id', $diseaseId)->whereDate('report_date', '<=', today())->orderByDesc('report_date')->orderByDesc('id')->lockForUpdate()->firstOrFail();
            abort_unless($report->is_outbreak && ! $report->outbreak_closed_at, 409, 'There is no open outbreak flag.');
            $report->update(['outbreak_closed_at' => now(), 'outbreak_closed_by' => session('user_id'), 'outbreak_closing_note' => $request->input('remarks')]);
            ActivityLogger::log('Disease Reports', 'Close Outbreak', 'Manually closed outbreak flag on report #'.$report->id.'.');
        });

        return redirect()->route('disease_reports.index')->with('success', 'Outbreak flag closed. Case counts and reporting completeness were preserved.');
    }

    public function destroy(int $id)
    {
        $report = DiseaseReport::where('health_unit_id', $this->unitId())->findOrFail($id);
        $report->delete();
        ActivityLogger::log('Disease Reports', 'Delete', 'Deleted disease report #'.$id.'.');

        return back()->with('success', 'Report deleted; this period will be missing until another report is submitted.');
    }

    public function chartData(Request $request)
    {
        $unitId = $this->unitId();
        $filters = $this->filters($request);
        $request->validate(['disease_id' => 'required|integer|exists:diseases,id']);
        $analysis = app(DiseaseAnalysisService::class)->analyze((int) $filters['disease_id'], $unitId, $filters['period_type'] ?? 'weekly', $filters['periods'] ?? 12);

        return response()->json(['analysis' => $analysis, 'labels' => array_column($analysis['periods'], 'start'),
            'datasets' => [['label' => 'Confirmed reported cases', 'data' => array_column($analysis['periods'], 'cases')]]]);
    }

    public function exportCsv(Request $request)
    {
        $unitId = $this->unitId();
        $filters = $this->filters($request);
        $reports = DiseaseReport::with(['disease', 'reportedByUser'])->where('health_unit_id', $unitId)
            ->when($filters['period_type'] ?? null, fn ($q, $type) => $q->where('period_type', $type))
            ->when($filters['disease_id'] ?? null, fn ($q, $id) => $q->where('disease_id', $id))->orderByDesc('report_date')->get();

        return response()->streamDownload(function () use ($reports) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Report date', 'Frequency', 'Period start', 'Period end', 'Data status', 'Disease', 'Cases', 'Outbreak flag', 'Flag closed at', 'Remarks', 'Reported by']);
            foreach ($reports as $r) {
                fputcsv($handle, [$r->report_date->toDateString(), $r->period_type, $r->period_start?->toDateString(), $r->period_end?->toDateString(),
                    ! $r->period_start ? 'Needs review' : ($r->is_complete ? 'Confirmed' : 'Provisional'), $r->disease->name ?? 'Unknown', $r->case_count,
                    $r->is_outbreak ? 'Yes' : 'No', $r->outbreak_closed_at?->toDateTimeString(), $r->remarks, $r->reportedByUser->name ?? 'Unknown']);
            }
            fclose($handle);
        }, 'disease_reports_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
