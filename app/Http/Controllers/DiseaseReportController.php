<?php

namespace App\Http\Controllers;

use App\Models\Disease;
use App\Models\Medicine;
use App\Services\DiseaseConsumptionTrendService;
use App\Services\MedicineUsageTrendService;
use App\Services\RecordedConsumptionGraphService;
use App\Support\BarangayDemandScope;
use App\Support\HistoricalDateRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiseaseReportController extends Controller
{
    private function scope(Request $request): array
    {
        abort_unless(session('user_id') && in_array(session('role'), ['admin', 'staff', 'midwife']), 403);
        abort_unless(session('health_unit_id'), 403, 'Disease trend analysis requires an assigned RHU.');
        $unitId = (int) session('health_unit_id');
        $filters = $request->validate([
            // Old bookmarked view links now open the same consumption analysis.
            'view' => 'nullable|in:demand,reports', 'usage_details' => 'nullable|boolean',
            'period_type' => 'nullable|in:weekly,monthly', 'periods' => 'nullable|integer|min:4|max:52',
            'disease_id' => 'nullable|integer|exists:diseases,id',
            'medicine_id' => 'nullable|integer|exists:medicines,id', 'barangay' => 'nullable|string|max:100',
            'consumption_search' => 'nullable|string|max:160',
            'consumption_scope' => 'nullable|in:personal,area',
        ]);
        $historyRange = HistoricalDateRange::fromRequest($request);
        $periodType = $filters['period_type'] ?? 'weekly';
        $dispenserId = session('role') === 'midwife' ? (int) session('user_id') : null;
        $barangayOptions = BarangayDemandScope::options($unitId, $dispenserId);
        $selectedBarangay = $request->has('barangay') ? trim((string) $request->input('barangay')) : null;
        if ($selectedBarangay) {
            if ($dispenserId !== null) {
                $normalizedArea = mb_strtolower(preg_replace('/\s+/u', ' ', $selectedBarangay));
                $selectedBarangay = $barangayOptions->first(fn ($area) => mb_strtolower(preg_replace('/\s+/u', ' ', $area)) === $normalizedArea) ?? $selectedBarangay;
            }
            abort_unless($barangayOptions->containsStrict($selectedBarangay), 403, 'This barangay is outside your assigned area.');
        }
        if ($dispenserId !== null) {
            // Old named-barangay links keep their area meaning. Opening the page
            // normally retains the midwife's own saved history after reassignment.
            $consumptionScope = $filters['consumption_scope'] ?? ($selectedBarangay ? 'area' : 'personal');
            if ($consumptionScope === 'area') {
                abort_unless($barangayOptions->isNotEmpty(), 403, 'No assigned barangay is available for this view.');
                $selectedBarangay = $selectedBarangay ?: $barangayOptions->first();
            } else {
                $selectedBarangay = null;
            }
        } else {
            abort_if(($filters['consumption_scope'] ?? null) === 'personal', 403, 'Personal consumption is a midwife view.');
            $consumptionScope = $selectedBarangay ? 'area' : 'rhu';
        }
        $selectedBarangay = $selectedBarangay ?: null;
        $selectedDiseaseId = isset($filters['disease_id']) ? (int) $filters['disease_id'] : null;
        $diseases = Disease::where('is_active', true)->orderBy('name')->get();
        if ($selectedDiseaseId && ! $diseases->contains('id', $selectedDiseaseId)) {
            throw ValidationException::withMessages(['disease_id' => 'Choose an active disease association.']);
        }

        return compact('unitId', 'filters', 'historyRange', 'periodType', 'dispenserId',
            'barangayOptions', 'selectedBarangay', 'selectedDiseaseId', 'diseases', 'consumptionScope');
    }

    private function context(Request $request): array
    {
        $context = $this->scope($request);
        extract($context);
        $service = app(DiseaseConsumptionTrendService::class);
        $diseaseTrends = $service->analyze($unitId, $selectedBarangay, $periodType,
            $selectedBarangay ? null : $dispenserId, $historyRange->from, $historyRange->to, $selectedDiseaseId);
        if ($selectedDiseaseId) {
            $diseaseTrends['signals'] = array_values(array_filter($diseaseTrends['signals'], fn ($signal) => $signal['id'] === $selectedDiseaseId));
            $diseaseTrends['medicine_signals'] = $diseaseTrends['signals'][0]['medicines'] ?? [];
            $diseaseTrends['total_transactions'] = $diseaseTrends['signals'][0]['transactions'] ?? 0;
            foreach (['elevated', 'new', 'typical', 'insufficient', 'historical'] as $status) {
                $diseaseTrends['overview'][$status] = count(array_filter($diseaseTrends['signals'], fn ($signal) => $signal['status'] === $status));
            }
            $diseaseTrends['overview']['diseases_reviewed'] = count($diseaseTrends['signals']);
            $diseaseTrends['overview']['medicines_reviewed'] = count($diseaseTrends['medicine_signals']);
        }
        $diseaseHistoryCount = $diseaseTrends['total_transactions'];

        $recorded = $service->recordsQuery($unitId, $selectedBarangay, $selectedBarangay ? null : $dispenserId);
        $savedConsumption = $historyRange->apply(clone $recorded, 'date_dispensed')
            ->selectRaw('COUNT(*) AS record_count, MIN(date_dispensed) AS first_date, MAX(date_dispensed) AS last_date')->first();
        $savedConsumptionCount = (int) $savedConsumption->record_count;
        $savedConsumptionFrom = $savedConsumption->first_date;
        $savedConsumptionTo = $savedConsumption->last_date;
        $graph = app(RecordedConsumptionGraphService::class)->build($unitId, $selectedBarangay,
            $selectedBarangay ? null : $dispenserId, $diseaseTrends, $historyRange->from, $historyRange->to, $selectedDiseaseId);
        $consumptionChartAnalysis = $graph['analysis'];
        $consumptionChartUsesSavedHistory = $graph['uses_saved_history'];
        $recordedMedicineIds = (clone $recorded)->select('medicine_id');
        $medicines = Medicine::withTrashed()->nonVaccine()->where(fn ($query) => $query
            ->where(fn ($active) => $active->whereNull('deleted_at')->where('is_dispensable', true))
            ->orWhereIn('id', $recordedMedicineIds))->orderBy('medicine_name')->orderBy('dosage')
            ->get(['id', 'medicine_name', 'dosage', 'dosage_form', 'unit', 'deleted_at', 'is_dispensable']);
        if (isset($filters['medicine_id']) && ! $medicines->contains('id', (int) $filters['medicine_id'])) {
            throw ValidationException::withMessages(['medicine_id' => 'Choose a non-vaccine medicine available for dispensing or with saved consumption history in your selected scope.']);
        }
        $selectedMedicineId = $filters['medicine_id'] ?? $historyRange->apply((clone $recorded)->whereIn('medicine_id', $medicines->modelKeys()), 'date_dispensed')
            ->orderByDesc('date_dispensed')->orderByDesc('id')->value('medicine_id') ?? $medicines->first()?->id;
        $selectedMedicine = $medicines->firstWhere('id', $selectedMedicineId);
        $usage = $selectedMedicine ? app(MedicineUsageTrendService::class)->analyze($unitId,
            $selectedBarangay ? null : $dispenserId, $selectedMedicine->id, $periodType,
            $filters['periods'] ?? config('disease_analysis.history_periods', 12),
            $selectedBarangay, $historyRange->from, $historyRange->to) : null;
        $usageHistoryCount = $selectedMedicine ? $historyRange->apply((clone $recorded)->where('medicine_id', $selectedMedicine->id), 'date_dispensed')->count() : 0;
        $showUsageDetails = $historyRange->isFiltered() || ($request->has('usage_details')
            ? $request->boolean('usage_details') : ($request->filled('medicine_id')
                || ($savedConsumptionCount > 0 && $diseaseHistoryCount === 0)));
        $activeView = 'demand';

        return $context + compact('diseaseTrends', 'diseaseHistoryCount', 'medicines', 'selectedMedicineId',
            'selectedMedicine', 'usage', 'usageHistoryCount', 'showUsageDetails', 'activeView',
            'savedConsumptionCount', 'savedConsumptionFrom', 'savedConsumptionTo',
            'consumptionChartAnalysis', 'consumptionChartUsesSavedHistory');
    }

    public function index(Request $request)
    {
        $context = $this->context($request);
        $consumptionSearch = trim((string) ($context['filters']['consumption_search'] ?? ''));
        $query = app(DiseaseConsumptionTrendService::class)->recordsQuery($context['unitId'],
            $context['selectedBarangay'], $context['selectedBarangay'] ? null : $context['dispenserId']);
        if ($context['selectedDiseaseId']) {
            $query->whereExists(fn ($mapping) => $mapping->selectRaw('1')->from('disease_medicine_map')
                ->join('diseases', 'diseases.id', '=', 'disease_medicine_map.disease_id')
                ->whereColumn('disease_medicine_map.medicine_id', 'dispensing_records.medicine_id')
                ->where('diseases.is_active', true)->where('diseases.id', $context['selectedDiseaseId']));
        }
        foreach (preg_split('/\s+/u', $consumptionSearch, flags: PREG_SPLIT_NO_EMPTY) as $word) {
            $query->whereHas('medicine', fn ($medicine) => $medicine->where(fn ($fields) => $fields
                ->where('medicine_name', 'like', '%'.$word.'%')->orWhere('dosage', 'like', '%'.$word.'%')
                ->orWhere('dosage_form', 'like', '%'.$word.'%')));
        }
        $consumptionRecords = $context['historyRange']->apply($query, 'date_dispensed')
            ->with(['medicine' => fn ($medicine) => $medicine->withTrashed()
                ->select('id', 'medicine_name', 'dosage', 'dosage_form', 'unit')])
            ->orderByDesc('date_dispensed')->orderByDesc('id')
            ->paginate(15, ['id', 'medicine_id', 'date_dispensed', 'quantity_dispensed', 'barangay', 'stock_source'])
            ->withQueryString()->appends($context['dispenserId'] !== null ? ['consumption_scope' => $context['consumptionScope']] : [])
            ->fragment('recorded-consumption');

        return view('disease_reports.index', $context + compact('consumptionRecords', 'consumptionSearch'));
    }

    public function chartData(Request $request)
    {
        $context = $this->context($request);

        return response()->json(['analysis' => $context['diseaseTrends'],
            'consumption_scope' => $context['consumptionScope'],
            'consumption_chart' => $context['consumptionChartAnalysis'],
            'consumption_chart_uses_saved_history' => $context['consumptionChartUsesSavedHistory']]);
    }

    public function exportCsv(Request $request)
    {
        $context = $this->scope($request);
        extract($context);
        $mappings = DB::table('disease_medicine_map as mapping')->join('diseases', 'diseases.id', '=', 'mapping.disease_id')
            ->where('diseases.is_active', true)->when($selectedDiseaseId, fn ($query) => $query->where('diseases.id', $selectedDiseaseId))
            ->select('mapping.medicine_id', 'diseases.id as disease_id', 'diseases.name')->distinct()->orderBy('diseases.name')->get()->groupBy('medicine_id');
        $query = app(DiseaseConsumptionTrendService::class)->recordsQuery($unitId, $selectedBarangay, $selectedBarangay ? null : $dispenserId)
            ->whereIn('medicine_id', $mappings->keys())->when($filters['medicine_id'] ?? null, fn ($query, $id) => $query->where('medicine_id', $id))
            ->with(['medicine' => fn ($query) => $query->withTrashed(), 'dispensedByUser']);
        $records = $historyRange->apply($query, 'date_dispensed')->orderByDesc('date_dispensed')->orderByDesc('id')->get();

        return response()->streamDownload(function () use ($records, $mappings) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Disease', 'Medicine', 'Strength', 'Dosage form', 'Unit', 'Consumption date', 'Quantity consumed', 'Barangay', 'Recorded by']);
            foreach ($records as $record) {
                foreach ($mappings[$record->medicine_id] ?? [] as $disease) {
                    fputcsv($handle, [$disease->name, $record->medicine->medicine_name ?? '', $record->medicine->dosage ?? '',
                        $record->medicine->dosage_form ?? '', $record->medicine?->unit ?: 'units',
                        $record->date_dispensed?->toDateString(), $record->quantity_dispensed, $record->barangay,
                        $record->dispensedByUser->name ?? '']);
                }
            }
            fclose($handle);
        }, 'disease_consumption_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
