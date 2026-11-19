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
            'requestedBy', 'inventory', 'receivedByUser', 'phoReviewer',
        ])->visibleToViewer($healthUnitId, session('role'));

        $transfers = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(20);

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

        $sourceHasStock = Inventory::availableFefo((int) $fromRhuId, $medicine->id)->exists();

        if (! $sourceHasStock) {
            return back()->with('error', 'The source RHU does not have this medicine in stock.');
        }

        DB::transaction(function () use ($request, $fromRhuId, $toRhuId, $medicine) {
            $transfer = TransferRequest::create([
                'initiator_type' => 'rhu_request',
                'from_health_unit_id' => $fromRhuId,
                'to_health_unit_id' => $toRhuId,
                'medicine_id' => $medicine->id,
                'quantity' => $request->quantity,
                'notes' => $request->notes,
                'status' => 'pending',
                'pho_review_status' => TransferRequest::PHO_REVIEW_PENDING,
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

            app(TransferService::class)->notifyPhoReviewRequested($transfer);
            ActivityLogger::log('Transfer', 'Request', 'Submitted transfer #'.$transfer->id.' for PHO review.');
        }, 3);

        return redirect()->route('transfers.index')
            ->with('success', 'Transfer request submitted for PHO approval.');
    }

    public function phoApprove($id)
    {
        $this->ensurePhoReviewer();
        app(TransferService::class)->reviewByPho((int) $id, (int) session('user_id'), true);

        return back()->with('success', 'Transfer request approved. The supplying RHU has been notified.');
    }

    public function phoReject(Request $request, $id)
    {
        $this->ensurePhoReviewer();
        $data = $request->validate(['rejection_reason' => 'nullable|string|max:500']);
        app(TransferService::class)->reviewByPho((int) $id, (int) session('user_id'), false, $data['rejection_reason'] ?? null);

        return back()->with('success', 'Transfer request rejected. The requesting RHU has been notified.');
    }

    private function ensurePhoReviewer(): void
    {
        abort_unless(session()->has('user_id') && session('role') === 'admin' && session('health_unit_id') === null, 403);
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

        $data = $request->validate(['rejection_reason' => 'nullable|string|max:500']);
        app(TransferService::class)->rejectBySource((int) $id, (int) session('health_unit_id'), (int) session('user_id'),
            $data['rejection_reason'] ?? null);

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
        if (! is_null(session('health_unit_id')) || session('role') !== 'admin') {
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
        if (! is_null(session('health_unit_id')) || session('role') !== 'admin') {
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

        $batch = Inventory::availableFefo((int) $request->from_health_unit_id, $medicine->id)
            ->where('available_stock', '>=', $request->quantity)
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
