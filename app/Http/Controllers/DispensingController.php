<?php

namespace App\Http\Controllers;

use App\Helpers\ActivityLogger;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\MidwifeBarangay;
use App\Models\PublicUser;
use App\Models\StockLog;
use App\Services\AllocationDispensingService;
use App\Services\StockMovementService;
use App\Support\HistoricalDateRange;
use App\Support\BarangayDemandScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DispensingController extends Controller
{
    private function stockSource(Request $request, ?DispensingRecord $record = null): string
    {
        $request->validate(['stock_source' => 'nullable|in:rhu,allocation']);
        $source = $request->input('stock_source') ?: ($record?->stock_source ?? 'rhu');
        if ($source === 'allocation' && session('role') !== 'midwife' && $record?->stock_source !== 'allocation') {
            throw ValidationException::withMessages(['stock_source' => 'Only a midwife can dispense from their received allocations.']);
        }

        return $source;
    }

    private function issueStock(DispensingRecord $record): void
    {
        if ($record->stock_source === 'allocation') {
            $usedBatches = app(AllocationDispensingService::class)->consume($record, (int) $record->dispensed_by);
        } else {
            $batches = Inventory::availableFefo((int) $record->health_unit_id, (int) $record->medicine_id)->lockForUpdate()->get();
            if ($batches->sum('available_stock') < $record->quantity_dispensed) {
                throw ValidationException::withMessages(['stock' => 'Insufficient stock. Available across all unexpired batches: '.$batches->sum('available_stock').' unit(s).']);
            }
            $remaining = (int) $record->quantity_dispensed;
            $usedBatches = [];
            foreach ($batches as $batch) {
                if ($remaining === 0) {
                    break;
                }
                $take = min($batch->available_stock, $remaining);
                $batch->decrement('available_stock', $take);
                app(StockMovementService::class)->record($record, $batch, -$take);
                $usedBatches[] = ['batch' => $batch, 'qty' => $take];
                $remaining -= $take;
            }
        }

        $record->update(['batch_no' => $usedBatches[0]['batch']->batch_no]);
        foreach ($usedBatches as $used) {
            $batch = $used['batch'];
            $batchNote = $batch->batch_no ? ', Batch: '.$batch->batch_no : '';
            $batchNote .= $batch->expiration_date ? ', Exp: '.$batch->expiration_date->format('M d, Y') : '';
            $allocationNote = isset($used['allocation_id']) ? ', received allocation #'.$used['allocation_id'] : '';
            StockLog::create(['medicine_id' => $record->medicine_id, 'health_unit_id' => $record->health_unit_id,
                'quantity_added' => $used['qty'], 'batch_no' => $batch->batch_no, 'inventory_id' => $batch->id,
                'storage_location' => $batch->storage_location, 'date_added' => $record->date_dispensed,
                'added_by' => session('user_id'), 'remarks' => 'Released — Dispensed '.$this->recipientLabel($record->patient_name, $record->barangay).
                    ' (Dispensing #'.$record->id.$batchNote.$allocationNote.')',
                'dispensing_record_id' => $record->id, 'movement_type' => 'dispensing']);
        }
    }

    private function barangayOptions(): array
    {
        if (session('role') === 'midwife') {
            return BarangayDemandScope::options((int) session('health_unit_id'), (int) session('user_id'))->all();
        }
        $assignments = MidwifeBarangay::whereHas('user', fn ($q) => $q
            ->where('health_unit_id', session('health_unit_id'))->where('role', 'midwife'))
            ->when(session('role') === 'midwife', fn ($q) => $q->where('user_id', session('user_id')))
            ->pluck('barangay');
        if (session('role') !== 'midwife') {
            $assignments = $assignments->merge(PublicUser::where('health_unit_id', session('health_unit_id'))
                ->whereNotNull('barangay')->where('barangay', '!=', '')->pluck('barangay'));
        }

        return $assignments->filter(fn ($name) => trim($name) !== '')->unique()->sort()->values()->all();
    }

    private function recipientLabel(?string $name, ?string $barangay): string
    {
        return $name ? 'to '.$name : ($barangay ? 'for recorded barangay dispensing in '.$barangay : 'for RHU dispensing');
    }

    /** Store the served area as a snapshot, without guessing an older record's area. */
    private function resolveBarangay(Request $request, ?DispensingRecord $record = null): ?string
    {
        if (session('role') !== 'midwife' && ! $request->exists('barangay')) {
            return $record?->barangay;
        }
        $request->validate(['barangay' => 'nullable|string|max:100']);
        $requested = $request->filled('barangay') ? trim($request->input('barangay')) : null;
        $options = $this->barangayOptions();
        if ($record && (! $request->exists('barangay') || ($requested === null && $record->barangay && ! in_array($record->barangay, $options, true)))) {
            return $record->barangay;
        }
        if (session('role') === 'midwife' && $requested !== null) {
            return BarangayDemandScope::validateForViewer((int) session('health_unit_id'), $requested);
        }
        if (! $record && session('role') === 'midwife' && $requested === null && count($options) === 1) {
            return $options[0];
        }
        if ($requested === null) {
            if (session('role') === 'midwife' && count($options) > 0) {
                throw ValidationException::withMessages(['barangay' => 'Choose the barangay served by this dispensing.']);
            }

            return null;
        }
        if (! in_array($requested, $options, true)) {
            throw ValidationException::withMessages(['barangay' => 'Choose a barangay assigned to you or available within your RHU.']);
        }

        return $requested;
    }

    private function checkAccess()
    {
        if (! session()->has('user_id')) {
            return redirect()->route('login');
        }
        if (! in_array(session('role'), ['admin', 'staff', 'midwife'])) {
            return redirect()->route('login');
        }

        return null;
    }

    private function checkNotPho()
    {
        if (is_null(session('health_unit_id'))) {
            return redirect()->route('dashboard')
                ->with('error', 'Dispensing is only available at the RHU level.');
        }

        return null;
    }

    /**
     * Find a dispensing record, scoped to the user's RHU.
     * Midwives are further limited to records they personally created.
     */
    private function findDispensingForCurrentUser($id)
    {
        $query = DispensingRecord::query();

        if (session('health_unit_id')) {
            $query->where('health_unit_id', session('health_unit_id'));
        }

        // Midwives: only their own records
        if (session('role') === 'midwife') {
            $query->where('dispensed_by', session('user_id'));
        }

        return $query->findOrFail($id);
    }

    private function readScope(Request $request): array
    {
        $filters = $request->validate([
            'dispensing_scope' => 'nullable|in:personal,area', 'barangay' => 'nullable|string|max:100',
            'barangay_missing' => 'nullable|boolean', 'medicine_id' => 'nullable|integer|exists:medicines,id',
        ]);
        $assignedAreas = session('role') === 'midwife'
            ? BarangayDemandScope::options((int) session('health_unit_id'), (int) session('user_id')) : collect();
        $selectedBarangay = trim((string) ($filters['barangay'] ?? '')) ?: null;
        $barangayMissing = $request->boolean('barangay_missing');
        $medicineId = ! empty($filters['medicine_id']) ? (int) $filters['medicine_id'] : null;
        if ($selectedBarangay !== null && $barangayMissing) {
            throw ValidationException::withMessages(['barangay' => 'Choose a barangay or records without a saved barangay.']);
        }
        if (session('role') === 'midwife') {
            $dispensingScope = $filters['dispensing_scope'] ?? ($assignedAreas->isNotEmpty() ? 'area' : 'personal');
            abort_if($dispensingScope === 'area' && $assignedAreas->isEmpty(), 403, 'No assigned barangay is available.');
            if ($selectedBarangay !== null && $dispensingScope === 'area') {
                $selectedBarangay = BarangayDemandScope::validateForViewer((int) session('health_unit_id'), $selectedBarangay);
            }
        } else {
            abort_if(($filters['dispensing_scope'] ?? null) === 'personal', 403);
            $dispensingScope = 'rhu';
        }

        $barangayFilterOptions = session('role') === 'midwife' && $dispensingScope === 'area'
            ? $assignedAreas : DispensingRecord::qualifying()->where('health_unit_id', session('health_unit_id'))
                ->when(session('role') === 'midwife', fn ($q) => $q->where('dispensed_by', session('user_id')))
                ->whereNotNull('barangay')->pluck('barangay')->map(fn ($area) => trim($area))->filter()
                ->unique(fn ($area) => mb_strtolower($area))->sort()->values();
        $selectedMedicine = $medicineId !== null ? Medicine::withTrashed()->find($medicineId) : null;

        return compact('assignedAreas', 'selectedBarangay', 'dispensingScope', 'barangayMissing', 'medicineId', 'barangayFilterOptions', 'selectedMedicine');
    }

    private function applyReadScope($query, array $scope)
    {
        if (session('role') === 'midwife' && $scope['dispensingScope'] === 'personal') {
            $query->where('dispensed_by', session('user_id'));
        }
        if ($scope['selectedBarangay'] !== null) {
            $query->forBarangays([$scope['selectedBarangay']]);
        } elseif (session('role') === 'midwife' && $scope['dispensingScope'] === 'area') {
            $query->forBarangays($scope['assignedAreas']);
        }

        return $query->when($scope['barangayMissing'], fn ($q) => $q->withoutRecordedBarangay())
            ->when($scope['medicineId'] !== null, fn ($q) => $q->where('medicine_id', $scope['medicineId']));
    }

    public function index(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        if ($redirect = $this->checkNotPho()) {
            return $redirect;
        }

        $historyRange = HistoricalDateRange::fromRequest($request);
        $search = $request->search;
        $category = $request->category;
        $healthUnitId = session('health_unit_id');
        $scope = $this->readScope($request);
        $query = $this->applyReadScope(DispensingRecord::qualifying()->with(['medicine', 'dispensedByUser', 'healthUnit'])
            ->where('health_unit_id', $healthUnitId), $scope)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('patient_name', 'like', "%{$search}%")
                        ->orWhere('doctor_name', 'like', "%{$search}%")
                        ->orWhere('batch_no', 'like', "%{$search}%")
                        ->orWhereHas('medicine', function ($mq) use ($search) {
                            $mq->where('medicine_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('dispensedByUser', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%");
                        });
                });
            })
            ->when($category, function ($q) use ($category) {
                $q->whereHas('medicine', fn ($mq) => $mq->where('category', $category));
            })
            ->when($historyRange->isFiltered(), fn ($q) => $historyRange->apply($q, 'date_dispensed'));
        $facilityRecordCount = (clone $query)->count();
        $barangayBreakdown = session('role') === 'midwife' ? collect() : (clone $query)->barangayBreakdown()->get();
        $records = $query->orderByDesc('date_dispensed')
            ->orderByDesc('id')
            ->paginate(config('pagination.dispensing', 15))
            ->appends(array_merge($request->except('page'), [
                'dispensing_scope' => $scope['dispensingScope'] === 'rhu' ? null : $scope['dispensingScope'],
                'barangay' => $scope['selectedBarangay'],
            ]));

        $healthUnits = HealthUnit::orderBy('name')->get();

        return view('dispensing.index', compact('records', 'healthUnits', 'historyRange', 'facilityRecordCount', 'barangayBreakdown') + $scope);
    }

    public function create()
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        if ($redirect = $this->checkNotPho()) {
            return $redirect;
        }

        // FEFO ordering:
        //   1) group by medicine (so all batches of the same medicine
        //      appear together in the dropdown)
        //   2) earliest expiry first within each medicine
        //   3) oldest recorded receipt first for equal or missing expiry dates
        // Expired batches are excluded so no one can pick them.
        $inventories = Inventory::with('medicine')
            ->where('health_unit_id', session('health_unit_id'))
            ->where('available_stock', '>', 0)
            ->where(function ($q) {
                $q->whereNull('expiration_date')
                    ->orWhereDate('expiration_date', '>=', now()->toDateString());
            })
            ->orderBy('medicine_id')
            ->fefo()
            ->get();

        $barangayOptions = $this->barangayOptions();
        $defaultBarangay = session('role') === 'midwife' && count($barangayOptions) === 1 ? $barangayOptions[0] : null;

        $allocationInventories = session('role') === 'midwife'
            ? app(AllocationDispensingService::class)->inventories((int) session('health_unit_id'), (int) session('user_id')) : collect();

        return view('dispensing.create', compact('inventories', 'allocationInventories', 'barangayOptions', 'defaultBarangay'));
    }

    public function store(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        if ($redirect = $this->checkNotPho()) {
            return $redirect;
        }

        $request->validate([
            'inventory_id' => 'required|exists:inventory,id',
            'patient_name' => 'nullable|string|max:255',
            'quantity_dispensed' => 'required|integer|min:1',
            'doctor_name' => 'nullable|string|max:150',
            'date_dispensed' => 'required|date|before_or_equal:today',
            'prescription_image' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:4096',
        ]);

        $barangay = $this->resolveBarangay($request);
        $stockSource = $this->stockSource($request);

        $requestedRow = Inventory::with('medicine')
            ->where('health_unit_id', session('health_unit_id'))
            ->findOrFail($request->inventory_id);

        $medicine = $requestedRow->medicine;

        if (! $medicine) {
            return back()->withInput()->with('error', 'Selected inventory has no medicine record.');
        }

        if ($medicine->requires_prescription && ! $request->doctor_name) {
            return back()->withInput()->with('error', 'Doctor name is required for prescription medicines.');
        }

        $imagePath = null;
        if ($request->hasFile('prescription_image')) {
            $rhuId = session('health_unit_id');
            $imagePath = $request->file('prescription_image')
                ->store("prescriptions/{$rhuId}", config('prescriptions.disk', 'local'));
        }

        $record = null;

        try {
            DB::transaction(function () use ($request, $medicine, $imagePath, $barangay, $stockSource, &$record) {
                $record = DispensingRecord::create([
                    'medicine_id' => $medicine->id,
                    'stock_source' => $stockSource,
                    'health_unit_id' => session('health_unit_id'),
                    'barangay' => $barangay,
                    'patient_name' => $request->patient_name,
                    'quantity_dispensed' => $request->quantity_dispensed,
                    'doctor_name' => $request->doctor_name,
                    'date_dispensed' => $request->date_dispensed,
                    'dispensed_by' => session('user_id'),
                    'prescription_image' => $imagePath,
                ]);
                $this->issueStock($record);
            });
        } catch (\Throwable $e) {
            if ($imagePath) {
                Storage::disk(config('prescriptions.disk', 'local'))->delete($imagePath);
            }

            return back()->withInput()
                ->with('error', $this->operationErrorMessage($e, 'Unable to save the dispensing record. Please try again.'));
        }

        ActivityLogger::log(
            'Dispensing',
            'Create',
            'Dispensed '.$record->quantity_dispensed.' unit(s) of '.$medicine->medicine_name.
            ' '.$this->recipientLabel($record->patient_name, $record->barangay).
            ($record->batch_no ? ' (FEFO batch: '.$record->batch_no.')' : '').
            ($imagePath ? ' (with prescription attachment)' : '').'.'
        );

        return redirect()->route('dispensing.index')
            ->with('success', 'Dispensed '.$record->quantity_dispensed.' unit(s) of '.
                              $medicine->medicine_name.' '.$this->recipientLabel($record->patient_name, $record->barangay).'.');
    }

    public function edit($id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        if ($redirect = $this->checkNotPho()) {
            return $redirect;
        }

        // findDispensingForCurrentUser() now throws 404 for midwives
        // trying to edit a record they didn't create.
        $record = $this->findDispensingForCurrentUser($id);

        // Same FEFO ordering as create(), plus the record's current batch
        // is included even if it's expired (so the user can swap it out).
        $inventories = Inventory::with('medicine')
            ->where('health_unit_id', $record->health_unit_id)
            ->where(function ($q) use ($record) {
                $q->where(function ($sub) {
                    $sub->where('available_stock', '>', 0)
                        ->where(function ($s) {
                            $s->whereNull('expiration_date')
                                ->orWhereDate('expiration_date', '>=', now()->toDateString());
                        });
                })
                    ->orWhere(function ($sub) use ($record) {
                        $sub->where('medicine_id', $record->medicine_id)
                            ->where('batch_no', $record->batch_no);
                    });
            })
            ->orderBy('medicine_id')
            ->fefo()
            ->get();

        $barangayOptions = $this->barangayOptions();

        $allocationInventories = session('role') === 'midwife' || $record->stock_source === 'allocation'
            ? app(AllocationDispensingService::class)->inventories((int) $record->health_unit_id, (int) $record->dispensed_by, $record) : collect();

        return view('dispensing.edit', compact('record', 'inventories', 'allocationInventories', 'barangayOptions'));
    }

    public function update(Request $request, $id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        if ($redirect = $this->checkNotPho()) {
            return $redirect;
        }

        $request->validate([
            'inventory_id' => 'required|exists:inventory,id',
            'patient_name' => 'nullable|string|max:255',
            'quantity_dispensed' => 'required|integer|min:1',
            'doctor_name' => 'nullable|string|max:150',
            'date_dispensed' => 'required|date|before_or_equal:today',
            'prescription_image' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:4096',
            'remove_prescription' => 'nullable|boolean',
        ]);

        // Midwives can only update their own records (404 otherwise)
        $record = $this->findDispensingForCurrentUser($id);

        $barangay = $this->resolveBarangay($request, $record);
        $stockSource = $this->stockSource($request, $record);

        $newRow = Inventory::with('medicine')
            ->where('health_unit_id', $record->health_unit_id)
            ->findOrFail($request->inventory_id);

        $medicine = $newRow->medicine;

        if (! $medicine) {
            return back()->withInput()->with('error', 'Selected inventory has no medicine record.');
        }

        if ($medicine->requires_prescription && ! $request->doctor_name) {
            return back()->withInput()->with('error', 'Doctor name is required for prescription medicines.');
        }

        $oldImagePath = $record->prescription_image;
        $imagePath = $request->boolean('remove_prescription') ? null : $oldImagePath;
        if ($request->hasFile('prescription_image')) {
            $imagePath = $request->file('prescription_image')->store('prescriptions/'.session('health_unit_id'), config('prescriptions.disk', 'local'));
        }

        $oldQty = $record->quantity_dispensed;

        try {
            DB::transaction(function () use ($request, $record, $medicine, $barangay, $stockSource, &$imagePath, &$oldImagePath) {
                $record = DispensingRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
                $oldImagePath = $record->prescription_image;
                if (! $request->hasFile('prescription_image') && ! $request->boolean('remove_prescription')) {
                    $imagePath = $oldImagePath;
                }
                app(StockMovementService::class)->reverse($record, 'Dispensing record corrected');
                $record->update([
                    'medicine_id' => $medicine->id,
                    'stock_source' => $stockSource,
                    'barangay' => $request->exists('barangay') ? $barangay : $record->barangay,
                    'patient_name' => $request->patient_name,
                    'quantity_dispensed' => $request->quantity_dispensed,
                    'doctor_name' => $request->doctor_name,
                    'date_dispensed' => $request->date_dispensed,
                    // An administrator correcting a recipient issue must retain that recipient's stock owner.
                    'dispensed_by' => $stockSource === 'allocation' ? $record->dispensed_by : session('user_id'),
                    'prescription_image' => $imagePath,
                ]);
                $this->issueStock($record);
            });
        } catch (\Throwable $e) {
            if ($imagePath && $imagePath !== $oldImagePath) {
                Storage::disk(config('prescriptions.disk', 'local'))->delete($imagePath);
            }

            return back()->withInput()->with('error', $this->operationErrorMessage($e, 'Unable to update the dispensing record. Please try again.'));
        }
        if ($oldImagePath && $oldImagePath !== $imagePath) {
            Storage::disk(config('prescriptions.disk', 'local'))->delete($oldImagePath);
        }
        $record->refresh();

        ActivityLogger::log(
            'Dispensing',
            'Update',
            'Updated dispensing #'.$record->id.' — patient '.$record->patient_name.
            ' ('.$oldQty.' unit(s) → '.$record->quantity_dispensed.' unit(s)).'
        );

        return redirect()->route('dispensing.index')
            ->with('success', 'Dispensing record updated successfully.');
    }

    public function destroy($id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        if ($redirect = $this->checkNotPho()) {
            return $redirect;
        }

        // Midwives can only delete their own records (404 otherwise)
        $record = $this->findDispensingForCurrentUser($id);

        $qty = $record->quantity_dispensed;
        $medicineName = optional($record->medicine)->medicine_name ?? 'medicine';
        $imagePath = $record->prescription_image;

        try {
            DB::transaction(function () use ($record) {
                $record = DispensingRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
                app(StockMovementService::class)->reverse($record, 'Dispensing record deleted');
                $record->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', $this->operationErrorMessage($e, 'Unable to delete the dispensing record. Please try again.'));
        }
        if ($imagePath) {
            Storage::disk(config('prescriptions.disk', 'local'))->delete($imagePath);
        }

        ActivityLogger::log(
            'Dispensing',
            'Delete',
            'Deleted dispensing #'.$id.' ('.$qty.' unit(s) of '.
            $medicineName.' — stock restored).'
        );

        return redirect()->route('dispensing.index')
            ->with('success', 'Dispensing record deleted and stock restored.');
    }

    public function showPrescription($id)
    {
        if (! session()->has('user_id')) {
            abort(403);
        }

        $record = DispensingRecord::findOrFail($id);

        if (session('health_unit_id') && $record->health_unit_id != session('health_unit_id')) {
            abort(403);
        }

        // Midwives can only view prescriptions for records they created
        if (session('role') === 'midwife'
            && (int) $record->dispensed_by !== (int) session('user_id')) {
            abort(403, 'You can only view prescriptions on your own dispensing records.');
        }

        if (! $record->prescription_image) {
            abort(404, 'No prescription attached to this record.');
        }

        $disk = Storage::disk(config('prescriptions.disk', 'local'));
        abort_unless($disk->exists($record->prescription_image), 404, 'Prescription file not found.');

        return $disk->response($record->prescription_image, basename($record->prescription_image), [
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    public function exportCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        if ($redirect = $this->checkNotPho()) {
            return $redirect;
        }

        $historyRange = HistoricalDateRange::fromRequest($request);
        $search = $request->search;
        $category = $request->category;
        $healthUnitId = session('health_unit_id');
        $scope = $this->readScope($request);
        $records = $this->applyReadScope(DispensingRecord::qualifying()->with(['medicine', 'dispensedByUser', 'healthUnit'])
            ->where('health_unit_id', $healthUnitId), $scope)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('patient_name', 'like', "%{$search}%")
                        ->orWhere('doctor_name', 'like', "%{$search}%")
                        ->orWhere('batch_no', 'like', "%{$search}%")
                        ->orWhereHas('medicine', function ($mq) use ($search) {
                            $mq->where('medicine_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('dispensedByUser', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%");
                        });
                });
            })
            ->when($category, function ($q) use ($category) {
                $q->whereHas('medicine', fn ($mq) => $mq->where('category', $category));
            })
            ->when($historyRange->isFiltered(), fn ($q) => $historyRange->apply($q, 'date_dispensed'))
            ->orderByDesc('date_dispensed')
            ->orderByDesc('id')
            ->get();

        $filename = 'dispensing_'.now()->format('Ymd_His').'.csv';
        return response()->streamDownload(function () use ($records) {
            $handle = fopen('php://output', 'w');

            $headers = [
                'ID',
                'Date',
                'Patient',
                'Medicine',
                'Batch No.',
                'Quantity Dispensed',
                'Doctor Name',
                'Dispensed By',
                'RHU',
            ];
            $headers[] = 'Barangay served';
            $headers[] = 'Stock source';
            $headers[] = 'Has Prescription';
            fputcsv($handle, $headers);

            foreach ($records as $record) {
                $row = [
                    $record->id,
                    $record->date_dispensed,
                    $record->patient_name,
                    $record->medicine->medicine_name ?? '',
                    $record->batch_no ?? '',
                    $record->quantity_dispensed,
                    $record->prescribingDoctorName(),
                    $record->dispensedByUser->name ?? '',
                    $record->healthUnit->name ?? '',
                ];
                $row[] = $record->barangay;
                $row[] = $record->stock_source ?: 'rhu';
                $row[] = $record->prescription_image ? 'Yes' : 'No';
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
