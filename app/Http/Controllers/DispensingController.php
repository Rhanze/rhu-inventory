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
use App\Support\SpreadsheetCsv;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DispensingController extends Controller
{
    private function patientForLink(mixed $id): ?PublicUser
    {
        if (! is_scalar($id) || ! filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            return null;
        }

        return PublicUser::forViewer()->find($id, ['id', 'first_name', 'last_name', 'birth_date']);
    }

    private function patientChoice(PublicUser $patient): array
    {
        return [
            'id' => $patient->id,
            'name' => $patient->full_name,
            'birth_date' => $patient->birth_date?->toDateString(),
        ];
    }

    public function patientMatches(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        abort_unless(session('health_unit_id'), 403);

        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:80', "regex:/^[\p{L}\p{M}\p{N} .'’\-]+$/u"],
        ]);
        $query = PublicUser::forViewer();
        foreach (explode(' ', $validated['query']) as $term) {
            if ($term !== '') {
                $query->where(fn ($names) => $names->where('first_name', 'like', '%'.$term.'%')
                    ->orWhere('last_name', 'like', '%'.$term.'%'));
            }
        }

        $matches = $query->orderBy('last_name')->orderBy('first_name')->orderBy('id')->limit(10)
            ->get(['id', 'first_name', 'last_name', 'birth_date'])
            ->map(fn (PublicUser $patient) => $this->patientChoice($patient));

        return response()->json($matches)->header('Cache-Control', 'private, no-store');
    }

    private function resolveRecipient(Request $request, ?DispensingRecord $record = null): array
    {
        if ($record && $request->boolean('unlink_patient')) {
            return ['public_user_id' => null, 'patient_name' => $request->input('patient_name')];
        }

        if ($request->filled('public_user_id')) {
            $patient = $this->patientForLink($request->input('public_user_id'));
            if (! $patient) {
                throw ValidationException::withMessages(['public_user_id' => 'Choose a registered patient available to your account.']);
            }

            $name = (int) $record?->public_user_id === (int) $patient->id
                ? ($record->patient_name ?? $patient->full_name)
                : $patient->full_name;

            return ['public_user_id' => $patient->id, 'patient_name' => $name];
        }

        if ($record?->public_user_id) {
            return ['public_user_id' => $record->public_user_id, 'patient_name' => $record->patient_name];
        }

        return ['public_user_id' => null, 'patient_name' => $request->input('patient_name')];
    }

    private function storePrescriptionUpload(Request $request): ?string
    {
        if (! $request->hasFile('prescription_image')) {
            return null;
        }

        try {
            $path = $request->file('prescription_image')->store('prescriptions/'.session('health_unit_id'), config('prescriptions.disk', 'local'));
        } catch (\Throwable $exception) {
            report($exception);
            $path = false;
        }

        if ($path === false) {
            throw ValidationException::withMessages(['prescription_image' => 'The prescription could not be saved. Try uploading it again.']);
        }

        return $path;
    }

    private function stockSource(Request $request, ?DispensingRecord $record = null): string
    {
        $request->validate(['stock_source' => 'nullable|in:rhu,allocation']);
        $source = $request->input('stock_source') ?: ($record?->stock_source ?? 'rhu');
        if ($source === 'allocation' && session('role') !== 'midwife' && $record?->stock_source !== 'allocation') {
            throw ValidationException::withMessages(['stock_source' => 'Only a midwife can dispense from their received allocations.']);
        }

        return $source;
    }

    private function selectedMedicine(Request $request, int $unitId, ?DispensingRecord $record = null): Medicine
    {
        $medicineId = $request->input('medicine_id');
        if ($request->filled('inventory_id')) {
            $row = Inventory::where('health_unit_id', $unitId)->findOrFail($request->input('inventory_id'));
            if ($medicineId !== null && (int) $medicineId !== (int) $row->medicine_id) {
                throw ValidationException::withMessages(['medicine_id' => 'Choose a single medicine.']);
            }
            $medicineId = $row->medicine_id;
        }

        return $record && (int) $medicineId === (int) $record->medicine_id && $record->medicine
            ? $record->medicine : Medicine::findOrFail($medicineId);
    }

    private function archivePrescription(DispensingRecord $record, string $reason): ?int
    {
        if (! $record->prescription_image) {
            return null;
        }

        return DB::table('dispensing_prescription_versions')->insertGetId([
            'dispensing_record_id' => $record->id,
            'prescription_image' => $record->prescription_image,
            'archived_by' => session('user_id'),
            'archived_at' => now(),
            'reason' => $reason,
        ]);
    }

    private function discardUnattachedUpload(?string $path): void
    {
        if ($path === null) {
            return;
        }

        try {
            if (! DispensingRecord::withTrashed()->where('prescription_image', $path)->exists()
                && ! DB::table('dispensing_prescription_versions')->where('prescription_image', $path)->exists()) {
                Storage::disk(config('prescriptions.disk', 'local'))->delete($path);
            }
        } catch (\Throwable $exception) {
            // Storage or connection errors must not replace the original transaction outcome.
            report($exception);
        }
    }

    private function historicalCapacity(Inventory $batch, string $date, int $restored = 0): int
    {
        $received = $batch->stock_received_at;
        if (($received !== null && substr($received, 0, 10) > $date)
            || ($received === null && $date < today()->toDateString())) {
            return 0;
        }

        $available = $batch->available_stock + $restored;
        if ($date >= today()->toDateString()) {
            return $available;
        }

        // Later additions may account for current units. Do not assign those units to an earlier issue.
        $laterAdditions = StockLog::query()->where('medicine_id', $batch->medicine_id)->where('health_unit_id', $batch->health_unit_id)
            ->whereNull('voided_at')->where('quantity_added', '>', 0)->additions()->whereDate('date_added', '>', $date)
            ->where(fn ($log) => $log->where('inventory_id', $batch->id)
                ->orWhere(fn ($legacy) => $legacy->whereNull('inventory_id')->where('batch_no', $batch->batch_no)))
            ->sum('quantity_added');

        return max(0, $available - (int) $laterAdditions);
    }

    private function planStock(DispensingRecord $record, bool $lock, ?DispensingRecord $prior = null): array
    {
        if ($record->stock_source === 'allocation') {
            return app(AllocationDispensingService::class)->plan($record, $lock, $prior);
        }

        $date = $record->date_dispensed->toDateString();
        $restored = $prior && $prior->stock_source !== 'allocation' && (int) $prior->medicine_id === (int) $record->medicine_id
            ? app(StockMovementService::class)->dispensingRestoration($prior) : collect();
        $query = Inventory::where('health_unit_id', $record->health_unit_id)->where('medicine_id', $record->medicine_id)
            ->where(fn ($available) => $available->where('available_stock', '>', 0)->orWhereIn('id', $restored->keys()))
            ->where(fn ($expiry) => $expiry->whereNull('expiration_date')->orWhereDate('expiration_date', '>=', $date))->fefo();
        $batches = ($lock ? $query->lockForUpdate() : $query)->get();
        $capacity = $batches->mapWithKeys(fn (Inventory $batch) => [$batch->id => $this->historicalCapacity($batch, $date, $restored->get($batch->id, 0))]);
        if ($capacity->sum() < $record->quantity_dispensed) {
            throw ValidationException::withMessages(['stock' => 'Insufficient stock for the dispensing date. Eligible stock: '.$capacity->sum().' unit(s).']);
        }

        $remaining = (int) $record->quantity_dispensed;
        $usedBatches = [];
        foreach ($batches as $batch) {
            if ($remaining === 0) {
                break;
            }
            $take = min($capacity[$batch->id], $remaining);
            if ($take > 0) {
                $usedBatches[] = ['batch' => $batch, 'qty' => $take];
                $remaining -= $take;
            }
        }

        return $usedBatches;
    }

    private function plannedLots(array $plan): array
    {
        return collect($plan)->groupBy('batch.id')->map(function ($parts) {
            $batch = $parts->first()['batch'];

            return [
                'inventory_id' => $batch->id,
                'batch_no' => $batch->batch_no,
                'expiration_date' => $batch->expiration_date?->toDateString(),
                'storage_location' => $batch->storage_location,
                'quantity' => (int) $parts->sum('qty'),
            ];
        })->values()->all();
    }

    private function recordedLots(DispensingRecord $record): array
    {
        return $record->issuedLots->groupBy(fn (StockLog $log) => $log->inventory_id ?? -$log->id)->map(function ($logs) {
            $lot = $logs->first();

            return [
                'inventory_id' => $lot->inventory_id,
                'batch_no' => $lot->batch_no,
                'expiration_date' => null,
                'storage_location' => $lot->storage_location,
                'quantity' => (int) $logs->sum('quantity_added'),
            ];
        })->values()->all();
    }

    private function lotToken(array $lots): string
    {
        return hash('sha256', json_encode($lots, JSON_THROW_ON_ERROR));
    }

    private function requireCurrentLotPreview(?string $preview, array $lots): void
    {
        if ($preview !== null && ! hash_equals($this->lotToken($lots), $preview)) {
            throw ValidationException::withMessages(['stock' => 'The batches have changed. Review the refreshed batch quantities before saving again.']);
        }
    }

    private function stockChanged(DispensingRecord $record, int $medicineId, int $quantity, string $date, string $source): bool
    {
        return (int) $record->medicine_id !== $medicineId || $record->quantity_dispensed !== $quantity
            || $record->date_dispensed->toDateString() !== $date || ($record->stock_source ?: 'rhu') !== $source;
    }

    private function issueStock(DispensingRecord $record, ?string $preview): void
    {
        $usedBatches = $this->planStock($record, lock: true);
        $this->requireCurrentLotPreview($preview, $this->plannedLots($usedBatches));
        if ($record->stock_source === 'allocation') {
            app(AllocationDispensingService::class)->consume($record, $usedBatches);
        } else {
            foreach ($usedBatches as ['batch' => $batch, 'qty' => $take]) {
                $batch->decrement('available_stock', $take);
                app(StockMovementService::class)->record($record, $batch, -$take);
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
                'added_by' => session('user_id'), 'remarks' => 'Released — Dispensed '.$this->recipientLabel(null, $record->barangay).
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

    private function searchRecords($query, string $search): void
    {
        $pattern = '%'.$search.'%';
        $query->where(function ($matches) use ($pattern) {
            $matches->forIssuedLot($pattern)
                ->orWhereHas('medicine', fn ($medicine) => $medicine->where('medicine_name', 'like', $pattern))
                ->orWhereHas('dispensedByUser', fn ($user) => $user->where('name', 'like', $pattern)
                    ->orWhere('username', 'like', $pattern))
                ->orWhere(function ($privateFields) use ($pattern) {
                    $privateFields->when(session('role') === 'midwife', fn ($owner) => $owner->where('dispensed_by', session('user_id')))
                        ->where(fn ($names) => $names->where('patient_name', 'like', $pattern)
                            ->orWhere('doctor_name', 'like', $pattern));
                });
        });
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
        $search = $request->validate(['search' => 'nullable|string|max:255'])['search'] ?? null;
        $category = $request->category;
        $healthUnitId = session('health_unit_id');
        $scope = $this->readScope($request);
        $query = $this->applyReadScope(DispensingRecord::qualifying()->with(['medicine', 'dispensedByUser', 'healthUnit', 'issuedLots'])
            ->where('health_unit_id', $healthUnitId), $scope)
            ->when($search, fn ($query, $search) => $this->searchRecords($query, $search))
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

    public function create(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        if ($redirect = $this->checkNotPho()) {
            return $redirect;
        }

        // Retain medicine choices for older dates even when their lots have since expired.
        $inventories = Inventory::with('medicine')
            ->where('health_unit_id', session('health_unit_id'))
            ->where('available_stock', '>', 0)
            ->orderBy('medicine_id')
            ->get();

        $barangayOptions = $this->barangayOptions();
        $defaultBarangay = session('role') === 'midwife' && count($barangayOptions) === 1 ? $barangayOptions[0] : null;

        $allocationInventories = session('role') === 'midwife'
            ? app(AllocationDispensingService::class)->inventories((int) session('health_unit_id'), (int) session('user_id')) : collect();

        $patient = $this->patientForLink($request->old('public_user_id'));
        $selectedPatient = $patient ? $this->patientChoice($patient) : null;
        $previousToken = $request->old('submission_token');
        $submissionToken = is_string($previousToken) && Str::isUuid($previousToken, 4) ? $previousToken : (string) Str::uuid();

        return view('dispensing.create', compact('inventories', 'allocationInventories', 'barangayOptions', 'defaultBarangay', 'selectedPatient', 'submissionToken'));
    }

    public function preview(Request $request, ?int $id = null): JsonResponse|RedirectResponse
    {
        if ($redirect = $this->checkAccess() ?? $this->checkNotPho()) {
            return $redirect;
        }

        $record = $id === null ? null : $this->findDispensingForCurrentUser($id);
        $validated = $request->validate([
            'medicine_id' => 'required|integer',
            'inventory_id' => 'prohibited',
            'quantity_dispensed' => 'required|integer|min:1',
            'date_dispensed' => 'required|date|before_or_equal:today',
        ]);
        $unitId = (int) session('health_unit_id');
        $source = $this->stockSource($request, $record);
        $medicine = $this->selectedMedicine($request, $unitId, $record);
        $quantity = (int) $validated['quantity_dispensed'];
        $date = Carbon::parse($validated['date_dispensed'])->toDateString();
        $recorded = $record && ! $this->stockChanged($record, $medicine->id, $quantity, $date, $source);
        if ($recorded) {
            $lots = $this->recordedLots($record);
        } else {
            $draft = new DispensingRecord([
                'medicine_id' => $medicine->id,
                'health_unit_id' => $unitId,
                'stock_source' => $source,
                'date_dispensed' => $date,
                'quantity_dispensed' => $quantity,
                'dispensed_by' => $record?->dispensed_by ?? session('user_id'),
            ]);
            $lots = $this->plannedLots($this->planStock($draft, lock: false, prior: $record));
        }

        return response()->json(['lots' => $lots, 'token' => $this->lotToken($lots), 'recorded' => $recorded])
            ->header('Cache-Control', 'private, no-store');
    }

    private function alreadySaved(): RedirectResponse
    {
        return redirect()->route('dispensing.index')->with('success', 'This dispensing submission has already been recorded.');
    }

    public function store(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        if ($redirect = $this->checkNotPho()) {
            return $redirect;
        }

        $validated = $request->validate([
            'submission_token' => 'nullable|uuid:4',
            'lot_preview' => 'sometimes|required|regex:/^[a-f0-9]{64}$/D',
            'inventory_id' => 'nullable|required_without:medicine_id|integer|exists:inventory,id',
            'medicine_id' => 'nullable|required_without:inventory_id|integer|exists:medicines,id',
            'public_user_id' => 'nullable|integer|min:1',
            'patient_name' => 'nullable|string|max:255',
            'quantity_dispensed' => 'required|integer|min:1',
            'doctor_name' => 'nullable|string|max:150',
            'date_dispensed' => 'required|date|before_or_equal:today',
            'prescription_image' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:4096',
        ], ['lot_preview.required' => 'Check the batches before saving.']);

        $formToken = $validated['submission_token'] ?? null;
        // Bind the token to its original submitter, even if another clinician later edits the record.
        $submissionToken = $formToken === null ? null : hash('sha256', session('health_unit_id').':'.session('user_id').':'.strtolower($formToken));
        if ($submissionToken && DispensingRecord::withTrashed()->where('submission_token', $submissionToken)->exists()) {
            return $this->alreadySaved();
        }

        $barangay = $this->resolveBarangay($request);
        $stockSource = $this->stockSource($request);

        $medicine = $this->selectedMedicine($request, (int) session('health_unit_id'));

        if ($medicine->requires_prescription && ! $request->doctor_name) {
            return back()->withInput()->with('error', 'Doctor name is required for prescription medicines.');
        }

        $recipient = $this->resolveRecipient($request);
        $imagePath = $this->storePrescriptionUpload($request);

        $record = null;

        try {
            DB::transaction(function () use ($request, $medicine, $imagePath, $barangay, $stockSource, $recipient, $submissionToken, &$record) {
                $record = DispensingRecord::create([
                    'medicine_id' => $medicine->id,
                    'submission_token' => $submissionToken,
                    'stock_source' => $stockSource,
                    'health_unit_id' => session('health_unit_id'),
                    'barangay' => $barangay,
                    'quantity_dispensed' => $request->quantity_dispensed,
                    'doctor_name' => $request->doctor_name,
                    'date_dispensed' => $request->date_dispensed,
                    'dispensed_by' => session('user_id'),
                    'prescription_image' => $imagePath,
                ] + $recipient);
                $this->issueStock($record, $request->input('lot_preview'));

                ActivityLogger::log('Dispensing', 'Create', 'Recorded dispensing #'.$record->id.
                    ' for '.$record->quantity_dispensed.' units of '.$medicine->medicine_name.'. Lots: '.$record->lotBreakdown().'.');
            });
        } catch (\Throwable $e) {
            $replay = $submissionToken && $e instanceof UniqueConstraintViolationException
                ? DispensingRecord::withTrashed()->where('submission_token', $submissionToken)->first()
                : null;
            if ($imagePath && $imagePath !== $replay?->prescription_image) {
                $this->discardUnattachedUpload($imagePath);
            }
            if ($replay) {
                return $this->alreadySaved();
            }

            return back()->withInput()
                ->with('error', $this->operationErrorMessage($e, 'Unable to save the dispensing record. Please try again.'));
        }

        return redirect()->route('dispensing.index')
            ->with('success', 'Recorded '.$record->quantity_dispensed.' unit(s) of '.$medicine->medicine_name.
                              ' '.$this->recipientLabel($record->patient_name, $record->barangay).'. Use the recorded lots: '.$record->lotBreakdown().'.');
    }

    public function edit(Request $request, $id)
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

        // The current medicine remains selectable for metadata corrections even when stock has changed.
        $inventories = Inventory::with('medicine')
            ->where('health_unit_id', $record->health_unit_id)
            ->where('available_stock', '>', 0)
            ->orderBy('medicine_id')
            ->get();

        $barangayOptions = $this->barangayOptions();

        $allocationInventories = session('role') === 'midwife' || $record->stock_source === 'allocation'
            ? app(AllocationDispensingService::class)->inventories((int) $record->health_unit_id, (int) $record->dispensed_by, $record) : collect();

        $patient = $request->old('unlink_patient') ? null
            : ($this->patientForLink($request->old('public_user_id')) ?? $this->patientForLink($record->public_user_id));
        $selectedPatient = $patient ? $this->patientChoice($patient) : null;

        return view('dispensing.edit', compact('record', 'inventories', 'allocationInventories', 'barangayOptions', 'selectedPatient'));
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
            'lot_preview' => 'sometimes|required|regex:/^[a-f0-9]{64}$/D',
            'inventory_id' => 'nullable|required_without:medicine_id|integer|exists:inventory,id',
            'medicine_id' => 'nullable|required_without:inventory_id|integer|exists:medicines,id',
            'public_user_id' => 'nullable|integer|min:1',
            'unlink_patient' => 'nullable|boolean',
            'patient_name' => 'nullable|string|max:255',
            'quantity_dispensed' => 'required|integer|min:1',
            'doctor_name' => 'nullable|string|max:150',
            'date_dispensed' => 'required|date|before_or_equal:today',
            'prescription_image' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:4096',
            'remove_prescription' => 'nullable|boolean',
        ], ['lot_preview.required' => 'Check the batches before saving.']);

        // Midwives can only update their own records (404 otherwise)
        $record = $this->findDispensingForCurrentUser($id);

        $barangay = $this->resolveBarangay($request, $record);
        $stockSource = $this->stockSource($request, $record);

        $medicine = $this->selectedMedicine($request, (int) $record->health_unit_id, $record);

        if ($medicine->requires_prescription && ! $request->doctor_name) {
            return back()->withInput()->with('error', 'Doctor name is required for prescription medicines.');
        }

        $upload = $this->storePrescriptionUpload($request);
        $date = Carbon::parse($request->date_dispensed)->toDateString();

        try {
            $record = DB::transaction(function () use ($request, $record, $medicine, $barangay, $stockSource, $upload, $date) {
                $record = DispensingRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
                $recipient = $this->resolveRecipient($request, $record);
                $oldQty = (int) $record->quantity_dispensed;
                $imagePath = $upload ?? ($request->boolean('remove_prescription') ? null : $record->prescription_image);
                if ($record->prescription_image !== $imagePath) {
                    $this->archivePrescription($record, $upload ? 'replaced' : 'detached');
                }

                $stockChanged = $this->stockChanged($record, $medicine->id, (int) $request->quantity_dispensed, $date, $stockSource);
                $preview = $request->input('lot_preview');
                if ($stockChanged) {
                    app(StockMovementService::class)->reverse($record, 'Dispensing stock corrected');
                } elseif ($preview !== null) {
                    $this->requireCurrentLotPreview($preview, $this->recordedLots($record));
                }

                $record->update([
                    'medicine_id' => $medicine->id,
                    'stock_source' => $stockSource,
                    'barangay' => $request->exists('barangay') ? $barangay : $record->barangay,
                    'quantity_dispensed' => $request->quantity_dispensed,
                    'doctor_name' => $request->doctor_name,
                    'date_dispensed' => $date,
                    'prescription_image' => $imagePath,
                ] + $recipient);

                if ($stockChanged) {
                    $this->issueStock($record, $preview);
                }

                ActivityLogger::log('Dispensing', 'Update', 'Updated dispensing #'.$record->id.
                    ' ('.$oldQty.' → '.$record->quantity_dispensed.' units).');

                return $record;
            });
        } catch (\Throwable $e) {
            $this->discardUnattachedUpload($upload);

            if ($e instanceof ValidationException && isset($e->errors()['public_user_id'])) {
                return back()->withInput()->withErrors($e->errors());
            }

            return back()->withInput()->with('error', $this->operationErrorMessage($e, 'Unable to update the dispensing record. Please try again.'));
        }
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

        try {
            $archiveId = DB::transaction(function () use ($record) {
                $record = DispensingRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
                app(StockMovementService::class)->reverse($record, 'Dispensing record deleted');
                $archiveId = $this->archivePrescription($record, 'voided');
                $record->delete();

                ActivityLogger::log('Dispensing', 'Delete', 'Voided dispensing #'.$record->id.
                    ' ('.$record->quantity_dispensed.' units restored).');

                return $archiveId;
            });
        } catch (\Throwable $e) {
            return back()->with('error', $this->operationErrorMessage($e, 'Unable to delete the dispensing record. Please try again.'));
        }

        return redirect()->route('dispensing.index')
            ->with('success', 'Dispensing record voided and stock restored.')
            ->with('dispensing_prescription_archive', $archiveId ? $id : null);
    }

    public function showPrescription(Request $request, $id)
    {
        $healthUnitId = session('health_unit_id');
        abort_unless(session('user_id') && $healthUnitId, 403);

        $validated = $request->validate(['version' => 'nullable|integer|min:1']);
        $versionId = $validated['version'] ?? null;
        $history = $request->boolean('history');
        $record = DispensingRecord::where('health_unit_id', $healthUnitId)
            ->when($history || $versionId !== null, fn ($query) => $query->withTrashed())
            ->when(session('role') === 'midwife', fn ($query) => $query->where('dispensed_by', session('user_id')))
            ->findOrFail($id);

        $versions = DB::table('dispensing_prescription_versions')->where('dispensing_record_id', $record->id);
        if ($history) {
            return view('dispensing.prescription_history', ['record' => $record, 'versions' => $versions->orderByDesc('id')->get()]);
        }

        $image = $versionId === null ? $record->prescription_image : $versions->where('id', $versionId)->value('prescription_image');

        if (! $image) {
            abort(404, 'No prescription attached to this record.');
        }

        $disk = Storage::disk(config('prescriptions.disk', 'local'));
        abort_unless($disk->exists($image), 404, 'Prescription file not found.');

        return $disk->response($image, basename($image), [
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
        $search = $request->validate(['search' => 'nullable|string|max:255'])['search'] ?? null;
        $category = $request->category;
        $healthUnitId = session('health_unit_id');
        $scope = $this->readScope($request);
        $records = $this->applyReadScope(DispensingRecord::qualifying()->with(['medicine', 'dispensedByUser', 'healthUnit', 'issuedLots'])
            ->where('health_unit_id', $healthUnitId), $scope)
            ->when($search, fn ($query, $search) => $this->searchRecords($query, $search))
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
            $headers[] = 'Lot quantities';
            SpreadsheetCsv::writeRow($handle, $headers);

            foreach ($records as $record) {
                $canViewDetails = session('role') !== 'midwife' || (int) $record->dispensed_by === (int) session('user_id');
                $row = [
                    $record->id,
                    $record->date_dispensed,
                    $canViewDetails ? $record->patient_name : null,
                    $record->medicine->medicine_name ?? '',
                    $record->issuedLots->pluck('batch_no')->filter()->unique()->implode(', ') ?: $record->batch_no,
                    $record->quantity_dispensed,
                    $canViewDetails ? $record->prescribingDoctorName() : null,
                    $record->dispensedByUser->name ?? '',
                    $record->healthUnit->name ?? '',
                ];
                $row[] = $record->barangay;
                $row[] = $record->stock_source ?: 'rhu';
                $row[] = $canViewDetails ? ($record->prescription_image ? 'Yes' : 'No') : null;
                $row[] = $record->lotBreakdown();
                SpreadsheetCsv::writeRow($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
