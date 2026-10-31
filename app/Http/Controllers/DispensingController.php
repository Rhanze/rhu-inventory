<?php

namespace App\Http\Controllers;

use App\Helpers\ActivityLogger;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\StockLog;
use App\Services\StockMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DispensingController extends Controller
{
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

    public function index(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        if ($redirect = $this->checkNotPho()) {
            return $redirect;
        }

        $search = $request->search;
        $category = $request->category;
        $healthUnitId = session('health_unit_id');

        // Midwives see every record at their RHU (continuity of care,
        // FHSIS reporting, cross-coverage). Edit/delete is guarded elsewhere.
        $records = DispensingRecord::with(['medicine', 'dispensedByUser', 'healthUnit'])
            ->where('health_unit_id', $healthUnitId)
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
            ->orderByDesc('id')
            ->paginate(config('pagination.dispensing', 15))
            ->appends([
                'search' => $search,
                'category' => $category,
            ]);

        $healthUnits = HealthUnit::orderBy('name')->get();

        return view('dispensing.index', compact('records', 'healthUnits'));
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
        //   3) newest first as a tiebreak so users see fresh uploads at top
        // Expired batches are excluded so no one can pick them.
        $inventories = Inventory::with('medicine')
            ->where('health_unit_id', session('health_unit_id'))
            ->where('available_stock', '>', 0)
            ->where(function ($q) {
                $q->whereNull('expiration_date')
                    ->orWhereDate('expiration_date', '>=', now()->toDateString());
            })
            ->orderBy('medicine_id')
            ->orderByRaw('expiration_date IS NULL, expiration_date ASC')
            ->orderBy('id')
            ->get();

        return view('dispensing.create', compact('inventories'));
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
            'patient_name' => 'required|string|max:255',
            'quantity_dispensed' => 'required|integer|min:1',
            'doctor_name' => 'nullable|string|max:150',
            'date_dispensed' => 'required|date|before_or_equal:today',
            'prescription_image' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

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
            DB::transaction(function () use ($request, $medicine, $imagePath, &$record) {
                $batches = Inventory::availableFefo(
                    session('health_unit_id'),
                    $medicine->id
                )->lockForUpdate()->get();

                $totalAvailable = $batches->sum('available_stock');

                if ($totalAvailable < $request->quantity_dispensed) {
                    throw new \Exception(
                        'Insufficient stock. '.
                        'Available across all unexpired batches: '.$totalAvailable.' unit(s). '.
                        'Requested: '.$request->quantity_dispensed.'.'
                    );
                }

                $remaining = (int) $request->quantity_dispensed;
                $usedBatches = [];
                $primaryBatchNo = null;

                foreach ($batches as $batch) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $take = min($batch->available_stock, $remaining);
                    if ($take <= 0) {
                        continue;
                    }

                    $batch->decrement('available_stock', $take);

                    if ($primaryBatchNo === null) {
                        $primaryBatchNo = $batch->batch_no;
                    }

                    $usedBatches[] = [
                        'batch' => $batch,
                        'qty' => $take,
                    ];

                    $remaining -= $take;
                }

                $record = DispensingRecord::create([
                    'medicine_id' => $medicine->id,
                    'health_unit_id' => session('health_unit_id'),
                    'patient_name' => $request->patient_name,
                    'quantity_dispensed' => $request->quantity_dispensed,
                    'batch_no' => $primaryBatchNo,
                    'doctor_name' => $request->doctor_name,
                    'date_dispensed' => $request->date_dispensed,
                    'dispensed_by' => session('user_id'),
                    'prescription_image' => $imagePath,
                ]);

                foreach ($usedBatches as $used) {
                    /** @var Inventory $batch */
                    $batch = $used['batch'];
                    $take = $used['qty'];
                    app(StockMovementService::class)->record($record, $batch, -$take);
                    $batchNote = $batch->batch_no ? ', Batch: '.$batch->batch_no : '';
                    $batchNote .= $batch->expiration_date
                        ? ', Exp: '.$batch->expiration_date->format('M d, Y')
                        : '';

                    StockLog::create([
                        'medicine_id' => $medicine->id,
                        'health_unit_id' => session('health_unit_id'),
                        'quantity_added' => $take,
                        'batch_no' => $batch->batch_no,
                        'inventory_id' => $batch->id,
                        'storage_location' => $batch->storage_location,
                        'date_added' => $request->date_dispensed,
                        'added_by' => session('user_id'),
                        'remarks' => 'Released — Dispensed to '.$request->patient_name.
                                                  ' (Dispensing #'.$record->id.$batchNote.')',
                        'dispensing_record_id' => $record->id,
                        'movement_type' => 'dispensing',
                    ]);
                }
            });
        } catch (\Throwable $e) {
            if ($imagePath) {
                Storage::disk(config('prescriptions.disk', 'local'))->delete($imagePath);
            }

            return back()->withInput()
                ->with('error', 'Failed to save dispense: '.$e->getMessage());
        }

        ActivityLogger::log(
            'Dispensing',
            'Create',
            'Dispensed '.$record->quantity_dispensed.' unit(s) of '.$medicine->medicine_name.
            ' to '.$record->patient_name.
            ($record->batch_no ? ' (FEFO batch: '.$record->batch_no.')' : '').
            ($imagePath ? ' (with prescription attachment)' : '').'.'
        );

        return redirect()->route('dispensing.index')
            ->with('success', 'Dispensed '.$record->quantity_dispensed.' unit(s) of '.
                              $medicine->medicine_name.' to '.$record->patient_name.'.');
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
            ->orderByRaw('expiration_date IS NULL, expiration_date ASC')
            ->orderBy('id')
            ->get();

        return view('dispensing.edit', compact('record', 'inventories'));
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
            'patient_name' => 'required|string|max:255',
            'quantity_dispensed' => 'required|integer|min:1',
            'doctor_name' => 'nullable|string|max:150',
            'date_dispensed' => 'required|date|before_or_equal:today',
            'prescription_image' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'remove_prescription' => 'nullable|boolean',
        ]);

        // Midwives can only update their own records (404 otherwise)
        $record = $this->findDispensingForCurrentUser($id);

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
        $oldMedId = $record->medicine_id;

        try {
            DB::transaction(function () use ($request, $record, $medicine, &$imagePath, &$oldImagePath) {
                $record = DispensingRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
                $oldImagePath = $record->prescription_image;
                if (! $request->hasFile('prescription_image') && ! $request->boolean('remove_prescription')) {
                    $imagePath = $oldImagePath;
                }
                app(StockMovementService::class)->reverse($record);

                $batches = Inventory::availableFefo(
                    $record->health_unit_id,
                    $medicine->id
                )->lockForUpdate()->get();

                $totalAvailable = $batches->sum('available_stock');

                if ($totalAvailable < $request->quantity_dispensed) {
                    throw new \Exception(
                        'Insufficient stock. Available across all unexpired batches: '.
                        $totalAvailable.' unit(s). Requested: '.$request->quantity_dispensed.'.'
                    );
                }

                $remaining = (int) $request->quantity_dispensed;
                $usedBatches = [];
                $primaryBatchNo = null;

                foreach ($batches as $batch) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $take = min($batch->available_stock, $remaining);
                    if ($take <= 0) {
                        continue;
                    }

                    $batch->decrement('available_stock', $take);

                    if ($primaryBatchNo === null) {
                        $primaryBatchNo = $batch->batch_no;
                    }

                    $usedBatches[] = ['batch' => $batch, 'qty' => $take];
                    $remaining -= $take;
                }

                $record->update([
                    'medicine_id' => $medicine->id,
                    'patient_name' => $request->patient_name,
                    'quantity_dispensed' => $request->quantity_dispensed,
                    'batch_no' => $primaryBatchNo,
                    'doctor_name' => $request->doctor_name ?? '',
                    'date_dispensed' => $request->date_dispensed,
                    'dispensed_by' => session('user_id'),
                    'prescription_image' => $imagePath,
                ]);

                StockLog::where('dispensing_record_id', $record->id)->whereNull('voided_at')->update(['voided_at' => now()]);

                foreach ($usedBatches as $used) {
                    $batch = $used['batch'];
                    $take = $used['qty'];
                    app(StockMovementService::class)->record($record, $batch, -$take);
                    $batchNote = $batch->batch_no ? ', Batch: '.$batch->batch_no : '';
                    $batchNote .= $batch->expiration_date
                        ? ', Exp: '.$batch->expiration_date->format('M d, Y')
                        : '';

                    StockLog::create([
                        'medicine_id' => $medicine->id,
                        'health_unit_id' => $record->health_unit_id,
                        'quantity_added' => $take,
                        'batch_no' => $batch->batch_no,
                        'inventory_id' => $batch->id,
                        'storage_location' => $batch->storage_location,
                        'date_added' => $request->date_dispensed,
                        'added_by' => session('user_id'),
                        'remarks' => 'Released — Dispensed to '.$request->patient_name.
                                                  ' (Dispensing #'.$record->id.$batchNote.', updated)',
                        'dispensing_record_id' => $record->id,
                        'movement_type' => 'dispensing',
                    ]);
                }
            });
        } catch (\Throwable $e) {
            if ($imagePath && $imagePath !== $oldImagePath) {
                Storage::disk(config('prescriptions.disk', 'local'))->delete($imagePath);
            }

            return back()->withInput()->with('error', $e->getMessage());
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
                app(StockMovementService::class)->reverse($record);
                $record->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
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

        $search = $request->search;
        $category = $request->category;
        $healthUnitId = session('health_unit_id');

        $records = DispensingRecord::with(['medicine', 'dispensedByUser', 'healthUnit'])
            ->where('health_unit_id', $healthUnitId)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('patient_name', 'like', "%{$search}%")
                        ->orWhere('doctor_name', 'like', "%{$search}%")
                        ->orWhere('batch_no', 'like', "%{$search}%")
                        ->orWhereHas('medicine', function ($mq) use ($search) {
                            $mq->where('medicine_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($category, function ($q) use ($category) {
                $q->whereHas('medicine', fn ($mq) => $mq->where('category', $category));
            })
            ->orderByDesc('id')
            ->get();

        $filename = 'dispensing_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($records) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID',
                'Date',
                'Patient',
                'Medicine',
                'Batch No.',
                'Quantity Dispensed',
                'Doctor Name',
                'Dispensed By',
                'RHU',
                'Has Prescription',
            ]);

            foreach ($records as $record) {
                fputcsv($handle, [
                    $record->id,
                    $record->date_dispensed,
                    $record->patient_name,
                    $record->medicine->medicine_name ?? '',
                    $record->batch_no ?? '',
                    $record->quantity_dispensed,
                    $record->doctor_name,
                    $record->dispensedByUser->name ?? '',
                    $record->healthUnit->name ?? '',
                    $record->prescription_image ? 'Yes' : 'No',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
