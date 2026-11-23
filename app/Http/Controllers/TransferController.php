<?php

namespace App\Http\Controllers;

use App\Helpers\ActivityLogger;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\StockLog;
use App\Models\TransferReceiptResolution;
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

    public function index(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');
        $isPHO = is_null($healthUnitId);
        $reviewOnly = $request->boolean('receipt_review');
        $overdueOnly = ! $reviewOnly && $request->boolean('overdue');

        $query = TransferRequest::with([
            'fromHealthUnit', 'toHealthUnit', 'medicine',
            'requestedBy', 'inventory', 'receivedByUser', 'phoReviewer', 'receiptReview.resolution',
        ])->visibleToViewer($healthUnitId, session('role'));

        $transfers = $query->when($reviewOnly, fn ($query) => $query->where('status', 'received')
            ->where(fn ($receipts) => $receipts->where('auto_completed', true)->orWhereHas('receiptReview')))
            ->when($overdueOnly, fn ($query) => $query->overdueForReceipt()->orderBy('approved_at'))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('transfers.index', compact('transfers', 'isPHO', 'reviewOnly', 'overdueOnly'));
    }

    public function create(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        if (is_null(session('health_unit_id'))) {
            return redirect()->route('transfers.index')
                ->with('error', 'Only RHU users can create transfer requests.');
        }

        $currentRhu = HealthUnit::find(session('health_unit_id'));
        $selection = $request->validate([
            'source_rhu_id' => 'nullable|integer|min:1',
            'medicine_id' => 'nullable|integer|min:1',
        ]);
        $previousMedicineId = $request->old('medicine_id', $selection['medicine_id'] ?? null);
        $previousMedicine = is_scalar($previousMedicineId)
            ? Medicine::find((int) $previousMedicineId)?->only(['id', 'medicine_name', 'category', 'dosage_form', 'dosage', 'unit'])
            : null;
        $selectedFacilityId = $request->old('source_rhu_id', $selection['source_rhu_id'] ?? null);

        return view('transfers.create', compact('currentRhu', 'previousMedicine', 'selectedFacilityId'));
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
            'quantity' => 'required|integer|min:1|max:2147483647',
            'notes' => 'nullable|string|max:500',
        ]);

        $toRhuId = session('health_unit_id');
        $fromRhuId = $request->source_rhu_id;

        if ($toRhuId == $fromRhuId) {
            return back()->with('error', 'You cannot request a transfer from your own RHU.');
        }

        $medicine = Medicine::findOrFail($request->medicine_id);

        $largestBatch = (int) Inventory::availableAt((int) $fromRhuId)
            ->where('medicine_id', $medicine->id)->max('available_stock');
        if ($largestBatch === 0) {
            return back()->withInput()->with('error', 'The source RHU does not have this medicine in stock.');
        }
        if ($largestBatch < $request->integer('quantity')) {
            return back()->withInput()->withErrors(['quantity' => 'No unexpired batch at the source can supply this quantity.']);
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
        $transfer = TransferRequest::findOrFail($id);
        abort_unless(session('health_unit_id') && $transfer->to_health_unit_id == session('health_unit_id'), 403);
        $receipt = $request->validate([
            'received_quantity' => 'required|integer|min:0|max:2147483647',
            'evidence_reference' => 'nullable|string|max:120',
        ]);
        try {
            app(TransferService::class)->complete((int) $id, session('user_id'), (int) session('health_unit_id'),
                (int) $receipt['received_quantity'], $receipt['evidence_reference'] ?? null);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('success', 'Transfer updated successfully.');
    }

    public function receiptReview(int $id)
    {
        $transfer = $this->reviewableTransfer()->with(['medicine', 'fromHealthUnit', 'receiptReview.resolution'])->findOrFail($id);

        return view('transfers.receipt_review', compact('transfer'));
    }

    public function storeReceiptReview(Request $request, int $id)
    {
        $transfer = $this->reviewableTransfer()->where('auto_completed', true)->findOrFail($id);
        $attributes = $request->validate([
            'received_quantity' => 'required|integer|min:0|max:2147483647',
            'evidence_reference' => 'required|string|max:120',
        ]);

        DB::transaction(function () use ($transfer, $attributes) {
            $current = $this->reviewableTransfer()->lockForUpdate()->findOrFail($transfer->id);
            if ($current->receiptReview()->exists()) {
                throw ValidationException::withMessages(['received_quantity' => 'A review already exists for this transfer.']);
            }

            $current->receiptReview()->create($attributes + [
                'expected_quantity' => $current->quantity,
                'reviewed_by' => session('user_id'),
                'reviewed_at' => now(),
            ]);
        }, 3);

        return redirect()->route('transfers.receipt_review', $transfer->id)->with('success', 'Receipt review saved. The receiving RHU administrator can resolve any discrepancy.');
    }

    public function resolveReceiptDiscrepancy(Request $request, int $id)
    {
        abort_unless(session('role') === 'admin', 403);
        $transfer = $this->reviewableTransfer()->findOrFail($id);
        $data = $request->validate([
            'decision' => 'required|in:adjustment,no_adjustment',
            'stock_log_id' => 'nullable|required_if:decision,adjustment|prohibited_unless:decision,adjustment|integer|min:1',
            'reason' => 'required|string|max:500',
        ]);

        DB::transaction(function () use ($transfer, $data) {
            $current = $this->reviewableTransfer()->lockForUpdate()->findOrFail($transfer->id);
            $review = $current->receiptReview()->with('resolution')->first();
            if (! $review || $review->matchesQuantity() || $review->resolution) {
                throw ValidationException::withMessages(['decision' => 'This receipt has no unresolved discrepancy.']);
            }

            $stockLogId = null;
            if ($data['decision'] === 'adjustment') {
                $difference = $review->received_quantity - $review->expected_quantity;
                if (! $current->auto_completed && $difference < 0) {
                    throw ValidationException::withMessages(['decision' => 'The reported shortage was already reflected in the receiving balance. Document its reconciliation without a second stock adjustment.']);
                }
                $receivedBatches = StockLog::activeForReporting()->select('inventory_id')
                    ->where('transfer_id', $current->id)->where('movement_type', 'transfer_in')
                    ->where('health_unit_id', $current->to_health_unit_id)->where('medicine_id', $current->medicine_id)
                    ->whereNotNull('inventory_id');
                $adjustment = StockLog::whereKey($data['stock_log_id'])->whereNull('voided_at')
                    ->where('health_unit_id', $current->to_health_unit_id)->where('medicine_id', $current->medicine_id)
                    ->where('movement_type', $difference > 0 ? 'adjustment_in' : 'adjustment_out')
                    ->where('quantity_added', abs($difference))->whereIn('inventory_id', $receivedBatches)
                    ->whereDate('date_added', '>=', $current->received_at ?? $review->reviewed_at)
                    ->lockForUpdate()->first();

                if (! $adjustment || TransferReceiptResolution::where('stock_log_id', $adjustment->id)->lockForUpdate()->first()) {
                    throw ValidationException::withMessages(['stock_log_id' => 'Choose an unused adjustment for this receipt batch that matches the discrepancy.']);
                }
                $stockLogId = $adjustment->id;
            }

            $review->resolution()->create(['stock_log_id' => $stockLogId, 'decision' => $data['decision'],
                'reason' => $data['reason'], 'resolved_by' => session('user_id'), 'resolved_at' => now()]);
        }, 3);

        return redirect()->route('transfers.receipt_review', $transfer->id)->with('success', 'Receipt discrepancy resolved.');
    }

    private function reviewableTransfer()
    {
        abort_unless(in_array(session('role'), ['admin', 'staff'], true) && session('health_unit_id'), 403);

        return TransferRequest::where('to_health_unit_id', session('health_unit_id'))
            ->where('status', 'received')
            ->where(fn ($receipts) => $receipts->where('auto_completed', true)->orWhereHas('receiptReview'));
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
