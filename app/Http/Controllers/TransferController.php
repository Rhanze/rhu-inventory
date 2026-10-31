<?php

namespace App\Http\Controllers;

use App\Helpers\ActivityLogger;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\TransferRequest;
use App\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferController extends Controller
{
    private function checkAccess()
    {
        if (! session()->has('user_id')) {
            return redirect()->route('login');
        }
        if (! in_array(session('role'), ['admin', 'staff'])) {
            return redirect()->route('login');
        }

        return null;
    }

    public function index()
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');
        $isPHO = is_null($healthUnitId);

        $query = TransferRequest::with([
            'fromHealthUnit', 'toHealthUnit', 'medicine',
            'requestedBy', 'inventory', 'receivedByUser',
        ]);

        if (! $isPHO) {
            $query->where(function ($q) use ($healthUnitId) {
                $q->where('from_health_unit_id', $healthUnitId)
                    ->orWhere('to_health_unit_id', $healthUnitId);
            });
        }

        $transfers = $query->orderByDesc('created_at')->paginate(20);

        return view('transfers.index', compact('transfers', 'isPHO'));
    }

    public function create()
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        if (is_null(session('health_unit_id'))) {
            return redirect()->route('transfers.index')
                ->with('error', 'Only RHU users can create transfer requests.');
        }

        $currentRhu = HealthUnit::find(session('health_unit_id'));

        return view('transfers.create', compact('currentRhu'));
    }

    public function store(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        if (is_null(session('health_unit_id'))) {
            return back()->with('error', 'Unauthorized.');
        }

        $request->validate([
            'source_rhu_id' => 'required|exists:health_units,id',
            'medicine_id' => 'required|exists:medicines,id',
            'dosage' => 'nullable|string|max:100',
            'dosage_form' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $toRhuId = session('health_unit_id');
        $fromRhuId = $request->source_rhu_id;

        if ($toRhuId == $fromRhuId) {
            return back()->with('error', 'You cannot request a transfer from your own RHU.');
        }

        $medicine = Medicine::findOrFail($request->medicine_id);

        $sourceHasStock = Inventory::where('health_unit_id', $fromRhuId)
            ->where('medicine_id', $medicine->id)
            ->where('available_stock', '>', 0)
            ->exists();

        if (! $sourceHasStock) {
            return back()->with('error', 'The source RHU does not have this medicine in stock.');
        }

        $fromRhu = HealthUnit::find($fromRhuId);
        $toRhu = HealthUnit::find($toRhuId);

        DB::transaction(function () use ($request, $fromRhuId, $toRhuId, $medicine, $toRhu) {
            $transfer = TransferRequest::create([
                'initiator_type' => 'rhu_request',
                'from_health_unit_id' => $fromRhuId,
                'to_health_unit_id' => $toRhuId,
                'medicine_id' => $medicine->id,
                'quantity' => $request->quantity,
                'notes' => $request->notes,
                'status' => 'pending',
                'requested_by' => session('user_id'),

                // Snapshot of what the requesting RHU asked for
                'dosage' => $request->input('dosage', $medicine->dosage),
                'dosage_form' => $request->input('dosage_form', $medicine->dosage_form),
                'category' => $request->input('category', $medicine->category),
                'unit' => $medicine->unit,

                // Pack info left null — source RHU fills it in at accept() time
                // because it depends on which batch they actually reserve
                'pack_type' => null,
                'pieces_per_pack' => null,
            ]);

            Notification::create([
                'health_unit_id' => $fromRhuId,
                'transfer_id' => $transfer->id,
                'type' => 'transfer_request',
                'link' => route('transfers.index'),
                'title' => 'New Stock Transfer Request',
                'message' => $toRhu->name.' is requesting '.$transfer->formatQuantity($request->quantity).
                                    ' of '.$medicine->medicine_name.
                                    ($request->notes ? ' — '.$request->notes : ''),
                'severity' => 'warning',
                'is_read' => false,
                'created_at' => now(),
            ]);
        });

        ActivityLogger::log(
            'Transfer', 'Request',
            'Requested '.$request->quantity.' units of '.$medicine->medicine_name.
            ' from '.$fromRhu->name.'.'
        );

        return redirect()->route('transfers.index')
            ->with('success', 'Transfer request sent to '.$fromRhu->name.'. Awaiting their acceptance.');
    }

    public function accept(Request $request, $id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        try {
            app(TransferService::class)->reserve((int) $id, (int) session('health_unit_id'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Transfer updated successfully.');
    }

    public function receive(Request $request, $id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        try {
            app(TransferService::class)->complete((int) $id, session('user_id'), (int) session('health_unit_id'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Transfer updated successfully.');
    }

    public function reject(Request $request, $id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $request->validate(['rejection_reason' => 'nullable|string|max:500']);

        $transfer = TransferRequest::findOrFail($id);

        if ($transfer->from_health_unit_id != session('health_unit_id')) {
            return back()->with('error', 'Only the source RHU can reject this request.');
        }
        if ($transfer->status !== 'pending') {
            return back()->with('error', 'This request is no longer pending.');
        }

        DB::transaction(function () use ($transfer, $request) {
            $transfer = TransferRequest::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            abort_unless($transfer->status === 'pending', 409);
            $transfer->update([
                'status' => 'rejected',
                'rejected_by' => session('user_id'),
                'rejected_at' => now(),
                'rejection_reason' => $request->rejection_reason,
            ]);

        });

        Notification::create([
            'health_unit_id' => $transfer->to_health_unit_id,
            'transfer_id' => $transfer->id,
            'type' => 'transfer_rejected',
            'link' => route('transfers.index'),
            'title' => 'Transfer Request Rejected',
            'message' => $transfer->fromHealthUnit->name.' rejected your request for '.
                                $transfer->formatQuantity($transfer->quantity).' of '.
                                ($transfer->medicine->medicine_name ?? 'medicine').
                                ($request->rejection_reason ? ' — Reason: '.$request->rejection_reason : ''),
            'severity' => 'danger',
            'is_read' => false,
            'created_at' => now(),
        ]);

        Notification::where('transfer_id', $transfer->id)
            ->where('health_unit_id', session('health_unit_id'))
            ->where('is_read', false)
            ->update(['is_read' => true]);

        ActivityLogger::log('Transfer', 'Reject', 'Rejected transfer #'.$id);

        return back()->with('success', 'Transfer rejected.');
    }

    public function cancel($id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        try {
            app(TransferService::class)->cancel((int) $id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Transfer updated successfully.');
    }

    public function distribute()
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        if (! is_null(session('health_unit_id'))) {
            return redirect()->route('transfers.index')
                ->with('error', 'Only PHO admins can create distribution directives.');
        }

        $healthUnits = HealthUnit::orderBy('name')->get();
        $medicines = Medicine::orderBy('medicine_name')->get();

        return view('transfers.distribute', compact('healthUnits', 'medicines'));
    }

    public function distributeStore(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        if (! is_null(session('health_unit_id'))) {
            return back()->with('error', 'Unauthorized.');
        }

        $request->validate([
            'from_health_unit_id' => 'required|exists:health_units,id',
            'to_health_unit_id' => 'required|exists:health_units,id|different:from_health_unit_id',
            'medicine_id' => 'required|exists:medicines,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $medicine = Medicine::findOrFail($request->medicine_id);

        $batch = Inventory::where('health_unit_id', $request->from_health_unit_id)
            ->where('medicine_id', $medicine->id)
            ->where('available_stock', '>=', $request->quantity)
            ->orderByRaw('expiration_date IS NULL, expiration_date ASC')
            ->first();

        if (! $batch) {
            return back()->with('error', 'The selected source RHU does not have enough stock in a single batch.');
        }

        $fromRhu = HealthUnit::find($request->from_health_unit_id);
        $toRhu = HealthUnit::find($request->to_health_unit_id);

        DB::transaction(function () use ($request, $batch, $medicine, $fromRhu, $toRhu) {
            $transfer = TransferRequest::create([
                'initiator_type' => 'pho_directive',
                'from_health_unit_id' => $request->from_health_unit_id,
                'to_health_unit_id' => $request->to_health_unit_id,
                'medicine_id' => $medicine->id,
                'inventory_id' => $batch->id,
                'batch_no' => $batch->batch_no,
                'quantity' => $request->quantity,
                'notes' => $request->notes,
                'status' => 'pending',
                'requested_by' => session('user_id'),
                'approved_by' => session('user_id'),
                'approved_at' => now(),

                // Snapshot from the batch (pack info is per-batch now)
                'dosage' => $medicine->dosage,
                'dosage_form' => $medicine->dosage_form,
                'category' => $medicine->category,
                'unit' => $medicine->unit,
                'pack_type' => $batch->pack_type ?? $medicine->pack_type,
                'pieces_per_pack' => $batch->pieces_per_pack ?? $medicine->pieces_per_pack,
            ]);

            app(TransferService::class)->reserve($transfer->id);

            Notification::create([
                'health_unit_id' => $request->from_health_unit_id,
                'transfer_id' => $transfer->id,
                'type' => 'pho_directive',
                'link' => route('transfers.index'),
                'title' => 'PHO Distribution Directive',
                'message' => 'PHO directs you to transfer '.$transfer->formatQuantity($request->quantity).
                                    ' of '.$medicine->medicine_name.
                                    ' to '.$toRhu->name.'.'.
                                    ($request->notes ? ' Notes: '.$request->notes : ''),
                'severity' => 'warning',
                'is_read' => false,
                'created_at' => now(),
            ]);

            Notification::create([
                'health_unit_id' => $request->to_health_unit_id,
                'transfer_id' => $transfer->id,
                'type' => 'pho_directive',
                'link' => route('transfers.index'),
                'title' => 'Incoming PHO Distribution',
                'message' => 'PHO directed '.$fromRhu->name.' to send you '.
                                    $transfer->formatQuantity($request->quantity).' of '.$medicine->medicine_name.'. '.
                                    'Confirm receipt once the items arrive.',
                'severity' => 'info',
                'is_read' => false,
                'created_at' => now(),
            ]);
        });

        ActivityLogger::log(
            'Transfer', 'Distribute',
            'PHO directed '.$fromRhu->name.' to send '.$request->quantity.
            ' units of '.$medicine->medicine_name.' to '.$toRhu->name.'.'
        );

        return redirect()->route('transfers.index')
            ->with('success', 'Directive sent to '.$fromRhu->name.'.');
    }
}
